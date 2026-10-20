<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\View;
use App\Models\User;
use App\Services\AuditLog;
use App\Services\PermissionService;
use PDO;
use PDOException;

final class UserAdminController
{
    /** @param array<string, mixed> $config */
    public static function index(array $config): void
    {
        try { $users = Database::connect($config)->query('SELECT id, full_name, username, email, role, is_active, created_at FROM users ORDER BY role, full_name')->fetchAll(); }
        catch (PDOException $e) { self::unavailable('users', $e); return; }
        View::render('users/index', ['title'=>'Users','user'=>Auth::user(),'csrfToken'=>Csrf::token(),'users'=>$users,'currentPath'=>'/users']);
    }

    public static function create(): void
    {
        View::render('users/form', ['title'=>'Create user','user'=>Auth::user(),'csrfToken'=>Csrf::token(),'person'=>['full_name'=>'','username'=>'','email'=>'','role'=>'staff'],'action'=>'/users/create','error'=>null]);
    }

    /** @param array<string, mixed> $config */
    public static function store(array $config): void
    {
        if (!self::verifyCsrf()) return;
        [$values,$error] = self::validatedInput($_POST, true);
        if ($error !== null) { self::renderForm('Create user',$values,'/users/create',$error); return; }
        if ($values['role']==='admin' && (Auth::user()['role']??'')!=='admin') { self::renderForm('Create user',$values,'/users/create','Only an administrator can create another administrator.'); return; }
        $db=Database::connect($config);
        try {
            $db->beginTransaction();
            $stmt=$db->prepare('INSERT INTO users (full_name, username, email, password_hash, role, is_active) VALUES (:full_name,:username,:email,:password_hash,:role,1)');
            $stmt->execute($values);
            $id=(int)$db->lastInsertId();
            AuditLog::record($db,(int)(Auth::user()['id']??0),'user.created','user',$id,'Created user '.$values['username'],['role'=>$values['role'],'is_active'=>true]);
            $db->commit();
            self::flash('success','User account created.'); header('Location: /users',true,303);
        } catch (PDOException $e) { if($db->inTransaction())$db->rollBack(); self::renderForm('Create user',$values,'/users/create',self::isDuplicate($e)?'Username or email is already in use.':'Could not create this user.'); }
    }

    /** @param array<string, mixed> $config */
    public static function edit(string $id,array $config): void
    {
        if (!ctype_digit($id) || (int)$id<1) { http_response_code(404); echo 'User not found.'; return; }
        $stmt=Database::connect($config)->prepare('SELECT id,full_name,username,email,role,is_active FROM users WHERE id=:id');$stmt->execute(['id'=>(int)$id]);$person=$stmt->fetch();
        if (!$person) { http_response_code(404); echo 'User not found.'; return; }
        View::render('users/form',['title'=>'Edit user','user'=>Auth::user(),'csrfToken'=>Csrf::token(),'person'=>$person,'action'=>'/users/'.$id.'/edit','error'=>null]);
    }

    /** @param array<string, mixed> $config */
    public static function update(string $id,array $config): void
    {
        if (!self::verifyCsrf()) return;
        $db=Database::connect($config);$before=User::findById($db,(int)$id);
        if (!ctype_digit($id)||(int)$id<1||$before===false) { http_response_code(404); echo 'User not found.'; return; }
        [$values,$error]=self::validatedInput($_POST,false);
        if ($error!==null) { self::renderForm('Edit user',$values,'/users/'.$id.'/edit',$error); return; }
        if ((int)$id===(int)(Auth::user()['id']??0) && $values['role']!==$before['role']) { self::renderForm('Edit user',$values,'/users/'.$id.'/edit','You cannot change your own role.'); return; }
        if ($values['role']==='admin' && ($actorRole=Auth::user()['role']??'')!=='admin') { self::renderForm('Edit user',$values,'/users/'.$id.'/edit','Only an administrator can assign the Admin role.'); return; }
        if ($before['role']==='admin' && $values['role']!=='admin' && (int)$before['is_active']===1) {
            $activeAdmins=(int)$db->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1")->fetchColumn();
            if($activeAdmins<=1){self::renderForm('Edit user',$values,'/users/'.$id.'/edit','The last active administrator must remain an administrator.');return;}
        }
        try {
            $db->beginTransaction();
            $stmt=$db->prepare('UPDATE users SET full_name=:full_name, username=:username, email=:email, role=:role WHERE id=:id');
            $stmt->execute(['full_name'=>$values['full_name'],'username'=>$values['username'],'email'=>$values['email'],'role'=>$values['role'],'id'=>(int)$id]);
            $actorId=(int)(Auth::user()['id']??0);
            $oldValues=['full_name'=>$before['full_name'],'username'=>$before['username'],'email'=>$before['email'],'role'=>$before['role']];
            $newValues=['full_name'=>$values['full_name'],'username'=>$values['username'],'email'=>$values['email'],'role'=>$values['role']];
            $roleChanged=$before['role']!==$values['role'];
            AuditLog::record($db,$actorId,$roleChanged?'user.role_changed':'user.updated','user',(int)$id,$roleChanged?'Changed role for '.$values['username']:'Updated user '.$values['username'],['before'=>$oldValues,'after'=>$newValues]);
            $db->commit();
            self::flash('success','User account updated.'); header('Location: /users',true,303);
        } catch (PDOException $e) { if($db->inTransaction())$db->rollBack(); self::renderForm('Edit user',$values,'/users/'.$id.'/edit',self::isDuplicate($e)?'Username or email is already in use.':'Could not update this user.'); }
    }

