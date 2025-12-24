// Manage Voucher behaviors (requires jQuery)

(function ($) {
    document.addEventListener('DOMContentLoaded', function () {

        // =======================================================
        // 1. MESSAGES & ALERTS
        // =======================================================
        // Check for stored success message and display it
        const storedMessage = sessionStorage.getItem('voucher_action_message');
        const storedType = sessionStorage.getItem('voucher_action_type');

        if (storedMessage && storedType) {
            const alertDiv = $('<div>')
                .addClass('alert alert-' + storedType)
                .text(storedMessage)
                .css('margin-bottom', '20px');

            $('.main-content .header').after(alertDiv);

            sessionStorage.removeItem('voucher_action_message');
            sessionStorage.removeItem('voucher_action_type');

            setTimeout(function () {
                alertDiv.fadeOut(function () {
                    $(this).remove();
                });
            }, 5000);
        }

        // =======================================================
        // 2. SEARCH
        // =======================================================
        let searchTimer = null;
        $('#mv-search').on('input', function () {
            clearTimeout(searchTimer);
            const q = $(this).val().trim();
            searchTimer = setTimeout(() => {
                const url = new URL(window.location.href);
                if (q) { url.searchParams.set('search', q); } else { url.searchParams.delete('search'); }
                url.searchParams.delete('page');
                window.location.href = url.toString();
            }, 300);
        });

        // =======================================================
        // 3. MODAL LOGIC (Create & Edit)
        // =======================================================
        function openEditModal(data) {
            $('#edit_voucher_id').val(data.voucher_id);
            $('#edit_code').val(data.code);
            $('#edit_discount_value').val(data.discount_value);
            $('#edit_discount_type').val(data.discount_type);
            $('#edit_min_spend').val(data.min_spend);
            $('#edit_max_discount').val(data.max_discount);
            $('#edit_usage_limit').val(data.usage_limit);
            $('#edit_usage_per_user').val(data.usage_per_user);
            if (data.start_date) $('#edit_start_date').val(data.start_date.replace(' ', 'T'));
            if (data.end_date) $('#edit_end_date').val(data.end_date.replace(' ', 'T'));
            $('#edit_status').val(data.status);

            $('#editModal').css('display', 'flex').show(); // Ensure display flex and visible
        }
        function closeEditModal() {
            $('#editModal').hide();
        }

        window.openCreateModal = function () {
            $('#createModal').css('display', 'flex').show();
        }
        window.closeCreateModal = function () {
            $('#createModal').hide();
        }

        // Expose to window for inline onclick fallback
        window.mvOpenEdit = openEditModal;
        window.mvCloseEdit = closeEditModal;

        // Close modal on backdrop click
        $('#editModal, #createModal').on('click', function (e) {
            if (e.target === this) {
                $(this).hide();
            }
        });

        $('.close').on('click', function () {
            $(this).closest('.modal').hide();
        });

        // Enhance delete confirmation
        // Handle form submission where the form itself has data-confirm
        $('form[data-confirm]').on('submit', function () {
            const msg = $(this).attr('data-confirm') || 'Are you sure?';
            return window.confirm(msg);
        });

        // Handle buttons with data-confirm inside a form (like the batch delete button)
        $('button[data-confirm][type="submit"]').on('click', function (e) {
            const msg = $(this).attr('data-confirm') || 'Are you sure?';
            if (!window.confirm(msg)) {
                e.preventDefault();
                return false;
            }
        });

        // Single delete helper for button type="button"
        window.confirmDeleteVoucher = function (id) {
            if (confirm('Delete this voucher permanently?')) {
                $('#delete_id_input').val(id);
                $('#singleDeleteForm').submit();
            }
        }

        // =======================================================
        // 4. BULK SELECTION & ROW CLICK (Like manage_member.php)
        // =======================================================

        function setupBulkSelect(config) {
            const form = document.getElementById(config.formId);
            if (!form) return;

            const selectAll = document.getElementById(config.selectAllId);
            const deleteBtn = document.getElementById(config.deleteButtonId);
            const checkboxSelector = `input[name="${config.checkboxName}"]`; // e.g. selected_vouchers[]

            // Helper to get all row checkboxes (excluding disabled ones)
            const getRowCheckboxes = () =>
                Array.from(form.querySelectorAll(checkboxSelector)).filter(cb => !cb.disabled);

            function updateRowHighlights() {
                getRowCheckboxes().forEach(cb => {
                    const row = cb.closest('.selectable-row');
                    if (row) {
                        row.classList.toggle('row-selected', cb.checked);
                    }
                });
            }

            function updateButtons() {
                const anyChecked = getRowCheckboxes().some(cb => cb.checked);
                if (deleteBtn) deleteBtn.disabled = !anyChecked;
                updateRowHighlights();
            }

            function syncSelectAll() {
                if (!selectAll) return;
                const checkboxes = getRowCheckboxes();
                if (checkboxes.length === 0) {
                    selectAll.checked = false;
                    selectAll.indeterminate = false;
                    return;
                }
                const checkedCount = checkboxes.filter(cb => cb.checked).length;
                if (checkedCount === 0) {
                    selectAll.checked = false;
                    selectAll.indeterminate = false;
                } else if (checkedCount === checkboxes.length) {
                    selectAll.checked = true;
                    selectAll.indeterminate = false;
                } else {
                    selectAll.checked = false;
                    selectAll.indeterminate = true;
                }
            }

            // Header Checkbox Listener
            selectAll?.addEventListener('change', () => {
                const checkboxes = getRowCheckboxes();
                checkboxes.forEach(cb => {
                    cb.checked = selectAll.checked;
                });
                updateButtons();
            });

            // Individual Checkbox Listener (delegation)
            form.addEventListener('change', (event) => {
                if (event.target.matches(checkboxSelector)) {
                    updateButtons();
                    syncSelectAll();
                }
            });

            // Initialize
            updateButtons();
            syncSelectAll();
        }

        function setupRowClickSelection(rowSelector) {
            document.querySelectorAll(rowSelector).forEach(row => {
                const checkboxId = row.getAttribute('data-checkbox-id');
                if (!checkboxId) return;
                const checkbox = document.getElementById(checkboxId);
                if (!checkbox) return;

                row.addEventListener('click', (event) => {
                    // Avoid triggering if clicking directly on checkbox or interactive elements
                    if (event.target === checkbox) return;
                    if (event.target.closest('a, button, input, label, select, textarea')) return;

                    if (checkbox.disabled) return;

                    checkbox.checked = !checkbox.checked;
                    // Trigger change event manually so sync logic runs
                    checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });
        }

        // Initialize Selection Logic
        setupBulkSelect({
            formId: 'voucherBulkForm',
            checkboxName: 'selected_vouchers[]',
            selectAllId: 'select_all_vouchers',
            deleteButtonId: 'deleteSelectedVouchersBtn'
        });

        setupRowClickSelection('#voucherBulkForm .selectable-row');


        // =======================================================
        // 5. CLIENT-SIDE SORTING (Like manage_member.php)
        // =======================================================
        const headers = document.querySelectorAll('th.sortable');
        headers.forEach(function (header) {
            header.addEventListener('click', function () {
                const table = header.closest('table');
                const tbody = table.querySelector('tbody');
                const colIdx = parseInt(header.getAttribute('data-column'), 10);
                const type = header.getAttribute('data-type') || 'string';

                // toggle sort direction
                const currentOrder = header.getAttribute('data-order') || 'asc';
                const newOrder = currentOrder === 'asc' ? 'desc' : 'asc';
                header.setAttribute('data-order', newOrder);

                // clear order on other headers
                headers.forEach(h => {
                    if (h !== header) h.removeAttribute('data-order');
                });

                const rows = Array.from(tbody.querySelectorAll('tr'));

                // Handle Empty/No Vouchers Row
                if (rows.length === 1 && rows[0].cells.length === 1) return; // "No vouchers found"

                rows.sort(function (rowA, rowB) {
                    // Ensure cells exist (bulk actions might mess with indices if not careful, but th/td should match)
                    const cellA = rowA.children[colIdx];
                    const cellB = rowB.children[colIdx];

                    if (!cellA || !cellB) return 0;

                    const textA = cellA.innerText.trim();
                    const textB = cellB.innerText.trim();

                    let valA = textA;
                    let valB = textB;

                    if (type === 'number') {
                        // Extract number from RM or ID
                        // Simple parse
                        valA = parseFloat(textA.replace(/[^0-9.-]+/g, '')) || 0;
                        valB = parseFloat(textB.replace(/[^0-9.-]+/g, '')) || 0;
                    } else {
                        valA = textA.toLowerCase();
                        valB = textB.toLowerCase();
                    }

                    if (valA < valB) return newOrder === 'asc' ? -1 : 1;
                    if (valA > valB) return newOrder === 'asc' ? 1 : -1;
                    return 0;
                });

                // re-attach sorted rows
                rows.forEach(row => tbody.appendChild(row));
            });
        });

    });
})(jQuery);
