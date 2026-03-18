// assets/js/dashboard-mobile.js
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector('.sidebar');
    if (!sidebar) {
        return;
    }

    const isMobileView = window.matchMedia('(max-width: 1024px)');

    const findBrandText = () => {
        const candidates = [
            '.sidebar .brand-logotype span',
            '.sidebar .sidebar-header span',
            '.sidebar .logo span',
            '.sidebar .brand-logotype',
        ];

        for (const selector of candidates) {
            const el = sidebar.querySelector(selector);
            if (el && el.textContent && el.textContent.trim() !== '') {
                return el.textContent.replace(/\s+/g, ' ').trim();
            }
        }

        return 'QR Kartvizit';
    };

    let topbar = document.querySelector('.mobile-topbar');
    if (!topbar) {
        topbar = document.createElement('div');
        topbar.className = 'mobile-topbar';
        topbar.innerHTML = `
            <button class="mobile-nav-toggle" type="button" aria-label="Menuyu ac" aria-expanded="false">
                <i data-lucide="menu"></i>
            </button>
            <div class="mobile-topbar__brand">${findBrandText()}</div>
            <div style="width:44px;height:44px;"></div>
        `;

        const dashboardLayout = document.querySelector('.dashboard-layout');
        if (dashboardLayout && dashboardLayout.parentNode) {
            dashboardLayout.parentNode.insertBefore(topbar, dashboardLayout);
        } else {
            document.body.insertBefore(topbar, document.body.firstChild);
        }
    }

    let overlay = document.querySelector('.sidebar-overlay');
    if (!overlay) {
        overlay = document.createElement('button');
        overlay.className = 'sidebar-overlay';
        overlay.setAttribute('type', 'button');
        overlay.setAttribute('aria-label', 'Menuyu kapat');
        document.body.appendChild(overlay);
    }

    const toggleButton = topbar.querySelector('.mobile-nav-toggle');
    if (!toggleButton) {
        return;
    }

    const closeSidebar = () => {
        sidebar.classList.remove('is-open');
        overlay.classList.remove('is-open');
        document.body.classList.remove('sidebar-open');
        toggleButton.setAttribute('aria-expanded', 'false');
    };

    const openSidebar = () => {
        sidebar.classList.add('is-open');
        overlay.classList.add('is-open');
        document.body.classList.add('sidebar-open');
        toggleButton.setAttribute('aria-expanded', 'true');
    };

    const toggleSidebar = () => {
        if (sidebar.classList.contains('is-open')) {
            closeSidebar();
            return;
        }
        openSidebar();
    };

    toggleButton.addEventListener('click', toggleSidebar);
    toggleButton.addEventListener('touchstart', (event) => {
        event.preventDefault();
        toggleSidebar();
    }, { passive: false });

    overlay.addEventListener('click', closeSidebar);
    overlay.addEventListener('touchstart', closeSidebar, { passive: true });

    sidebar.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            if (isMobileView.matches) {
                closeSidebar();
            }
        });
    });

    window.addEventListener('resize', () => {
        if (!isMobileView.matches) {
            closeSidebar();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });

    if (window.lucide && typeof window.lucide.createIcons === 'function') {
        window.lucide.createIcons();
    }
});