    /** @param array<string, mixed> $config */
    public static function toggle(string $id,array $config): void
    {
        if (!self::verifyCsrf()) return;
        $actor=Auth::user(); if ((int)$id===(int)($actor['id']??0)) { self::flash('error','You cannot disable your own account.'); header('Location: /users',true,303); return; }
        $db=Database::connect($config);$db->beginTransaction();
        try {
            $stmt=$db->prepare('SELECT id,username,role,is_active FROM users WHERE id=:id FOR UPDATE');$stmt->execute(['id'=>(int)$id]);$person=$stmt->fetch();
            if (!$person) { $db->rollBack(); http_response_code(404); echo 'User not found.'; return; }
            $next=(int)$person['is_active']===0;
            if (!$next && $person['role']==='admin') {
                $count=(int)$db->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1")->fetchColumn();
                if ($count<=1) { $db->rollBack(); self::flash('error','The last active administrator cannot be disabled.'); header('Location: /users',true,303); return; }
            }
            $db->prepare('UPDATE users SET is_active=:active WHERE id=:id')->execute(['active'=>$next?1:0,'id'=>(int)$id]);
            AuditLog::record($db,(int)($actor['id']??0),$next?'user.enabled':'user.disabled','user',(int)$id,($next?'Enabled':'Disabled').' user '.$person['username'],['is_active'=>['before'=>(bool)$person['is_active'],'after'=>$next]]);
            $db->commit();self::flash('success',$next?'User account enabled.':'User account disabled.');header('Location: /users',true,303);
        } catch (PDOException $e) { if($db->inTransaction())$db->rollBack(); self::unavailable('users',$e); }
    }

