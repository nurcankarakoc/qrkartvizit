// assets/js/mobile-form.js
document.addEventListener('DOMContentLoaded', () => {
    const isMobile = () =>
        window.matchMedia('(max-width: 768px)').matches ||
        window.matchMedia('(pointer: coarse)').matches;

    const isFormField = (element) => {
        if (!element) {
            return false;
        }

        const tag = element.tagName;
        return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT';
    };

    document.addEventListener('focusin', (event) => {
        const target = event.target;
        if (!isMobile() || !isFormField(target)) {
            return;
        }

        // Delay ensures browser keyboard viewport recalculation is complete.
        setTimeout(() => {
            try {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center',
                    inline: 'nearest',
                });
            } catch (error) {
                // No-op fallback for older browsers.
            }
        }, 220);
    }, true);
});
