(function () {
    var modal = document.getElementById('coupon-modal');
    if (!modal) {
        return;
    }

    var panel = modal.querySelector('[data-coupon-panel]');
    var openTriggers = document.querySelectorAll('[data-coupon-modal-open]');
    var closeTriggers = modal.querySelectorAll('[data-coupon-modal-close]');
    var tabButtons = panel ? panel.querySelectorAll('[data-coupon-tab]') : [];
    var cards = panel ? panel.querySelectorAll('li[data-coupon-category]') : [];
    var emptyMsg = panel ? panel.querySelector('[data-coupon-empty]') : null;
    var lastFocus = null;

    function filterTab(tab) {
        if (!panel) {
            return;
        }

        var visible = 0;
        cards.forEach(function (card) {
            var category = card.getAttribute('data-coupon-category') || '';
            var show = tab === 'all' || category === tab;
            card.hidden = !show;
            card.classList.toggle('is-tab-hidden', !show);
            if (show) {
                visible += 1;
            }
        });

        if (emptyMsg) {
            emptyMsg.hidden = visible > 0;
            emptyMsg.classList.toggle('is-hidden', visible > 0);
        }
    }

    function openModal() {
        lastFocus = document.activeElement;
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('coupon-modal-open');

        var activeTab = panel.querySelector('[data-coupon-tab].is-active');
        filterTab(activeTab ? activeTab.getAttribute('data-coupon-tab') || 'all' : 'all');

        var closeBtn = modal.querySelector('.coupon-modal__close');
        if (closeBtn) {
            closeBtn.focus();
        }
    }

    function closeModal() {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('coupon-modal-open');
        if (lastFocus && typeof lastFocus.focus === 'function') {
            lastFocus.focus();
        }
    }

    openTriggers.forEach(function (btn) {
        btn.addEventListener('click', openModal);
    });

    closeTriggers.forEach(function (el) {
        el.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });

    tabButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var tab = btn.getAttribute('data-coupon-tab') || 'all';
            tabButtons.forEach(function (other) {
                var active = other === btn;
                other.classList.toggle('is-active', active);
                other.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            filterTab(tab);
        });
    });

    filterTab('all');
})();
