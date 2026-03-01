/* list_edit.js - 詳細・編集画面用スクリプト */

function showModal(src) {
    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('modalImage');
    if (modal && modalImg) {
        modal.style.display = "flex";
        modalImg.src = src;
    }
}

function addItemRow(name = '', price = '') {
    const tbody = document.getElementById('items-tbody');
    const tr = document.createElement('tr');

    tr.innerHTML = `
        <td><input type="text" name="item_name[]" value="${name}" placeholder="商品名" class="form-input" required></td>
        <td><input type="number" name="item_price[]" value="${price}" placeholder="金額" class="form-input" required></td>
        <td style="text-align: center;">
            <button type="button" class="btn btn-danger btn-remove btn-remove-responsive" onclick="this.closest('tr').remove();" title="削除">×</button>
        </td>
    `;
    tbody.appendChild(tr);
}

document.addEventListener('DOMContentLoaded', function () {
    const btnAddItem = document.getElementById('btn-add-item');
    if (btnAddItem) {
        btnAddItem.addEventListener('click', function () {
            addItemRow();
        });
    }

    const modal = document.getElementById('imageModal');
    if (modal) {
        modal.onclick = function () {
            this.style.display = 'none';
        };
    }
});
