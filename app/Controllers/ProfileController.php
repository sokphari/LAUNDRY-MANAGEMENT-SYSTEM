<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\View;
use App\Services\AuditLog;
use PDOException;

final class ProfileController
{
    /** @param array<string, mixed> $config */
    public static function show(array $config): void
    {
        try {
            $statement = Database::connect($config)->prepare('SELECT id, full_name, gender, profile, username, email FROM users WHERE id = :id AND is_active = 1 LIMIT 1');
            $statement->execute(['id' => (int) (Auth::user()['id'] ?? 0)]);
            $person = $statement->fetch();
        } catch (PDOException $exception) {
            error_log('[profile] Could not load account profile: ' . $exception->getMessage());
            http_response_code(503);
            echo 'Profile is temporarily unavailable.';
            return;
        }
        if (!is_array($person)) { http_response_code(404); echo 'User not found.'; return; }
        if (!self::isSafeImage($person['profile'] ?? null)) $person['profile'] = null;
        $flash = $_SESSION['profile_flash'] ?? null;
        unset($_SESSION['profile_flash']);
        View::render('profile/index', ['title' => 'My Profile', 'user' => Auth::user(), 'csrfToken' => Csrf::token(), 'person' => $person, 'error' => null, 'flash' => $flash, 'currentPath' => '/profile']);
    }

