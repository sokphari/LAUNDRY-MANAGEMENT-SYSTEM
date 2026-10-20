<?php

declare(strict_types=1);

// Interactive one-time seeder for the initial Admin and Staff accounts.
// Run after database/migrate.php. Passwords are prompted and stored as hashes.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$database = require dirname(__DIR__) . '/cli-bootstrap.php';

function prompt(string $label, string $default = ''): string
{
    fwrite(STDOUT, $label . ($default !== '' ? " [{$default}]" : '') . ': ');
    $answer = trim((string) fgets(STDIN));
    return $answer === '' ? $default : $answer;
}

function promptPassword(string $label): string
{
    fwrite(STDOUT, $label . ': ');
    $canHide = DIRECTORY_SEPARATOR !== '\\'
        && function_exists('shell_exec')
        && function_exists('stream_isatty')
        && stream_isatty(STDIN);
    if ($canHide) {
        shell_exec('stty -echo');
    }

    try {
        $password = trim((string) fgets(STDIN));
    } finally {
        if ($canHide) {
            shell_exec('stty echo');
            fwrite(STDOUT, "\n");
        }
    }

    return $password;
}

function collectAccount(string $role, string $defaultName, string $defaultUsername): array
{
    fwrite(STDOUT, "\n{$role} account\n");
    $fullName = prompt('Full name', $defaultName);
    $username = prompt('Username', $defaultUsername);
    $email = prompt('Email (optional)');
    $password = promptPassword('Password (at least 12 characters)');
    $confirmation = promptPassword('Confirm password');

    if ($fullName === '' || mb_strlen($fullName) > 120) {
        throw new InvalidArgumentException('Full name is required and must be 120 characters or fewer.');
    }
    if (!preg_match('/\A[a-zA-Z0-9._-]{3,60}\z/', $username)) {
        throw new InvalidArgumentException('Username must be 3–60 characters using letters, numbers, dot, underscore, or hyphen.');
    }
    if ($email !== '' && (mb_strlen($email) > 150 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
        throw new InvalidArgumentException('Enter a valid email address of 150 characters or fewer, or leave it blank.');
    }
    if (strlen($password) < 12) {
        throw new InvalidArgumentException('Password must be at least 12 characters.');
    }
    if (!hash_equals($password, $confirmation)) {
        throw new InvalidArgumentException('Passwords do not match.');
    }

    return [
        'full_name' => $fullName,
        'username' => $username,
        'email' => $email === '' ? null : $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => strtolower($role),
    ];
}

try {
    $admin = collectAccount('Admin', 'Laundry Admin', 'admin');
    $staff = collectAccount('Staff', 'Laundry Staff', 'staff');

    if (strcasecmp($admin['username'], $staff['username']) === 0
        || ($admin['email'] !== null && $staff['email'] !== null && strcasecmp($admin['email'], $staff['email']) === 0)) {
        throw new InvalidArgumentException('Admin and Staff accounts must use different usernames and email addresses.');
    }

    $database->beginTransaction();
    $exists = $database->prepare('SELECT id FROM users WHERE username = :username OR (:email_check IS NOT NULL AND email = :email_value) LIMIT 1');
    foreach ([$admin, $staff] as $account) {
        $exists->execute(['username' => $account['username'], 'email_check' => $account['email'], 'email_value' => $account['email']]);
        if ($exists->fetchColumn() !== false) {
            throw new RuntimeException("An account already uses username/email for '{$account['username']}'. No accounts were added.");
        }
    }

    $insert = $database->prepare(
        'INSERT INTO users (full_name, username, email, password_hash, role, is_active)
         VALUES (:full_name, :username, :email, :password_hash, :role, 1)'
    );
    foreach ([$admin, $staff] as $account) {
        $insert->execute($account);
    }
    $database->commit();
} catch (Throwable $exception) {
    if ($database->inTransaction()) {
        $database->rollBack();
    }
    fwrite(STDERR, "Could not seed Admin and Staff accounts: {$exception->getMessage()}\n");
    exit(1);
}

echo "Created active Admin '{$admin['username']}' and Staff '{$staff['username']}' accounts.\n";
