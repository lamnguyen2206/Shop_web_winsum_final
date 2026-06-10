(function () {
    'use strict';

    var shippingSelect = document.querySelector('[data-checkout-shipping]');
    var summary = document.querySelector('[data-checkout-summary]');
    if (!shippingSelect || !summary) {
        return;
    }

    var apiUrl = summary.getAttribute('data-totals-api') || 'api/checkout-totals.php';
    var subtotalEl = summary.querySelector('[data-checkout-subtotal]');
    var shippingEl = summary.querySelector('[data-checkout-shipping]');
    var discountEl = summary.querySelector('[data-checkout-discount]');
    var totalEl = summary.querySelector('[data-checkout-total]');
    var debounceTimer = null;
    var abortController = null;

    function formatVnd(amount) {
        return Number(amount).toLocaleString('vi-VN') + 'đ';
    }

    function applyTotals(data) {
        if (!data || !data.ok) {
            return;
        }
        if (subtotalEl) {
            subtotalEl.textContent = formatVnd(data.subtotal);
        }
        if (shippingEl) {
            shippingEl.textContent = formatVnd(data.shipping);
        }
        if (discountEl) {
            discountEl.textContent = formatVnd(data.discount);
        }
        if (totalEl) {
            totalEl.textContent = formatVnd(data.total);
        }
    }

    function fetchTotals(shippingMethodId) {
        if (abortController) {
            abortController.abort();
        }
        abortController = new AbortController();

        var url = apiUrl + '?shipping_method_id=' + encodeURIComponent(String(shippingMethodId));

        fetch(url, { signal: abortController.signal, headers: { Accept: 'application/json' } })
            .then(function (res) {
                return res.json();
            })
            .then(applyTotals)
            .catch(function (err) {
                if (err.name === 'AbortError') {
                    return;
                }
                var option = shippingSelect.options[shippingSelect.selectedIndex];
                if (!option) {
                    return;
                }
                var fee = Number(option.getAttribute('data-shipping-fee') || 0);
                var subtotalText = subtotalEl ? subtotalEl.textContent.replace(/\D/g, '') : '0';
                var discountText = discountEl ? discountEl.textContent.replace(/\D/g, '') : '0';
                var subtotal = Number(subtotalText) || 0;
                var discount = Number(discountText) || 0;
                if (shippingEl) {
                    shippingEl.textContent = formatVnd(fee);
                }
                if (totalEl) {
                    totalEl.textContent = formatVnd(Math.max(0, subtotal + fee - discount));
                }
            });
    }

    shippingSelect.addEventListener('change', function () {
        var id = Number(shippingSelect.value);
        if (!id) {
            return;
        }
        window.clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(function () {
            fetchTotals(id);
        }, 120);
    });
})();
