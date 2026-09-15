/**
 * NetFree Builder — Animations & Effects
 * Анимации появления, параллакс, sticky элементы, плавные переходы
 */
(function () {
    'use strict';

    // ============================================================
    // Конфигурация анимаций
    // ============================================================
    var ANIMATION_CONFIG = {
        fadeIn: {
            from: { opacity: 0, transform: 'translateY(30px)' },
            to: { opacity: 1, transform: 'translateY(0)' },
            duration: 600,
            easing: 'cubic-bezier(0.4, 0, 0.2, 1)'
        },
        fadeInLeft: {
            from: { opacity: 0, transform: 'translateX(-30px)' },
            to: { opacity: 1, transform: 'translateX(0)' },
            duration: 600,
            easing: 'cubic-bezier(0.4, 0, 0.2, 1)'
        },
        fadeInRight: {
            from: { opacity: 0, transform: 'translateX(30px)' },
            to: { opacity: 1, transform: 'translateX(0)' },
            duration: 600,
            easing: 'cubic-bezier(0.4, 0, 0.2, 1)'
        },
        scaleIn: {
            from: { opacity: 0, transform: 'scale(0.9)' },
            to: { opacity: 1, transform: 'scale(1)' },
            duration: 500,
            easing: 'cubic-bezier(0.34, 1.56, 0.64, 1)'
        },
        slideUp: {
            from: { opacity: 0, transform: 'translateY(50px)' },
            to: { opacity: 1, transform: 'translateY(0)' },
            duration: 700,
            easing: 'cubic-bezier(0.4, 0, 0.2, 1)'
        }
    };

    // ============================================================
    // Intersection Observer для анимаций появления
    // ============================================================
    var animationObserver = null;

    function setupScrollAnimations(iframeDoc) {
        if (!iframeDoc || !('IntersectionObserver' in window)) return;

        if (animationObserver) {
            animationObserver.disconnect();
        }

        animationObserver = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    var element = entry.target;
                    var animType = element.getAttribute('data-animation') || 'fadeIn';
                    var delay = parseInt(element.getAttribute('data-animation-delay') || '0', 10);

                    setTimeout(function() {
                        animateElement(element, animType);
                        animationObserver.unobserve(element);
                    }, delay);
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });

        // Найти все элементы с атрибутом data-animation
        var animatedElements = iframeDoc.querySelectorAll('[data-animation]');
        animatedElements.forEach(function(el) {
            // Скрыть элемент до анимации
            var animType = el.getAttribute('data-animation') || 'fadeIn';
            var config = ANIMATION_CONFIG[animType];
            if (config && config.from) {
                Object.keys(config.from).forEach(function(prop) {
                    el.style[prop] = config.from[prop];
                });
            }
            animationObserver.observe(el);
        });
    }

    function animateElement(element, animationType) {
        var config = ANIMATION_CONFIG[animationType];
        if (!config) return;

        element.style.transition = 'all ' + config.duration + 'ms ' + config.easing;

        Object.keys(config.to).forEach(function(prop) {
            element.style[prop] = config.to[prop];
        });

        // Убрать transition после завершения
        setTimeout(function() {
            element.style.transition = '';
        }, config.duration);
    }

    // ============================================================
    // Параллакс эффект
    // ============================================================
    var parallaxElements = [];

    function setupParallax(iframeDoc) {
        if (!iframeDoc) return;

        parallaxElements = [];
        var elements = iframeDoc.querySelectorAll('[data-parallax]');

        elements.forEach(function(el) {
            var speed = parseFloat(el.getAttribute('data-parallax-speed') || '0.5');
            parallaxElements.push({
                element: el,
                speed: speed,
                initialOffset: el.offsetTop
            });
        });

        if (parallaxElements.length > 0) {
            iframeDoc.addEventListener('scroll', throttleParallax);
        }
    }

    var lastParallaxTime = 0;
    function throttleParallax() {
        var now = Date.now();
        if (now - lastParallaxTime < 16) return; // ~60fps
        lastParallaxTime = now;

        requestAnimationFrame(updateParallax);
    }

    function updateParallax() {
        var iframe = document.getElementById('bCanvas');
        if (!iframe || !iframe.contentWindow) return;

        var scrollTop = iframe.contentWindow.pageYOffset || 0;

        parallaxElements.forEach(function(item) {
            var offset = (scrollTop - item.initialOffset) * item.speed;
            item.element.style.transform = 'translateY(' + offset + 'px)';
        });
    }

    // ============================================================
    // Sticky элементы
    // ============================================================
    function setupSticky(iframeDoc) {
        if (!iframeDoc) return;

        var stickyElements = iframeDoc.querySelectorAll('[data-sticky]');

        stickyElements.forEach(function(el) {
            var offset = parseInt(el.getAttribute('data-sticky-offset') || '0', 10);

            // Современный способ через CSS
            el.style.position = 'sticky';
            el.style.top = offset + 'px';
            el.style.zIndex = '10';
        });
    }

    // ============================================================
    // Плавные переходы между секциями
    // ============================================================
    function setupSmoothScroll(iframeDoc) {
        if (!iframeDoc) return;

        // CSS scroll behavior
        iframeDoc.documentElement.style.scrollBehavior = 'smooth';

        // Обработка якорных ссылок
        var anchorLinks = iframeDoc.querySelectorAll('a[href^="#"]');
        anchorLinks.forEach(function(link) {
            link.addEventListener('click', function(e) {
                var targetId = link.getAttribute('href').slice(1);
                if (!targetId) return;

                var target = iframeDoc.getElementById(targetId);
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }

    // ============================================================
    // Hover эффекты для карточек
    // ============================================================
    function setupHoverEffects(iframeDoc) {
        if (!iframeDoc) return;

        var cards = iframeDoc.querySelectorAll('.nf-card, .nf-widget[data-hover]');

        cards.forEach(function(card) {
            var hoverEffect = card.getAttribute('data-hover') || 'lift';

            card.style.transition = 'transform 0.3s ease, box-shadow 0.3s ease';

            card.addEventListener('mouseenter', function() {
                switch (hoverEffect) {
                    case 'lift':
                        card.style.transform = 'translateY(-5px)';
                        card.style.boxShadow = '0 10px 30px rgba(0,0,0,0.15)';
                        break;
                    case 'scale':
                        card.style.transform = 'scale(1.05)';
                        break;
                    case 'glow':
                        card.style.boxShadow = '0 0 20px rgba(59,130,246,0.5)';
                        break;
                }
            });

            card.addEventListener('mouseleave', function() {
                card.style.transform = '';
                card.style.boxShadow = '';
            });
        });
    }

    // ============================================================
    // Счётчики с анимацией
    // ============================================================
    function animateCounters(iframeDoc) {
        if (!iframeDoc) return;

        var counters = iframeDoc.querySelectorAll('.nf-counter-number');

        var counterObserver = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    var counter = entry.target;
                    animateCounter(counter);
                    counterObserver.unobserve(counter);
                }
            });
        }, { threshold: 0.5 });

        counters.forEach(function(counter) {
            counterObserver.observe(counter);
        });
    }

    function animateCounter(element) {
        var text = element.textContent;
        var match = text.match(/[\d,.\s]+/);
        if (!match) return;

        var targetStr = match[0].replace(/[\s,]/g, '');
        var target = parseFloat(targetStr);
        if (isNaN(target)) return;

        var prefix = text.split(match[0])[0];
        var suffix = text.split(match[0])[1] || '';

        var duration = 2000;
        var start = 0;
        var startTime = Date.now();

        function update() {
            var now = Date.now();
            var progress = Math.min((now - startTime) / duration, 1);

            // Easing function
            var eased = 1 - Math.pow(1 - progress, 3);
            var current = Math.floor(start + (target - start) * eased);

            // Форматирование с пробелами
            var formatted = current.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            element.textContent = prefix + formatted + suffix;

            if (progress < 1) {
                requestAnimationFrame(update);
            } else {
                element.textContent = text; // Вернуть оригинал
            }
        }

        update();
    }

    // ============================================================
    // Прогресс-бары с анимацией заполнения
    // ============================================================
    function animateProgressBars(iframeDoc) {
        if (!iframeDoc) return;

        var progressBars = iframeDoc.querySelectorAll('.nf-progress-fill');

        var progressObserver = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    var bar = entry.target;
                    var targetWidth = bar.style.width;

                    bar.style.width = '0%';
                    bar.style.transition = 'width 1.5s cubic-bezier(0.4, 0, 0.2, 1)';

                    setTimeout(function() {
                        bar.style.width = targetWidth;
                    }, 100);

                    progressObserver.unobserve(bar);
                }
            });
        }, { threshold: 0.3 });

        progressBars.forEach(function(bar) {
            progressObserver.observe(bar);
        });
    }

    // ============================================================
    // Typing эффект для текста
    // ============================================================
    function setupTypingEffect(iframeDoc) {
        if (!iframeDoc) return;

        var typingElements = iframeDoc.querySelectorAll('[data-typing]');

        typingElements.forEach(function(el) {
            var text = el.textContent;
            var speed = parseInt(el.getAttribute('data-typing-speed') || '50', 10);

            el.textContent = '';
            el.style.borderRight = '2px solid currentColor';
            el.style.paddingRight = '5px';

            var i = 0;
            function type() {
                if (i < text.length) {
                    el.textContent += text.charAt(i);
                    i++;
                    setTimeout(type, speed);
                } else {
                    // Убрать курсор после завершения
                    setTimeout(function() {
                        el.style.borderRight = 'none';
                    }, 500);
                }
            }

            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        type();
                        observer.unobserve(el);
                    }
                });
            }, { threshold: 0.5 });

            observer.observe(el);
        });
    }

    // ============================================================
    // Ripple эффект для кнопок
    // ============================================================
    function setupRippleEffect(iframeDoc) {
        if (!iframeDoc) return;

        var buttons = iframeDoc.querySelectorAll('.nf-btn, [data-ripple]');

        buttons.forEach(function(btn) {
            btn.style.position = 'relative';
            btn.style.overflow = 'hidden';

            btn.addEventListener('click', function(e) {
                var ripple = iframeDoc.createElement('span');
                var rect = btn.getBoundingClientRect();
                var size = Math.max(rect.width, rect.height);
                var x = e.clientX - rect.left - size / 2;
                var y = e.clientY - rect.top - size / 2;

                ripple.style.cssText = 'position:absolute;border-radius:50%;background:rgba(255,255,255,0.6);' +
                    'width:' + size + 'px;height:' + size + 'px;' +
                    'left:' + x + 'px;top:' + y + 'px;' +
                    'transform:scale(0);animation:nf-ripple 0.6s ease-out;pointer-events:none;';

                btn.appendChild(ripple);

                setTimeout(function() {
                    ripple.remove();
                }, 600);
            });
        });

        // Добавить CSS анимацию
        var style = iframeDoc.getElementById('nf-animations');
        if (!style) {
            style = iframeDoc.createElement('style');
            style.id = 'nf-animations';
            style.textContent = '@keyframes nf-ripple{to{transform:scale(4);opacity:0;}}';
            iframeDoc.head.appendChild(style);
        }
    }

    // ============================================================
    // Инициализация всех эффектов
    // ============================================================
    function initAllEffects() {
        var iframe = document.getElementById('bCanvas');
        if (!iframe) return;

        iframe.addEventListener('load', function() {
            var iframeDoc = iframe.contentDocument;
            if (!iframeDoc) return;

            setTimeout(function() {
                setupScrollAnimations(iframeDoc);
                setupParallax(iframeDoc);
                setupSticky(iframeDoc);
                setupSmoothScroll(iframeDoc);
                setupHoverEffects(iframeDoc);
                animateCounters(iframeDoc);
                animateProgressBars(iframeDoc);
                setupTypingEffect(iframeDoc);
                setupRippleEffect(iframeDoc);

                console.log('[Animations] All effects initialized');
            }, 200);
        });
    }

    // ============================================================
    // Публичный API
    // ============================================================
    window.NFAnimations = {
        init: initAllEffects,
        setupScrollAnimations: setupScrollAnimations,
        setupParallax: setupParallax,
        animateElement: animateElement,
        animateCounters: animateCounters,
        config: ANIMATION_CONFIG
    };

    // Автоматический запуск
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAllEffects);
    } else {
        initAllEffects();
    }

    console.log('[Animations] Module loaded');
})();
