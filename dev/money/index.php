<?php
require_once __DIR__ . '/auth/check.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/class/DBC.php';

$db = new DBC();
$categories = [];
try {
    $categories = $db->select("SELECT category_name FROM money_category_master WHERE delete_flag = 0 ORDER BY sort_order ASC");
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>レシートAI解析 家計簿登録</title>
    <link rel="stylesheet" href="src/css/common.css">
</head>
<body>

<div class="user-info">
    👤 <?= htmlspecialchars($userEmail) ?> としてログイン中
    <a href="auth/logout.php">ログアウト</a>
</div>

<div class="container overflow-hidden">
    <h1 class="title-with-border">レシート読み取り登録</h1>
    
    <div style="text-align: right; margin-bottom: 20px;">
        <a href="manual/index.php" class="btn btn-primary" style="font-size: 14px; margin-right: 10px;">✍️ 手入力</a>
        <a href="dashboard/index.php" class="btn btn-accent" style="font-size: 14px; margin-right: 10px;">📊 レポート</a>
        <a href="list/index.php" class="btn btn-info" style="font-size: 14px;">一覧を見る ➡️</a>
    </div>
    
    <form id="upload-form">
        <label for="receipt-image" class="upload-area">
            <p>📷 ここをタップしてレシートを撮影<br>（複数枚選択・撮影可能です）</p>
            <input type="file" id="receipt-image" name="receipt_images[]" accept="image/*" capture="environment" multiple onchange="previewImages(this)">
        </label>

        <div id="save-image-container" style="margin-top: 15px; text-align: center; display: none;">
            <label style="cursor: pointer; font-size: 14px; color: #555; display: inline-flex; align-items: center; gap: 8px;">
                <input type="checkbox" id="save-image" name="save_image" value="1" checked> レシート画像を保存する
            </label>
        </div>

        <div id="preview-container" style="display: none; margin-top: 20px; text-align: left;">
            <p style="font-size: 14px; color: #666; margin-bottom: 5px;">プレビュー (クリックで拡大):</p>
            <div id="preview-grid" class="preview-grid"></div>
        </div>

        <!-- 画像拡大用モーダル -->
        <div id="image-modal" class="modal" onclick="closeModal()">
            <span class="modal-close">&times;</span>
            <img class="modal-content" id="modal-img">
        </div>

        <button type="submit" class="btn btn-success btn-full" id="submit-btn" disabled>AIで分析して登録</button>
    </form>

    <div id="loading" style="display: none; margin-top: 20px;">
        <p>AIが分析中です。しばらくお待ちください... ⏳</p>
    </div>

    <!-- 解析結果の表示エリア（編集フォーム） -->
    <div id="result-container" style="display: none; margin-top: 20px; text-align: left; background: #fafafa; padding: 20px; border-radius: 8px; border: 1px solid #e0e0e0; width: 100%; box-sizing: border-box;">
        <h3 style="margin-top: 0; color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px;">解析結果の確認・修正</h3>
        
        <form id="edit-form">
            <input type="hidden" id="edit-saved-images" name="saved_images" value="">
            <div class="form-group">
                <label>店舗名</label>
                <input type="text" id="edit-store-name" name="store_name" class="form-input" required>
            </div>
            
            <div class="form-group">
                <label>日付</label>
                <input type="date" id="edit-date" name="date" class="form-input" required>
            </div>

            <div class="form-group">
                <label>カテゴリ</label>
                <select id="edit-category" name="category" class="form-select" required>
                    <option value="">-- 選択してください --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['category_name']) ?>">
                            <?= htmlspecialchars($cat['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>合計金額 (円)</label>
                <input type="number" id="edit-total-amount" name="total_amount" class="form-input" required>
            </div>

            <div class="form-group">
                <label>内、消費税 (円)</label>
                <input type="number" id="edit-tax-amount" name="tax_amount" class="form-input">
            </div>

            <div class="form-group" style="margin-top: 25px;">
                <label>購入商品リスト</label>
                <table id="items-table" class="table table-responsive">
                    <thead>
                        <tr>
                            <th>商品名</th>
                            <th style="width: 80px;">金額</th>
                            <th style="width: 40px;"></th>
                        </tr>
                    </thead>
                    <tbody id="items-tbody">
                    </tbody>
                </table>
                <button type="button" id="btn-add-item" class="btn btn-warning" style="margin-top: 10px;">＋ 行を追加</button>
            </div>

            <button type="submit" id="btn-register" class="btn btn-primary btn-full" style="margin-top: 25px;">この内容で家計簿に登録する</button>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="src/js/index.js"></script>
</body>
</html>