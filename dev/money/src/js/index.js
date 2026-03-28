/* index.js - トップ画面用スクリプト (jQuery版) */

// 画像プレビュー機能
function previewImages(input) {
    const $previewContainer = $('#preview-container');
    const $previewGrid = $('#preview-grid');
    const $saveImageContainer = $('#save-image-container');
    const $submitBtn = $('#submit-btn');

    $previewGrid.empty();

    if (input.files && input.files.length > 0) {
        Array.from(input.files).forEach(file => {
            const reader = new FileReader();
            reader.onload = function (e) {
                $('<img>', {
                    src: e.target.result,
                    class: 'preview-item',
                    click: function () { openModal(e.target.result); }
                }).appendTo($previewGrid);
            }
            reader.readAsDataURL(file);
        });
        $previewContainer.show();
        $saveImageContainer.show();
        $submitBtn.prop('disabled', false);
    } else {
        $previewContainer.hide();
        $saveImageContainer.hide();
        $submitBtn.prop('disabled', true);
    }
}

// モーダル操作
function openModal(src) {
    $('#modal-img').attr('src', src);
    $('#image-modal').css('display', 'flex');
}

function closeModal() {
    $('#image-modal').hide();
}

// 商品行追加
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

    // 解析フォーム送信
    $('#upload-form').on('submit', function (e) {
        e.preventDefault();

        const fileInput = $('#receipt-image')[0];
        if (!fileInput.files.length) return;

        const formData = new FormData();
        Array.from(fileInput.files).forEach(file => {
            formData.append('receipt_images[]', file);
        });

        const saveImage = $('#save-image').is(':checked') ? '1' : '0';
        formData.append('save_image', saveImage);

        const $submitBtn = $('#submit-btn');
        const $loading = $('#loading');
        const $resultContainer = $('#result-container');

        $submitBtn.prop('disabled', true).text("分析中...");
        $loading.show();
        $resultContainer.hide();

        $.ajax({
            url: 'analyze_receipt.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (data) {
                $('#edit-store-name').val(data.store_name || '');
                $('#edit-date').val(data.date || '');
                $('#edit-category').val(data.category || '');
                $('#edit-total-amount').val(data.total_amount || '');
                $('#edit-tax-amount').val(data.tax_amount || 0);
                $('#edit-saved-images').val(data.saved_images ? JSON.stringify(data.saved_images) : '');

                const $tbody = $('#items-tbody').empty();
                if (data.items && Array.isArray(data.items)) {
                    $.each(data.items, (i, item) => {
                        addItemRow(item.name || '', item.price || '');
                    });
                } else {
                    addItemRow();
                }

                $resultContainer.show();
            },
            error: function (xhr) {
                console.error('Raw response:', xhr.responseText);
                const errorData = xhr.responseJSON || {};
                const msg = errorData.error || '不明なエラー (' + xhr.status + ' ' + xhr.statusText + ')';
                alert("エラーが発生しました。\n" + msg + "\n詳細はブラウザのコンソールを確認してください。");
            },
            complete: function () {
                $submitBtn.prop('disabled', false).text("AIで分析して登録");
                $loading.hide();
            }
        });
    });

    // 行追加
    $('#btn-add-item').on('click', function () {
        addItemRow();
    });

    // 編集フォーム送信
    $('#edit-form').on('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(this);
        const submitData = Object.fromEntries(formData.entries());

        submitData.items = [];
        const itemNames = formData.getAll('item_name[]');
        const itemPrices = formData.getAll('item_price[]');

        for (let i = 0; i < itemNames.length; i++) {
            submitData.items.push({
                name: itemNames[i],
                price: itemPrices[i]
            });
        }

        const savedImagesJson = $('#edit-saved-images').val();
        if (savedImagesJson) {
            try {
                submitData.saved_images = JSON.parse(savedImagesJson);
            } catch (err) {
                console.error('Failed to parse saved_images:', err);
            }
        }

        const $submitBtn = $('#btn-register');
        $submitBtn.prop('disabled', true).text('登録中...');

        $.ajax({
            url: 'save_receipt.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(submitData),
            success: function (data) {
                alert("✅ " + data.message + "\n\nレシートID: " + data.receipt_id + "\n登録された商品数: " + data.inserted_items);
                $('#edit-form')[0].reset();
                $('#result-container').hide();
                $('#preview-grid').empty();
                $('#preview-container').hide();
                $('#save-image-container').hide();
                $('#receipt-image').val("");
                $('#submit-btn').prop('disabled', true);
            },
            error: function (xhr) {
                const errorData = xhr.responseJSON || {};
                const msg = errorData.error || 'データベースの登録に失敗しました (' + xhr.status + ' ' + xhr.statusText + ')';
                alert("エラーが発生しました。\n" + msg);
            },
            complete: function () {
                $submitBtn.prop('disabled', false).text('この内容で家計簿に登録する');
            }
        });
    });
});
