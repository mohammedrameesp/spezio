/**
 * Spezio Apartments - Main JavaScript
 * Clean, modern interactions for the apartment website
 */

document.addEventListener('DOMContentLoaded', function() {

    // ==========================================================================
    // Custom Popup Modal (replaces browser alerts)
    // ==========================================================================

    // Create popup HTML
    const popupHTML = `
        <div class="popup-overlay" id="customPopup">
            <div class="popup-box">
                <div class="popup-icon" id="popupIcon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                </div>
                <h3 class="popup-title" id="popupTitle">Success</h3>
                <p class="popup-message" id="popupMessage"></p>
                <button class="popup-btn" id="popupBtn">OK</button>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', popupHTML);

    const popup = document.getElementById('customPopup');
    const popupIcon = document.getElementById('popupIcon');
    const popupTitle = document.getElementById('popupTitle');
    const popupMessage = document.getElementById('popupMessage');
    const popupBtn = document.getElementById('popupBtn');

    function showPopup(message, type = 'info') {
        const icons = {
            success: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>',
            error: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>',
            info: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>'
        };

        const titles = {
            success: 'Success!',
            error: 'Oops!',
            info: 'Notice'
        };

        popupIcon.className = 'popup-icon ' + type;
        popupIcon.innerHTML = icons[type] || icons.info;
        popupTitle.textContent = titles[type] || titles.info;
        popupMessage.textContent = message;
        popup.classList.add('active');

        // Close on button click
        popupBtn.onclick = () => popup.classList.remove('active');

        // Close on overlay click
        popup.onclick = (e) => {
            if (e.target === popup) popup.classList.remove('active');
        };

        // Close on Escape key
        document.addEventListener('keydown', function escHandler(e) {
            if (e.key === 'Escape') {
                popup.classList.remove('active');
                document.removeEventListener('keydown', escHandler);
            }
        });
    }


    // ==========================================================================
    // Create Dynamic Elements (Scroll Progress, Scroll Top Button)
    // ==========================================================================

    // Scroll Progress Bar
    const scrollProgress = document.createElement('div');
    scrollProgress.className = 'scroll-progress';
    document.body.appendChild(scrollProgress);

    // Scroll to Top Button
    const scrollTopBtn = document.createElement('button');
    scrollTopBtn.className = 'scroll-top';
    scrollTopBtn.setAttribute('aria-label', 'Scroll to top');
    scrollTopBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"></polyline></svg>';
    document.body.appendChild(scrollTopBtn);

    // Page Transition Overlay
    const pageTransition = document.createElement('div');
    pageTransition.className = 'page-transition';
    document.body.appendChild(pageTransition);

    // ==========================================================================
    // Header Scroll Effect (optimized to prevent forced reflow)
    // ==========================================================================
    const header = document.getElementById('header');
    let ticking = false;
    let lastScrollY = 0;
    let cachedDocHeight = 0;

    // Cache document height on load and resize
    function updateDocHeight() {
        cachedDocHeight = document.documentElement.scrollHeight - window.innerHeight;
    }
    updateDocHeight();
    window.addEventListener('resize', updateDocHeight);

    function handleScroll() {
        lastScrollY = window.scrollY;
        if (!ticking) {
            requestAnimationFrame(() => {
                if (lastScrollY > 50) {
                    header.classList.add('scrolled');
                } else {
                    header.classList.remove('scrolled');
                }
                if (cachedDocHeight > 0) {
                    scrollProgress.style.width = (lastScrollY / cachedDocHeight) * 100 + '%';
                }
                if (lastScrollY > 300) {
                    scrollTopBtn.classList.add('visible');
                } else {
                    scrollTopBtn.classList.remove('visible');
                }
                ticking = false;
            });
            ticking = true;
        }
    }

    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll();

    // Scroll to Top Button click handler
    scrollTopBtn.addEventListener('click', function() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });

    // ==========================================================================
    // Mobile Navigation Toggle - Bottom Sheet Style
    // ==========================================================================
    const navToggle = document.getElementById('navToggle');
    const navMenu = document.getElementById('navMenu');

    if (navToggle && navMenu) {
        navToggle.addEventListener('click', function() {
            navToggle.classList.toggle('active');
            navMenu.classList.toggle('active');
            document.body.classList.toggle('menu-open');
            document.body.style.overflow = navMenu.classList.contains('active') ? 'hidden' : '';
        });

        // Close menu when clicking on a link
        const navLinks = navMenu.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                closeMenu();
            });
        });

        // Close menu when clicking on overlay (outside menu)
        document.addEventListener('click', function(e) {
            if (navMenu.classList.contains('active') &&
                !navMenu.contains(e.target) &&
                !navToggle.contains(e.target)) {
                closeMenu();
            }
        });

        function closeMenu() {
            navToggle.classList.remove('active');
            navMenu.classList.remove('active');
            document.body.classList.remove('menu-open');
            document.body.style.overflow = '';
        }
    }

    // ==========================================================================
    // FAQ Accordion
    // ==========================================================================
    const faqItems = document.querySelectorAll('.faq-item');

    faqItems.forEach(item => {
        const question = item.querySelector('.faq-question');
        if (question) {
            question.addEventListener('click', function() {
                const isActive = item.classList.contains('active');

                // Close all other items
                faqItems.forEach(otherItem => {
                    otherItem.classList.remove('active');
                });

                // Toggle current item
                if (!isActive) {
                    item.classList.add('active');
                }
            });
        }
    });

    // ==========================================================================
    // Gallery Lightbox
    // ==========================================================================
    const lightbox = document.getElementById('lightbox');
    const lightboxImage = document.getElementById('lightboxImage');
    const lightboxClose = document.getElementById('lightboxClose');
    const galleryItems = document.querySelectorAll('.gallery-item');

    if (lightbox && lightboxImage && galleryItems.length > 0) {
        galleryItems.forEach(item => {
            item.addEventListener('click', function() {
                const imgSrc = this.getAttribute('data-src') || this.querySelector('img').src;
                lightboxImage.src = imgSrc;
                lightbox.classList.add('active');
                document.body.style.overflow = 'hidden';
            });
        });

        // Close lightbox
        if (lightboxClose) {
            lightboxClose.addEventListener('click', closeLightbox);
        }

        lightbox.addEventListener('click', function(e) {
            if (e.target === lightbox) {
                closeLightbox();
            }
        });

        // Close with escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && lightbox.classList.contains('active')) {
                closeLightbox();
            }
        });

        function closeLightbox() {
            lightbox.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    // ==========================================================================
    // Smooth Scroll for Anchor Links
    // ==========================================================================
    const anchorLinks = document.querySelectorAll('a[href^="#"]');

    anchorLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;

            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                e.preventDefault();
                const headerHeight = header.offsetHeight;
                const targetPosition = targetElement.offsetTop - headerHeight - 20;

                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });

    // ==========================================================================
    // Contact Form Handling
    // ==========================================================================
    const contactForm = document.getElementById('contactForm');

    if (contactForm) {
        contactForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const submitBtn = contactForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;

            // Get form data
            const formData = new FormData(contactForm);
            const data = {};
            formData.forEach((value, key) => {
                data[key] = typeof value === 'string' ? value.trim() : value;
            });

            // Basic validation
            if (!data.name || !data.phone || !data.message) {
                showPopup('Please fill in all required fields.', 'error');
                return;
            }

            // Show loading state
            submitBtn.textContent = 'Sending...';
            submitBtn.disabled = true;

            try {
                const response = await fetch('api/contact.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    showPopup(result.message, 'success');
                    contactForm.reset();
                } else {
                    showPopup(result.message || 'Something went wrong. Please try again.', 'error');
                }
            } catch (error) {
                console.error('Form error:', error);
                showPopup('Connection error. Please call us at +91 75102 03232', 'error');
            } finally {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            }
        });

        // Set minimum date for check-in to today
        const checkinInput = document.getElementById('checkin');
        const checkoutInput = document.getElementById('checkout');

        if (checkinInput && checkoutInput) {
            const today = new Date().toISOString().split('T')[0];
            checkinInput.setAttribute('min', today);

            checkinInput.addEventListener('change', function() {
                checkoutInput.setAttribute('min', this.value);
                if (checkoutInput.value && checkoutInput.value < this.value) {
                    checkoutInput.value = '';
                }
            });
        }
    }

    // ==========================================================================
    // Enhanced Scroll Animations (Intersection Observer)
    // ==========================================================================

    // Elements to animate on scroll
    const animateElements = document.querySelectorAll(
        '.section-header, .about-grid, .room-card, .feature-card, ' +
        '.testimonial-card, .attraction-item, .faq-item, .gallery-item, ' +
        '.stat-item, .footer-grid > div'
    );

    // Add animation classes to elements
    animateElements.forEach((el, index) => {
        if (!el.classList.contains('animate-on-scroll')) {
            el.classList.add('animate-on-scroll');
        }
    });

    // Stagger animation for grids
    const gridContainers = document.querySelectorAll('.grid, .gallery-grid, .about-stats');
    gridContainers.forEach(grid => {
        grid.classList.add('stagger-animation');
    });

    if ('IntersectionObserver' in window) {
        const animationObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animated');
                    animationObserver.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.15,
            rootMargin: '0px 0px -80px 0px'
        });

        // Observe all animated elements
        document.querySelectorAll('.animate-on-scroll, .stagger-animation').forEach(el => {
            animationObserver.observe(el);
        });
    } else {
        // Fallback for browsers without IntersectionObserver
        document.querySelectorAll('.animate-on-scroll, .stagger-animation').forEach(el => {
            el.classList.add('animated');
        });
    }

    // ==========================================================================
    // Lazy Loading Images
    // ==========================================================================
    if ('loading' in HTMLImageElement.prototype) {
        const images = document.querySelectorAll('img[loading="lazy"]');
        images.forEach(img => {
            img.src = img.src;
        });
    } else {
        // Fallback for browsers that don't support native lazy loading
        const lazyImages = document.querySelectorAll('img[loading="lazy"]');

        if ('IntersectionObserver' in window) {
            const lazyImageObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const lazyImage = entry.target;
                        lazyImage.src = lazyImage.dataset.src || lazyImage.src;
                        lazyImageObserver.unobserve(lazyImage);
                    }
                });
            });

            lazyImages.forEach(img => lazyImageObserver.observe(img));
        }
    }

    // ==========================================================================
    // Active Navigation Link
    // ==========================================================================
    const currentPage = window.location.pathname.split('/').pop() || 'index.html';
    const navLinksAll = document.querySelectorAll('.nav-link');

    navLinksAll.forEach(link => {
        const href = link.getAttribute('href');
        // Handle clean URLs (without .html)
        const cleanCurrentPage = currentPage.replace('.html', '');
        const cleanHref = href.replace('.html', '');
        if (cleanHref === cleanCurrentPage ||
            (cleanCurrentPage === '' && (cleanHref === 'index' || cleanHref === './' || href === './'))) {
            link.classList.add('active');
        }
    });

    // ==========================================================================
    // Page Transition Effect (for internal links)
    // ==========================================================================
    const internalLinks = document.querySelectorAll('a[href]:not([href^="http"]):not([href^="#"]):not([href^="tel"]):not([href^="mailto"]):not([href^="wa.me"])');

    internalLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');

            // Skip if it's a special link or same page
            if (!href || href === '#' || href.startsWith('javascript:')) return;

            e.preventDefault();

            // Trigger page transition
            pageTransition.classList.add('active');

            // Navigate after animation
            setTimeout(() => {
                window.location.href = href;
            }, 400);
        });
    });

    // ==========================================================================
    // Enhanced Image Loading with Fade-in
    // ==========================================================================
    const lazyImages = document.querySelectorAll('img[loading="lazy"]');

    lazyImages.forEach(img => {
        if (img.complete) {
            img.classList.add('loaded');
        } else {
            img.addEventListener('load', function() {
                this.classList.add('loaded');
            });
        }
    });

    // ==========================================================================
    // Counter Animation for Stats
    // ==========================================================================
    const statNumbers = document.querySelectorAll('.stat-number');

    if (statNumbers.length > 0 && 'IntersectionObserver' in window) {
        const counterObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const el = entry.target;
                    const text = el.textContent;
                    const number = parseInt(text.replace(/\D/g, ''));

                    if (!isNaN(number) && number > 0) {
                        animateCounter(el, number, text);
                    }
                    counterObserver.unobserve(el);
                }
            });
        }, { threshold: 0.5 });

        statNumbers.forEach(el => counterObserver.observe(el));
    }

    function animateCounter(element, target, originalText) {
        const duration = 1500;
        const start = 0;
        const startTime = performance.now();
        const suffix = originalText.replace(/[\d.,]/g, '');

        function update(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);

            // Easing function (ease out)
            const easeOut = 1 - Math.pow(1 - progress, 3);
            const current = Math.floor(start + (target - start) * easeOut);

            element.textContent = current + suffix;
            element.classList.add('counting');

            if (progress < 1) {
                requestAnimationFrame(update);
            } else {
                element.textContent = originalText;
                element.classList.remove('counting');
            }
        }

        requestAnimationFrame(update);
    }

});
