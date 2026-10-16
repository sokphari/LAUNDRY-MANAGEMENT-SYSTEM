<?php

declare(strict_types=1);

$database = require __DIR__ . '/cli-bootstrap.php';

function prompt(string $label): string
{
    fwrite(STDOUT, $label);
    return trim((string) fgets(STDIN));
}

function promptPassword(string $label): string
{
    fwrite(STDOUT, $label);
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

$fullName = prompt('Admin full name: ');
$username = prompt('Admin username (3-60 letters, numbers, ., _, or -): ');
$email = prompt('Admin email (optional): ');
$password = promptPassword('Password (at least 12 characters): ');
$passwordConfirmation = promptPassword('Confirm password: ');

$adminCount = (int) $database->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
if ($adminCount > 0) {
    fwrite(STDERR, "An admin account already exists. No seeder row was added.\n");
    exit(1);
}

if ($fullName === '' || mb_strlen($fullName) > 120) {
    fwrite(STDERR, "Full name is required and must be 120 characters or fewer.\n");
    exit(1);
}
if (!preg_match('/\A[a-zA-Z0-9._-]{3,60}\z/', $username)) {
    fwrite(STDERR, "Username must be 3-60 characters using letters, numbers, dot, underscore, or hyphen.\n");
    exit(1);
}
if ($email !== '' && (mb_strlen($email) > 150 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
    fwrite(STDERR, "Enter a valid email address of 150 characters or fewer, or leave it blank.\n");
    exit(1);
}
if (strlen($password) < 12) {
    fwrite(STDERR, "Password must be at least 12 characters.\n");
    exit(1);
}
if (!hash_equals($password, $passwordConfirmation)) {
    fwrite(STDERR, "Passwords do not match.\n");
    exit(1);
}

try {
    $statement = $database->prepare(
        'INSERT INTO users (full_name, username, email, password_hash, role, is_active)
         VALUES (:full_name, :username, :email, :password_hash, :role, 1)'
    );
    $statement->execute([
        'full_name' => $fullName,
        'username' => $username,
        'email' => $email === '' ? null : $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => 'admin',
    ]);
} catch (PDOException $exception) {
    fwrite(STDERR, "Could not create admin account: {$exception->getMessage()}\n");
    exit(1);
}

echo "Admin account created successfully. You can now sign in at /login.\n";
