/**
 * NetFree Builder — Performance Optimizations
 * Ленивая загрузка, кеширование, debounce, оптимизация рендеринга
 */
(function () {
    'use strict';

    // ============================================================
    // Ленивая загрузка изображений (Intersection Observer)
    // ============================================================
    function setupLazyLoading() {
        if (!('IntersectionObserver' in window)) {
            return; // Fallback для старых браузеров
        }

        var iframe = document.getElementById('bCanvas');
        if (!iframe || !iframe.contentDocument) return;

        var iframeDoc = iframe.contentDocument;

        var lazyObserver = new IntersectionObserver(function(entries, observer) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    var img = entry.target;
                    var src = img.getAttribute('data-src');
                    if (src) {
                        img.src = src;
                        img.removeAttribute('data-src');
                        img.classList.remove('lazy');
                        observer.unobserve(img);
                    }
                }
            });
        }, {
            rootMargin: '50px' // Загружать за 50px до появления в viewport
        });

        // Наблюдать за всеми изображениями с data-src
        var lazyImages = iframeDoc.querySelectorAll('img.lazy, img[data-src]');
        lazyImages.forEach(function(img) {
            lazyObserver.observe(img);
        });

        // Повторно проверять при изменении DOM
        var mutationObserver = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.tagName === 'IMG' && (node.classList.contains('lazy') || node.hasAttribute('data-src'))) {
                        lazyObserver.observe(node);
                    }
                    if (node.querySelectorAll) {
                        var imgs = node.querySelectorAll('img.lazy, img[data-src]');
                        imgs.forEach(function(img) { lazyObserver.observe(img); });
                    }
                });
            });
        });

        mutationObserver.observe(iframeDoc.body, {
            childList: true,
            subtree: true
        });
    }

    // ============================================================
    // Кеширование рендеринга секций
    // ============================================================
    var renderCache = {
        data: {},
        maxSize: 50,

        key: function(section) {
            return JSON.stringify(section);
        },

        get: function(section) {
            var k = this.key(section);
            return this.data[k] || null;
        },

        set: function(section, html, css) {
            var k = this.key(section);
            this.data[k] = { html: html, css: css, timestamp: Date.now() };

            // Очистка старого кеша
            var keys = Object.keys(this.data);
            if (keys.length > this.maxSize) {
                var oldest = keys.reduce(function(a, b) {
                    return this.data[a].timestamp < this.data[b].timestamp ? a : b;
                }.bind(this));
                delete this.data[oldest];
            }
        },

        clear: function() {
            this.data = {};
        },

        invalidateSection: function(sectionId) {
            Object.keys(this.data).forEach(function(key) {
                if (key.indexOf('"id":"' + sectionId + '"') !== -1) {
                    delete this.data[key];
                }
            }.bind(this));
        }
    };

    // Обёртка для серверного рендеринга с кешированием
    function renderSectionCached(section, callback) {
        var cached = renderCache.get(section);
        if (cached) {
            console.log('[Cache HIT] Section:', section.id);
            callback(cached.html, cached.css);
            return;
        }

        console.log('[Cache MISS] Section:', section.id);
        // Вызов оригинального серверного рендеринга
        if (window.NFBuilder && window.NFBuilder.renderSection) {
            window.NFBuilder.renderSection(section, function(html, css) {
                renderCache.set(section, html, css);
                callback(html, css);
            });
        }
    }

    // ============================================================
    // Улучшенный debounce для частых операций
    // ============================================================
    function smartDebounce(fn, wait, options) {
        var timeout;
        var immediate = options && options.immediate;
        var maxWait = options && options.maxWait;
        var lastCallTime;

        return function() {
            var context = this;
            var args = arguments;
            var now = Date.now();

            var later = function() {
                timeout = null;
                if (!immediate) {
                    fn.apply(context, args);
                }
            };

            var callNow = immediate && !timeout;

            clearTimeout(timeout);

            // Принудительный вызов если прошло maxWait
            if (maxWait && lastCallTime && (now - lastCallTime) >= maxWait) {
                fn.apply(context, args);
                lastCallTime = now;
            } else {
                timeout = setTimeout(later, wait);
                lastCallTime = now;
            }

            if (callNow) {
                fn.apply(context, args);
            }
        };
    }

    // ============================================================
    // Оптимизация live CSS обновлений
    // ============================================================
    var cssUpdateQueue = [];
    var cssUpdateScheduled = false;

    function queueCSSUpdate(selector, props) {
        cssUpdateQueue.push({ selector: selector, props: props });

        if (!cssUpdateScheduled) {
            cssUpdateScheduled = true;
            requestAnimationFrame(function() {
                flushCSSUpdates();
                cssUpdateScheduled = false;
            });
        }
    }

    function flushCSSUpdates() {
        if (cssUpdateQueue.length === 0) return;

        var iframe = document.getElementById('bCanvas');
        if (!iframe || !iframe.contentDocument) return;

        var style = iframe.contentDocument.getElementById('nf-live');
        if (!style) {
            style = iframe.contentDocument.createElement('style');
            style.id = 'nf-live';
            iframe.contentDocument.head.appendChild(style);
        }

        var css = '';
        cssUpdateQueue.forEach(function(update) {
            var decls = '';
            Object.keys(update.props).forEach(function(key) {
                decls += key + ':' + update.props[key] + ';';
            });
            css += update.selector + '{' + decls + '}';
        });

        style.textContent += css;
        cssUpdateQueue = [];
    }

    // ============================================================
    // Виртуализация длинных списков в структуре
    // ============================================================
    function virtualizeStructureTree() {
        var structurePanel = document.getElementById('bStructure');
        if (!structurePanel) return;

        var items = structurePanel.querySelectorAll('.b-tree-item');
        if (items.length < 50) return; // Виртуализация только для длинных списков

        var ITEM_HEIGHT = 32;
        var VISIBLE_COUNT = Math.ceil(structurePanel.clientHeight / ITEM_HEIGHT) + 5;
        var scrollTop = structurePanel.scrollTop;
        var startIndex = Math.floor(scrollTop / ITEM_HEIGHT);
        var endIndex = Math.min(startIndex + VISIBLE_COUNT, items.length);

        items.forEach(function(item, index) {
            if (index >= startIndex && index < endIndex) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    }

    // ============================================================
    // Throttle для scroll событий
    // ============================================================
    function throttle(fn, wait) {
        var lastTime = 0;
        return function() {
            var now = Date.now();
            if (now - lastTime >= wait) {
                lastTime = now;
                fn.apply(this, arguments);
            }
        };
    }

    // ============================================================
    // Предзагрузка изображений из медиабиблиотеки
    // ============================================================
    var imagePreloadCache = new Map();

    function preloadImage(url) {
        if (imagePreloadCache.has(url)) {
            return imagePreloadCache.get(url);
        }

        var promise = new Promise(function(resolve, reject) {
            var img = new Image();
            img.onload = function() { resolve(img); };
            img.onerror = reject;
            img.src = url;
        });

        imagePreloadCache.set(url, promise);
        return promise;
    }

    // ============================================================
    // Минификация CSS на лету
    // ============================================================
    function minifyCSS(css) {
        return css
            .replace(/\/\*[\s\S]*?\*\//g, '') // Удалить комментарии
            .replace(/\s+/g, ' ')              // Схлопнуть пробелы
            .replace(/\s*([{}:;,])\s*/g, '$1') // Убрать пробелы вокруг символов
            .replace(/;}/g, '}')               // Убрать последнюю точку с запятой
            .trim();
    }

    // ============================================================
    // Батчинг DOM операций
    // ============================================================
    var domBatch = {
        reads: [],
        writes: [],
        scheduled: false,

        read: function(fn) {
            this.reads.push(fn);
            this.schedule();
        },

        write: function(fn) {
            this.writes.push(fn);
            this.schedule();
        },

        schedule: function() {
            if (this.scheduled) return;
            this.scheduled = true;

            requestAnimationFrame(function() {
                // Сначала все чтения (layout thrashing prevention)
                this.reads.forEach(function(fn) { fn(); });
                this.reads = [];

                // Потом все записи
                this.writes.forEach(function(fn) { fn(); });
                this.writes = [];

                this.scheduled = false;
            }.bind(this));
        }
    };

    // ============================================================
    // Мониторинг производительности
    // ============================================================
    var perfMonitor = {
        marks: {},

        start: function(label) {
            this.marks[label] = performance.now();
        },

        end: function(label) {
            if (!this.marks[label]) return;
            var duration = performance.now() - this.marks[label];
            if (duration > 100) {
                console.warn('[Performance] ' + label + ' took ' + duration.toFixed(2) + 'ms');
            }
            delete this.marks[label];
            return duration;
        },

        measure: function(label, fn) {
            this.start(label);
            var result = fn();
            this.end(label);
            return result;
        }
    };

    // ============================================================
    // Инициализация
    // ============================================================
    function init() {
        console.log('[Performance] Initializing optimizations...');

        // Ленивая загрузка изображений
        var iframe = document.getElementById('bCanvas');
        if (iframe) {
            iframe.addEventListener('load', function() {
                setTimeout(setupLazyLoading, 100);
            });
        }

        // Виртуализация структуры при скролле
        var structurePanel = document.getElementById('bStructure');
        if (structurePanel) {
            structurePanel.addEventListener('scroll', throttle(virtualizeStructureTree, 100));
        }

        // Debounce для частых событий
        if (window.NFBuilder) {
            var originalRefreshCSS = window.NFBuilder.refreshLiveCSS;
            if (originalRefreshCSS) {
                window.NFBuilder.refreshLiveCSS = smartDebounce(originalRefreshCSS, 150, { maxWait: 500 });
            }
        }

        // Экспорт утилит
        window.NFPerf = {
            renderCache: renderCache,
            preloadImage: preloadImage,
            minifyCSS: minifyCSS,
            domBatch: domBatch,
            perfMonitor: perfMonitor,
            queueCSSUpdate: queueCSSUpdate
        };

        console.log('[Performance] Optimizations loaded');
    }

    // Запуск после загрузки DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
