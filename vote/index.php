<?php
// --- 1. PHPサーバーサイド処理 ---

// データベース接続設定（ダミー）
function getDbConnection() {
    $host = 'localhost:3306';
    $dbname = 'xxopfrlp_shinsei';
    $username = 'xxopfrlp_ayuhou10';
    $password = 'sh1nse15651487';
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        return new PDO($dsn, $username, $password, $options);
    } catch (PDOException $e) {
        die('データベース接続エラー: ' . $e->getMessage());
    }
}

/**
 * 本日の投票回数を取得する関数
 */
function getTodayVoteCount() {
    if (!isset($_COOKIE['vote_limit'])) {
        return 0;
    }

    $voteData = json_decode($_COOKIE['vote_limit'], true);
    if (!$voteData || !isset($voteData['date']) || !isset($voteData['count'])) {
        return 0;
    }

    // 日付が今日かチェック
    if ($voteData['date'] !== date('Y-m-d')) {
        return 0; // 日付が変わったらリセット
    }

    return (int)$voteData['count'];
}

/**
 * 残り投票可能回数を取得する関数
 */
function getRemainingVotes() {
    $maxVotes = 3;
    $todayCount = getTodayVoteCount();
    return max(0, $maxVotes - $todayCount);
}

/**
 * 投票回数をカウントアップする関数
 */
function incrementVoteCount() {
    $todayCount = getTodayVoteCount();
    $newCount = $todayCount + 1;

    $voteData = [
        'date' => date('Y-m-d'),
        'count' => $newCount
    ];

    // 翌日の0時までCookieを有効にする
    $tomorrow = strtotime('tomorrow');
    setcookie('vote_limit', json_encode($voteData), $tomorrow, '/');
}

/**
 * 投票可能かチェックする関数
 */
function canVote() {
    return getRemainingVotes() > 0;
}

/**
 * 投票リストのHTMLを生成する関数
 */
function renderVoteList() {
    $pdo = getDbConnection();

    // DBから全投票項目を取得（得票数降順）
    $stmt = $pdo->query('SELECT item_name, vote_count FROM vote ORDER BY vote_count DESC, item_name ASC');
    $items = $stmt->fetchAll();

    // 合計票数を計算
    $total_votes = array_sum(array_column($items, 'vote_count'));

    $html = '<table class="table align-middle">';
    $html .= '<thead><tr><th><i class="fas fa-list"></i> 項目名</th><th><i class="fas fa-poll"></i> 得票数</th><th style="min-width: 200px;"><i class="fas fa-chart-bar"></i> グラフ</th></tr></thead>';
    $html .= '<tbody>';

    if (empty($items)) {
        $html .= '<tr><td colspan="3" class="empty-state">';
        $html .= '<div class="empty-state-icon"><i class="fas fa-inbox"></i></div>';
        $html .= '<div>まだ項目がありません</div>';
        $html .= '<div style="font-size: 0.9rem; margin-top: 0.5rem;">上の入力欄から項目を追加してください</div>';
        $html .= '</td></tr>';
    } else {
        foreach ($items as $item) {
            $item_name = $item['item_name'];
            $count = $item['vote_count'];

            // グラフのパーセンテージ計算 (合計が0の場合は0)
            $percentage = ($total_votes > 0) ? ($count / $total_votes) * 100 : 0;

            // XSS対策 (HTMLエスケープ)
            $safe_item_name = htmlspecialchars($item_name, ENT_QUOTES, 'UTF-8');

            $html .= '<tr>';
            // 項目名と投票ボタン
            $html .= '<td>';
            $html .= '<button class="btn vote-btn me-2 mb-2 mb-md-0" data-item="' . $safe_item_name . '">';
            $html .= '<i class="fas fa-hand-point-up"></i> 投票';
            $html .= '</button>';
            $html .= '<span class="item-name">' . $safe_item_name . '</span>';
            $html .= '</td>';

            // 得票数
            $html .= '<td><span class="vote-count-badge">' . $count . '</span></td>';

            // 横グラフ (Bootstrap Progress bar)
            $html .= '<td>';
            $html .= '<div class="progress" role="progressbar" aria-label="投票グラフ" aria-valuenow="' . $percentage . '" aria-valuemin="0" aria-valuemax="100">';
            $html .= '<div class="progress-bar" style="width: ' . $percentage . '%">' . number_format($percentage, 1) . '%</div>';
            $html .= '</div>';
            $html .= '</td>';

            $html .= '</tr>';
        }
    }

    $html .= '</tbody></table>';
    return $html;
}


