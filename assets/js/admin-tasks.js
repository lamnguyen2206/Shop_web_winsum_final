(function () {
    'use strict';

    var root = document.getElementById('admin-tasks-root');
    if (!root) {
        return;
    }

    var apiUrl = root.getAttribute('data-api-url') || 'api/admin-tasks.php';
    var csrfToken = root.getAttribute('data-csrf-token') || '';
    var pollMs = 60000;

    function postTaskAction(body) {
        var payload = Object.assign({ csrf_token: csrfToken }, body);
        return fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        }).then(function (res) {
            return res.json();
        });
    }

    function applyPayload(data) {
        if (!data || !data.ok) {
            return;
        }

        var banner = document.getElementById('admin-task-banner');
        if (banner) {
            if (!data.banner_visible) {
                banner.remove();
            }
        }
    }

    function refreshTasks() {
        fetch(apiUrl, { credentials: 'same-origin', cache: 'no-store' })
            .then(function (res) { return res.json(); })
            .then(applyPayload)
            .catch(function () { /* ignore poll errors */ });
    }

    document.addEventListener('click', function (event) {
        var dismissBtn = event.target.closest('[data-admin-task-dismiss]');
        if (dismissBtn) {
            event.preventDefault();
            postTaskAction({
                action: 'dismiss_banner',
                fingerprint: dismissBtn.getAttribute('data-fingerprint') || '',
            }).then(function (data) {
                applyPayload(data);
                var banner = document.getElementById('admin-task-banner');
                if (banner) {
                    banner.remove();
                }
            });
            return;
        }

        var markBtn = event.target.closest('[data-admin-task-mark]');
        if (markBtn) {
            event.preventDefault();
            postTaskAction({
                action: 'mark_read',
                task_key: markBtn.getAttribute('data-task-key') || '',
                task_count: parseInt(markBtn.getAttribute('data-task-count') || '0', 10),
            }).then(function (data) {
                applyPayload(data);
                var item = markBtn.closest('.admin-task-banner-item');
                if (item) {
                    item.remove();
                }
                var banner = document.getElementById('admin-task-banner');
                if (banner && banner.querySelectorAll('.admin-task-banner-item').length === 0) {
                    banner.remove();
                }
            });
        }
    });

    window.setInterval(refreshTasks, pollMs);
})();