    /** @param array<string, mixed> $config */
    public static function update(array $config): void
    {
        if (!Csrf::verify(isset($_POST['_csrf_token']) && is_string($_POST['_csrf_token']) ? $_POST['_csrf_token'] : null)) {
            http_response_code(419); echo 'Your session expired. Reload and try again.'; return;
        }

        $actor = Auth::user();
        if (!$actor) { http_response_code(401); return; }
        $database = Database::connect($config);
        $statement = $database->prepare('SELECT id, full_name, gender, profile, username, email FROM users WHERE id = :id AND is_active = 1 LIMIT 1');
        $statement->execute(['id' => (int) $actor['id']]);
        $before = $statement->fetch();
        if (!is_array($before)) { http_response_code(404); echo 'User not found.'; return; }
        $oldProfile = self::isSafeImage($before['profile'] ?? null) ? (string) $before['profile'] : '';

        $name = trim((string) ($_POST['full_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $gender = trim((string) ($_POST['gender'] ?? ''));
        $profileUrl = trim((string) ($_POST['profile_url'] ?? ''));
        $removeImage = ($_POST['remove_profile_image'] ?? '') === '1';
        $values = ['id' => (int) $actor['id'], 'full_name' => $name, 'email' => $email, 'gender' => $gender, 'profile' => $oldProfile ?: null, 'profile_url' => $profileUrl, 'remove_profile_image' => $removeImage, 'username' => $before['username']];
        $error = null;

        if ($name === '' || mb_strlen($name) > 120) $error = 'Enter a name up to 120 characters.';
        elseif ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150)) $error = 'Enter a valid email address.';
        elseif ($gender !== '' && !in_array($gender, ['male', 'female', 'other', 'prefer_not_to_say'], true)) $error = 'Choose a valid gender option.';

        $file = $_FILES['profile_image'] ?? null;
        $hasUpload = is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($error === null && $profileUrl !== '' && $hasUpload) $error = 'Choose either an image file or an image URL, not both.';
        if ($error === null && $removeImage && ($profileUrl !== '' || $hasUpload)) $error = 'Remove the current image or provide a replacement, not both.';
        if ($error === null && $profileUrl !== '' && !self::isAllowedUrl($profileUrl)) $error = 'Enter a valid HTTP or HTTPS image URL.';

        $newUploadPath = null;
        $newProfile = $oldProfile ?: null;
        if ($error === null && $profileUrl !== '') $newProfile = $profileUrl;
        elseif ($error === null && $hasUpload) {
            [$newUploadPath, $error] = self::storeImage(is_array($file) ? $file : []);
            if ($error === null) $newProfile = $newUploadPath;
        } elseif ($error === null && $removeImage) $newProfile = null;

        if ($error !== null) {
            self::render($values, $oldProfile, $error);
            return;
        }

        try {
            $database->beginTransaction();
            $database->prepare('UPDATE users SET full_name = :full_name, gender = :gender, profile = :profile, email = :email WHERE id = :id')
                ->execute(['full_name' => $name, 'gender' => $gender !== '' ? $gender : null, 'profile' => $newProfile, 'email' => $email !== '' ? $email : null, 'id' => (int) $actor['id']]);
            AuditLog::record($database, (int) $actor['id'], 'user.profile_updated', 'user', (int) $actor['id'], 'User updated their own profile', [
                'before' => ['full_name' => $before['full_name'], 'gender' => $before['gender'], 'profile' => $before['profile'], 'email' => $before['email']],
                'after' => ['full_name' => $name, 'gender' => $gender !== '' ? $gender : null, 'profile' => $newProfile, 'email' => $email !== '' ? $email : null],
            ]);
            $database->commit();
            if (($before['profile'] ?? null) !== $newProfile) self::deleteStoredImage($before['profile'] ?? null);
            $_SESSION['user']['full_name'] = $name;
            $_SESSION['user']['email'] = $email !== '' ? $email : null;
            $_SESSION['user']['profile'] = $newProfile;
            $_SESSION['profile_flash'] = ['type' => 'success', 'message' => 'Profile updated successfully.'];
            header('Location: /profile', true, 303);
        } catch (PDOException $exception) {
            if ($database->inTransaction()) $database->rollBack();
            self::deleteStoredImage($newUploadPath);
            error_log('[profile] Could not update account profile: ' . $exception->getMessage());
            $values['profile_url'] = '';
            $values['remove_profile_image'] = false;
            self::render($values, $oldProfile, 'Could not update your profile. Please try again.');
        }
    }

    /** @param array<string, mixed> $values */
    private static function render(array $values, string $currentImage, string $error): void
    {
        $values['profile'] = $currentImage ?: null;
        View::render('profile/index', ['title' => 'My Profile', 'user' => Auth::user(), 'csrfToken' => Csrf::token(), 'person' => $values, 'error' => $error, 'flash' => null, 'currentPath' => '/profile']);
    }

    /** @param array<string, mixed> $file @return array{?string,?string} */
    private static function storeImage(array $file): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) return [null, $error === UPLOAD_ERR_NO_FILE ? 'Choose an image file to upload.' : 'The image upload failed. Please try again.'];
        $path = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if ($size < 1 || $size > 5 * 1024 * 1024 || !is_uploaded_file($path)) return [null, 'Choose a valid image smaller than 5 MB.'];
        $image = @getimagesize($path);
        if (!is_array($image) || ($image[0] ?? 0) > 8000 || ($image[1] ?? 0) > 8000 || ($image[0] ?? 0) * ($image[1] ?? 0) > 40000000) return [null, 'Choose a valid image no larger than 8,000 by 8,000 pixels.'];
        try { $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path); } catch (\Throwable) { return [null, 'The uploaded image could not be verified.']; }
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if (!isset($extensions[$mime]) || ($image['mime'] ?? null) !== $mime) return [null, 'Use a JPEG, PNG, WebP, or GIF image.'];
        $directory = BASE_PATH . '/public/uploads/profiles';
        if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) return [null, 'Profile image storage is unavailable.'];
        try { $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime]; } catch (\Throwable) { return [null, 'Could not prepare the profile image.']; }
        if (!move_uploaded_file($path, $directory . '/' . $filename)) return [null, 'Could not save the profile image.'];
        return ['/uploads/profiles/' . $filename, null];
    }

    private static function isAllowedUrl(string $url): bool
    {
        if (mb_strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) return false;
        $parts = parse_url($url);
        return is_array($parts) && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true) && !empty($parts['host']) && !isset($parts['user'], $parts['pass']);
    }

    private static function isSafeImage(mixed $image): bool
    {
        return is_string($image) && (preg_match('~\A/uploads/profiles/[a-f0-9]{32}\.(?:jpg|png|webp|gif)\z~', $image) === 1 || self::isAllowedUrl($image));
    }

    private static function deleteStoredImage(mixed $path): void
    {
        if (!is_string($path) || preg_match('~\A/uploads/profiles/[a-f0-9]{32}\.(?:jpg|png|webp|gif)\z~', $path) !== 1) return;
        $file = BASE_PATH . '/public' . $path;
        if (is_file($file)) @unlink($file);
    }
}
