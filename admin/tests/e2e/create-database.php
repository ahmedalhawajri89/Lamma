<?php

/*
 * ينشئ قاعدة اختبارات E2E إن لم تكن موجودة. يُشغَّل من مجلد api.
 * لارافل لا يتصل بقاعدة غير موجودة، فالإنشاء باتصال PDO بالخادم وحده،
 * والإعداد يُقرأ من .env.e2e بالمكتبة نفسها التي يقرؤه بها لارافل.
 */
require getcwd().'/vendor/autoload.php';

$env = Dotenv\Dotenv::createArrayBacked(getcwd(), '.env.e2e')->load();

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s', $env['DB_HOST'] ?? '127.0.0.1', $env['DB_PORT'] ?? '3306'),
    $env['DB_USERNAME'] ?? 'root',
    $env['DB_PASSWORD'] ?? '',
);

$name = str_replace('`', '', $env['DB_DATABASE']);
$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

echo "e2e database ready: {$name}\n";
