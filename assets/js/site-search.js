(function () {
    'use strict';

    var overlay = document.getElementById('site-search-overlay');
    if (!overlay) {
        return;
    }

    var openBtn = document.getElementById('site-search-open');
    var input = overlay.querySelector('[data-site-search-input]');
    var clearBtn = overlay.querySelector('[data-site-search-clear]');
    var form = overlay.querySelector('[data-site-search-form]');
    var dropdown = overlay.querySelector('[data-site-search-dropdown]');
    var suggestionsEl = overlay.querySelector('[data-site-search-suggestions]');
    var productsWrap = overlay.querySelector('[data-site-search-products]');
    var productList = overlay.querySelector('[data-site-search-product-list]');
    var emptyEl = overlay.querySelector('[data-site-search-empty]');
    var viewAllEl = overlay.querySelector('[data-site-search-view-all]');

    var apiUrl = overlay.getAttribute('data-api-url') || 'api/product-search.php';
    var debounceTimer = null;
    var abortController = null;
    var lastQuery = '';

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function openSearch() {
        overlay.hidden = false;
        overlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('site-search-open');
        if (openBtn) {
            openBtn.setAttribute('aria-expanded', 'true');
        }
        window.setTimeout(function () {
            input.focus();
        }, 50);
    }

    function closeSearch() {
        overlay.hidden = true;
        overlay.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('site-search-open');
        if (openBtn) {
            openBtn.setAttribute('aria-expanded', 'false');
        }
        hideDropdown();
    }

    function hideDropdown() {
        dropdown.hidden = true;
        suggestionsEl.hidden = true;
        productsWrap.hidden = true;
        emptyEl.hidden = true;
        viewAllEl.hidden = true;
    }

    function toggleClear() {
        clearBtn.hidden = input.value.trim() === '';
    }

    function renderSuggestions(items) {
        if (!items.length) {
            suggestionsEl.innerHTML = '';
            suggestionsEl.hidden = true;
            return;
        }
        suggestionsEl.hidden = false;
        suggestionsEl.innerHTML = items.map(function (item) {
            var label = escapeHtml(item.text);
            if (item.type === 'category' && item.highlight) {
                var parts = item.text.split(item.highlight);
                if (parts.length > 1) {
                    label = escapeHtml(parts[0]) + '<strong>' + escapeHtml(item.highlight) + '</strong>' + escapeHtml(parts.slice(1).join(item.highlight));
                }
            }
            return (
                '<a class="site-search-suggestion" href="' + escapeHtml(item.url) + '">' +
                '<span class="site-search-suggestion-text">' + label + '</span>' +
                '<span class="site-search-suggestion-icon" aria-hidden="true">↖</span>' +
                '</a>'
            );
        }).join('');
    }

    function renderProducts(items) {
        if (!items.length) {
            productList.innerHTML = '';
            productsWrap.hidden = true;
            return;
        }
        productsWrap.hidden = false;
        productList.innerHTML = items.map(function (p) {
            var compare = p.compare_price_label
                ? '<span class="site-search-product-compare">' + escapeHtml(p.compare_price_label) + '</span>'
                : '';
            var discount = p.discount_percent
                ? '<span class="site-search-product-discount">-' + escapeHtml(String(p.discount_percent)) + '%</span>'
                : '';
            return (
                '<a class="site-search-product" href="' + escapeHtml(p.url) + '">' +
                '<img src="' + escapeHtml(p.image) + '" alt="" width="56" height="56" loading="lazy">' +
                '<span class="site-search-product-body">' +
                '<span class="site-search-product-name">' + escapeHtml(p.name) + '</span>' +
                '<span class="site-search-product-price-row">' +
                '<span class="site-search-product-price">' + escapeHtml(p.price_label) + '</span>' +
                discount +
                '</span>' +
                compare +
                '</span>' +
                '</a>'
            );
        }).join('');
    }

    function renderResults(data) {
        dropdown.hidden = false;
        var hasSuggestions = data.suggestions && data.suggestions.length > 0;
        var hasProducts = data.products && data.products.length > 0;

        renderSuggestions(hasSuggestions ? data.suggestions : []);

        if (hasProducts) {
            renderProducts(data.products);
            emptyEl.hidden = true;
        } else {
            productsWrap.hidden = true;
            emptyEl.hidden = !lastQuery || lastQuery.length < 2 ? true : false;
        }

        if (data.total > 0 && data.catalog_url) {
            viewAllEl.href = data.catalog_url;
            viewAllEl.textContent = 'Xem tất cả ' + data.total + ' kết quả';
            viewAllEl.hidden = false;
        } else {
            viewAllEl.hidden = true;
        }
    }

    function fetchSearch(query) {
        if (abortController) {
            abortController.abort();
        }
        abortController = new AbortController();

        var url = apiUrl + '?q=' + encodeURIComponent(query) + '&limit=8';

        fetch(url, { signal: abortController.signal, headers: { Accept: 'application/json' } })
            .then(function (res) {
                return res.json();
            })
            .then(function (data) {
                if (data.query !== undefined && input.value.trim() !== data.query && input.value.trim() !== lastQuery) {
                    return;
                }
                renderResults(data);
            })
            .catch(function (err) {
                if (err.name === 'AbortError') {
                    return;
                }
                hideDropdown();
            });
    }

    function onInputChange() {
        var query = input.value.trim();
        lastQuery = query;
        toggleClear();

        if (query.length < 2) {
            hideDropdown();
            return;
        }

        window.clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(function () {
            fetchSearch(query);
        }, 280);
    }

    if (openBtn) {
        openBtn.addEventListener('click', function () {
            if (overlay.hidden) {
                openSearch();
            } else {
                closeSearch();
            }
        });
    }

    overlay.querySelectorAll('[data-site-search-close]').forEach(function (el) {
        el.addEventListener('click', closeSearch);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !overlay.hidden) {
            closeSearch();
        }
    });

    input.addEventListener('input', onInputChange);

    clearBtn.addEventListener('click', function () {
        input.value = '';
        toggleClear();
        hideDropdown();
        input.focus();
    });

    form.addEventListener('submit', function () {
        closeSearch();
    });

    window.WinsumProductSearch = {
        fetch: fetchSearch,
        renderInto: function (container, data) {
            var prevDropdown = dropdown;
            dropdown = container;
            suggestionsEl = container.querySelector('[data-site-search-suggestions]') || suggestionsEl;
            productsWrap = container.querySelector('[data-site-search-products]') || productsWrap;
            productList = container.querySelector('[data-site-search-product-list]') || productList;
            emptyEl = container.querySelector('[data-site-search-empty]') || emptyEl;
            viewAllEl = container.querySelector('[data-site-search-view-all]') || viewAllEl;
            renderResults(data);
            dropdown = prevDropdown;
        },
        apiUrl: apiUrl,
    };
})();
