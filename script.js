// ========== DOM ==========
const searchInput = document.getElementById('searchInput');
const searchClear = document.getElementById('searchClear');
const timelineFeed = document.getElementById('timelineFeed');
const emptyState = document.getElementById('emptyState');
const modalOverlay = document.getElementById('modalOverlay');
const modalBody = document.getElementById('modalBody');
const modalClose = document.getElementById('modalClose');
const guestbookForm = document.getElementById('guestbookForm');
const guestbookList = document.getElementById('guestbookList');

let cardObserver = null;
let lastFocusedEl = null;
let currentQuery = '';
let showingFavoritesOnly = false;
let sortAscending = false; // default: newest first
let currentPoem = null;

const randomBtn = document.getElementById('randomBtn');
const themeToggle = document.getElementById('themeToggle');
const yearNav = document.getElementById('yearNav');
const statsCard = document.getElementById('statsCard');
const favFilterBtn = document.getElementById('favFilterBtn');
const progressBar = document.getElementById('readingProgress');

// ========== 排序 ==========
function sortPoems() {
  poems.sort((a, b) => {
    const pa = a.sortKey.split('-').map(Number);
    const pb = b.sortKey.split('-').map(Number);
    for (let i = 0; i < Math.max(pa.length, pb.length); i++) {
      const va = pa[i] || 0;
      const vb = pb[i] || 0;
      if (va !== vb) return sortAscending ? va - vb : vb - va;
    }
    return 0;
  });
}

function toggleSortOrder() {
  sortAscending = !sortAscending;
  sortPoems();
  const btn = document.getElementById('sortToggle');
  if (btn) btn.textContent = sortAscending ? '↓ 最早' : '↑ 最新';

  // 触发搜索输入事件以刷新列表（保留搜索关键词和收藏筛选）
  searchInput.dispatchEvent(new Event('input'));
  renderYearNav();
  renderStats();
}

// ========== 渲染时间线 ==========
function renderTimeline(list, query = '') {
  if (cardObserver) cardObserver.disconnect();

  // 只清除诗作条目和月份标签，保留 emptyState 不动
  timelineFeed.querySelectorAll('.poem-entry, .month-label').forEach(el => el.remove());
  emptyState.style.display = 'none';

  if (list.length === 0) {
    emptyState.style.display = 'block';
    if (showingFavoritesOnly && currentQuery) {
      emptyState.innerHTML = '<p>在收藏中没有找到匹配的诗作</p>';
    } else if (showingFavoritesOnly) {
      emptyState.innerHTML = '<p>还没有收藏的诗作</p>';
    } else {
      emptyState.innerHTML = '<p>没有找到匹配的诗作</p>';
    }
    return;
  }

  let currentLabel = null;
  let currentYear = null;

  list.forEach((poem, i) => {
    // 年份锚点
    const year = poem.sortKey.substring(0, 4);
    if (year !== currentYear) {
      currentYear = year;
      const marker = document.createElement('div');
      marker.id = 'year-' + year;
      marker.className = 'year-marker';
      timelineFeed.appendChild(marker);
    }

    // 季节标签
    const showLabel = poem.monthLabel && poem.monthLabel !== currentLabel;
    if (showLabel) {
      currentLabel = poem.monthLabel;
      const label = document.createElement('div');
      label.className = 'month-label';
      label.innerHTML = `
        <div class="month-dot"></div>
        <span class="month-text">${poem.monthLabel}</span>
      `;
      timelineFeed.appendChild(label);
    }

    // 诗作条目
    const entry = document.createElement('div');
    entry.className = 'poem-entry';
    entry.dataset.year = poem.sortKey.substring(0, 4);
    entry.style.transitionDelay = `${Math.min(i * 0.06, 0.5)}s`;

    let titleHtml = escapeHtml(poem.title);
    let excerptHtml = escapeHtml(poem.content.split('\n').slice(0, 2).join('\n'));
    if (query) {
      titleHtml = highlightText(titleHtml, query);
      excerptHtml = highlightText(excerptHtml, query);
    }

    const isFav = isFavorited(poem.id);
    const favStar = isFav ? '<span class="fav-star">★</span>' : '';

    entry.innerHTML = `
      <div class="entry-dot"></div>
      <div class="entry-line"></div>
      <div class="poem-card${isFav ? ' is-fav' : ''}" data-id="${poem.id}">
        <div class="poem-date">${poem.date}</div>
        <div class="poem-title-text">${titleHtml}${favStar}</div>
        <div class="poem-excerpt">${excerptHtml}</div>
      </div>
    `;

    entry.querySelector('.poem-card').addEventListener('click', () => openPoem(poem, currentQuery));
    timelineFeed.appendChild(entry);
    if (isRead(poem.id)) entry.classList.add('read');
  });

  // 入场动画 observer
  cardObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
        cardObserver.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1 });

  document.querySelectorAll('.poem-entry').forEach(el => cardObserver.observe(el));
}

