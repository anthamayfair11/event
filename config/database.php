<?php
/**
 * DB接続・認証情報の共通ヘルパー
 *
 * 認証情報は config/secrets.php（Git管理外）から読み込む。
 * 各ページはこのファイルを require_once するだけでよく、
 * 接続情報を個別にハードコードしない。
 */

/**
 * 機密設定を一度だけ読み込んで返す。
 */
function eventConfig()
{
    static $config = null;
    if ($config === null) {
        $path = __DIR__ . '/secrets.php';
        if (!is_file($path)) {
            http_response_code(500);
            die('設定ファイル config/secrets.php が見つかりません。config/secrets.sample.php を複製して作成してください。');
        }
        $config = require $path;
    }
    return $config;
}

/**
 * DB接続を取得する。
 *
 * @param bool $jsonError 接続失敗時に JSON で返すなら true（API用）。
 *                        false の場合はプレーンテキストで返す。
 * @return PDO
 */
function getDbConnection($jsonError = false)
{
    $db  = eventConfig()['db'];
    $dsn = "mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        return new PDO($dsn, $db['user'], $db['password'], $options);
    } catch (PDOException $e) {
        // 例外メッセージは外部に出さない（認証情報や内部構造の漏えい防止）
        if ($jsonError) {
            die(json_encode(['success' => false, 'message' => 'データベース接続エラー']));
        }
        die('データベース接続エラー');
    }
}

/**
 * 管理者パスワードを取得する。
 *
 * @return string
 */
function getAdminPassword()
{
    return eventConfig()['admin_password'];
}
