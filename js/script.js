/**
 * Spezio Apartments - Main JavaScript
 * Clean, modern interactions for the apartment website
 */

document.addEventListener('DOMContentLoaded', function() {

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
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();

            // Get form data
            const formData = new FormData(contactForm);
            const data = Object.fromEntries(formData.entries());

            // Basic validation
            if (!data.name || !data.email || !data.message) {
                alert('Please fill in all required fields.');
                return;
            }

            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(data.email)) {
                alert('Please enter a valid email address.');
                return;
            }

            // Create WhatsApp message
            let whatsappMessage = `Hello Spezio Apartments!\n\n`;
            whatsappMessage += `Name: ${data.name}\n`;
            whatsappMessage += `Email: ${data.email}\n`;
            if (data.phone) whatsappMessage += `Phone: ${data.phone}\n`;
            if (data.roomType) whatsappMessage += `Room Type: ${data.roomType}\n`;
            if (data.checkin) whatsappMessage += `Check-in: ${data.checkin}\n`;
            if (data.checkout) whatsappMessage += `Check-out: ${data.checkout}\n`;
            whatsappMessage += `\nMessage:\n${data.message}`;

            // Encode message for URL
            const encodedMessage = encodeURIComponent(whatsappMessage);

            // Open WhatsApp with pre-filled message
            window.open(`https://wa.me/919876543210?text=${encodedMessage}`, '_blank');

            // Show success message
            alert('Thank you for your message! You will be redirected to WhatsApp to complete your inquiry.');

            // Reset form
            contactForm.reset();
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
