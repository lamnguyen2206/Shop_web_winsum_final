document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = btn.parentElement.querySelector('input');
            if (!input) {
                return;
            }
            var isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            btn.setAttribute('aria-label', isHidden ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
            btn.classList.toggle('is-visible', isHidden);
        });
    });

    var scrim = document.getElementById('auth-modal-scrim');
    var wrap = document.querySelector('.nav-account-wrap');
    var trigger = document.getElementById('nav-account-btn');
    var menu = document.getElementById('nav-account-menu');

    function closeNavDropdown() {
        if (!menu || !trigger) {
            return;
        }
        menu.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
    }

    function toggleNavDropdown() {
        if (!menu || !trigger) {
            return;
        }
        var open = menu.hidden;
        menu.hidden = !open;
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    function setModalOpen(open) {
        if (!scrim) {
            return;
        }
        scrim.hidden = !open;
        document.body.classList.toggle('auth-modal-open', open);
    }

    function showAuthPanel(which) {
        which = which === 'register' ? 'register' : 'login';
        document.querySelectorAll('[data-auth-panel]').forEach(function (p) {
            var id = p.getAttribute('data-auth-panel');
            p.hidden = id !== which;
        });
        var dlg = document.querySelector('.auth-modal-content');
        if (dlg) {
            dlg.setAttribute('aria-labelledby', which === 'register' ? 'auth-register-title' : 'auth-login-title');
        }
    }

    function openAuthModal(which) {
        if (!scrim) {
            return;
        }
        showAuthPanel(which || 'login');
        setModalOpen(true);
        closeNavDropdown();
        window.setTimeout(function () {
            var focusSel =
                which === 'register' ? 'input[name="phone"]' : '#auth-login-identifier';
            var el = scrim.querySelector(focusSel);
            if (el) {
                el.focus();
            }
        }, 30);
    }

    function closeAuthModal() {
        setModalOpen(false);
    }

    if (scrim) {
        scrim.querySelectorAll('[data-auth-close]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                closeAuthModal();
            });
        });
        document.querySelectorAll('[data-auth-switch]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                showAuthPanel(btn.getAttribute('data-auth-switch'));
            });
        });
    }

    document.querySelectorAll('[data-open-auth]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openAuthModal(btn.getAttribute('data-open-auth'));
        });
    });

    if (trigger && menu && wrap) {
        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            toggleNavDropdown();
        });
        document.addEventListener('click', function (e) {
            if (!wrap.contains(e.target)) {
                closeNavDropdown();
            }
        });
    }

    var initial = document.body.getAttribute('data-auth-open');
    if (initial && scrim) {
        openAuthModal(initial);
        document.body.removeAttribute('data-auth-open');
    }

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') {
            return;
        }
        if (scrim && !scrim.hidden) {
            closeAuthModal();
        }
        closeNavDropdown();
    });
});
