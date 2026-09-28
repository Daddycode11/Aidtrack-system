// client/partials/client.js
lucide.createIcons();

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('overlay').classList.toggle('show');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('overlay').classList.remove('show');
}

// Auto-close sidebar on nav click (mobile)
document.querySelectorAll('.nav-link').forEach(function(link) {
    link.addEventListener('click', function() {
        if (window.innerWidth <= 960) closeSidebar();
    });
});

// ─── REAL-TIME NOTIFICATION POLLING ───
(function pollNotifications() {
    setInterval(() => {
        fetch('../api/notification_count.php')
            .then(r => r.json())
            .then(data => {
                // Update sidebar badges
                document.querySelectorAll('.nav-badge').forEach(b => {
                    const link = b.closest('.nav-link');
                    if (link && link.href.includes('messages')) {
                        const total = (data.unread_total || 0);
                        b.textContent = total;
                        b.style.display = total > 0 ? '' : 'none';
                    }
                    if (link && link.href.includes('aid_history')) {
                        b.textContent = data.pending_apps || 0;
                        b.style.display = (data.pending_apps > 0) ? '' : 'none';
                    }
                });
                // Update topbar bell
                const bell = document.querySelector('.notif-count');
                if (bell) {
                    const total = data.unread_total || 0;
                    bell.textContent = total;
                    bell.style.display = total > 0 ? 'flex' : 'none';
                }
            })
            .catch(() => {});
    }, 30000);
})();

// ─── MOBILE SWIPE SECTIONS ───
function initSwipeSections() {
    if (window.innerWidth > 768) return;
    document.querySelectorAll('.swipe-sections').forEach(function(container) {
        var sections = Array.from(container.querySelectorAll('.swipe-section'));
        if (sections.length < 2) return;

        // Read tab labels from data-label attributes on swipe-sections
        var tabsEl = container.closest('.swipe-container') && container.closest('.swipe-container').querySelector('.swipe-tabs');
        var dotsEl = document.createElement('div');
        dotsEl.className = 'swipe-dots';
        container.after(dotsEl);

        sections.forEach(function(_, i) {
            var dot = document.createElement('div');
            dot.className = 'swipe-dot' + (i === 0 ? ' active' : '');
            dot.addEventListener('click', function() { scrollToSection(i); });
            dotsEl.appendChild(dot);
        });

        function updateActive(idx) {
            dotsEl.querySelectorAll('.swipe-dot').forEach(function(d, i) {
                d.classList.toggle('active', i === idx);
            });
            if (tabsEl) {
                tabsEl.querySelectorAll('.swipe-tab').forEach(function(t, i) {
                    t.classList.toggle('active', i === idx);
                });
            }
        }

        function scrollToSection(idx) {
            container.scrollTo({ left: container.offsetWidth * idx, behavior: 'smooth' });
        }

        // Wire tab clicks
        if (tabsEl) {
            tabsEl.querySelectorAll('.swipe-tab').forEach(function(tab, i) {
                tab.addEventListener('click', function() { scrollToSection(i); });
            });
        }

        // Update dots/tabs on scroll
        var scrollTimer;
        container.addEventListener('scroll', function() {
            clearTimeout(scrollTimer);
            scrollTimer = setTimeout(function() {
                var idx = Math.round(container.scrollLeft / Math.max(container.offsetWidth, 1));
                updateActive(idx);
            }, 50);
        });

        // Touch swipe support (fallback for older browsers)
        var touchStartX = 0;
        container.addEventListener('touchstart', function(e) {
            touchStartX = e.touches[0].clientX;
        }, { passive: true });
        container.addEventListener('touchend', function(e) {
            var dx = e.changedTouches[0].clientX - touchStartX;
            var idx = Math.round(container.scrollLeft / Math.max(container.offsetWidth, 1));
            if (Math.abs(dx) > 40) {
                scrollToSection(dx < 0 ? Math.min(idx + 1, sections.length - 1) : Math.max(idx - 1, 0));
            }
        }, { passive: true });
    });
}
document.addEventListener('DOMContentLoaded', initSwipeSections);

// ─── PAGINATION ───
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('table[data-paginate]').forEach(initPagination);
});

function initPagination(table) {
    const perPage = parseInt(table.dataset.paginate) || 10;
    const tbody = table.querySelector('tbody');
    if (!tbody) return;
    const allRows = Array.from(tbody.querySelectorAll('tr'));
    if (allRows.length <= perPage) return;

    let currentPage = 1;
    const totalPages = Math.ceil(allRows.length / perPage);

    const pag = document.createElement('div');
    pag.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:12px 20px;border-top:1px solid var(--border,#e2e8f0);font-size:.78rem;';

    const info = document.createElement('span');
    info.style.cssText = 'color:var(--muted,#64748b);font-weight:600;';

    const btns = document.createElement('div');
    btns.style.cssText = 'display:flex;gap:4px;';

    pag.appendChild(info);
    pag.appendChild(btns);

    const tblWrap = table.closest('.tbl-wrap') || table.parentElement;
    tblWrap.parentElement.insertBefore(pag, tblWrap.nextSibling);

    function render() {
        const start = (currentPage - 1) * perPage;
        const end = start + perPage;
        allRows.forEach((row, i) => {
            row.style.display = (i >= start && i < end) ? '' : 'none';
        });
        info.textContent = `Showing ${start + 1}\u2013${Math.min(end, allRows.length)} of ${allRows.length}`;
        btns.innerHTML = '';
        addBtn('\u2039', currentPage > 1, () => { currentPage--; render(); });
        const maxV = 5;
        let sp = Math.max(1, currentPage - Math.floor(maxV / 2));
        let ep = Math.min(totalPages, sp + maxV - 1);
        if (ep - sp < maxV - 1) sp = Math.max(1, ep - maxV + 1);
        if (sp > 1) { addBtn('1', true, () => { currentPage = 1; render(); }); if (sp > 2) addEll(); }
        for (let p = sp; p <= ep; p++) addBtn(p, true, () => { currentPage = p; render(); }, p === currentPage);
        if (ep < totalPages) { if (ep < totalPages - 1) addEll(); addBtn(totalPages, true, () => { currentPage = totalPages; render(); }); }
        addBtn('\u203A', currentPage < totalPages, () => { currentPage++; render(); });
    }

    function addBtn(label, enabled, onClick, active) {
        const b = document.createElement('button');
        b.textContent = label;
        b.disabled = !enabled;
        b.style.cssText = `padding:5px 10px;border:1px solid var(--border,#e2e8f0);border-radius:6px;font-family:inherit;font-size:.76rem;font-weight:700;cursor:${enabled?'pointer':'default'};transition:all .15s;background:${active?'var(--brand,#e87400)':'var(--white,#fff)'};color:${active?'#fff':'var(--slate,#334155)'};opacity:${enabled?'1':'.4'};`;
        if (enabled && onClick) b.addEventListener('click', onClick);
        btns.appendChild(b);
    }

    function addEll() {
        const s = document.createElement('span');
        s.textContent = '\u2026';
        s.style.cssText = 'padding:5px 6px;color:var(--muted,#64748b);';
        btns.appendChild(s);
    }

    render();
}
