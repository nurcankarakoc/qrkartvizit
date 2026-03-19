/**
 * assets/js/transitions.js
 * Tüm sayfalar için global sayfa geçiş efekti (navy → gold sweep)
 * Her <a> tıklamasında overlay animasyonu tetiklenir.
 */
(function () {
    'use strict';

    /* ── Overlay oluştur ─────────────────────────────────────── */
    function createOverlay() {
        if (document.getElementById('zs-page-overlay')) return;
        const el = document.createElement('div');
        el.id = 'zs-page-overlay';
        el.setAttribute('aria-hidden', 'true');
        el.innerHTML = `<div class="zs-overlay-logo">
            <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                <rect width="40" height="40" rx="10" fill="rgba(166,128,63,0.15)"/>
                <text x="50%" y="55%" dominant-baseline="middle" text-anchor="middle"
                      fill="#A6803F" font-family="Inter,sans-serif" font-size="22" font-weight="800">Z</text>
            </svg>
        </div>`;
        document.body.appendChild(el);
    }

    /* ── Geçiş mantığı ──────────────────────────────────────── */
    function triggerTransition(href) {
        const overlay = document.getElementById('zs-page-overlay');
        if (!overlay) return;
        overlay.classList.add('zs-active');
        setTimeout(() => { window.location.href = href; }, 380);
    }

    function handleLinks() {
        document.querySelectorAll('a[href]').forEach(function (link) {
            const href = link.getAttribute('href') || '';
            // Dışlananlar: #, javascript:, mailto:, tel:, target=_blank, zaten aktif
            if (!href || href.startsWith('#') || href.startsWith('javascript')
                || href.startsWith('mailto:') || href.startsWith('tel:')
                || link.getAttribute('target') === '_blank'
                || link.dataset.noTransition !== undefined) {
                return;
            }
            link.addEventListener('click', function (e) {
                // Modifier tuş basılıysa normal davran
                if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
                e.preventDefault();
                triggerTransition(href);
            });
        });
    }

    /* ── Sayfa yüklenince overlay'i kaldır ──────────────────── */
    function onPageLoad() {
        const overlay = document.getElementById('zs-page-overlay');
        if (overlay) {
            overlay.classList.remove('zs-active');
        }
    }

    /* ── Init ────────────────────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', function () {
        createOverlay();
        handleLinks();
    });

    window.addEventListener('pageshow', onPageLoad);
})();
