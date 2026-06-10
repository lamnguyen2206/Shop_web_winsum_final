(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var toast = document.getElementById('auth-toast');
        if (!toast) {
            return;
        }

        var hideMs = parseInt(toast.getAttribute('data-autohide') || '3000', 10);
        var hideTimer = null;

        function hideToast() {
            toast.classList.remove('is-visible');
            toast.classList.add('is-hiding');
            window.setTimeout(function () {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 350);
        }

        function scheduleHide() {
            if (hideTimer) {
                window.clearTimeout(hideTimer);
            }
            hideTimer = window.setTimeout(hideToast, hideMs);
        }

        requestAnimationFrame(function () {
            toast.classList.add('is-visible');
            scheduleHide();
        });

        var closeBtn = toast.querySelector('.auth-toast-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                if (hideTimer) {
                    window.clearTimeout(hideTimer);
                }
                hideToast();
            });
        }
    });
})();
