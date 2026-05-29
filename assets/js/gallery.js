/**
 * ギャラリー いいね・コメント機能
 */

const GalleryAPI = {
    apiUrl: '../gallery/api.php',

    /**
     * ページ読み込み時にいいね・コメント数を取得
     */
    init: function() {
        const imageIds = [];
        document.querySelectorAll('.gallery-item[data-image-id]').forEach(item => {
            imageIds.push(item.dataset.imageId);
        });

        if (imageIds.length === 0) return;

        const formData = new FormData();
        formData.append('action', 'get_likes');
        imageIds.forEach(id => formData.append('image_ids[]', id));

        fetch(this.apiUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // 各画像のいいね・コメント数を更新
                Object.keys(data.data).forEach(imageId => {
                    const info = data.data[imageId];
                    this.updateLikeUI(imageId, info.liked, info.like_count);
                    this.updateCommentCount(imageId, info.comment_count);
                });

                // 残りコメント数を更新
                this.updateRemainingComments(data.remaining_comments);
            }
        })
        .catch(error => console.error('Error:', error));
    },

    /**
     * いいねトグル
     */
    toggleLike: function(imageId) {
        const formData = new FormData();
        formData.append('action', 'toggle_like');
        formData.append('image_id', imageId);

        fetch(this.apiUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.updateLikeUI(imageId, data.liked, data.like_count);
            } else {
                alert(data.message || 'エラーが発生しました');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('通信エラーが発生しました');
        });
    },

    /**
     * いいねUIを更新
     */
    updateLikeUI: function(imageId, liked, count) {
        const item = document.querySelector(`.gallery-item[data-image-id="${imageId}"]`);
        if (!item) return;

        const likeBtn = item.querySelector('.like-btn');
        const likeIcon = item.querySelector('.like-icon');
        const likeCount = item.querySelector('.like-count');

        if (likeBtn) {
            if (liked) {
                likeBtn.classList.add('liked');
                if (likeIcon) likeIcon.textContent = '♥';
            } else {
                likeBtn.classList.remove('liked');
                if (likeIcon) likeIcon.textContent = '♡';
            }
        }
        if (likeCount) {
            likeCount.textContent = count;
        }
    },

    /**
     * コメント数を更新
     */
    updateCommentCount: function(imageId, count) {
        const item = document.querySelector(`.gallery-item[data-image-id="${imageId}"]`);
        if (!item) return;

        const commentCount = item.querySelector('.comment-count');
        if (commentCount) {
            commentCount.textContent = count;
        }
    },

    /**
     * 残りコメント投稿数を更新
     */
    updateRemainingComments: function(remaining) {
        const el = document.getElementById('remaining-comments');
        if (el) {
            el.textContent = remaining;
        }
    },

    /**
     * コメント投稿モーダルを開く
     */
    openCommentModal: function(imageId) {
        const modal = document.getElementById('commentModal');
        if (!modal) return;

        document.getElementById('comment-image-id').value = imageId;
        document.getElementById('comment-text').value = '';
        document.getElementById('comment-error').style.display = 'none';
        document.getElementById('comment-success').style.display = 'none';

        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    },

    /**
     * コメント送信
     */
    submitComment: function() {
        const imageId = document.getElementById('comment-image-id').value;
        const commentText = document.getElementById('comment-text').value.trim();
        const errorEl = document.getElementById('comment-error');
        const successEl = document.getElementById('comment-success');
        const submitBtn = document.getElementById('comment-submit-btn');

        // バリデーション
        if (!commentText) {
            errorEl.textContent = 'コメントを入力してください';
            errorEl.style.display = 'block';
            return;
        }

        if (commentText.length > 500) {
            errorEl.textContent = 'コメントは500文字以内で入力してください';
            errorEl.style.display = 'block';
            return;
        }

        // 送信中は無効化
        submitBtn.disabled = true;
        submitBtn.textContent = '送信中...';

        const formData = new FormData();
        formData.append('action', 'post_comment');
        formData.append('image_id', imageId);
        formData.append('comment', commentText);

        fetch(this.apiUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.textContent = '送信';

            if (data.success) {
                errorEl.style.display = 'none';
                successEl.textContent = data.message;
                successEl.style.display = 'block';
                document.getElementById('comment-text').value = '';

                // 残りコメント数を更新
                this.updateRemainingComments(data.remaining);

                // コメント数を+1
                const item = document.querySelector(`.gallery-item[data-image-id="${imageId}"]`);
                if (item) {
                    const countEl = item.querySelector('.comment-count');
                    if (countEl) {
                        countEl.textContent = parseInt(countEl.textContent || 0) + 1;
                    }
                }

                // 2秒後にモーダルを閉じる
                setTimeout(() => {
                    bootstrap.Modal.getInstance(document.getElementById('commentModal')).hide();
                }, 1500);
            } else {
                errorEl.textContent = data.message;
                errorEl.style.display = 'block';
            }
        })
        .catch(error => {
            submitBtn.disabled = false;
            submitBtn.textContent = '送信';
            errorEl.textContent = '通信エラーが発生しました';
            errorEl.style.display = 'block';
            console.error('Error:', error);
        });
    },

    /**
     * コメント閲覧モーダルを開く
     */
    viewComments: function(imageId) {
        const modal = document.getElementById('viewCommentsModal');
        if (!modal) return;

        const listEl = document.getElementById('comments-list');
        listEl.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary" role="status"></div></div>';

        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();

        const formData = new FormData();
        formData.append('action', 'get_comments');
        formData.append('image_id', imageId);

        fetch(this.apiUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.comments.length === 0) {
                    listEl.innerHTML = '<div class="text-center text-muted py-3">まだコメントはありません</div>';
                } else {
                    listEl.innerHTML = data.comments.map(comment => `
                        <div class="comment-item border-bottom pb-2 mb-2">
                            <div class="text-muted small mb-1">${this.formatDate(comment.created_at)}</div>
                            <div>${this.escapeHtml(comment.comment_text)}</div>
                        </div>
                    `).join('');
                }
            } else {
                listEl.innerHTML = '<div class="alert alert-danger">コメントの読み込みに失敗しました</div>';
            }
        })
        .catch(error => {
            listEl.innerHTML = '<div class="alert alert-danger">通信エラーが発生しました</div>';
            console.error('Error:', error);
        });
    },

    /**
     * 日付フォーマット
     */
    formatDate: function(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleDateString('ja-JP', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    },

    /**
     * HTMLエスケープ
     */
    escapeHtml: function(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// グローバル関数（onclick用）
function toggleLike(imageId) {
    GalleryAPI.toggleLike(imageId);
}

function openCommentModal(imageId) {
    GalleryAPI.openCommentModal(imageId);
}

function viewComments(imageId) {
    GalleryAPI.viewComments(imageId);
}

function submitComment() {
    GalleryAPI.submitComment();
}

// ページ読み込み時に初期化
document.addEventListener('DOMContentLoaded', function() {
    GalleryAPI.init();
});
