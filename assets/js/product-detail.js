document.addEventListener('DOMContentLoaded', function () {
    var root = document.querySelector('[data-product-detail]');
    if (!root) {
        return;
    }

    var mainImage = root.querySelector('[data-main-image]');
    if (mainImage) {
        root.querySelectorAll('[data-thumb]').forEach(function (btn) {
            btn.setAttribute('aria-pressed', btn.classList.contains('is-active') ? 'true' : 'false');
            btn.addEventListener('click', function () {
                var url = btn.getAttribute('data-thumb');
                if (!url) {
                    return;
                }
                var thumbImg = btn.querySelector('img');
                mainImage.src = url;
                if (thumbImg && thumbImg.getAttribute('alt')) {
                    mainImage.alt = thumbImg.getAttribute('alt');
                }
                root.querySelectorAll('.thumb-btn').forEach(function (el) {
                    el.classList.remove('is-active');
                    el.setAttribute('aria-pressed', 'false');
                });
                btn.classList.add('is-active');
                btn.setAttribute('aria-pressed', 'true');
            });
        });
    }

    var tabsRoot = root.querySelector('[data-product-tabs]');
    if (!tabsRoot) {
        return;
    }

    var tabButtons = tabsRoot.querySelectorAll('.product-tabs-nav [role="tab"]');
    var panels = tabsRoot.querySelectorAll('.product-tab-panel');

    function activateTab(button) {
        var target = button.getAttribute('data-tab');
        if (!target) {
            return;
        }
        tabButtons.forEach(function (b) {
            var on = b === button;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
            b.setAttribute('tabindex', on ? '0' : '-1');
        });
        panels.forEach(function (panel) {
            var isMatch = panel.getAttribute('data-panel') === target;
            panel.classList.toggle('is-active', isMatch);
            panel.hidden = !isMatch;
        });
    }

    tabButtons.forEach(function (button, index) {
        if (!button.hasAttribute('aria-selected')) {
            button.setAttribute(
                'aria-selected',
                button.classList.contains('is-active') ? 'true' : 'false'
            );
        }
        if (!button.hasAttribute('tabindex')) {
            button.setAttribute('tabindex', button.classList.contains('is-active') ? '0' : '-1');
        }
        button.addEventListener('click', function () {
            activateTab(button);
        });
        button.addEventListener('keydown', function (e) {
            var key = e.key;
            if (key !== 'ArrowRight' && key !== 'ArrowLeft' && key !== 'Home' && key !== 'End') {
                return;
            }
            e.preventDefault();
            var count = tabButtons.length;
            var next = index;
            if (key === 'ArrowRight') {
                next = (index + 1) % count;
            } else if (key === 'ArrowLeft') {
                next = (index - 1 + count) % count;
            } else if (key === 'Home') {
                next = 0;
            } else if (key === 'End') {
                next = count - 1;
            }
            activateTab(tabButtons[next]);
            tabButtons[next].focus();
        });
    });

    function openReviewsFromHash() {
        if (window.location.hash !== '#product-reviews') {
            return;
        }
        var reviewsTab = tabsRoot.querySelector('[data-tab="reviews"]');
        if (!reviewsTab) {
            return;
        }
        activateTab(reviewsTab);
        requestAnimationFrame(function () {
            var targetEl = document.getElementById('product-reviews');
            if (targetEl) {
                targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    }

    openReviewsFromHash();
    window.addEventListener('hashchange', openReviewsFromHash);
});