// ========== 诗作详情 ==========
function openPoem(poem, query) {
  lastFocusedEl = document.activeElement;
  currentPoem = poem;
  window.location.hash = 'poem-' + poem.id;
  markRead(poem.id);
  // 更新已读样式
  document.querySelectorAll('.poem-entry').forEach(el => {
    const card = el.querySelector('.poem-card');
    if (card && parseInt(card.dataset.id) === poem.id) {
      el.classList.add('read');
    }
  });

  const contentLines = poem.content.split('\n').map(line => {
    let html = escapeHtml(line);
    if (query) html = highlightText(html, query);
    return `<span class="line">${html}</span>`;
  }).join('');

  let bgText = poem.background ? escapeHtml(poem.background) : '';
  if (query && bgText) bgText = highlightText(bgText, query);

  const backgroundHtml = bgText ? `
    <div class="poem-background">
      <h4>创作手记</h4>
      <p>${bgText}</p>
    </div>
  ` : '';

  const liked = isLiked(poem.id);
  const likeCount = getLikeCount(poem.id);
  const favorited = isFavorited(poem.id);

  const actionsHtml = `
    <div class="poem-actions">
      <button class="poem-action-btn ${liked ? 'liked' : ''}" data-action="like" data-id="${poem.id}">
        ${liked ? '♥' : '♡'} <span class="like-num">${likeCount}</span>
      </button>
      <button class="poem-action-btn ${favorited ? 'favorited' : ''}" data-action="fav" data-id="${poem.id}">
        ${favorited ? '★' : '☆'} 收藏
      </button>
      <button class="poem-action-btn" data-action="share" data-id="${poem.id}" style="margin-left:auto;">
        ↗ 分享
      </button>
    </div>
  `;

  modalBody.innerHTML = `
    <div class="poem-detail-date">${poem.date}</div>
    <h2 class="poem-detail-title">${escapeHtml(poem.title)}</h2>
    <div class="poem-detail-body">${contentLines}</div>
    ${backgroundHtml}
    ${actionsHtml}
  `;

  // 绑定喜欢/收藏按钮事件
  modalBody.querySelectorAll('.poem-action-btn').forEach(btn => {
    btn.addEventListener('click', e => {
      e.stopPropagation();
      const id = parseInt(btn.dataset.id);
      if (btn.dataset.action === 'like') {
        toggleLike(id).then(result => {
          btn.classList.toggle('liked', result.liked);
          btn.innerHTML = `${result.liked ? '♥' : '♡'} <span class="like-num">${result.count}</span>`;
        });
      } else if (btn.dataset.action === 'fav') {
        const nowFav = toggleFavorite(id);
        btn.classList.toggle('favorited', nowFav);
        btn.innerHTML = nowFav ? '★ 收藏' : '☆ 收藏';
        updateFavFilterVisibility();
      } else if (btn.dataset.action === 'share') {
        openShareDialog(id);
      }
    });
  });

  modalOverlay.classList.add('open');
  document.body.style.overflow = 'hidden';

  // 将焦点移入弹窗
  modalClose.focus();
}

