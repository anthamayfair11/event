// カウントダウンタイマー
(function() {
  // イベント開催日時: 2026年2月14日（土）13:00
  const eventDate = new Date('2026-02-14T13:00:00').getTime();

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

