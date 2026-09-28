/* admin/partials/admin.js
   Shared JavaScript for all AIDTRACK admin pages.
   Include at bottom of body: <script src="../partials/admin.js"></script>
*/

// Init Lucide icons
lucide.createIcons();

// Sidebar toggle (mobile)
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('overlay').classList.toggle('show');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('overlay').classList.remove('show');
}

// Topbar search toggle
function toggleSearch() {
    const s = document.getElementById('topSearch');
    if (!s) return;
    const visible = s.style.display === 'flex';
    s.style.display = visible ? 'none' : 'flex';
    if (!visible) s.querySelector('input')?.focus();
}

// Close sidebar when clicking a nav link on mobile
document.querySelectorAll('.nav-link').forEach(link => {
    link.addEventListener('click', () => {
        if (window.innerWidth <= 960) closeSidebar();
    });
});

// ─── REAL-TIME NOTIFICATION POLLING ───
(function pollNotifications() {
    setInterval(() => {
        fetch('../api/notification_count.php')
            .then(r => r.json())
            .then(data => {
                // Update notification bell badge
                const badge = document.querySelector('.notif-count');
                if (badge && data.pending_apps !== undefined) {
                    const total = (data.pending_apps || 0) + (data.unread_messages || 0);
                    badge.textContent = total;
                    badge.style.display = total > 0 ? 'flex' : 'none';
                }
                // Update sidebar pending badge
                const navBadges = document.querySelectorAll('.nav-badge');
                navBadges.forEach(b => {
                    const link = b.closest('.nav-link');
                    if (link && link.href.includes('applications')) {
                        b.textContent = data.pending_apps || 0;
                        b.style.display = (data.pending_apps > 0) ? '' : 'none';
                    }
                });
            })
            .catch(() => {});
    }, 30000); // Poll every 30 seconds
})();

// ─── PAGINATION ───
// Auto-paginate any table with data-paginate="N" attribute on the <table>
// e.g. <table data-paginate="10"> will show 10 rows per page
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('table[data-paginate]').forEach(initPagination);
});

function initPagination(table) {
    const perPage = parseInt(table.dataset.paginate) || 10;
    const tbody = table.querySelector('tbody');
    if (!tbody) return;
    const allRows = Array.from(tbody.querySelectorAll('tr'));
    if (allRows.length <= perPage) return; // No pagination needed

    let currentPage = 1;
    const totalPages = Math.ceil(allRows.length / perPage);

    // Create pagination container
    const pag = document.createElement('div');
    pag.className = 'pagination';
    pag.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:12px 20px;border-top:1px solid var(--border,#e2e8f0);font-size:.78rem;';

    const info = document.createElement('span');
    info.style.cssText = 'color:var(--muted,#64748b);font-weight:600;';

    const btns = document.createElement('div');
    btns.style.cssText = 'display:flex;gap:4px;';

    pag.appendChild(info);
    pag.appendChild(btns);

    // Insert after the tbl-wrap parent
    const tblWrap = table.closest('.tbl-wrap') || table.parentElement;
    tblWrap.parentElement.insertBefore(pag, tblWrap.nextSibling);

    function render() {
        const start = (currentPage - 1) * perPage;
        const end = start + perPage;
        allRows.forEach((row, i) => {
            row.style.display = (i >= start && i < end) ? '' : 'none';
        });
        info.textContent = `Showing ${start + 1}–${Math.min(end, allRows.length)} of ${allRows.length}`;

        btns.innerHTML = '';
        // Prev
        addBtn('‹', currentPage > 1, () => { currentPage--; render(); });
        // Page numbers
        const maxVisible = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
        let endPage = Math.min(totalPages, startPage + maxVisible - 1);
        if (endPage - startPage < maxVisible - 1) startPage = Math.max(1, endPage - maxVisible + 1);

        if (startPage > 1) { addBtn('1', true, () => { currentPage = 1; render(); }); if (startPage > 2) addEllipsis(); }
        for (let p = startPage; p <= endPage; p++) {
            addBtn(p, true, () => { currentPage = p; render(); }, p === currentPage);
        }
        if (endPage < totalPages) { if (endPage < totalPages - 1) addEllipsis(); addBtn(totalPages, true, () => { currentPage = totalPages; render(); }); }
        // Next
        addBtn('›', currentPage < totalPages, () => { currentPage++; render(); });
    }

    function addBtn(label, enabled, onClick, active) {
        const b = document.createElement('button');
        b.textContent = label;
        b.disabled = !enabled;
        b.style.cssText = `padding:5px 10px;border:1px solid var(--border,#e2e8f0);border-radius:6px;font-family:inherit;font-size:.76rem;font-weight:700;cursor:${enabled?'pointer':'default'};transition:all .15s;background:${active?'var(--brand,#1a56db)':'var(--white,#fff)'};color:${active?'#fff':'var(--slate,#334155)'};opacity:${enabled?'1':'.4'};`;
        if (enabled && onClick) b.addEventListener('click', onClick);
        btns.appendChild(b);
    }

    function addEllipsis() {
        const s = document.createElement('span');
        s.textContent = '…';
        s.style.cssText = 'padding:5px 6px;color:var(--muted,#64748b);font-size:.78rem;';
        btns.appendChild(s);
    }

    render();
}