function closeModal() {
  modalOverlay.classList.remove('open');
  document.body.style.overflow = '';
  history.replaceState(null, '', window.location.pathname);
  currentPoem = null;

  // 恢复焦点到触发元素
  if (lastFocusedEl) lastFocusedEl.focus();
}

modalClose.addEventListener('click', closeModal);
modalOverlay.addEventListener('click', e => {
  if (e.target === modalOverlay) closeModal();
});
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeModal();
});

// 焦点陷阱：Tab 键在弹窗内循环
modalOverlay.addEventListener('keydown', e => {
  if ((e.key === 'ArrowLeft' || e.key === 'ArrowRight') && modalOverlay.classList.contains('open') && currentPoem) {
    e.preventDefault();
    const idx = poems.findIndex(p => p.id === currentPoem.id);
    if (idx === -1) return;
    const next = poems[e.key === 'ArrowRight' ? Math.min(idx + 1, poems.length - 1) : Math.max(idx - 1, 0)];
    if (next && next.id !== currentPoem.id) openPoem(next, currentQuery);
  }

  if (e.key === 'Tab' && modalOverlay.classList.contains('open')) {
    const focusable = modalOverlay.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
    if (focusable.length === 0) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (e.shiftKey && document.activeElement === first) {
      last.focus();
      e.preventDefault();
    } else if (!e.shiftKey && document.activeElement === last) {
      first.focus();
      e.preventDefault();
    }
  }
});

// ========== 搜索 ==========
searchInput.addEventListener('input', () => {
  currentQuery = searchInput.value.trim();
  searchClear.classList.toggle('visible', currentQuery.length > 0);

  if (!currentQuery) {
    if (showingFavoritesOnly) {
      const data = loadFavData();
      const favIds = new Set(Object.entries(data).filter(([, v]) => v.favorited).map(([id]) => Number(id)));
      renderTimeline(poems.filter(p => favIds.has(p.id)));
    } else {
      renderTimeline(poems);
    }
    return;
  }

  const lower = currentQuery.toLowerCase();
  let filtered = poems.filter(p =>
    p.title.toLowerCase().includes(lower) ||
    p.content.toLowerCase().includes(lower) ||
    p.background.toLowerCase().includes(lower)
  );
  if (showingFavoritesOnly) {
    const data = loadFavData();
    const favIds = new Set(Object.entries(data).filter(([, v]) => v.favorited).map(([id]) => Number(id)));
    filtered = filtered.filter(p => favIds.has(p.id));
  }
  logSearch(currentQuery);
  renderTimeline(filtered, currentQuery);
});

searchClear.addEventListener('click', () => {
  searchInput.value = '';
  currentQuery = '';
  searchClear.classList.remove('visible');
  if (showingFavoritesOnly) {
    const data = loadFavData();
    const favIds = new Set(Object.entries(data).filter(([, v]) => v.favorited).map(([id]) => Number(id)));
    renderTimeline(poems.filter(p => favIds.has(p.id)));
  } else {
    renderTimeline(poems);
  }
});

// ========== 搜索日志 ==========
let searchLogTimer = null;
function logSearch(keyword) {
  if (searchLogTimer) clearTimeout(searchLogTimer);
  searchLogTimer = setTimeout(() => {
    if (keyword) {
      fetch('log_search.php', {
        method: 'POST',
        body: JSON.stringify({ keyword })
      }).catch(() => {});
    }
  }, 800);
}

