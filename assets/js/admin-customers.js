/**
 * Handlers quản lý khách hàng (admin).
 */
(function () {
    'use strict';

    const cfg = window.adminCustomersConfig || {};
    const listBase = cfg.listUrl || 'index.php?view=admin-customers';

    function buildDetailUrl(id) {
        const joiner = listBase.indexOf('?') >= 0 ? '&' : '?';
        return listBase + joiner + 'id=' + encodeURIComponent(String(id));
    }

    function submitPost(action, customerId) {
        const form = document.getElementById('admin-customer-action-form');
        if (!form) {
            return;
        }
        const actionInput = form.querySelector('[name="action"]');
        const idInput = form.querySelector('[name="customer_id"]');
        if (!actionInput || !idInput) {
            return;
        }
        actionInput.value = action;
        idInput.value = String(customerId);
        form.submit();
    }

    window.AdminCustomers = {
        onView(customerId) {
            window.location.href = buildDetailUrl(customerId);
        },

        onEdit(customerId) {
            window.location.href = buildDetailUrl(customerId) + '#customer-edit';
        },

        onToggleBlock(customerId) {
            submitPost('toggle_customer_block', customerId);
        },
    };

    document.addEventListener('DOMContentLoaded', function () {
        if (window.location.hash === '#customer-edit') {
            const crud = document.getElementById('customer-edit');
            if (crud) {
                crud.scrollIntoView({ behavior: 'smooth', block: 'start' });
                const focusEl = crud.querySelector('select, input, button');
                if (focusEl) {
                    focusEl.focus();
                }
            }
        }
    });
})();