    /** @param array<string, mixed> $config */
    public static function roles(array $config): void
    {
        $db=Database::connect($config);$roles=$db->query('SELECT id,name,description,is_system FROM roles ORDER BY id')->fetchAll();$permissions=$db->query('SELECT id,permission_key,module,label FROM permissions ORDER BY module,id')->fetchAll();
        foreach($roles as &$role){$stmt=$db->prepare('SELECT permission_id FROM role_permissions WHERE role_id=:id');$stmt->execute(['id'=>$role['id']]);$role['permission_ids']=array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));}unset($role);
        View::render('users/roles',['title'=>'Roles & Permissions','user'=>Auth::user(),'csrfToken'=>Csrf::token(),'roles'=>$roles,'permissions'=>$permissions,'currentPath'=>'/roles']);
    }

    /** @param array<string, mixed> $config */
    public static function saveRolePermissions(string $id,array $config): void
    {
        if(!self::verifyCsrf())return;$db=Database::connect($config);$stmt=$db->prepare('SELECT id,name FROM roles WHERE id=:id');$stmt->execute(['id'=>(int)$id]);$role=$stmt->fetch();
        if(!$role){http_response_code(404);echo 'Role not found.';return;}if($role['name']==='admin'){self::flash('error','Administrator permissions are fixed to preserve full system access.');header('Location: /roles',true,303);return;}
        $ids=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['permissions']??[])),static fn(int $n):bool=>$n>0)));
        $protectedId=$db->query("SELECT id FROM permissions WHERE permission_key='roles.manage'")->fetchColumn();
        if($role['name']!=='admin'&&$protectedId!==false)$ids=array_values(array_diff($ids,[(int)$protectedId]));
        $before=PermissionService::effective($db,0,(string)$role['name']);
        try{$db->beginTransaction();$db->prepare('DELETE FROM role_permissions WHERE role_id=:id')->execute(['id'=>(int)$id]);$insert=$db->prepare('INSERT INTO role_permissions(role_id,permission_id) SELECT :role,id FROM permissions WHERE id=:permission');foreach($ids as $permissionId)$insert->execute(['role'=>(int)$id,'permission'=>$permissionId]);$afterStmt=$db->prepare('SELECT p.permission_key FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE rp.role_id=:id');$afterStmt->execute(['id'=>(int)$id]);$after=$afterStmt->fetchAll(PDO::FETCH_COLUMN);AuditLog::record($db,(int)(Auth::user()['id']??0),'role.permissions_updated','role',(int)$id,'Updated permissions for role '.$role['name'],['before'=>$before,'after'=>$after]);$db->commit();self::flash('success','Role permissions updated.');}catch(PDOException $e){if($db->inTransaction())$db->rollBack();self::unavailable('roles',$e);return;}header('Location: /roles',true,303);
    }

    /** @param array<string, mixed> $config */
    public static function userPermissions(string $id,array $config): void
    {
        $db=Database::connect($config);$person=User::findById($db,(int)$id);if(!$person){http_response_code(404);echo 'User not found.';return;}
        $permissions=$db->query('SELECT id,permission_key,module,label FROM permissions ORDER BY module,id')->fetchAll();$overrides=PermissionService::overrides($db,(int)$id);
        foreach($permissions as &$permission)$permission['effective']=PermissionService::allows($db,(int)$id,(string)$person['role'],(string)$permission['permission_key']);unset($permission);
        View::render('users/permissions',['title'=>'User Permissions','user'=>Auth::user(),'csrfToken'=>Csrf::token(),'person'=>$person,'permissions'=>$permissions,'overrides'=>$overrides]);
    }

    /** @param array<string, mixed> $config */
    public static function saveUserPermissions(string $id,array $config): void
    {
        if(!self::verifyCsrf())return;$db=Database::connect($config);$person=User::findById($db,(int)$id);if(!$person){http_response_code(404);echo 'User not found.';return;}if($person['role']==='admin'){self::flash('error','Administrator accounts always retain full access.');header('Location: /users',true,303);return;}
        $values=(array)($_POST['permission']??[]);$actor=Auth::user();if((int)($actor['id']??0)===(int)$id){self::flash('error','You cannot change your own permission overrides.');header('Location: /users',true,303);return;}$before=PermissionService::overrides($db,(int)$id);
        try{$db->beginTransaction();$db->prepare('DELETE FROM user_permissions WHERE user_id=:id')->execute(['id'=>(int)$id]);$stmt=$db->prepare("INSERT INTO user_permissions(user_id,permission_id,effect) SELECT :user_id,id,:effect FROM permissions WHERE permission_key=:permission");foreach($values as $key=>$effect){if(!is_string($key)||!in_array($effect,['allow','deny'],true)||in_array($key,['users.manage','roles.manage','users.permissions'],true))continue;$stmt->execute(['user_id'=>(int)$id,'effect'=>$effect,'permission'=>$key]);}AuditLog::record($db,(int)($actor['id']??0),'user.permissions_updated','user',(int)$id,'Updated permissions for user '.$person['username'],['before'=>$before,'after'=>PermissionService::overrides($db,(int)$id)]);$db->commit();self::flash('success','User permission overrides updated.');}catch(PDOException $e){if($db->inTransaction())$db->rollBack();self::unavailable('users',$e);return;}header('Location: /users/'.$id.'/permissions',true,303);
    }

    public static function accountSecurity(): void
    {
        View::render('users/password',['title'=>'Account Security','user'=>Auth::user(),'csrfToken'=>Csrf::token(),'mode'=>'change','person'=>Auth::user(),'error'=>null]);
    }

    /** @param array<string, mixed> $config */
    public static function changePassword(array $config): void
    {
        if(!self::verifyCsrf())return;$actor=Auth::user();$current=(string)($_POST['current_password']??'');$password=(string)($_POST['new_password']??'');$confirm=(string)($_POST['confirm_password']??'');
        if(strlen($password)<12||$password!==$confirm){self::passwordForm('change',$actor,$password!==$confirm?'The new passwords do not match.':'New password must be at least 12 characters.');return;}
        $db=Database::connect($config);$stmt=$db->prepare('SELECT password_hash FROM users WHERE id=:id AND is_active=1');$stmt->execute(['id'=>(int)$actor['id']]);$hash=$stmt->fetchColumn();
        if(!is_string($hash)||!password_verify($current,$hash)){self::passwordForm('change',$actor,'Current password is incorrect.');return;}
        try{$db->beginTransaction();$db->prepare('UPDATE users SET password_hash=:hash,auth_version=auth_version+1 WHERE id=:id')->execute(['hash'=>password_hash($password,PASSWORD_DEFAULT),'id'=>(int)$actor['id']]);$newVersionStmt=$db->prepare('SELECT auth_version FROM users WHERE id=:id');$newVersionStmt->execute(['id'=>(int)$actor['id']]);$newVersion=(int)$newVersionStmt->fetchColumn();AuditLog::record($db,(int)$actor['id'],'user.password_changed','user',(int)$actor['id'],'User changed their password');$db->commit();$_SESSION['user']['auth_version']=$newVersion;session_regenerate_id(true);self::flash('success','Password changed successfully.');header('Location: /account/security',true,303);}catch(PDOException $e){if($db->inTransaction())$db->rollBack();self::unavailable('password',$e);}
    }

    /** @param array<string, mixed> $config */
    public static function resetPasswordForm(string $id,array $config): void
    {
        $person=User::findById(Database::connect($config),(int)$id);if(!$person){http_response_code(404);echo 'User not found.';return;}
        View::render('users/password',['title'=>'Reset user password','user'=>Auth::user(),'csrfToken'=>Csrf::token(),'mode'=>'reset','person'=>$person,'error'=>null]);
    }

    /** @param array<string, mixed> $config */
    public static function resetPassword(string $id,array $config): void
    {
        if(!self::verifyCsrf())return;$db=Database::connect($config);$person=User::findById($db,(int)$id);if(!$person){http_response_code(404);echo 'User not found.';return;}
        $password=(string)($_POST['new_password']??'');$confirm=(string)($_POST['confirm_password']??'');if(strlen($password)<12||$password!==$confirm){self::passwordForm('reset',$person,$password!==$confirm?'The new passwords do not match.':'Password must be at least 12 characters.');return;}
        try{$db->beginTransaction();$db->prepare('UPDATE users SET password_hash=:hash,auth_version=auth_version+1 WHERE id=:id')->execute(['hash'=>password_hash($password,PASSWORD_DEFAULT),'id'=>(int)$id]);AuditLog::record($db,(int)(Auth::user()['id']??0),'user.password_reset','user',(int)$id,'Administrator reset password for '.$person['username']);$db->commit();self::flash('success','Password reset. The user must sign in again on their devices.');header('Location: /users',true,303);}catch(PDOException $e){if($db->inTransaction())$db->rollBack();self::unavailable('password-reset',$e);}
    }

    /** @param array<string, mixed> $input @return array{array<string,mixed>,?string} */
    private static function validatedInput(array $input,bool $withPassword): array
    {
        $values=['full_name'=>trim((string)($input['full_name']??'')),'username'=>trim((string)($input['username']??'')),'email'=>trim((string)($input['email']??'')),'role'=>in_array(($input['role']??''),['admin','staff'],true)?(string)$input['role']:''];
        if($values['full_name']===''||mb_strlen($values['full_name'])>120)return[$values,'Enter a name up to 120 characters.'];
        if(!preg_match('/^[A-Za-z0-9_.-]{3,60}$/',$values['username']))return[$values,'Username must be 3–60 characters and use letters, numbers, dots, dashes, or underscores.'];
        if($values['email']!==''&&!filter_var($values['email'],FILTER_VALIDATE_EMAIL))return[$values,'Enter a valid email address.'];
        if($values['email']==='')$values['email']=null;
        if($values['role']==='')return[$values,'Choose a valid role.'];
        if($withPassword){$password=(string)($input['password']??'');if(strlen($password)<12)return[$values,'Password must be at least 12 characters.'];$values['password_hash']=password_hash($password,PASSWORD_DEFAULT);}
        return[$values,null];
    }

    private static function verifyCsrf(): bool { if(Csrf::verify(isset($_POST['_csrf_token'])&&is_string($_POST['_csrf_token'])?$_POST['_csrf_token']:null))return true;http_response_code(419);echo 'Your session expired. Reload and try again.';return false; }
    private static function renderForm(string $title,array $person,string $action,string $error): void { View::render('users/form',['title'=>$title,'user'=>Auth::user(),'csrfToken'=>Csrf::token(),'person'=>$person,'action'=>$action,'error'=>$error]); }
    private static function passwordForm(string $mode,array $person,string $error): void { View::render('users/password',['title'=>$mode==='change'?'Account Security':'Reset user password','user'=>Auth::user(),'csrfToken'=>Csrf::token(),'mode'=>$mode,'person'=>$person,'error'=>$error]); }
    private static function isDuplicate(PDOException $e): bool { return (string)$e->getCode()==='23000'; }
    private static function flash(string $type,string $message): void { $_SESSION['user_flash']=['type'=>$type,'message'=>$message]; }
    private static function unavailable(string $area,PDOException $e): void { error_log('[user-admin/'.$area.'] '.$e->getMessage());http_response_code(500);echo 'User management is temporarily unavailable.'; }
}
