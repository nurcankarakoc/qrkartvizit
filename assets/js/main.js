// assets/js/main.js
document.addEventListener('DOMContentLoaded', () => {
    const menuBtn = document.querySelector('.mobile-menu-btn');
    const navLinks = document.querySelector('.nav-links');
    const closeMenu = () => {
        if (!menuBtn || !navLinks) {
            return;
        }
        navLinks.classList.remove('mobile-open');
        menuBtn.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('nav-open');
    };

    if(menuBtn && navLinks) {
        const openMenu = () => {
            navLinks.classList.add('mobile-open');
            menuBtn.setAttribute('aria-expanded', 'true');
            document.body.classList.add('nav-open');
        };

        const toggleMenu = () => {
            if (navLinks.classList.contains('mobile-open')) {
                closeMenu();
                return;
            }
            openMenu();
        };

        menuBtn.addEventListener('click', toggleMenu);
        menuBtn.addEventListener('touchstart', (event) => {
            event.preventDefault();
            toggleMenu();
        }, { passive: false });

        document.addEventListener('click', (event) => {
            if (window.innerWidth > 768) {
                closeMenu();
                return;
            }
            if (!menuBtn.contains(event.target) && !navLinks.contains(event.target)) {
                closeMenu();
            }
        });
    }

    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if(target) {
                target.scrollIntoView({ behavior: 'smooth' });
                if(window.innerWidth <= 768 && navLinks) {
                    closeMenu();
                }
            }
        });
    });



    // How It Works Grid Highlight & Progress Logic
    const steps = document.querySelectorAll('.step-card');
    const progressFill = document.querySelector('.progress-fill');
    let currentStep = 0;
    const stepDuration = 3000; // 3 seconds per step

    function updateSteps() {
        steps.forEach((s, idx) => {
            if (idx === currentStep) {
                s.classList.add('active');
            } else {
                s.classList.remove('active');
            }
        });

        // Update progress line width
        // 0% -> 25% -> 50% -> 75% -> 100% for 5 steps (gap based)
        // Or simply highlight the segment. 
        // Let's make it smooth:
        const progressPerStep = 100 / (steps.length - 1);
        const targetWidth = currentStep * progressPerStep;
        if (progressFill) {
            progressFill.style.width = `${targetWidth}%`;
        }
    }

    function nextStepCycle() {
        currentStep = (currentStep + 1) % steps.length;
        updateSteps();
    }

    if (steps.length > 0) {
        updateSteps();
        setInterval(nextStepCycle, stepDuration);
    }

    // Phone Screen Rotation (4s Profile, 2s Card)
    const profileView = document.querySelector('.v-profile-view');
    const cardView = document.querySelector('.v-card-view');
    
    if (profileView && cardView) {
        function rotatePhoneView() {
            // Step 1: Show Profile, wait 4s
            profileView.classList.add('active');
            cardView.classList.remove('active');
            
            setTimeout(() => {
                // Step 2: Show Card, wait 2s
                profileView.classList.remove('active');
                cardView.classList.add('active');
                
                setTimeout(() => {
                    // Step 3: Loop back
                    rotatePhoneView();
                }, 2000); // Card duration
            }, 4000); // Profile duration
        }
        
        rotatePhoneView();
    }
});
