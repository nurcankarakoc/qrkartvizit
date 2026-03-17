// assets/js/main.js
document.addEventListener('DOMContentLoaded', () => {
    const menuBtn = document.querySelector('.mobile-menu-btn');
    const navLinks = document.querySelector('.nav-links');

    if(menuBtn && navLinks) {
        menuBtn.addEventListener('click', () => {
            if (navLinks.style.display === 'flex') {
                navLinks.style.display = 'none';
            } else {
                navLinks.style.display = 'flex';
                navLinks.style.flexDirection = 'column';
                navLinks.style.position = 'absolute';
                navLinks.style.top = '100%';
                navLinks.style.left = '0';
                navLinks.style.width = '100%';
                navLinks.style.background = 'rgba(6, 9, 19, 0.95)';
                navLinks.style.padding = '1rem';
                navLinks.style.borderBottom = '1px solid rgba(255,255,255,0.08)';
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
                    navLinks.style.display = 'none';
                }
            }
        });
    });

    // Testimonial Slider Logic
    const testimonials = document.querySelectorAll('.testimonial-item');
    const dots = document.querySelectorAll('.dot');
    const sliderContainer = document.querySelector('.testimonial-slider-wrapper');
    let currentIndex = 0;
    let sliderInterval;
    const intervalTime = 3000;

    function showTestimonial(index) {
        testimonials.forEach(t => t.classList.remove('active'));
        dots.forEach(d => d.classList.remove('active'));

        testimonials[index].classList.add('active');
        dots[index].classList.add('active');
        currentIndex = index;
    }

    function nextTestimonial() {
        let nextIndex = (currentIndex + 1) % testimonials.length;
        showTestimonial(nextIndex);
    }

    function startSlider() {
        sliderInterval = setInterval(nextTestimonial, intervalTime);
    }

    function stopSlider() {
        clearInterval(sliderInterval);
    }

    if (sliderContainer) {
        startSlider();

        sliderContainer.addEventListener('mouseenter', stopSlider);
        sliderContainer.addEventListener('mouseleave', startSlider);
        sliderContainer.addEventListener('touchstart', stopSlider);
        sliderContainer.addEventListener('touchend', startSlider);

        dots.forEach(dot => {
            dot.addEventListener('click', () => {
                const index = parseInt(dot.getAttribute('data-index'));
                showTestimonial(index);
            });
        });
    }

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
