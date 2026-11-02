(function () {
    'use strict';

    var hideTimers = new WeakMap();

    function hideToast(toast) {
        if (!toast) {
            return;
        }
        toast.classList.remove('is-visible');
        toast.classList.add('is-hiding');
        window.setTimeout(function () {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 350);
    }

    function scheduleHide(toast) {
        var previous = hideTimers.get(toast);
        if (previous) {
            window.clearTimeout(previous);
        }
        var hideMs = parseInt(toast.getAttribute('data-autohide') || '3000', 10);
        hideTimers.set(toast, window.setTimeout(function () {
            hideToast(toast);
        }, hideMs));
    }

    function showWinsumToast(message, type) {
        var text = (message || '').trim();
        if (text === '') {
            return;
        }
        var kind = type === 'success' || type === 'error' ? type : 'info';
        document.querySelectorAll('.auth-toast').forEach(hideToast);

        var toast = document.createElement('div');
        toast.className = 'auth-toast auth-toast--' + kind;
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');
        toast.setAttribute('data-autohide', '3000');

        var icon = document.createElement('span');
        icon.className = 'auth-toast-icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = kind === 'success' ? '✓' : (kind === 'error' ? '!' : 'i');

        var body = document.createElement('p');
        body.className = 'auth-toast-text';
        body.textContent = text;

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'auth-toast-close';
        close.setAttribute('aria-label', 'Đóng thông báo');
        close.textContent = '×';
        close.addEventListener('click', function () {
            var timer = hideTimers.get(toast);
            if (timer) {
                window.clearTimeout(timer);
            }
            hideToast(toast);
        });

        toast.appendChild(icon);
        toast.appendChild(body);
        toast.appendChild(close);
        document.body.appendChild(toast);

        window.requestAnimationFrame(function () {
            toast.classList.add('is-visible');
            scheduleHide(toast);
        });
    }

    window.showWinsumToast = showWinsumToast;

    document.addEventListener('DOMContentLoaded', function () {
        var serverToast = document.getElementById('auth-toast');
        if (serverToast) {
            window.requestAnimationFrame(function () {
                serverToast.classList.add('is-visible');
                scheduleHide(serverToast);
            });
            var closeBtn = serverToast.querySelector('.auth-toast-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function () {
                    var timer = hideTimers.get(serverToast);
                    if (timer) {
                        window.clearTimeout(timer);
                    }
                    hideToast(serverToast);
                });
            }
        }
    });

    document.addEventListener('click', function (event) {
        var source = event.target.closest('[data-toast]');
        if (!source) {
            return;
        }
        var form = source.closest('form');
        var password = form ? form.querySelector('input[name="password"][minlength]') : null;
        if (password && source.type === 'submit' && password.value.length > 0 && password.value.length < 6) {
            event.preventDefault();
            showWinsumToast('Mật khẩu bao gồm ít nhất 6 ký tự.', 'info');
            return;
        }
        showWinsumToast(source.getAttribute('data-toast') || '', source.getAttribute('data-toast-type') || 'info');
    });

    document.addEventListener('click', function (event) {
        var submit = event.target.closest('form.auth-form button[type="submit"], form.auth-form .auth-submit');
        if (!submit || submit.hasAttribute('data-toast')) {
            return;
        }
        var form = submit.closest('form');
        var password = form ? form.querySelector('input[name="password"][minlength]') : null;
        if (!password || password.value.length === 0 || password.value.length >= 6) {
            return;
        }
        event.preventDefault();
        showWinsumToast('Mật khẩu bao gồm ít nhất 6 ký tự.', 'info');
    });
})();
