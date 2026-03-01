/* manual.js - 手入力画面用スクリプト */

function calculateTotal() {
    const prices = document.getElementsByName('item_price[]');
    let total = 0;
    let hasItem = false;

    for (let i = 0; i < prices.length; i++) {
        const val = parseInt(prices[i].value, 10);
        if (!isNaN(val)) {
            total += val;
            hasItem = true;
        }
    }

    if (hasItem) {
        document.getElementById('input-total').value = total;
    }
}

function addItemRow() {
    const tbody = document.getElementById('items-tbody');
    const tr = document.createElement('tr');

    tr.innerHTML = `
        <td><input type="text" name="item_name[]" value="" placeholder="商品名" class="form-input"></td>
        <td><input type="number" name="item_price[]" value="" placeholder="金額" class="form-input"></td>
        <td style="text-align: center;">
            <button type="button" class="btn btn-danger btn-remove btn-remove-responsive" onclick="this.closest('tr').remove(); calculateTotal();" title="削除">×</button>
        </td>
    `;
    tbody.appendChild(tr);
}

document.addEventListener('DOMContentLoaded', function () {
    const itemsTbody = document.getElementById('items-tbody');
    if (itemsTbody) {
        itemsTbody.addEventListener('input', function (e) {
            if (e.target.name === 'item_price[]') {
                calculateTotal();
            }
        });
    }

    const btnAddItem = document.getElementById('btn-add-item');
    if (btnAddItem) {
        btnAddItem.addEventListener('click', function () {
            addItemRow();
        });
    }

    const manualForm = document.getElementById('manual-form');
    if (manualForm) {
        manualForm.addEventListener('submit', async function (e) {
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

            const submitBtn = document.getElementById('btn-submit');
            submitBtn.disabled = true;
            submitBtn.textContent = '登録中...';

            try {
                const response = await fetch('../save_receipt.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(submitData)
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error || 'データベースの登録に失敗しました');
                }

                alert("✅ 手入力データの登録が完了しました！\n\nレシートID: " + data.receipt_id);

                this.reset();
                document.getElementById('input-date').value = new Date().toISOString().split('T')[0];

            } catch (error) {
                console.error('Error:', error);
                alert("エラーが発生しました。\n" + error.message);
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = '家計簿に登録する';
            }
        });
    }
});
