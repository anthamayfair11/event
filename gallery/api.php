<?php
/**
 * ギャラリー API エンドポイント
 * いいね・コメント機能
 */

// データベース接続（認証情報は config/database.php → config/secrets.php に集約）
require_once __DIR__ . '/../config/database.php';

/**
 * ユーザー識別子を取得（なければ生成）
 */
function getUserIdentifier() {
    if (!isset($_COOKIE['gallery_user_id'])) {
        $userId = 'user_' . uniqid() . '_' . time();
        setcookie('gallery_user_id', $userId, strtotime('+1 year'), '/');
        return $userId;
    }
    return $_COOKIE['gallery_user_id'];
}

/**
 * コメント投稿可能かチェック（1日3件まで）
 */
function canPostComment() {
    if (!isset($_COOKIE['gallery_comment_count'])) {
        return true;
    }
    $data = json_decode($_COOKIE['gallery_comment_count'], true);
    if (!$data || $data['date'] !== date('Y-m-d')) {
        return true;
    }
    return ($data['count'] ?? 0) < 3;
}

/**
 * コメント投稿回数をカウントアップ
 */
function incrementCommentCount() {
    $count = 0;
    if (isset($_COOKIE['gallery_comment_count'])) {
        $data = json_decode($_COOKIE['gallery_comment_count'], true);
        if ($data && $data['date'] === date('Y-m-d')) {
            $count = $data['count'] ?? 0;
        }
    }
    $count++;
    $data = ['date' => date('Y-m-d'), 'count' => $count];
    setcookie('gallery_comment_count', json_encode($data), strtotime('tomorrow'), '/');
}

/**
 * 残りコメント投稿可能回数を取得
 */
function getRemainingComments() {
    if (!isset($_COOKIE['gallery_comment_count'])) {
        return 3;
    }
    $data = json_decode($_COOKIE['gallery_comment_count'], true);
    if (!$data || $data['date'] !== date('Y-m-d')) {
        return 3;
    }
    return max(0, 3 - ($data['count'] ?? 0));
}

// CORS ヘッダー（同一オリジンなら不要だが念のため）
header('Content-Type: application/json; charset=utf-8');

// リクエスト処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $pdo = getDbConnection(true);

    switch ($action) {
        /**
         * いいねトグル
         */
        case 'toggle_like':
            $imageId = trim($_POST['image_id'] ?? '');
            if (empty($imageId)) {
                echo json_encode(['success' => false, 'message' => '画像IDが必要です']);
                exit;
            }

            $userId = getUserIdentifier();

            // 既にいいねしているかチェック
            $stmt = $pdo->prepare('SELECT id FROM gallery_likes WHERE image_id = :image_id AND user_identifier = :user_id');
            $stmt->execute(['image_id' => $imageId, 'user_id' => $userId]);
            $existing = $stmt->fetch();

            if ($existing) {
                // いいね解除
                $stmt = $pdo->prepare('DELETE FROM gallery_likes WHERE image_id = :image_id AND user_identifier = :user_id');
                $stmt->execute(['image_id' => $imageId, 'user_id' => $userId]);
                $liked = false;
            } else {
                // いいね追加
                $stmt = $pdo->prepare('INSERT INTO gallery_likes (image_id, user_identifier) VALUES (:image_id, :user_id)');
                $stmt->execute(['image_id' => $imageId, 'user_id' => $userId]);
                $liked = true;
            }

            // 最新のいいね数を取得
            $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM gallery_likes WHERE image_id = :image_id');
            $stmt->execute(['image_id' => $imageId]);
            $result = $stmt->fetch();

            echo json_encode([
                'success' => true,
                'liked' => $liked,
                'like_count' => (int)$result['count']
            ]);
            exit;

        /**
         * コメント投稿
         */
        case 'post_comment':
            $imageId = trim($_POST['image_id'] ?? '');
            $commentText = trim($_POST['comment'] ?? '');

            if (empty($imageId)) {
                echo json_encode(['success' => false, 'message' => '画像IDが必要です']);
                exit;
            }

            // 投稿制限チェック
            if (!canPostComment()) {
                echo json_encode([
                    'success' => false,
                    'message' => '本日のコメント投稿上限（3件）に達しました。明日またお越しください。'
                ]);
                exit;
            }

            // バリデーション
            $length = mb_strlen($commentText, 'UTF-8');
            if ($length < 1 || $length > 500) {
                echo json_encode(['success' => false, 'message' => 'コメントは1〜500文字で入力してください']);
                exit;
            }

            $userId = getUserIdentifier();

            // コメント保存
            $stmt = $pdo->prepare('INSERT INTO gallery_comments (image_id, comment_text, user_identifier) VALUES (:image_id, :comment, :user_id)');
            $stmt->execute([
                'image_id' => $imageId,
                'comment' => $commentText,
                'user_id' => $userId
            ]);

            incrementCommentCount();

            echo json_encode([
                'success' => true,
                'message' => 'コメントを投稿しました',
                'remaining' => getRemainingComments()
            ]);
            exit;

        /**
         * コメント一覧取得
         */
        case 'get_comments':
            $imageId = trim($_POST['image_id'] ?? '');
            if (empty($imageId)) {
                echo json_encode(['success' => false, 'message' => '画像IDが必要です']);
                exit;
            }

            $stmt = $pdo->prepare('SELECT id, comment_text, created_at FROM gallery_comments WHERE image_id = :image_id AND is_published = 1 ORDER BY created_at DESC LIMIT 50');
            $stmt->execute(['image_id' => $imageId]);
            $comments = $stmt->fetchAll();

            // コメント数を取得
            $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM gallery_comments WHERE image_id = :image_id AND is_published = 1');
            $stmt->execute(['image_id' => $imageId]);
            $countResult = $stmt->fetch();

            echo json_encode([
                'success' => true,
                'comments' => $comments,
                'comment_count' => (int)$countResult['count']
            ]);
            exit;

        /**
         * いいね状態・数を取得（ページ読み込み時）
         */
        case 'get_likes':
            $imageIds = $_POST['image_ids'] ?? [];
            if (!is_array($imageIds) || empty($imageIds)) {
                echo json_encode(['success' => false, 'message' => '画像IDが必要です']);
                exit;
            }

            $userId = getUserIdentifier();
            $result = [];

            foreach ($imageIds as $imageId) {
                $imageId = trim($imageId);

                // いいね数
                $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM gallery_likes WHERE image_id = :image_id');
                $stmt->execute(['image_id' => $imageId]);
                $countResult = $stmt->fetch();

                // ユーザーがいいねしているか
                $stmt = $pdo->prepare('SELECT 1 FROM gallery_likes WHERE image_id = :image_id AND user_identifier = :user_id');
                $stmt->execute(['image_id' => $imageId, 'user_id' => $userId]);
                $liked = $stmt->fetch() !== false;

                // コメント数
                $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM gallery_comments WHERE image_id = :image_id AND is_published = 1');
                $stmt->execute(['image_id' => $imageId]);
                $commentCount = $stmt->fetch();

                $result[$imageId] = [
                    'like_count' => (int)$countResult['count'],
                    'liked' => $liked,
                    'comment_count' => (int)$commentCount['count']
                ];
            }

            echo json_encode([
                'success' => true,
                'data' => $result,
                'remaining_comments' => getRemainingComments()
            ]);
            exit;

        default:
            echo json_encode(['success' => false, 'message' => '不明なアクション']);
            exit;
    }
}

// GET リクエストは許可しない
echo json_encode(['success' => false, 'message' => 'POSTリクエストのみ受け付けます']);
