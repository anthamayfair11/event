$(document).ready(function() {

    // --- Cookieを取得する関数 ---
    function getCookie(name) {
        let matches = document.cookie.match(new RegExp(
            "(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"
        ));
        return matches ? decodeURIComponent(matches[1]) : null;
    }

    // --- 本日の投票回数を取得する関数 ---
    function getTodayVoteCount() {
        let voteLimit = getCookie('vote_limit');
        if (!voteLimit) {
            return 0;
        }

        try {
            let voteData = JSON.parse(voteLimit);
            let today = new Date().toISOString().split('T')[0]; // YYYY-MM-DD

            // 日付が今日かチェック
            if (voteData.date !== today) {
                return 0; // 日付が変わったらリセット
            }

            return parseInt(voteData.count) || 0;
        } catch (e) {
            return 0;
        }
    }

    // --- 残り投票回数を取得する関数 ---
    function getRemainingVotes() {
        const maxVotes = 3;
        let todayCount = getTodayVoteCount();
        return Math.max(0, maxVotes - todayCount);
    }

    // --- 残り投票回数を表示する関数 ---
    function updateRemainingVotes() {
        let remaining = getRemainingVotes();
        $('#remaining-votes').text(remaining);

        // 残り回数に応じて表示色を変更
        let alertDiv = $('#vote-limit-info');
        alertDiv.removeClass('warning danger');

        if (remaining === 0) {
            alertDiv.addClass('danger');
            // 投票ボタンを全て無効化
            $('.vote-btn').prop('disabled', true).addClass('disabled');
            // メッセージを表示
            $('#vote-limit-message').fadeIn(300);
        } else if (remaining === 1) {
            alertDiv.addClass('warning');
            // ボタンを有効化
            $('.vote-btn').prop('disabled', false).removeClass('disabled');
            // メッセージを非表示
            $('#vote-limit-message').fadeOut(300);
        } else {
            // ボタンを有効化
            $('.vote-btn').prop('disabled', false).removeClass('disabled');
            // メッセージを非表示
            $('#vote-limit-message').fadeOut(300);
        }
    }

    // --- 投票リストを更新する関数 ---
    function loadVoteList() {
        $.ajax({
            type: 'POST',
            url: 'index.php', // このファイル自身
            data: { action: 'load' }, // 'load' アクション (実質リスト取得)
            success: function(responseHtml) {
                $('#vote-list-container').html(responseHtml);
                // リスト更新後に投票ボタンの状態を更新
                updateRemainingVotes();
            },
            error: function() {
                $('#vote-list-container').html('<div class="alert alert-danger">投票リストの読み込みに失敗しました。</div>');
            }
        });
    }

    // --- 1. ページ読み込み時に投票リストと残り投票回数を取得 ---
    loadVoteList();

    // --- 2. [追加]ボタンクリック時の処理 ---
    $('#add-item-form').on('submit', function(e) {
        e.preventDefault(); // 通常のフォーム送信をキャンセル

        let itemName = $('#new-item-name').val().trim();
        if (itemName === '') {
            return; // 空の場合は何もしない
        }

        // 文字数チェック（50文字以内）
        if (itemName.length > 50) {
            alert('項目名は50文字以内で入力してください。');
            return;
        }

        $.ajax({
            type: 'POST',
            url: 'index.php',
            data: {
                action: 'add_item',
                item_name: itemName
            },
            success: function(responseHtml) {
                $('#vote-list-container').html(responseHtml); // リストを更新
                $('#new-item-name').val(''); // 入力欄をクリア
                updateRemainingVotes(); // 投票ボタンの状態を更新
            },
            error: function() {
                alert('項目の追加に失敗しました。');
            }
        });
    });

    // --- 3. [投票]ボタンクリック時の処理 ---
    // (動的に追加されるボタンに対応するため、イベントデリゲーションを使用)
    $('#vote-list-container').on('click', '.vote-btn', function() {
        // 残り投票回数をチェック
        if (getRemainingVotes() === 0) {
            alert('本日の投票回数上限（3回）に達しました。明日またご投票ください。');
            return;
        }

        let itemName = $(this).data('item'); // data-item属性から項目名を取得

        $.ajax({
            type: 'POST',
            url: 'index.php',
            data: {
                action: 'vote',
                item_name: itemName
            },
            success: function(responseHtml) {
                $('#vote-list-container').html(responseHtml); // リストを更新
                updateRemainingVotes(); // 残り投票回数を更新
            },
            error: function() {
                alert('投票に失敗しました。');
            }
        });
    });

});
