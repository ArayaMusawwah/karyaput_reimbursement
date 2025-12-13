// Externalized script for request.php item handling
(function () {
    // Simple safety: ensure console exists
    var log = function () { try { console.log.apply(console, arguments); } catch (e) { } };

    log('request-items.js loaded');

    var container = document.getElementById('items-container');
    var statusElem = document.getElementById('js-status');

    var initialItems = [];
    if (container && container.dataset && container.dataset.initialItems) {
        try {
            initialItems = JSON.parse(container.dataset.initialItems);
        } catch (e) {
            log('Failed to parse initialItems', e);
        }
    }

    function setStatus(text) {
        try { if (statusElem) statusElem.textContent = text; } catch (e) { }
    }

    function updateTotalAmount() {
        var amountInputs = document.querySelectorAll('.item-amount');
        var total = 0;
        amountInputs.forEach(function (input) { total += parseFloat(input.value) || 0; });
        var totalEl = document.getElementById('total-amount');
        if (totalEl) totalEl.textContent = 'Rp' + total.toLocaleString('id-ID');
        setStatus('JS: total Rp' + total.toLocaleString('id-ID'));
    }

    function reindexItems() {
        if (!container) return;
        var rows = container.querySelectorAll('.item-row');
        rows.forEach(function (row, idx) {
            var nameInput = row.querySelector('.item-name');
            var amountInput = row.querySelector('.item-amount');
            var labels = row.querySelectorAll('label.form-label');
            if (nameInput) {
                nameInput.setAttribute('name', 'items[' + idx + '][name]');
                nameInput.setAttribute('id', 'item-name-' + idx);
            }
            if (amountInput) {
                amountInput.setAttribute('name', 'items[' + idx + '][amount]');
                amountInput.setAttribute('id', 'item-amount-' + idx);
            }
            // try to associate first label to name input
            if (labels && labels[0]) {
                try { labels[0].setAttribute('for', 'item-name-' + idx); } catch (e) { }
            }
        });
    }

    function removeItem(button) {
        var row = button.closest ? button.closest('.item-row') : null;
        if (row) row.remove();
        reindexItems();
        updateTotalAmount();
    }

    // Create a new item row; data can be {name, amount}
    function addItemRow(data) {
        if (!container) return;
        var itemIndex = container.children.length;
        var itemDiv = document.createElement('div');
        itemDiv.className = 'item-row';
        itemDiv.innerHTML = '\n                <div class="row">\n                    <div class="col-md-6">\n                        <label class="form-label" for="item-name-' + itemIndex + '"></label>\n                        <input id="item-name-' + itemIndex + '" type="text" class="form-control item-name" name="items[' + itemIndex + '][name]" required>\n                    </div>\n                    <div class="col-md-5">\n                        <label class="form-label" for="item-amount-' + itemIndex + '"></label>\n                        <input id="item-amount-' + itemIndex + '" type="number" step="0.01" class="form-control item-amount" name="items[' + itemIndex + '][amount]" min="0" required>\n                    </div>\n                    <div class="col-md-1">\n                        <label class="form-label">&nbsp;</label>\n                        <button type="button" class="btn btn-danger remove-item">X</button>\n                    </div>\n                </div>\n            ';

        container.appendChild(itemDiv);

        if (data) {
            var nameInput = itemDiv.querySelector('.item-name');
            var amountInput = itemDiv.querySelector('.item-amount');
            if (nameInput && data.name !== undefined) nameInput.value = data.name;
            if (amountInput && data.amount !== undefined) amountInput.value = data.amount;
        }

        var amountInputEl = itemDiv.querySelector('.item-amount');
        if (amountInputEl) amountInputEl.addEventListener('input', updateTotalAmount);
        var removeBtn = itemDiv.querySelector('.remove-item');
        if (removeBtn) removeBtn.addEventListener('click', function () { removeItem(this); });

        reindexItems();
        updateTotalAmount();
    }

    function initItems() {
        log('initItems running');
        setStatus('JS: init');
        if (container && container.children.length > 0) {
            var rows = container.querySelectorAll('.item-row');
            rows.forEach(function (row) {
                var amountInput = row.querySelector('.item-amount');
                if (amountInput) amountInput.addEventListener('input', updateTotalAmount);
                var removeBtn = row.querySelector('.remove-item');
                if (removeBtn) removeBtn.addEventListener('click', function () { removeItem(this); });
            });
            reindexItems();
        } else {
            if (initialItems && Array.isArray(initialItems) && initialItems.length > 0) {
                initialItems.forEach(function (it) { addItemRow(it); });
            } else {
                addItemRow();
            }
        }

        var addBtn = document.getElementById('add-item');
        if (addBtn) {
            log('binding addBtn click');
            setStatus('JS: bind addBtn');
            addBtn.addEventListener('click', function () { log('addBtn clicked'); setStatus('JS: addBtn clicked'); addItemRow(); });
        } else {
            log('addBtn not found during init');
            setStatus('JS: addBtn missing');
        }

        // Delegated click fallback
        document.addEventListener('click', function (ev) {
            var btn = ev.target.closest ? ev.target.closest('#add-item') : null;
            if (btn) { log('delegated addBtn click'); setStatus('JS: delegated click'); addItemRow(); }
        });

        updateTotalAmount();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initItems);
    } else {
        initItems();
    }

})();