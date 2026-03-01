<?php
require_once __DIR__ . '/../auth/check.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../class/DBC.php';

$db = new DBC();

// カテゴリマスタの取得
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
    <title>家計簿 手入力登録</title>
    <link rel="stylesheet" href="../src/css/common.css">
</head>
<body>

<div class="user-info">
    👤 <?= htmlspecialchars($userEmail) ?> としてログイン中
    <a href="../auth/logout.php">ログアウト</a>
</div>

<div class="container overflow-hidden">
    <h1 class="title-with-border">家計簿 手入力登録</h1>
    
    <div style="text-align: right; margin-bottom: 20px;">
        <a href="../index.php" class="btn btn-info btn-back" style="margin-bottom: 20px;">⬅️ 戻る</a>
    </div>
    
    <form id="manual-form">
        <div class="form-group">
            <label>店舗名</label>
            <input type="text" name="store_name" class="form-input" placeholder="例: スーパーABC" required>
        </div>
        
        <div class="form-group">
            <label>日付</label>
            <input type="date" name="date" id="input-date" value="<?= date('Y-m-d') ?>" class="form-input" required>
        </div>

        <div class="form-group">
            <label>カテゴリ</label>
            <select name="category" class="form-select" required>
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
            <input type="number" name="total_amount" id="input-total" class="form-input" placeholder="0" required>
        </div>

        <div class="form-group">
            <label>内、消費税 (円)</label>
            <input type="number" name="tax_amount" class="form-input" placeholder="0">
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
                    <tr>
                        <td><input type="text" name="item_name[]" placeholder="商品名" class="form-input"></td>
                        <td><input type="number" name="item_price[]" placeholder="金額" class="form-input"></td>
                        <td style="text-align: center;"></td>
                    </tr>
                </tbody>
            </table>
            <button type="button" id="btn-add-item" class="btn btn-warning" style="margin-top: 10px;">＋ 行を追加</button>
        </div>

        <button type="submit" id="btn-submit" class="btn btn-success btn-full" style="margin-top: 25px;">家計簿に登録する</button>
    </form>
</div>

<script src="../src/js/manual.js"></script>

</body>
</html>
