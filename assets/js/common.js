// カウントダウンタイマー
(function() {
  // イベント開催日時: 2025年11月15日（土）13:00
  const eventDate = new Date('2025-11-15T13:00:00').getTime();

  function updateCountdown() {
    const now = new Date().getTime();
    const distance = eventDate - now;

    // 時間計算
    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((distance % (1000 * 60)) / 1000);

    // DOM更新
    const daysEl = document.getElementById('days');
    const hoursEl = document.getElementById('hours');
    const minutesEl = document.getElementById('minutes');
    const secondsEl = document.getElementById('seconds');

    if (daysEl) daysEl.textContent = days;
    if (hoursEl) hoursEl.textContent = hours;
    if (minutesEl) minutesEl.textContent = minutes;
    if (secondsEl) secondsEl.textContent = seconds;

    // イベント終了後の処理
    if (distance < 0) {
      clearInterval(countdownInterval);
      const countdownEl = document.getElementById('countdown');
      const statusEl = document.getElementById('event-status');
      if (countdownEl) countdownEl.style.display = 'none';
      if (statusEl) {
        statusEl.style.display = 'block';
        statusEl.innerHTML = '<strong>🎉 イベント開催中または終了しました</strong>';
      }
    }
  }

  // 初回実行
  updateCountdown();

  // 1秒ごとに更新
  const countdownInterval = setInterval(updateCountdown, 1000);
})();

// 参加申込状況の更新
(function() {
  // ここで参加者数を設定（管理者が手動で変更）
  const currentParticipants = 4;
  const targetParticipants = 20;

  function updateParticipationStatus() {
    // パーセンテージ計算
    const percentage = Math.round((currentParticipants / targetParticipants) * 100);
    const remaining = targetParticipants - currentParticipants;

    // DOM更新
    const currentEl = document.getElementById('current-participants');
    const targetEl = document.getElementById('target-participants');

    if (currentEl) currentEl.textContent = currentParticipants;
    if (targetEl) targetEl.textContent = targetParticipants;

    const progressBar = document.getElementById('participation-progress');
    if (progressBar) {
      progressBar.style.width = percentage + '%';
      progressBar.textContent = percentage + '%';
      progressBar.setAttribute('aria-valuenow', percentage);
    }

    // メッセージ更新
    const statusMessage = document.querySelector('.participation-status .text-center.mt-2 small');
    if (statusMessage) {
      if (currentParticipants >= targetParticipants) {
        statusMessage.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> 目標達成！ありがとうございます';
        if (progressBar) {
          progressBar.classList.remove('progress-bar-animated');
          progressBar.style.background = 'linear-gradient(90deg, #48bb78 0%, #38a169 100%)';
        }
      } else {
        statusMessage.innerHTML = '<i class="bi bi-info-circle"></i> あと' + remaining + '名で目標達成！';
      }
    }
  }

  // 初回実行
  updateParticipationStatus();
})();
