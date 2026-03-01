/* index.js - トップ画面用スクリプト */

function previewImages(input) {
    const previewContainer = document.getElementById('preview-container');
    const previewGrid = document.getElementById('preview-grid');
    const submitBtn = document.getElementById('submit-btn');

    previewGrid.innerHTML = '';

    if (input.files && input.files.length > 0) {
        Array.from(input.files).forEach(file => {
            const reader = new FileReader();
            reader.onload = function (e) {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.className = 'preview-item';
                img.onclick = function () { openModal(this.src); };
                previewGrid.appendChild(img);
            }
            reader.readAsDataURL(file);
        });
        previewContainer.style.display = 'block';
        document.getElementById('save-image-container').style.display = 'block';
        submitBtn.disabled = false;
    } else {
        previewContainer.style.display = 'none';
        document.getElementById('save-image-container').style.display = 'none';
        submitBtn.disabled = true;
    }
}

// モーダルを開く
function openModal(src) {
    const modal = document.getElementById('image-modal');
    const modalImg = document.getElementById('modal-img');
    modal.style.display = "flex";
    modalImg.src = src;
}

// モーダルを閉じる
function closeModal() {
    document.getElementById('image-modal').style.display = "none";
}

// 商品行を追加する関数
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
    // フォーム送信処理 (Ajax)
    const uploadForm = document.getElementById('upload-form');
    if (uploadForm) {
        uploadForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            const fileInput = document.getElementById('receipt-image');
            if (!fileInput.files.length) return;

            const formData = new FormData();
            Array.from(fileInput.files).forEach(file => {
                formData.append('receipt_images[]', file);
            });

            const saveImage = document.getElementById('save-image').checked ? '1' : '0';
            formData.append('save_image', saveImage);

            const submitBtn = document.getElementById('submit-btn');
            const loadingDiv = document.getElementById('loading');
            const resultContainer = document.getElementById('result-container');

            submitBtn.disabled = true;
            submitBtn.textContent = "分析中...";
            loadingDiv.style.display = 'block';
            resultContainer.style.display = 'none';

            try {
                const response = await fetch('analyze_receipt.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error || '不明なエラーが発生しました');
                }

                document.getElementById('edit-store-name').value = data.store_name || '';
                document.getElementById('edit-date').value = data.date || '';
                document.getElementById('edit-category').value = data.category || '';
                document.getElementById('edit-total-amount').value = data.total_amount || '';
                document.getElementById('edit-tax-amount').value = data.tax_amount || 0;

                if (data.saved_images) {
                    document.getElementById('edit-saved-images').value = JSON.stringify(data.saved_images);
                } else {
                    document.getElementById('edit-saved-images').value = '';
                }

                const tbody = document.getElementById('items-tbody');
                tbody.innerHTML = '';

                if (data.items && Array.isArray(data.items)) {
                    data.items.forEach(item => {
                        addItemRow(item.name || '', item.price || '');
                    });
                } else {
                    addItemRow();
                }

                resultContainer.style.display = 'block';

            } catch (error) {
                console.error('Error:', error);
                alert("エラーが発生しました。\n" + error.message);
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = "AIで分析して登録";
                loadingDiv.style.display = 'none';
            }
        });
    }

    // 「行を追加」ボタンの処理
    const btnAddItem = document.getElementById('btn-add-item');
    if (btnAddItem) {
        btnAddItem.addEventListener('click', function () {
            addItemRow();
        });
    }

    // 編集フォーム送信（登録）時の処理
    const editForm = document.getElementById('edit-form');
    if (editForm) {
        editForm.addEventListener('submit', async function (e) {
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

            const savedImagesJson = document.getElementById('edit-saved-images').value;
            if (savedImagesJson) {
                try {
                    submitData.saved_images = JSON.parse(savedImagesJson);
                } catch (e) {
                    console.error('Failed to parse saved_images:', e);
                }
            }

            const submitBtn = document.getElementById('btn-register');
            submitBtn.disabled = true;
            submitBtn.textContent = '登録中...';

            try {
                const response = await fetch('save_receipt.php', {
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

                alert("✅ " + data.message + "\n\nレシートID: " + data.receipt_id + "\n登録された商品数: " + data.inserted_items);

                editForm.reset();
                document.getElementById('result-container').style.display = 'none';
                document.getElementById('preview-grid').innerHTML = "";
                document.getElementById('preview-container').style.display = 'none';
                document.getElementById('save-image-container').style.display = 'none';
                document.getElementById('receipt-image').value = "";
                document.getElementById('submit-btn').disabled = true;

            } catch (error) {
                console.error('Error:', error);
                alert("エラーが発生しました。\n" + error.message);
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'この内容で家計簿に登録する';
            }
        });
    }
});
