document.addEventListener('DOMContentLoaded', function() {
    var navCategorySelect = document.querySelector('[data-nav-category-select]');
    if (navCategorySelect) {
        var catalogBaseUrl = navCategorySelect.getAttribute('data-catalog-url') || 'index.php?view=catalog';

        navCategorySelect.addEventListener('change', function() {
            var slug = navCategorySelect.value;
            var target = catalogBaseUrl;
            if (slug !== '') {
                var joiner = catalogBaseUrl.indexOf('?') >= 0 ? '&' : '?';
                target = catalogBaseUrl + joiner + 'category=' + encodeURIComponent(slug);
            }
            navCategorySelect.value = '';
            window.location.href = target;
        });
    }

    function formatVnd(value) {
        return Number(value).toLocaleString('vi-VN') + 'đ';
    }

    function updateCartTotals(cartRoot) {
        var itemRows = cartRoot.querySelectorAll('[data-cart-item]');
        var subtotal = 0;

        itemRows.forEach(function(row) {
            var priceEl = row.querySelector('[data-price]');
            var qtyInput = row.querySelector('[data-qty-input]');
            var lineTotalEl = row.querySelector('[data-line-total]');

            var price = Number(priceEl.getAttribute('data-price'));
            var qty = Math.max(1, Number(qtyInput.value) || 1);
            qtyInput.value = String(qty);

            var lineTotal = price * qty;
            subtotal += lineTotal;
            lineTotalEl.textContent = formatVnd(lineTotal);
        });

        var shipping = 0;
        var discount = 0;
        var total = subtotal + shipping - discount;

        cartRoot.querySelector('[data-summary="subtotal"]').textContent = formatVnd(subtotal);
        cartRoot.querySelector('[data-summary="shipping"]').textContent = formatVnd(shipping);
        cartRoot.querySelector('[data-summary="discount"]').textContent = formatVnd(discount);
        cartRoot.querySelector('[data-summary="total"]').textContent = formatVnd(total);
    }

    var cart = document.querySelector('[data-cart]');
    if (!cart) {
        return;
    }

    cart.addEventListener('click', function(event) {
        var button = event.target.closest('[data-qty-btn]');
        if (!button) {
            return;
        }

        var row = button.closest('[data-cart-item]');
        var input = row.querySelector('[data-qty-input]');
        var currentValue = Math.max(1, Number(input.value) || 1);
        var type = button.getAttribute('data-qty-btn');
        input.value = String(type === 'plus' ? currentValue + 1 : Math.max(1, currentValue - 1));
        updateCartTotals(cart);
    });

    cart.addEventListener('input', function(event) {
        if (event.target.matches('[data-qty-input]')) {
            updateCartTotals(cart);
        }
    });

    updateCartTotals(cart);
});