// ========== 回到顶部 & 阅读进度 ==========
const backToTop = document.getElementById('backToTop');
if (backToTop) {
  window.addEventListener('scroll', () => {
    const scrollY = window.scrollY;
    backToTop.classList.toggle('visible', scrollY > 400);

    // 阅读进度条
    if (progressBar) {
      const docHeight = document.documentElement.scrollHeight - window.innerHeight;
      const pct = docHeight > 0 ? (scrollY / docHeight) * 100 : 0;
      progressBar.style.width = pct + '%';
    }
  });
  backToTop.addEventListener('click', () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
}

// ========== 留言板（服务端） ==========
function renderGuestbook() {
  fetch('guestbook.php?t=' + Date.now())
    .then(r => r.json())
    .then(messages => {
      guestbookList.innerHTML = '';
      if (messages.length === 0) {
        guestbookList.innerHTML = '<p style="color:var(--text-tertiary);font-size:0.9rem;">还没有留言，写下第一句吧。</p>';
        return;
      }
      messages.forEach(msg => {
        const item = document.createElement('div');
        item.className = 'guestbook-item';
        item.innerHTML = `
          <div class="gb-header">
            <span class="gb-name">${escapeHtml(msg.name)}</span>
            <span class="gb-time">${escapeHtml(msg.time)}</span>
          </div>
          <div class="gb-msg">${escapeHtml(msg.message)}</div>
          <button class="gb-delete" data-id="${escapeHtml(msg.id)}">删除</button>
        `;
        guestbookList.appendChild(item);
      });
    });
}

guestbookList.addEventListener('click', e => {
  const btn = e.target.closest('.gb-delete');
  if (!btn) return;
  fetch('guestbook.php', {
    method: 'POST',
    body: JSON.stringify({ action: 'delete', id: btn.dataset.id })
  }).then(r => r.json()).then(() => renderGuestbook());
});

guestbookForm.addEventListener('submit', e => {
  e.preventDefault();
  const name = document.getElementById('gbName').value.trim();
  const message = document.getElementById('gbMessage').value.trim();
  if (!name || !message) return;

  fetch('guestbook.php', {
    method: 'POST',
    body: JSON.stringify({ action: 'add', name, message })
  }).then(r => r.json()).then(() => {
    renderGuestbook();
    document.getElementById('gbName').value = '';
    document.getElementById('gbMessage').value = '';
  });
});

// ========== 工具函数 ==========
function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

function highlightText(html, query) {
  const escaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const regex = new RegExp(`(${escaped})`, 'gi');
  return html.replace(regex, '<span class="highlight">$1</span>');
}

// ========== 点赞（服务端） / 收藏（本地） ==========
let likeData = {};

function loadLikeData() {
  fetch('likes.php?t=' + Date.now())
    .then(r => r.json())
    .then(data => { likeData = data; renderRanking(); })
    .catch(() => {});
}

// ========== 最受欢迎排行 ==========
function renderRanking() {
  const container = document.getElementById('rankingList');
  const section = document.getElementById('rankingSection');
  if (!container || !section) return;

  const sorted = Object.entries(likeData)
    .filter(([, v]) => v.c > 0)
    .sort(([, a], [, b]) => b.c - a.c)
    .slice(0, 5);

  if (sorted.length === 0) {
    section.style.display = 'none';
    return;
  }
  section.style.display = 'flex';

  container.innerHTML = sorted.map(([id, data], i) => {
    const poem = poems.find(p => p.id === Number(id));
    const title = poem ? poem.title : '未知';
    return `<span class="ranking-item" data-id="${id}">
      <span class="ranking-num">${i + 1}</span>
      <span class="ranking-poem-title">${escapeHtml(title)}</span>
      <span class="ranking-count">${data.c}</span>
    </span>`;
  }).join('');

  container.querySelectorAll('.ranking-item').forEach(el => {
    el.addEventListener('click', () => {
      const poem = poems.find(p => p.id === Number(el.dataset.id));
      if (poem) openPoem(poem, '');
    });
  });
}

function isLiked(id) {
  return likeData[id]?.liked || false;
}

function getLikeCount(id) {
  return likeData[id]?.c || 0;
}

function toggleLike(id) {
  return fetch('likes.php', {
    method: 'POST',
    body: JSON.stringify({ action: 'toggle', id })
  }).then(r => r.json()).then(result => {
    if (!likeData[id]) likeData[id] = {};
    likeData[id].liked = result.liked;
    likeData[id].c = result.count;
    renderRanking();
    return result;
  });
}

// 收藏（纯本地）
function loadFavData() {
  return JSON.parse(localStorage.getItem('poetry_favorites') || '{}');
}

function saveFavData(data) {
  localStorage.setItem('poetry_favorites', JSON.stringify(data));
}

function isFavorited(id) {
  return !!loadFavData()[id]?.favorited;
}

function toggleFavorite(id) {
  const data = loadFavData();
  if (!data[id]) data[id] = { liked: false, favorited: false };
  data[id].favorited = !data[id].favorited;
  saveFavData(data);
  return data[id].favorited;
}

// ========== 已读标记（本地） ==========
function markRead(id) {
  const data = JSON.parse(localStorage.getItem('poetry_read') || '{}');
  data[id] = true;
  localStorage.setItem('poetry_read', JSON.stringify(data));
}
function isRead(id) {
  const data = JSON.parse(localStorage.getItem('poetry_read') || '{}');
  return !!data[id];
}

// ========== 年份导航 ==========
function renderYearNav() {
  if (!yearNav) return;
  const years = [...new Set(poems.map(p => p.sortKey.substring(0, 4)))].sort();
  yearNav.innerHTML = years.map(y =>
    `<a href="#" data-year="${y}">${y}</a>`
  ).join('');

  yearNav.querySelectorAll('a').forEach(a => {
    a.addEventListener('click', e => {
      e.preventDefault();
      const el = document.getElementById('year-' + a.dataset.year);
      if (el) el.scrollIntoView({ behavior: 'smooth' });
    });
  });
}

// ========== 统计卡片 ==========
function computeStats() {
  if (poems.length === 0) return null;
  const sorted = [...poems].sort((a, b) => a.sortKey.localeCompare(b.sortKey));
  const longest = poems.reduce((best, p) => {
    const lines = p.content.split('\n').length;
    return lines > best.lines ? { ...p, lines } : best;
  }, { lines: 0 });
  return {
    total: poems.length,
    longestTitle: longest.title,
    longestLines: longest.lines,
    earliest: sorted[0],
    latest: sorted[sorted.length - 1],
  };
}

function renderStats() {
  if (!statsCard) return;
  const s = computeStats();
  if (!s) {
    statsCard.innerHTML = '';
    return;
  }
  statsCard.innerHTML = `
    <span class="stat-item">共 <span class="stat-value">${s.total}</span><span class="stat-label"> 首</span></span>
    <span class="stat-sep">·</span>
    <span class="stat-item"><span class="stat-value">${s.earliest.date}</span><span class="stat-label"> 最早</span></span>
    <span class="stat-sep">·</span>
    <span class="stat-item"><span class="stat-value">${s.latest.date}</span><span class="stat-label"> 最近</span></span>
    <span class="stat-sep">·</span>
    <span class="stat-item">最长「<span class="stat-value">${escapeHtml(s.longestTitle)}</span>」<span class="stat-label">${s.longestLines} 行</span></span>
  `;
}

// ========== 收藏夹筛选 ==========
function updateFavFilterVisibility() {
  if (!favFilterBtn) return;
  const data = loadFavData();
  const hasFav = Object.values(data).some(v => v.favorited);
  favFilterBtn.classList.toggle('visible', hasFav);
}

function toggleFavFilter() {
  showingFavoritesOnly = !showingFavoritesOnly;
  favFilterBtn.classList.toggle('active', showingFavoritesOnly);

  if (showingFavoritesOnly) {
    const data = loadFavData();
    const favIds = new Set(
      Object.entries(data).filter(([, v]) => v.favorited).map(([id]) => Number(id))
    );
    const filtered = poems.filter(p => favIds.has(p.id));
    renderTimeline(filtered);
  } else {
    renderTimeline(poems);
  }
}

// ========== 随机一诗 ==========
if (randomBtn) {
  randomBtn.addEventListener('click', () => {
    if (poems.length === 0) return;
    const picked = poems[Math.floor(Math.random() * poems.length)];
    openPoem(picked, '');
  });
}

// ========== 暗色模式 ==========
if (themeToggle) {
  const savedTheme = localStorage.getItem('poetry_theme') || 'light';
  document.documentElement.setAttribute('data-theme', savedTheme);
  themeToggle.textContent = savedTheme === 'dark' ? '亮色' : '暗色';

  themeToggle.addEventListener('click', () => {
    const current = document.documentElement.getAttribute('data-theme');
    const next = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('poetry_theme', next);
    themeToggle.textContent = next === 'dark' ? '亮色' : '暗色';
  });
}

// ========== 收藏夹按钮事件 ==========
if (favFilterBtn) {
  favFilterBtn.addEventListener('click', toggleFavFilter);
}

// ========== 加载个人简介 ==========
function loadAboutContent() {
  const container = document.getElementById('aboutContent');
  if (!container) return;
  fetch('about_content.json')
    .then(r => r.json())
    .then(data => {
      if (data && data.text) {
        container.innerHTML = data.text;
      } else {
        container.innerHTML = '<p style="color:var(--text-tertiary);font-size:0.9rem;">暂无介绍</p>';
      }
    })
    .catch(() => {
      container.innerHTML = '<p style="color:var(--text-tertiary);font-size:0.9rem;">暂无介绍</p>';
    });
}

// ========== 加载额外诗作（管理员发布） ==========
function loadExtraPoems() {
  fetch('poems_extra.json?_t=' + Date.now())
    .then(r => {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(extra => {
      if (extra && extra.length > 0) {
        poems.push(...extra);
        sortPoems();
        renderTimeline(poems);
        renderYearNav();
        renderStats();
        updateFavFilterVisibility();
      }
    })
    .catch(e => console.warn('loadExtraPoems:', e));
}

// ========== 访客追踪 ==========
fetch('track.php?s=' + screen.width + 'x' + screen.height);

// ========== 排序切换 ==========
const sortToggle = document.getElementById('sortToggle');
if (sortToggle) sortToggle.addEventListener('click', toggleSortOrder);

// ========== 初始化 ==========
sortPoems();
const stBtn = document.getElementById('sortToggle');
if (stBtn) stBtn.textContent = '↑ 最新';
renderTimeline(poems);
renderYearNav();
renderStats();
updateFavFilterVisibility();
renderGuestbook();
loadExtraPoems();
loadAboutContent();
loadLikeData();

// ========== 分享卡片（HTML预览 + Canvas下载） ==========
let currentSharePoem = null;
let currentShareTemplate = 0;

const SHARE_TPL = [
  { bg: '#FFFFFF', text: '#1a1a1a', title: '#1a1a1a', author: '#9b9b9b', date: '#b5b5b5', divider: '#e0e0e0' },
  { bg: '#F8F4EE', text: '#3a3229', title: '#5a4a3a', author: '#b5a89a', date: '#c4b8aa', divider: '#e0d6c8' },
  { bg: '#1e1e1e', text: '#d5cdc0', title: '#f0e8dc', author: '#8a8078', date: '#6b6560', divider: '#4a4440' },
];

function buildSharePreview(poem, idx) {
  var t = SHARE_TPL[idx];
  var titleSize = poem.title.length > 8 ? 22 : 26;
  var lines = poem.content.split('\n').map(function(l) { return escapeHtml(l); }).join('<br>');
  return '<div style="background:' + t.bg + ';color:' + t.text + ';font-family:\'Noto Serif SC\',serif;padding:48px 36px;border-radius:8px;text-align:center;max-width:520px;margin:0 auto;">'
    + '<div style="color:' + t.title + ';font-weight:700;font-size:' + titleSize + 'px;margin-bottom:2px;">' + escapeHtml(poem.title) + '</div>'
    + '<div style="color:' + t.author + ';font-size:13px;margin-bottom:14px;">艾苇</div>'
    + '<div style="background:' + t.divider + ';height:1px;width:48px;margin:0 auto 18px;"></div>'
    + '<div style="color:' + t.text + ';font-size:16px;line-height:2.2;white-space:pre-line;">' + lines + '</div>'
    + '<div style="color:' + t.date + ';font-size:12px;margin-top:20px;">' + escapeHtml(poem.date) + '</div>'
    + '</div>';
}

function openShareDialog(poemId) {
  closeModal();
  var poem = poems.find(function(p) { return p.id === poemId; });
  if (!poem) return;
  currentSharePoem = poem;
  currentShareTemplate = 0;

  document.querySelectorAll('.share-tmpl-btn').forEach(function(b) {
    b.classList.toggle('active', parseInt(b.dataset.tmpl) === 1);
  });
  document.getElementById('sharePreview').innerHTML = buildSharePreview(poem, 0);
  document.getElementById('shareOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function switchShareTemplate(num) {
  currentShareTemplate = num - 1;
  document.querySelectorAll('.share-tmpl-btn').forEach(function(b) {
    b.classList.toggle('active', parseInt(b.dataset.tmpl) === num);
  });
  if (currentSharePoem) {
    document.getElementById('sharePreview').innerHTML = buildSharePreview(currentSharePoem, currentShareTemplate);
  }
}

function downloadShare() {
  if (!currentSharePoem) return;
  var t = SHARE_TPL[currentShareTemplate];
  var lines = currentSharePoem.content.split('\n');
  var lh = 40, pad = 56, titleH = 90, footerH = 60;
  var h = pad + titleH + lines.length * lh + footerH + pad;
  var w = 600, height = Math.max(h, 480);

  var c = document.createElement('canvas');
  c.width = w;
  c.height = height;
  var ctx = c.getContext('2d');

  ctx.fillStyle = t.bg;
  ctx.fillRect(0, 0, w, height);
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';

  ctx.fillStyle = t.title;
  ctx.font = '700 ' + (currentSharePoem.title.length > 8 ? 22 : 26) + 'px "Noto Serif SC", "SimSun", "STSong", serif';
  ctx.fillText(currentSharePoem.title, w / 2, pad + 28);

  ctx.fillStyle = t.author;
  ctx.font = '13px "Noto Serif SC", "SimSun", serif';
  ctx.fillText('艾苇', w / 2, pad + 58);

  ctx.strokeStyle = t.divider;
  ctx.lineWidth = 1;
  ctx.beginPath();
  ctx.moveTo(w / 2 - 24, pad + titleH - 6);
  ctx.lineTo(w / 2 + 24, pad + titleH - 6);
  ctx.stroke();

  ctx.fillStyle = t.text;
  ctx.font = '16px "Noto Serif SC", "SimSun", "STSong", serif';
  var y = pad + titleH + 16;
  for (var i = 0; i < lines.length; i++) {
    ctx.fillText(lines[i] || ' ', w / 2, y);
    y += lh;
  }

  ctx.fillStyle = t.date;
  ctx.font = '12px Georgia, "Noto Serif SC", serif';
  ctx.fillText(currentSharePoem.date, w / 2, height - pad + 16);

  c.toBlob(function(blob) {
    var link = document.createElement('a');
    link.download = 'poem-' + currentSharePoem.id + '-' + (currentShareTemplate + 1) + '.png';
    link.href = URL.createObjectURL(blob);
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    setTimeout(function() { document.body.removeChild(link); }, 500);
  });
}

document.getElementById('shareClose').addEventListener('click', () => {
  document.getElementById('shareOverlay').classList.remove('open');
  document.body.style.overflow = '';
});
document.getElementById('shareOverlay').addEventListener('click', e => {
  if (e.target === document.getElementById('shareOverlay')) {
    document.getElementById('shareOverlay').classList.remove('open');
    document.body.style.overflow = '';
  }
});
document.querySelectorAll('.share-tmpl-btn').forEach(btn => {
  btn.addEventListener('click', () => switchShareTemplate(parseInt(btn.dataset.tmpl)));
});
document.getElementById('shareDownload').addEventListener('click', downloadShare);

// ========== URL 哈希深链 ==========
function openPoemFromHash() {
  const match = window.location.hash.match(/^#poem-(\d+)$/);
  if (!match) return;
  const id = parseInt(match[1]);
  const poem = poems.find(p => p.id === id);
  if (poem) setTimeout(() => openPoem(poem, ''), 300);
}

window.addEventListener('hashchange', () => {
  if (!window.location.hash.startsWith('#poem-')) {
    closeModal();
  } else {
    openPoemFromHash();
  }
});

openPoemFromHash();
