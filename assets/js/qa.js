let allData = null;
let currentTabInstance = null;

// JSONファイルからQ&Aデータを読み込んで表示
async function loadFAQs() {
  try {
    const response = await fetch('faqs.json');
    if (!response.ok) throw new Error('JSONファイルの読み込みに失敗しました');

    allData = await response.json();

    // タブとコンテンツを生成
    renderTabs();
    renderAllCategories();

    // 最初のタブをアクティブにする
    const firstTab = document.querySelector('#categoryTabs .nav-link');
    if (firstTab) {
      const tab = new bootstrap.Tab(firstTab);
      tab.show();
    }

  } catch (error) {
    console.error('エラー:', error);
    const container = document.getElementById('categoryTabContent');
    const errorMsg = document.createElement('div');
    errorMsg.className = 'alert alert-danger';
    errorMsg.textContent = 'Q&Aデータの読み込みに失敗しました。';
    container.appendChild(errorMsg);
  }
}

// タブを生成
function renderTabs() {
  const tabsContainer = document.getElementById('categoryTabs');
  tabsContainer.innerHTML = '';

  allData.categories.forEach((category, index) => {
    const li = document.createElement('li');
    li.className = 'nav-item';
    li.setAttribute('role', 'presentation');

    const button = document.createElement('button');
    button.className = index === 0 ? 'nav-link active' : 'nav-link';
    button.id = `${category.id}-tab`;
    button.setAttribute('data-bs-toggle', 'tab');
    button.setAttribute('data-bs-target', `#${category.id}`);
    button.setAttribute('type', 'button');
    button.setAttribute('role', 'tab');
    button.setAttribute('aria-controls', category.id);
    button.setAttribute('aria-selected', index === 0 ? 'true' : 'false');

    // アイコンとカテゴリ名を追加（XSS対策: textContentを分けて使用）
    const iconSpan = document.createElement('span');
    iconSpan.className = 'tab-icon';
    iconSpan.textContent = category.icon;

    const nameText = document.createTextNode(category.name);

    button.appendChild(iconSpan);
    button.appendChild(nameText);

    li.appendChild(button);
    tabsContainer.appendChild(li);
  });
}

// すべてのカテゴリのコンテンツを生成
function renderAllCategories() {
  const container = document.getElementById('categoryTabContent');
  container.innerHTML = '';

  allData.categories.forEach((category, index) => {
    const tabPane = document.createElement('div');
    tabPane.className = index === 0 ? 'tab-pane fade show active' : 'tab-pane fade';
    tabPane.id = category.id;
    tabPane.setAttribute('role', 'tabpanel');
    tabPane.setAttribute('aria-labelledby', `${category.id}-tab`);

    const accordion = createAccordion(category.faqs, category.id);
    tabPane.appendChild(accordion);
    container.appendChild(tabPane);
  });
}

// アコーディオンを生成
function createAccordion(faqs, categoryId) {
  const accordion = document.createElement('div');
  accordion.className = 'accordion';
  accordion.id = `accordion-${categoryId}`;

  faqs.forEach(faq => {
    const item = createAccordionItem(faq, categoryId);
    accordion.appendChild(item);
  });

  return accordion;
}

// アコーディオンアイテムを生成
function createAccordionItem(faq, parentId) {
  const item = document.createElement('div');
  item.className = 'accordion-item';
  item.setAttribute('data-faq-id', faq.id);
  item.setAttribute('data-question', faq.question.toLowerCase());
  item.setAttribute('data-answer', faq.answer.toLowerCase());

  // ヘッダー部分
  const header = document.createElement('h2');
  header.className = 'accordion-header';
  header.id = `heading-${parentId}-${faq.id}`;

  const button = document.createElement('button');
  button.className = faq.defaultOpen ? 'accordion-button' : 'accordion-button collapsed';
  button.type = 'button';
  button.setAttribute('data-bs-toggle', 'collapse');
  button.setAttribute('data-bs-target', `#collapse-${parentId}-${faq.id}`);
  button.setAttribute('aria-expanded', faq.defaultOpen ? 'true' : 'false');
  button.setAttribute('aria-controls', `collapse-${parentId}-${faq.id}`);
  // XSS対策: textContentを使用
  button.textContent = `Q. ${faq.question}`;

  header.appendChild(button);

  // コンテンツ部分
  const collapseDiv = document.createElement('div');
  collapseDiv.id = `collapse-${parentId}-${faq.id}`;
  collapseDiv.className = faq.defaultOpen ? 'accordion-collapse collapse show' : 'accordion-collapse collapse';
  collapseDiv.setAttribute('aria-labelledby', `heading-${parentId}-${faq.id}`);
  collapseDiv.setAttribute('data-bs-parent', `#accordion-${parentId}`);

  const body = document.createElement('div');
  body.className = 'accordion-body';
  // XSS対策: textContentを使用
  body.textContent = `A. ${faq.answer}`;

  collapseDiv.appendChild(body);

  // 全体を組み立て
  item.appendChild(header);
  item.appendChild(collapseDiv);

  return item;
}

