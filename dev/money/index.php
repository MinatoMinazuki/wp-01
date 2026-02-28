<?php
// 今後ここに画像アップロード処理やAI連携処理を追加します
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>レシート読み取り</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Arial, 'Hiragino Kaku Gothic ProN', 'Hiragino Sans', Meiryo, sans-serif;
            background-color: #f4f6f8;
            color: #333;
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .container {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 30px;
            width: 100%;
            max-width: 500px;
            box-sizing: border-box;
            text-align: center;
        }
        h1 {
            font-size: 24px;
            margin-top: 0;
            margin-bottom: 20px;
            color: #2c3e50;
        }
        .upload-area {
            border: 2px dashed #3498db;
            border-radius: 8px;
            padding: 40px 20px;
            background-color: #f0f8ff;
            cursor: pointer;
            transition: background-color 0.3s ease;
            margin-bottom: 20px;
            display: block;
        }
        .upload-area:hover {
            background-color: #e1f0fa;
        }
        .upload-area p {
            margin: 0;
            color: #3498db;
            font-weight: bold;
            line-height: 1.5;
        }
        input[type="file"] {
            display: none;
        }
        #preview-container {
            display: none;
            margin-bottom: 20px;
        }
        #preview-image {
            max-width: 100%;
            max-height: 300px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            object-fit: contain;
        }
        .btn-submit {
            background-color: #2ecc71;
            color: white;
            border: none;
            padding: 12px 24px;
            font-size: 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            width: 100%;
            transition: background-color 0.3s ease;
        }
        .btn-submit:hover {
            background-color: #27ae60;
        }
        .btn-submit:disabled {
            background-color: #95a5a6;
            cursor: not-allowed;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>レシート読み取り登録</h1>
    
    <form id="upload-form">
        
        <label for="receipt-image" class="upload-area">
            <p>📷 ここをタップしてレシートを撮影<br>または画像を選択</p>
            <!-- capture="environment" でスマホの背面カメラを優先起動します -->
            <input type="file" id="receipt-image" name="receipt_image" accept="image/*" capture="environment" onchange="previewImage(this)">
        </label>

        <div id="preview-container">
            <p style="font-size: 14px; color: #666; margin-bottom: 10px;">プレビュー:</p>
            <img id="preview-image" src="" alt="レシートプレビュー">
        </div>

        <button type="submit" class="btn-submit" id="submit-btn" disabled>AIで分析して登録</button>
        
    </form>

    <div id="loading" style="display: none; margin-top: 20px;">
        <p>AIが分析中です。しばらくお待ちください... ⏳</p>
    </div>

    <!-- 解析結果の表示エリア（編集フォーム） -->
    <div id="result-container" style="display: none; margin-top: 20px; text-align: left; background: #fafafa; padding: 20px; border-radius: 8px; border: 1px solid #e0e0e0; width: 100%; box-sizing: border-box;">
        <h3 style="margin-top: 0; color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px;">解析結果の確認・修正</h3>
        
        <form id="edit-form">
            <div class="form-group">
                <label>店舗名</label>
                <input type="text" id="edit-store-name" name="store_name" required>
            </div>
            
            <div class="form-group">
                <label>日付</label>
                <input type="date" id="edit-date" name="date" required>
            </div>

            <div class="form-group">
                <label>カテゴリ</label>
                <input type="text" id="edit-category" name="category">
            </div>

            <div class="form-group">
                <label>合計金額 (円)</label>
                <input type="number" id="edit-total-amount" name="total_amount" required>
            </div>

            <div class="form-group" style="margin-top: 25px;">
                <label>購入商品リスト</label>
                <table id="items-table">
                    <thead>
                        <tr>
                            <th>商品名</th>
                            <th style="width: 80px;">金額</th>
                            <th style="width: 40px;"></th>
                        </tr>
                    </thead>
                    <tbody id="items-tbody">
                        <!-- ここにJavaScriptで行が追加されます -->
                    </tbody>
                </table>
                <button type="button" id="btn-add-item" style="margin-top: 10px; background-color: #f1c40f; color: #333; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 14px;">＋ 行を追加</button>
            </div>

            <button type="submit" id="btn-register" class="btn-submit" style="margin-top: 25px; background-color: #3498db;">この内容で家計簿に登録する</button>
        </form>
    </div>
</div>

<style>
    /* 追加のフォーム用スタイル */
    .form-group {
        margin-bottom: 15px;
    }
    .form-group label {
        display: block;
        font-size: 14px;
        color: #555;
        margin-bottom: 5px;
        font-weight: bold;
    }
    .form-group input[type="text"],
    .form-group input[type="date"],
    .form-group input[type="number"] {
        width: 100%;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
        font-size: 16px;
    }
    #items-table {
        width: 100%;
        border-collapse: collapse;
    }
    #items-table th {
        font-size: 12px;
        color: #666;
        text-align: left;
        padding-bottom: 5px;
        border-bottom: 1px solid #ddd;
    }
    #items-table td {
        padding: 5px 0;
    }
    #items-table input {
        width: 100%;
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
        font-size: 14px;
    }
    .btn-remove-item {
        background-color: #e74c3c;
        color: white;
        border: none;
        border-radius: 4px;
        width: 30px;
        height: 30px;
        font-size: 16px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-left: 5px;
    }
