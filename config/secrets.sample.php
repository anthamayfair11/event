<?php
/**
 * 機密情報テンプレート
 *
 * このファイルを config/secrets.php として複製し、実際の値を記入すること。
 * config/secrets.php は Git 管理対象外（.gitignore 済み）。
 * 値は環境変数（EVENT_DB_* / EVENT_ADMIN_PASSWORD）でも上書き可能。
 */
return [
    'db' => [
        'host'     => 'localhost:3306',
        'name'     => 'YOUR_DB_NAME',
        'user'     => 'YOUR_DB_USER',
        'password' => 'YOUR_DB_PASSWORD',
        'charset'  => 'utf8mb4',
    ],
    'admin_password' => 'CHANGE_ME',
];
