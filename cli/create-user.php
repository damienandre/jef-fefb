<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Jef\Database;

if ($argc < 3) {
    echo "Usage: php cli/create-user.php <username> <password>\n";
    exit(1);
}

$username = $argv[1];
$password = $argv[2];

$hash = password_hash($password, PASSWORD_DEFAULT);

$db = Database::get();

try {
    $stmt = $db->prepare("INSERT INTO jef_users (username, password_hash) VALUES (?, ?)");
    $stmt->execute([$username, $hash]);
    echo "User '{$username}' created successfully.\n";
} catch (\PDOException $e) {
    if ($e->getCode() === '23000') {
        echo "Error: User '{$username}' already exists.\n";
        exit(1);
    }
    throw $e;
}