</style>

<script>
    function previewImage(input) {
        const previewContainer = document.getElementById('preview-container');
        const previewImage = document.getElementById('preview-image');
        const submitBtn = document.getElementById('submit-btn');

        if (input.files && input.files[0]) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                previewImage.src = e.target.result;
                previewContainer.style.display = 'block';
                submitBtn.disabled = false; // 画像が選択されたらボタンを有効化
            }
            
            reader.readAsDataURL(input.files[0]);
        } else {
            previewImage.src = "";
            previewContainer.style.display = 'none';
            submitBtn.disabled = true; // 画像がなければボタンを無効化
        }
    }

    // 商品行を追加する関数
    function addItemRow(name = '', price = '') {
        const tbody = document.getElementById('items-tbody');
        const tr = document.createElement('tr');
        
        tr.innerHTML = `
            <td><input type="text" name="item_name[]" value="${name}" placeholder="商品名" required></td>
            <td><input type="number" name="item_price[]" value="${price}" placeholder="金額" required></td>
            <td style="text-align: center;">
                <button type="button" class="btn-remove-item" onclick="this.closest('tr').remove();" title="削除">×</button>
            </td>
        `;
        tbody.appendChild(tr);
    }

    // フォーム送信処理 (Ajax)
    document.getElementById('upload-form').addEventListener('submit', async function(e) {
        e.preventDefault(); // 通常の画面遷移を防ぐ

        const fileInput = document.getElementById('receipt-image');
        if (!fileInput.files[0]) return;

        const formData = new FormData();
        formData.append('receipt_image', fileInput.files[0]);

        // UI状態変更
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

            // 成功時、フォームに値をセット
            document.getElementById('edit-store-name').value = data.store_name || '';
            document.getElementById('edit-date').value = data.date || '';
            document.getElementById('edit-category').value = data.category || '';
            document.getElementById('edit-total-amount').value = data.total_amount || '';

            // 商品リストをクリアして再構築
            const tbody = document.getElementById('items-tbody');
            tbody.innerHTML = ''; // 既存の行を削除

            if (data.items && Array.isArray(data.items)) {
                data.items.forEach(item => {
                    addItemRow(item.name || '', item.price || '');
                });
            } else {
                // 商品が1つも見つからなかった場合は空行を1つ追加
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

    // 「行を追加」ボタンの処理
    document.getElementById('btn-add-item').addEventListener('click', function() {
        addItemRow();
    });

    // 編集フォーム送信（登録）時の処理
    document.getElementById('edit-form').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // FormDataを使って現在のフォームの値を取得
        const formData = new FormData(this);
        const submitData = Object.fromEntries(formData.entries());
        
        // 配列名(item_name[], item_price[]) は別途処理する
        submitData.items = [];
        const itemNames = formData.getAll('item_name[]');
        const itemPrices = formData.getAll('item_price[]');
        
        for (let i = 0; i < itemNames.length; i++) {
            submitData.items.push({
                name: itemNames[i],
                price: itemPrices[i]
            });
        }
        
        // JSONにしてサーバーへ送信
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

            // 成功メッセージ
            alert("✅ " + data.message + "\n\nレシートID: " + data.receipt_id + "\n登録された商品数: " + data.inserted_items);
            
            // フォームのリセットとエリアの非表示等（任意）
            document.getElementById('edit-form').reset();
            document.getElementById('result-container').style.display = 'none';
            document.getElementById('preview-image').src = "";
            document.getElementById('preview-container').style.display = 'none';
            document.getElementById('receipt-image').value = ""; // ファイル選択リセット
            document.getElementById('submit-btn').disabled = true;

        } catch (error) {
            console.error('Error:', error);
            alert("エラーが発生しました。\n" + error.message);
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'この内容で家計簿に登録する';
        }
    });
</script>

</body>
</html>