// --- 2. Ajaxリクエストの処理 ---
// (JavaScriptからのPOSTリクエストをここで処理します)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $pdo = getDbConnection();

    switch ($action) {
        // [追加]ボタンが押された時
        case 'add_item':
            $item_name = trim($_POST['item_name'] ?? '');
            // 文字数チェック（50文字以内）
            if (!empty($item_name) && mb_strlen($item_name, 'UTF-8') <= 50) {
                try {
                    // 新しい項目を0票で追加（重複はUNIQUE制約でエラーになるため無視）
                    $stmt = $pdo->prepare('INSERT IGNORE INTO vote (item_name, vote_count) VALUES (:item_name, 0)');
                    $stmt->execute(['item_name' => $item_name]);
                } catch (PDOException $e) {
                    // エラーハンドリング（ログ出力など）
                    error_log('投票項目追加エラー: ' . $e->getMessage());
                }
            }
            break;

        // [投票]ボタンが押された時
        case 'vote':
            $item_name = $_POST['item_name'] ?? '';
            if (!empty($item_name)) {
                // 投票回数制限チェック
                if (!canVote()) {
                    // 投票制限に達している場合はエラーメッセージを返す
                    echo '<div class="alert alert-warning">本日の投票回数上限（3回）に達しました。明日またご投票ください。</div>';
                    echo renderVoteList();
                    exit;
                }

                try {
                    // 票数をカウントアップ
                    $stmt = $pdo->prepare('UPDATE vote SET vote_count = vote_count + 1 WHERE item_name = :item_name');
                    $stmt->execute(['item_name' => $item_name]);

                    // 投票成功したらCookieをカウントアップ
                    incrementVoteCount();
                } catch (PDOException $e) {
                    // エラーハンドリング（ログ出力など）
                    error_log('投票エラー: ' . $e->getMessage());
                }
            }
            break;
    }

    // [load], [add_item], [vote] いずれの場合も、
    // 最新のHTMLリストを生成して返し、処理を終了する
    echo renderVoteList();
    exit;
}

// --- 3. HTML & JavaScript (通常アクセス時) ---
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>落書き投票</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/common.css">
    <link rel="stylesheet" href="../assets/css/vote.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/vote.css'); ?>">
</head>
<body>

    <!-- ナビゲーション -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-3">
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
                        <a class="nav-link active" href="../vote/">🗳️ 投票</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../question/">💬 質問掲示板</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-4">

        <!-- カウントダウンタイマー -->
        <div class="countdown-timer fade-in">
            <div class="card-body p-3">
                <div class="text-center">
                    <div class="small text-muted mb-2">
                        <i class="fas fa-clock"></i> イベントまで
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
        <div class="participation-status fade-in">
            <div class="card-body p-3">
                <div class="text-center mb-2">
                    <div class="small text-muted mb-2">
                        <i class="fas fa-users"></i> 現在の参加予定者
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
                        <i class="fas fa-info-circle"></i> あと16名で目標達成！
                    </small>
                </div>
            </div>
        </div>

        <!-- ヘッダーカード -->
        <div class="header-card fade-in">
            <h1 class="page-title mb-3">
                <i class="fas fa-vote-yea"></i> 落書き投票
            </h1>

            <div class="alert alert-info mb-3" role="alert">
                <i class="fas fa-info-circle"></i> 毎日3回まで投票できます
            </div>

            <form id="add-item-form" class="d-flex flex-column flex-sm-row">
                <input type="text" id="new-item-name" class="form-control me-0 me-sm-2 mb-2 mb-sm-0" placeholder="新しい項目を追加..." maxlength="50" required>
                <button type="submit" class="btn btn-add text-nowrap">
                    <i class="fas fa-plus-circle"></i> 追加
                </button>
            </form>
        </div>

        <!-- 残り投票回数カード -->
        <div id="vote-limit-info" class="vote-limit-card" role="alert">
            <div>
                <i class="fas fa-chart-pie fa-lg mb-1"></i>
            </div>
            <div style="font-size: 0.9rem; opacity: 0.9;">本日の残り投票回数</div>
            <div class="remaining-count">
                <span id="remaining-votes">-</span><span style="font-size: 1.2rem; margin-left: 0.3rem;">回</span>
            </div>
            <div id="vote-limit-message" class="vote-limit-message" style="display: none;">
                <i class="fas fa-calendar-day"></i> また明日、投票してください
            </div>
        </div>

        <!-- 投票リストカード -->
        <div class="vote-list-card slide-in">
            <div id="vote-list-container"></div>
        </div>

    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/common.js"></script>
    <script src="../assets/js/vote.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/vote.js'); ?>"></script>

</body>
</html>