/* list_edit.js - 詳細・編集画面用スクリプト (jQuery版) */

function showModal(src) {
    const $modal = $('#imageModal');
    const $modalImg = $('#modalImage');
    if ($modal.length && $modalImg.length) {
        $modal.css('display', 'flex');
        $modalImg.attr('src', src);
    }
}

function addItemRow(name = '', price = '') {
    const $tbody = $('#items-tbody');
    const $tr = $('<tr>').append(`
        <td><input type="text" name="item_name[]" value="${name}" placeholder="商品名" class="form-input" required></td>
        <td><input type="number" name="item_price[]" value="${price}" placeholder="金額" class="form-input" required></td>
        <td style="text-align: center;">
            <button type="button" class="btn btn-danger btn-remove btn-remove-responsive" title="削除">×</button>
        </td>
    `);
    $tbody.append($tr);
}

$(function () {
    // 削除ボタンの委譲
    $('#items-tbody').on('click', '.btn-remove', function () {
        $(this).closest('tr').remove();
    });

    $('#btn-add-item').on('click', function () {
        addItemRow();
    });

    $('#imageModal').on('click', function () {
        $(this).hide();
    });
});
