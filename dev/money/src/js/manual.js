/* manual.js - 手入力画面用スクリプト (jQuery版) */

function addItemRow() {
    const $tbody = $('#items-tbody');
    const $tr = $('<tr>').append(`
        <td><input type="text" name="item_name[]" value="" placeholder="商品名" class="form-input"></td>
        <td><input type="number" name="item_price[]" value="" placeholder="金額" class="form-input"></td>
        <td style="text-align: center;">
            <button type="button" class="btn btn-danger btn-remove btn-remove-responsive btn-delete-item" title="削除">×</button>
        </td>
    `);
    $tbody.append($tr);
}

$(function () {
    const $inputSubtotal = $('#input-subtotal');
    const $inputTax = $('#input-tax');
    const $inputTotal = $('#input-total');
    const $btnSubtotalTax8 = $('#btn-subtotal-tax8');
    const $btnSubtotalTax10 = $('#btn-subtotal-tax10');
    const $btnToggleTotalLock = $('#btn-toggle-total-lock');

    // 税金計算ボタンの有効・無効を切り替える関数
    const updateTaxButtons = () => {
        const hasValue = $inputSubtotal.val().trim() !== '';
        $btnSubtotalTax8.prop('disabled', !hasValue);
        $btnSubtotalTax10.prop('disabled', !hasValue);
    };

    // 合計金額（小計+税）を更新する共通関数
    const updateTotalAmount = () => {
        const subtotal = parseInt($inputSubtotal.val(), 10) || 0;
        const tax = parseInt($inputTax.val(), 10) || 0;
        $inputTotal.val(subtotal + tax);
    };

    // 行の追加・削除時の合計計算 (小計に反映)
    const calculateTotals = () => {
        let subtotal = 0;
        $('input[name="item_price[]"]').each(function () {
            subtotal += parseInt($(this).val()) || 0;
        });
        $inputSubtotal.val(subtotal);
        updateTaxButtons();
        updateTotalAmount();
    };

    // 商品名・金額の入力イベント委譲
    $('#items-tbody').on('input', 'input[name="item_price[]"]', function () {
        calculateTotals();
    }).on('click', '.btn-delete-item', function () {
        $(this).closest('tr').remove();
        calculateTotals();
    });

    $('#btn-add-item').on('click', function () {
        addItemRow();
    });

    // 小計・消費税の入力時に合計を更新
    $inputSubtotal.on('input', () => {
        updateTaxButtons();
        updateTotalAmount();
    });
    $inputTax.on('input', updateTotalAmount);

    // 小計からの税額と合計の計算
    const calculateFromSubtotal = (rate) => {
        const subtotal = parseInt($inputSubtotal.val(), 10);
        if (isNaN(subtotal)) return;
        const tax = Math.floor(subtotal * rate / 100);
        $inputTax.val(tax);
        updateTotalAmount();
    };

    $btnSubtotalTax8.on('click', () => calculateFromSubtotal(8));
    $btnSubtotalTax10.on('click', () => calculateFromSubtotal(10));

    // 合計金額のロック解除トグル
    $btnToggleTotalLock.on('click', function () {
        const isReadOnly = $inputTotal.prop('readonly');
        $inputTotal.prop('readonly', !isReadOnly);
        $(this).text(isReadOnly ? '確定' : '変更')
            .toggleClass('btn-warning btn-success');

        if (!isReadOnly) {
            $inputTotal.focus();
        }
    });

    // 小計があれば合計も更新する共通関数 (計算機用)
    const syncTotalFromSubtotalAndTax = () => {
        const subtotal = parseInt($inputSubtotal.val(), 10);
        const tax = parseInt($inputTax.val(), 10) || 0;
        if (!isNaN(subtotal)) {
            $inputTotal.val(subtotal + tax);
        }
    };

    // 金額指定での計算ロジック
    const calculateTaxFromBase = (rate) => {
        const base = parseInt($('#calc-base-amount').val(), 10);
        if (isNaN(base)) {
            alert('金額を入力してください');
            return;
        }
        const tax = Math.floor(base * rate / 100);
        $inputTax.val(tax);
        syncTotalFromSubtotalAndTax();
    };

    $('#btn-calc-tax8').on('click', () => calculateTaxFromBase(8));
    $('#btn-calc-tax10').on('click', () => calculateTaxFromBase(10));

    // エリアの表示切り替え
    $('#btn-toggle-tax-calc').on('click', function () {
        const $taxCalcArea = $('#tax-calc-area');
        $taxCalcArea.toggleClass('hidden');
        const isHidden = $taxCalcArea.hasClass('hidden');
        $(this).text(isHidden ? '▶︎ 金額指定で計算する' : '▼ 金額指定で計算する');
    });

    // フォーム送信
    $('#manual-form').on('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(this);
        const submitData = Object.fromEntries(formData.entries());

        submitData.items = [];
        const itemNames = formData.getAll('item_name[]');
        const itemPrices = formData.getAll('item_price[]');

        for (let i = 0; i < itemNames.length; i++) {
            if (itemNames[i].trim() !== '') {
                submitData.items.push({
                    name: itemNames[i],
                    price: itemPrices[i]
                });
            }
        }

        const $submitBtn = $('#btn-submit');
        $submitBtn.prop('disabled', true).text('登録中...');

        $.ajax({
            url: '../save_receipt.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(submitData),
            success: function (data) {
                alert("✅ 手入力データの登録が完了しました！\n\nレシートID: " + data.receipt_id);
                $('#manual-form')[0].reset();
                $('#input-date').val(new Date().toISOString().split('T')[0]);
                updateTaxButtons();
                updateTotalAmount();
            },
            error: function (xhr) {
                const errorData = xhr.responseJSON || {};
                alert("エラーが発生しました。\n" + (errorData.error || '不明なエラー'));
            },
            complete: function () {
                $submitBtn.prop('disabled', false).text('家計簿に登録する');
            }
        });
    });
});
