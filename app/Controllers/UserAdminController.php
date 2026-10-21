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
        try {
            $users = Database::connect($config)->query('SELECT id, full_name, gender, profile, username, email, role, is_active, created_at FROM users ORDER BY role, full_name')->fetchAll();
            foreach ($users as &$person) if (!self::isSafeProfileImage($person['profile'] ?? null)) $person['profile'] = null;
            unset($person);
        }
        catch (PDOException $e) { self::unavailable('users', $e); return; }
        View::render('users/index', ['title'=>'Users','user'=>Auth::user(),'csrfToken'=>Csrf::token(),'users'=>$users,'currentPath'=>'/users']);
    }

    public static function create(): void
    {
        View::render('users/form', ['title'=>'Create user','user'=>Auth::user(),'csrfToken'=>Csrf::token(),'person'=>['full_name'=>'','gender'=>'','profile'=>'','username'=>'','email'=>'','role'=>'staff'],'action'=>'/users/create','error'=>null]);
    }

    /** @param array<string, mixed> $config */
    public static function store(array $config): void
    {
        if (!self::verifyCsrf()) return;
        [$values,$error] = self::validatedInput($_POST, $_FILES, true);
        if ($error !== null) { self::renderForm('Create user',$values,'/users/create',$error); return; }
        if ($values['role']==='admin' && (Auth::user()['role']??'')!=='admin') { self::deleteStoredProfileImage($values['_uploaded_profile_path'] ?? null); $values['profile']=null; unset($values['_uploaded_profile_path']); self::renderForm('Create user',$values,'/users/create','Only an administrator can create another administrator.'); return; }
        try { $db=Database::connect($config); }
        catch (PDOException) {
            self::deleteStoredProfileImage($values['_uploaded_profile_path'] ?? null);
            $values['profile'] = null;
            unset($values['_uploaded_profile_path']);
            self::renderForm('Create user',$values,'/users/create','Could not create this user.');
            return;
        }
        try {
            $db->beginTransaction();
            $stmt=$db->prepare('INSERT INTO users (full_name, gender, profile, username, email, password_hash, role, is_active) VALUES (:full_name,:gender,:profile,:username,:email,:password_hash,:role,1)');
            $stmt->execute(['full_name'=>$values['full_name'],'gender'=>$values['gender'],'profile'=>$values['profile'],'username'=>$values['username'],'email'=>$values['email'],'password_hash'=>$values['password_hash'],'role'=>$values['role']]);
            $id=(int)$db->lastInsertId();
            AuditLog::record($db,(int)(Auth::user()['id']??0),'user.created','user',$id,'Created user '.$values['username'],['role'=>$values['role'],'is_active'=>true]);
            $db->prepare('INSERT INTO user_notification_reads (user_id, last_read_audit_id) SELECT :user_id, COALESCE(MAX(id), 0) FROM audit_logs')->execute(['user_id'=>$id]);
            $db->commit();
            self::flash('success','User account created.'); header('Location: /users',true,303);
        } catch (PDOException $e) { if($db->inTransaction())$db->rollBack(); self::deleteStoredProfileImage($values['_uploaded_profile_path'] ?? null); $values['profile']=null; unset($values['_uploaded_profile_path']); self::renderForm('Create user',$values,'/users/create',self::isDuplicate($e)?'Username or email is already in use.':'Could not create this user.'); }
    }

    /** @param array<string, mixed> $config */
    public static function edit(string $id,array $config): void
    {
        if (!ctype_digit($id) || (int)$id<1) { http_response_code(404); echo 'User not found.'; return; }
        $stmt=Database::connect($config)->prepare('SELECT id,full_name,gender,profile,username,email,role,is_active FROM users WHERE id=:id');$stmt->execute(['id'=>(int)$id]);$person=$stmt->fetch();
        if (!$person) { http_response_code(404); echo 'User not found.'; return; }
        if (!self::isSafeProfileImage($person['profile'] ?? null)) $person['profile'] = null;
        View::render('users/form',['title'=>'Edit user','user'=>Auth::user(),'csrfToken'=>Csrf::token(),'person'=>$person,'action'=>'/users/'.$id.'/edit','error'=>null]);
    }

    /** @param array<string, mixed> $config */
    public static function update(string $id,array $config): void
    {
        if (!self::verifyCsrf()) return;
        $db=Database::connect($config);$before=User::findById($db,(int)$id);
        if (!ctype_digit($id)||(int)$id<1||$before===false) { http_response_code(404); echo 'User not found.'; return; }
        $existingProfile=self::isSafeProfileImage($before['profile']??null)?(string)$before['profile']:'';
        [$values,$error]=self::validatedInput($_POST,$_FILES,false,$existingProfile);
        if ($error!==null) { self::renderForm('Edit user',$values,'/users/'.$id.'/edit',$error); return; }
        if ((int)$id===(int)(Auth::user()['id']??0) && $values['role']!==$before['role']) { self::deleteStoredProfileImage($values['_uploaded_profile_path'] ?? null); $values['profile']=$existingProfile?:null; unset($values['_uploaded_profile_path']); self::renderForm('Edit user',$values,'/users/'.$id.'/edit','You cannot change your own role.'); return; }
        if ($values['role']==='admin' && ($actorRole=Auth::user()['role']??'')!=='admin') { self::deleteStoredProfileImage($values['_uploaded_profile_path'] ?? null); $values['profile']=$existingProfile?:null; unset($values['_uploaded_profile_path']); self::renderForm('Edit user',$values,'/users/'.$id.'/edit','Only an administrator can assign the Admin role.'); return; }
        if ($before['role']==='admin' && $values['role']!=='admin' && (int)$before['is_active']===1) {
            $activeAdmins=(int)$db->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1")->fetchColumn();
            if($activeAdmins<=1){self::deleteStoredProfileImage($values['_uploaded_profile_path'] ?? null);$values['profile']=$existingProfile?:null;unset($values['_uploaded_profile_path']);self::renderForm('Edit user',$values,'/users/'.$id.'/edit','The last active administrator must remain an administrator.');return;}
        }
        try {
            $db->beginTransaction();
            $stmt=$db->prepare('UPDATE users SET full_name=:full_name, gender=:gender, profile=:profile, username=:username, email=:email, role=:role WHERE id=:id');
            $stmt->execute(['full_name'=>$values['full_name'],'gender'=>$values['gender'],'profile'=>$values['profile'],'username'=>$values['username'],'email'=>$values['email'],'role'=>$values['role'],'id'=>(int)$id]);
            $actorId=(int)(Auth::user()['id']??0);
            $oldValues=['full_name'=>$before['full_name'],'gender'=>$before['gender'],'profile'=>$before['profile'],'username'=>$before['username'],'email'=>$before['email'],'role'=>$before['role']];
            $newValues=['full_name'=>$values['full_name'],'gender'=>$values['gender'],'profile'=>$values['profile'],'username'=>$values['username'],'email'=>$values['email'],'role'=>$values['role']];
            $roleChanged=$before['role']!==$values['role'];
            AuditLog::record($db,$actorId,$roleChanged?'user.role_changed':'user.updated','user',(int)$id,$roleChanged?'Changed role for '.$values['username']:'Updated user '.$values['username'],['before'=>$oldValues,'after'=>$newValues]);
            $db->commit();
            if (($before['profile'] ?? null) !== ($values['profile'] ?? null)) self::deleteStoredProfileImage($before['profile'] ?? null);
            self::flash('success','User account updated.'); header('Location: /users',true,303);
        } catch (PDOException $e) { if($db->inTransaction())$db->rollBack(); self::deleteStoredProfileImage($values['_uploaded_profile_path'] ?? null); $values['profile']=$existingProfile!==''?$existingProfile:null; unset($values['_uploaded_profile_path']); self::renderForm('Edit user',$values,'/users/'.$id.'/edit',self::isDuplicate($e)?'Username or email is already in use.':'Could not update this user.'); }
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

    /** @param array<string, mixed> $input @param array<string, mixed> $files @return array{array<string,mixed>,?string} */
    private static function validatedInput(array $input,array $files,bool $withPassword,string $existingProfile=''): array
    {
        $gender=trim((string)($input['gender']??''));
        $profileUrl=trim((string)($input['profile_url']??''));
        $removeProfileImage=($input['remove_profile_image']??'')==='1';
        $values=['full_name'=>trim((string)($input['full_name']??'')),'gender'=>in_array($gender,['male','female','other','prefer_not_to_say'],true)?$gender:null,'profile'=>$existingProfile!==''?$existingProfile:null,'profile_url'=>$profileUrl,'remove_profile_image'=>$removeProfileImage,'username'=>trim((string)($input['username']??'')),'email'=>trim((string)($input['email']??'')),'role'=>in_array(($input['role']??''),['admin','staff'],true)?(string)$input['role']:''];
        if($values['full_name']===''||mb_strlen($values['full_name'])>120)return[$values,'Enter a name up to 120 characters.'];
        if(!preg_match('/^[A-Za-z0-9_.-]{3,60}$/',$values['username']))return[$values,'Username must be 3–60 characters and use letters, numbers, dots, dashes, or underscores.'];
        if($values['email']!==''&&!filter_var($values['email'],FILTER_VALIDATE_EMAIL))return[$values,'Enter a valid email address.'];
        if($values['email']==='')$values['email']=null;
        if($gender!==''&&!in_array($gender,['male','female','other','prefer_not_to_say'],true))return[$values,'Choose a valid gender option.'];
        if($values['role']==='')return[$values,'Choose a valid role.'];
        if($withPassword){$password=(string)($input['password']??'');if(strlen($password)<12)return[$values,'Password must be at least 12 characters.'];$values['password_hash']=password_hash($password,PASSWORD_DEFAULT);}

        $file=$files['profile_image']??null;
        $hasUpload=is_array($file)&&(int)($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE;
        if ($profileUrl!==''&&$hasUpload) return [$values,'Choose either an image file or an image URL, not both.'];
        if ($removeProfileImage&&($profileUrl!==''||$hasUpload)) return [$values,'Remove the current image or provide a replacement, not both.'];
        if ($profileUrl!=='') {
            if (mb_strlen($profileUrl)>2048||!self::isAllowedProfileUrl($profileUrl)) return [$values,'Enter a valid HTTP or HTTPS image URL.'];
            $values['profile']=$profileUrl;
        } elseif ($hasUpload) {
            if (!is_array($file)) return [$values,'Choose an image file to upload.'];
            [$storedPath,$uploadError]=self::storeProfileImage($file);
            if ($uploadError!==null) return [$values,$uploadError];
            $values['profile']=$storedPath;
            $values['_uploaded_profile_path']=$storedPath;
        } elseif ($removeProfileImage) {
            $values['profile']=null;
        }
        return[$values,null];
    }

    /** @param array<string, mixed> $file @return array{?string,?string} */
    private static function storeProfileImage(array $file): array
    {
        $error=(int)($file['error']??UPLOAD_ERR_NO_FILE);
        if ($error===UPLOAD_ERR_NO_FILE) return [null,'Choose an image file to upload.'];
        if ($error!==UPLOAD_ERR_OK) return [null,'The image upload failed. Please try again.'];
        $temporaryPath=(string)($file['tmp_name']??'');
        $size=(int)($file['size']??0);
        if ($size<1 || $size>5*1024*1024) return [null,'The image must be smaller than 5 MB.'];
        if (!is_uploaded_file($temporaryPath)) return [null,'The uploaded image could not be verified.'];
        $imageInfo=@getimagesize($temporaryPath);
        if (!is_array($imageInfo) || ($imageInfo[0]??0)>8000 || ($imageInfo[1]??0)>8000 || ($imageInfo[0]??0)*($imageInfo[1]??0)>40000000) return [null,'Choose a valid image no larger than 8,000 by 8,000 pixels.'];
        try { $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($temporaryPath); }
        catch (\Throwable) { return [null,'The uploaded image could not be verified.']; }
        $extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
        if (!isset($extensions[$mime]) || ($imageInfo['mime']??null)!==$mime) return [null,'Use a JPEG, PNG, WebP, or GIF image.'];
        $directory=BASE_PATH.'/public/uploads/profiles';
        if (!is_dir($directory) && !@mkdir($directory,0750,true) && !is_dir($directory)) return [null,'Profile image storage is unavailable.'];
        try { $filename=bin2hex(random_bytes(16)).'.'.$extensions[$mime]; }
        catch (\Throwable) { return [null,'Could not prepare the profile image.']; }
        if (!move_uploaded_file($temporaryPath,$directory.'/'.$filename)) return [null,'Could not save the profile image.'];
        return ['/uploads/profiles/'.$filename,null];
    }

    private static function isAllowedProfileUrl(string $url): bool
    {
        if (filter_var($url,FILTER_VALIDATE_URL)===false) return false;
        $parts=parse_url($url);
        return is_array($parts) && in_array(strtolower((string)($parts['scheme']??'')),['http','https'],true)
            && !empty($parts['host']) && !isset($parts['user']) && !isset($parts['pass']);
    }

    private static function isSafeProfileImage(mixed $profile): bool
    {
        if (!is_string($profile)) return false;
        if (preg_match('~\\A/uploads/profiles/[a-f0-9]{32}\\.(?:jpg|png|webp|gif)\\z~',$profile)) return true;
        return self::isAllowedProfileUrl($profile);
    }

    private static function deleteStoredProfileImage(mixed $path): void
    {
        if (!is_string($path) || !preg_match('~\\A/uploads/profiles/[a-f0-9]{32}\\.(?:jpg|png|webp|gif)\\z~',$path)) return;
        $file=BASE_PATH.'/public'.$path;
        if (is_file($file)) @unlink($file);
    }

    private static function verifyCsrf(): bool { if(Csrf::verify(isset($_POST['_csrf_token'])&&is_string($_POST['_csrf_token'])?$_POST['_csrf_token']:null))return true;http_response_code(419);echo 'Your session expired. Reload and try again.';return false; }
    private static function renderForm(string $title,array $person,string $action,string $error): void { View::render('users/form',['title'=>$title,'user'=>Auth::user(),'csrfToken'=>Csrf::token(),'person'=>$person,'action'=>$action,'error'=>$error]); }
    private static function passwordForm(string $mode,array $person,string $error): void { View::render('users/password',['title'=>$mode==='change'?'Account Security':'Reset user password','user'=>Auth::user(),'csrfToken'=>Csrf::token(),'mode'=>$mode,'person'=>$person,'error'=>$error]); }
    private static function isDuplicate(PDOException $e): bool { return (string)$e->getCode()==='23000'; }
    private static function flash(string $type,string $message): void { $_SESSION['user_flash']=['type'=>$type,'message'=>$message]; }
    private static function unavailable(string $area,PDOException $e): void { error_log('[user-admin/'.$area.'] '.$e->getMessage());http_response_code(500);echo 'User management is temporarily unavailable.'; }
}
