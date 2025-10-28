<?php
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

// 投稿制限チェック（1日3件まで）
function canPost() {
    if (!isset($_COOKIE['question_count'])) {
        return true;
    }
    $data = json_decode($_COOKIE['question_count'], true);
    if ($data['date'] !== date('Y-m-d')) {
        return true;
    }
    return ($data['count'] ?? 0) < 3;
}

function incrementPostCount() {
    $count = 0;
    if (isset($_COOKIE['question_count'])) {
        $data = json_decode($_COOKIE['question_count'], true);
        if ($data['date'] === date('Y-m-d')) {
            $count = $data['count'];
        }
    }
    $count++;
    $data = ['date' => date('Y-m-d'), 'count' => $count];
    setcookie('question_count', json_encode($data), strtotime('tomorrow'), '/');
}

// Ajax処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $pdo = getDbConnection();

    // 質問投稿
    if ($action === 'post_question') {
        $question = trim($_POST['question'] ?? '');
        $category = $_POST['category'] ?? 'other';

        if (!canPost()) {
            echo json_encode(['success' => false, 'message' => '1日の投稿上限（3件）に達しました']);
            exit;
        }

        if (!empty($question) && mb_strlen($question) <= 500) {
            $stmt = $pdo->prepare('INSERT INTO question_board (question_text, category) VALUES (:question, :category)');
            $stmt->execute(['question' => $question, 'category' => $category]);
            incrementPostCount();
            echo json_encode(['success' => true]);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => '質問内容を入力してください（500文字以内）']);
            exit;
        }
    }

    // 質問一覧取得
    if ($action === 'get_questions') {
        $stmt = $pdo->query('SELECT * FROM question_board WHERE is_published = 1 ORDER BY created_at DESC LIMIT 50');
        $questions = $stmt->fetchAll();
        echo json_encode(['success' => true, 'questions' => $questions]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>質問掲示板｜イベント情報</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/common.css">
    <style>
        .question-item {
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 1rem;
            margin-bottom: 1rem;
        }
        .question-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        .answer-box {
            background-color: #f8f9fa;
            border-left: 4px solid #28a745;
            padding: 1rem;
            border-radius: 0.25rem;
            margin-top: 0.5rem;
        }
        .waiting-answer {
            color: #6c757d;
            font-style: italic;
        }
    </style>
</head>
<body class="bg-light">

    <!-- ナビゲーション -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4">
        <div class="container">
            <span class="navbar-brand mb-0 h1">イベント情報</span>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../requirements/">📋 募集要項</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../QA/">❓ よくある質問</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../vote/">🗳️ 投票</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="../question/">💬 質問掲示板</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <!-- カウントダウンタイマー -->
        <div class="countdown-timer card shadow-sm mb-4">
            <div class="card-body p-3">
                <div class="text-center">
                    <div class="small text-muted mb-2">
                        <i class="bi bi-clock"></i> イベントまで
                    </div>
                    <div id="countdown" class="d-flex justify-content-center gap-2 flex-wrap">
                        <div class="countdown-item">
                            <div class="countdown-number" id="days">--</div>
                            <div class="countdown-label">日</div>
                        </div>
                        <div class="countdown-item">
                            <div class="countdown-number" id="hours">--</div>
                            <div class="countdown-label">時間</div>
                        </div>
                        <div class="countdown-item">
                            <div class="countdown-number" id="minutes">--</div>
                            <div class="countdown-label">分</div>
                        </div>
                        <div class="countdown-item">
                            <div class="countdown-number" id="seconds">--</div>
                            <div class="countdown-label">秒</div>
                        </div>
                    </div>
                    <div id="event-status" class="mt-2 small text-muted" style="display: none;"></div>
                </div>
            </div>
        </div>

        <!-- 参加申込状況 -->
        <div class="participation-status card shadow-sm mb-4">
            <div class="card-body p-3">
                <div class="text-center mb-2">
                    <div class="small text-muted mb-2">
                        <i class="bi bi-people-fill"></i> 現在の参加予定者
                    </div>
                    <div class="d-flex justify-content-center align-items-baseline gap-2">
                        <span class="participation-number" id="current-participants">4</span>
                        <span class="text-muted small">/ 目標 <span id="target-participants">20</span>名</span>
                    </div>
                </div>
                <div class="progress" style="height: 20px; border-radius: 10px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated"
                         role="progressbar"
                         id="participation-progress"
                         style="width: 20%; background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);"
                         aria-valuenow="20"
                         aria-valuemin="0"
                         aria-valuemax="100">
                        <strong>20%</strong>
                    </div>
                </div>
                <div class="text-center mt-2">
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i> あと16名で目標達成！
                    </small>
                </div>
            </div>
        </div>

        <h1 class="mb-4">💬 質問掲示板</h1>

        <!-- 質問投稿フォーム -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h5 class="card-title mb-3">📝 質問を投稿する</h5>
                <p class="text-muted small mb-3">
                    イベントについて気になることを匿名で質問できます。主催者が回答します。<br>
                    よくある質問は後日FAQに反映されます。
                </p>

                <form id="question-form">
                    <div class="mb-3">
                        <label class="form-label fw-bold">質問内容 <span class="text-danger">*</span></label>
                        <textarea name="question" class="form-control" rows="4" maxlength="500" required
                                  placeholder="例：初めてですが、どんな雰囲気ですか？"></textarea>
                        <small class="text-muted">500文字以内・1日3件まで投稿可能</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">カテゴリ（任意）</label>
                        <select name="category" class="form-select">
                            <option value="other">その他</option>
                            <option value="participation">参加について</option>
                            <option value="venue">会場について</option>
                            <option value="content">イベント内容</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">📮 匿名で投稿</button>
                </form>

                <div id="success-message" class="alert alert-success mt-3" style="display: none;">
                    ✅ 質問を投稿しました！主催者が回答するまでお待ちください。
                </div>
                <div id="error-message" class="alert alert-danger mt-3" style="display: none;"></div>
            </div>
        </div>

        <!-- 質問一覧 -->
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title mb-3">📌 質問一覧（新着順）</h5>
                <div id="questions-list">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">読み込み中...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <footer class="text-center mt-5 text-muted small">
            © 2025 イベント運営事務局
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/common.js"></script>
    <script>
        // 質問一覧読み込み
        function loadQuestions() {
            const formData = new FormData();
            formData.append('action', 'get_questions');

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderQuestions(data.questions);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('questions-list').innerHTML =
                    '<p class="text-danger">質問の読み込みに失敗しました</p>';
            });
        }

        // 質問描画
        function renderQuestions(questions) {
            const container = document.getElementById('questions-list');
            container.innerHTML = '';

            if (questions.length === 0) {
                container.innerHTML = '<p class="text-muted text-center py-4">まだ質問がありません。最初の質問を投稿してみませんか？</p>';
                return;
            }

            const categoryLabels = {
                'participation': '参加について',
                'venue': '会場について',
                'content': 'イベント内容',
                'other': 'その他'
            };

            questions.forEach(q => {
                const createdDate = new Date(q.created_at);
                const timeAgo = getTimeAgo(createdDate);

                const questionDiv = document.createElement('div');
                questionDiv.className = 'question-item';

                let html = `
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="badge bg-secondary">${categoryLabels[q.category] || 'その他'}</span>
                        <small class="text-muted">${timeAgo}</small>
                    </div>
                    <div class="fw-bold mb-2">💬 Q: ${escapeHtml(q.question_text)}</div>
                `;

                if (q.answer_text) {
                    const answeredDate = new Date(q.answered_at);
                    const answerTimeAgo = getTimeAgo(answeredDate);
                    html += `
                        <div class="answer-box">
                            <div class="text-success fw-bold mb-1">
                                ✅ 回答: ${escapeHtml(q.answered_by || 'あゆ')}
                                <small class="text-muted">(${answerTimeAgo})</small>
                            </div>
                            <div>${escapeHtml(q.answer_text).replace(/\n/g, '<br>')}</div>
                        </div>
                    `;
                } else {
                    html += '<div class="waiting-answer">⏳ 回答待ち...</div>';
                }

                questionDiv.innerHTML = html;
                container.appendChild(questionDiv);
            });
        }

        // HTML エスケープ
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // 相対時間表示
        function getTimeAgo(date) {
            const now = new Date();
            const diff = Math.floor((now - date) / 1000); // 秒単位

            if (diff < 60) return 'たった今';
            if (diff < 3600) return `${Math.floor(diff / 60)}分前`;
            if (diff < 86400) return `${Math.floor(diff / 3600)}時間前`;
            if (diff < 604800) return `${Math.floor(diff / 86400)}日前`;

            return date.toLocaleDateString('ja-JP');
        }

        // 質問投稿
        document.getElementById('question-form').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('action', 'post_question');

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('success-message').style.display = 'block';
                    document.getElementById('error-message').style.display = 'none';
                    document.getElementById('question-form').reset();
                    loadQuestions();

                    setTimeout(() => {
                        document.getElementById('success-message').style.display = 'none';
                    }, 5000);
                } else {
                    document.getElementById('error-message').textContent = data.message || '投稿に失敗しました';
                    document.getElementById('error-message').style.display = 'block';
                    document.getElementById('success-message').style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('error-message').textContent = '投稿に失敗しました';
                document.getElementById('error-message').style.display = 'block';
            });
        });

        // 初回読み込み
        loadQuestions();

        // 30秒ごとに自動更新
        setInterval(loadQuestions, 30000);
    </script>
</body>
</html>