// 検索機能
function handleSearch() {
  const searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();

  if (!searchTerm) {
    // 検索語が空の場合は通常表示に戻す
    renderAllCategories();
    return;
  }

  // 全カテゴリから該当する質問を抽出
  const searchResults = [];
  allData.categories.forEach(category => {
    category.faqs.forEach(faq => {
      if (faq.question.toLowerCase().includes(searchTerm) ||
          faq.answer.toLowerCase().includes(searchTerm)) {
        searchResults.push({
          ...faq,
          categoryName: category.name,
          categoryIcon: category.icon
        });
      }
    });
  });

  // 検索結果を表示
  displaySearchResults(searchResults, searchTerm);
}

// 検索結果を表示
function displaySearchResults(results, searchTerm) {
  const container = document.getElementById('categoryTabContent');
  container.innerHTML = '';

  const resultPane = document.createElement('div');
  resultPane.className = 'tab-pane fade show active';

  if (results.length === 0) {
    const noResults = document.createElement('div');
    noResults.className = 'no-results';
    noResults.innerHTML = `
      <p class="h5">「${escapeHtml(searchTerm)}」に一致する質問が見つかりませんでした</p>
      <p class="text-muted mt-3">別のキーワードで検索してみてください</p>
    `;
    resultPane.appendChild(noResults);
  } else {
    const resultHeader = document.createElement('div');
    resultHeader.className = 'alert alert-info mb-3';
    resultHeader.textContent = `検索結果: ${results.length}件の質問が見つかりました`;
    resultPane.appendChild(resultHeader);

    const accordion = document.createElement('div');
    accordion.className = 'accordion';
    accordion.id = 'accordion-search';

    results.forEach(faq => {
      const item = createAccordionItem(faq, 'search');

      // カテゴリバッジを追加
      const badge = document.createElement('span');
      badge.className = 'badge bg-secondary ms-2';
      badge.textContent = `${faq.categoryIcon} ${faq.categoryName}`;

      const button = item.querySelector('.accordion-button');
      button.appendChild(badge);

      accordion.appendChild(item);
    });

    resultPane.appendChild(accordion);
  }

  container.appendChild(resultPane);

  // タブをすべて非アクティブにする
  document.querySelectorAll('#categoryTabs .nav-link').forEach(tab => {
    tab.classList.remove('active');
    tab.setAttribute('aria-selected', 'false');
  });
}

// HTML エスケープ関数
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

// 検索クリア
function clearSearch() {
  document.getElementById('searchInput').value = '';
  renderAllCategories();

  // 最初のタブをアクティブにする
  const firstTab = document.querySelector('#categoryTabs .nav-link');
  if (firstTab) {
    firstTab.classList.add('active');
    firstTab.setAttribute('aria-selected', 'true');
    const tab = new bootstrap.Tab(firstTab);
    tab.show();
  }
}

// イベントリスナー設定
document.addEventListener('DOMContentLoaded', () => {
  loadFAQs();

  // 検索ボックスのイベント
  const searchInput = document.getElementById('searchInput');
  searchInput.addEventListener('input', handleSearch);

  // クリアボタン
  document.getElementById('clearSearch').addEventListener('click', clearSearch);

  // Enterキーで検索
  searchInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
      handleSearch();
    }
  });
});
