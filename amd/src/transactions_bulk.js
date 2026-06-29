// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Xử lý bulk actions, checkbox selection, perpage toggle cho trang transactions.
 *
 * @module     enrol_sepay/transactions_bulk
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'core/str',
    'core/notification'
], function(Str, Notification) {

    /**
     * Màu CSS class tương ứng với từng bulk action.
     */
    var bulkBtnColors = {
        'bulk_approve': 'btn-success',
        'bulk_reject':  'btn-warning',
        'bulk_unenrol': 'btn-dark',
        'bulk_delete':  'btn-danger'
    };

    /**
     * Cập nhật trạng thái enable/disable của dropdown và các nút bulk action
     * dựa trên danh sách checkbox đang được tick.
     *
     * @param {HTMLElement} container - Phần tử cha chứa bảng và các nút.
     */
    function updateDropdownState(container) {
        var dropdown = document.getElementById('formactionid');
        var allChecked  = container.querySelectorAll('.transaction-checkbox:checked');
        var hasPending  = Array.from(allChecked).some(function(cb) { return cb.dataset.status === 'pending'; });
        var hasProcessed = Array.from(allChecked).some(function(cb) { return cb.dataset.status === 'processed'; });
        var hasAny      = allChecked.length > 0;

        if (dropdown) {
            dropdown.disabled = !hasAny;
        }

        document.querySelectorAll('button[data-bulk-action]').forEach(function(btn) {
            var action     = btn.dataset.bulkAction;
            var colorClass = bulkBtnColors[action];
            var shouldEnable = (action === 'bulk_delete')  ? hasAny
                             : (action === 'bulk_unenrol') ? hasProcessed
                             : hasPending;

            if (shouldEnable) {
                btn.disabled = false;
                btn.classList.remove('btn-outline-secondary');
                if (colorClass) {
                    btn.classList.add(colorClass);
                }
            } else {
                btn.disabled = true;
                if (colorClass) {
                    btn.classList.remove(colorClass);
                }
                btn.classList.add('btn-outline-secondary');
            }
        });
    }

    /**
     * Gắn event listeners cho checkbox "chọn tất cả" và các checkbox riêng lẻ.
     *
     * @param {HTMLElement} container - Phần tử cha chứa bảng.
     */
    function bindSelectAll(container) {
        var selectAll = container.querySelector('#select-all');
        if (!selectAll) {
            return;
        }
        selectAll.addEventListener('change', function() {
            container.querySelectorAll('.transaction-checkbox').forEach(function(cb) {
                cb.checked = selectAll.checked;
            });
            updateDropdownState(container);
        });
        container.querySelectorAll('.transaction-checkbox').forEach(function(cb) {
            cb.addEventListener('change', function() {
                updateDropdownState(container);
            });
        });
    }

    /**
     * Fetch HTML mới và thay thế #sepay-table-container mà không reload trang.
     *
     * @param {string}  fetchUrl      - URL cần fetch.
     * @param {boolean} shouldCheckAll - Có tự động tick tất cả checkbox sau khi load không.
     */
    function fetchAndReplaceTable(fetchUrl, shouldCheckAll) {
        fetch(fetchUrl)
            .then(function(res) { return res.text(); })
            .then(function(html) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');
                var newContainer = doc.getElementById('sepay-table-container');
                var oldContainer = document.getElementById('sepay-table-container');
                if (newContainer && oldContainer) {
                    oldContainer.replaceWith(newContainer);
                    bindSelectAll(newContainer);

                    if (shouldCheckAll) {
                        newContainer.querySelectorAll('.transaction-checkbox').forEach(function(cb) {
                            cb.checked = true;
                        });
                        var selectAll = newContainer.querySelector('#select-all');
                        if (selectAll) {
                            selectAll.checked = true;
                        }
                    }
                    // Đồng bộ trạng thái nút/dropdown sau khi thay container, kể cả khi đổi
                    // perpage (không chỉ khi "chọn tất cả") để không giữ trạng thái cũ.
                    updateDropdownState(newContainer);

                    var newToggle = newContainer.querySelector('a[data-perpage-toggle]');
                    if (newToggle) {
                        newToggle.scrollIntoView(false);
                    }
                }
            })
            .catch(function(err) {
                Notification.exception(err);
            });
    }

    /**
     * Xử lý khi chọn option từ dropdown "With selected...".
     * Được gọi bởi data-action-select trên thẻ <select>.
     *
     * @param {HTMLSelectElement} selectObj
     * @param {Object}            confirmStrings - Map action => confirm string.
     * @param {string}            noSelectionStr - Chuỗi "no transactions selected".
     */
    function runBulkAction(selectObj, confirmStrings, noSelectionStr) {
        if (selectObj.value === '') {
            return;
        }
        var checkboxes = document.querySelectorAll('.transaction-checkbox:checked');
        if (checkboxes.length === 0) {
            alert(noSelectionStr); // eslint-disable-line no-alert
            selectObj.value = '';
            return;
        }

        // Gửi tin nhắn — dùng AMD module participants của Moodle.
        if (selectObj.value === '#messageselect') {
            var userids = [];
            checkboxes.forEach(function(cb) {
                var uid = parseInt(cb.dataset.userid, 10);
                if (uid && userids.indexOf(uid) === -1) {
                    userids.push(uid);
                }
            });
            require(['core_user/local/participants/bulkactions'], function(BulkActions) { // eslint-disable-line
                BulkActions.showSendMessage(userids);
            });
            selectObj.value = '';
            return;
        }

        // Download URL — chuyển form action và submit.
        if (selectObj.value.indexOf('http') === 0 || selectObj.value.indexOf('/') === 0) {
            selectObj.form.action = selectObj.value;
            selectObj.form.submit();
            return;
        }

        // Bulk action — hỏi xác nhận rồi submit.
        var msg = confirmStrings[selectObj.value] || '';
        if (msg && !window.confirm(msg)) { // eslint-disable-line no-alert
            selectObj.value = '';
            return;
        }
        var hiddenInput = document.createElement('input');
        hiddenInput.type  = 'hidden';
        hiddenInput.name  = 'action';
        hiddenInput.value = selectObj.value;
        selectObj.form.appendChild(hiddenInput);
        selectObj.form.submit();
    }

    return {
        /**
         * Khởi tạo module.
         *
         * @param {string[]} confirmStringKeys - Mảng [action, stringKey] để load qua core/str.
         * @param {string}   noSelectionKey    - String key cho "no transactions selected".
         */
        init: function(confirmStringKeys, noSelectionKey) {
            // Load tất cả strings cần thiết từ Moodle string API.
            var strRequests = confirmStringKeys.map(function(item) {
                return {key: item.key, component: 'enrol_sepay'};
            });
            strRequests.push({key: noSelectionKey, component: 'enrol_sepay'});

            Str.get_strings(strRequests).then(function(strings) {
                var confirmStrings = {};
                confirmStringKeys.forEach(function(item, i) {
                    confirmStrings[item.action] = strings[i];
                });
                var noSelectionStr = strings[strings.length - 1];

                // Bind select-all và checkbox listeners.
                bindSelectAll(document);

                // Nút bulk action (đầu trang): click → confirm → submit form.
                document.addEventListener('click', function(e) {
                    var btn = e.target.closest('button[data-bulk-action]');
                    if (!btn) {
                        return;
                    }
                    var action = btn.dataset.bulkAction;
                    var checkboxes = document.querySelectorAll('.transaction-checkbox:checked');
                    if (checkboxes.length === 0) {
                        alert(noSelectionStr); // eslint-disable-line no-alert
                        return;
                    }
                    var msg = confirmStrings[action] || '';
                    if (msg && !window.confirm(msg)) { // eslint-disable-line no-alert
                        return;
                    }
                    var form = document.getElementById('bulk-delete-form');
                    var input = document.createElement('input');
                    input.type  = 'hidden';
                    input.name  = 'action';
                    input.value = action;
                    form.appendChild(input);
                    form.submit();
                });

                // Link perpage-toggle và checkall-btn: fetch partial HTML.
                document.addEventListener('click', function(e) {
                    var link       = e.target.closest('a[data-perpage-toggle]');
                    var checkAllBtn = e.target.closest('[data-checkall-btn]');
                    if (!link && !checkAllBtn) {
                        return;
                    }
                    e.preventDefault();
                    var fetchUrl      = link ? link.href : checkAllBtn.dataset.href;
                    var shouldCheckAll = !!checkAllBtn;
                    fetchAndReplaceTable(fetchUrl, shouldCheckAll);
                });

                // Dropdown "With selected...". Dùng event delegation trên document để listener
                // vẫn hoạt động sau khi #sepay-table-container (chứa dropdown) bị thay mới bởi
                // fetchAndReplaceTable — bind trực tiếp vào node sẽ mất khi node bị replaceWith.
                document.addEventListener('change', function(e) {
                    var dropdown = e.target.closest('#formactionid');
                    if (!dropdown) {
                        return;
                    }
                    runBulkAction(dropdown, confirmStrings, noSelectionStr);
                });

                return true;
            }).catch(Notification.exception);
        }
    };
});
