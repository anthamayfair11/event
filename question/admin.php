<?php
session_start();

// 管理者パスワード（実際には環境変数や設定ファイルで管理すべき）
define('ADMIN_PASSWORD', 'event2025admin');

// データベース接続
function getDbConnection() {
    $host = 'localhost:3306';
    $dbname = 'xxopfrlp_shinsei';
    $username = 'xxopfrlp_ayuhou10';
    $password = 'sh1nse15651487';
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        return new PDO($dsn, $username, $password, $options);
    } catch (PDOException $e) {
        die('データベース接続エラー: ' . $e->getMessage());
    }
}

// ログイン処理
if (isset($_POST['login'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $login_error = 'パスワードが正しくありません';
    }
}

// ログアウト処理
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// 認証チェック
$is_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// 回答投稿処理
if ($is_logged_in && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['answer'])) {
    $pdo = getDbConnection();
    $question_id = intval($_POST['question_id']);
    $answer_text = trim($_POST['answer_text']);
    $answered_by = trim($_POST['answered_by']) ?: 'あゆ';

    if (!empty($answer_text)) {
        $stmt = $pdo->prepare('UPDATE question_board SET answer_text = :answer, answered_by = :by, answered_at = NOW() WHERE id = :id');
        $stmt->execute([
            'answer' => $answer_text,
            'by' => $answered_by,
            'id' => $question_id
        ]);
        $success_message = '回答を投稿しました';
    }
}

// 質問の公開/非公開切り替え
if ($is_logged_in && isset($_GET['toggle_publish'])) {
    $pdo = getDbConnection();
    $question_id = intval($_GET['toggle_publish']);
    $stmt = $pdo->prepare('UPDATE question_board SET is_published = NOT is_published WHERE id = :id');
    $stmt->execute(['id' => $question_id]);
    header('Location: admin.php');
    exit;
}

// 質問削除
if ($is_logged_in && isset($_GET['delete'])) {
    $pdo = getDbConnection();
    $question_id = intval($_GET['delete']);
    $stmt = $pdo->prepare('DELETE FROM question_board WHERE id = :id');
    $stmt->execute(['id' => $question_id]);
    header('Location: admin.php');
    exit;
}

// 質問一覧取得
if ($is_logged_in) {
    $pdo = getDbConnection();
    $stmt = $pdo->query('SELECT * FROM question_board ORDER BY created_at DESC');
    $questions = $stmt->fetchAll();

    $unanswered = array_filter($questions, fn($q) => empty($q['answer_text']));
    $answered = array_filter($questions, fn($q) => !empty($q['answer_text']));
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>質問管理画面</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php if (!$is_logged_in): ?>
    <!-- ログイン画面 -->
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body">
                        <h3 class="card-title text-center mb-4">🔐 管理者ログイン</h3>
                        <?php if (isset($login_error)): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($login_error) ?></div>
                        <?php endif; ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">パスワード</label>
                                <input type="password" name="password" class="form-control" required autofocus>
                            </div>
                            <button type="submit" name="login" class="btn btn-primary w-100">ログイン</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- 管理画面 -->
    <nav class="navbar navbar-dark bg-primary mb-4">
        <div class="container">
            <span class="navbar-brand">質問管理画面</span>
            <div>
                <a href="index.php" class="btn btn-light btn-sm me-2">👁️ 公開ページを見る</a>
                <a href="?logout" class="btn btn-outline-light btn-sm">ログアウト</a>
            </div>
        </div>
    </nav>

    <div class="container mb-5">
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>

        <!-- 未回答の質問 -->
        <h3 class="mb-3">📝 未回答の質問（<?= count($unanswered) ?>件）</h3>

        <?php if (count($unanswered) === 0): ?>
            <div class="alert alert-info">未回答の質問はありません</div>
        <?php else: ?>
            <?php foreach ($unanswered as $q): ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge bg-secondary">#<?= $q['id'] ?> - <?= $q['category'] ?></span>
                            <small class="text-muted"><?= date('Y-m-d H:i', strtotime($q['created_at'])) ?></small>
                        </div>
                        <div class="fw-bold mb-3">💬 Q: <?= nl2br(htmlspecialchars($q['question_text'])) ?></div>

                        <form method="POST" class="border-top pt-3">
                            <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                            <div class="mb-2">
                                <label class="form-label fw-bold">回答内容</label>
                                <textarea name="answer_text" class="form-control" rows="3" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">回答者名</label>
                                <input type="text" name="answered_by" class="form-control" value="あゆ">
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" name="answer" class="btn btn-success">✅ 回答して公開</button>
                                <a href="?toggle_publish=<?= $q['id'] ?>" class="btn btn-warning">👁️‍🗨️ 非公開にする</a>
                                <a href="?delete=<?= $q['id'] ?>" class="btn btn-danger" onclick="return confirm('本当に削除しますか？')">🗑️ 削除</a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- 回答済みの質問 -->
        <h3 class="mb-3 mt-5">✅ 回答済みの質問（<?= count($answered) ?>件）</h3>

        <?php if (count($answered) === 0): ?>
            <div class="alert alert-info">回答済みの質問はありません</div>
        <?php else: ?>
            <?php foreach ($answered as $q): ?>
                <div class="card shadow-sm mb-3 <?= $q['is_published'] ? '' : 'border-warning' ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <span class="badge bg-secondary">#<?= $q['id'] ?> - <?= $q['category'] ?></span>
                                <?php if (!$q['is_published']): ?>
                                    <span class="badge bg-warning text-dark">非公開</span>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted"><?= date('Y-m-d H:i', strtotime($q['created_at'])) ?></small>
                        </div>
                        <div class="fw-bold mb-2">💬 Q: <?= nl2br(htmlspecialchars($q['question_text'])) ?></div>
                        <div class="bg-light p-3 rounded mb-2">
                            <div class="text-success fw-bold mb-1">✅ 回答: <?= htmlspecialchars($q['answered_by']) ?></div>
                            <div><?= nl2br(htmlspecialchars($q['answer_text'])) ?></div>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="?toggle_publish=<?= $q['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                <?= $q['is_published'] ? '👁️‍🗨️ 非公開にする' : '👁️ 公開する' ?>
                            </a>
                            <a href="?delete=<?= $q['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('本当に削除しますか？')">🗑️ 削除</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
