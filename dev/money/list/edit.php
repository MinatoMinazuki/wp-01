<?php
require_once __DIR__ . '/../auth/check.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../class/DBC.php';

$db = new DBC();
$error = '';
$success = '';

// データの更新・論理削除処理 (POST時)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $receipt_id = filter_input(INPUT_POST, 'receipt_id', FILTER_VALIDATE_INT);
    
    if (!$receipt_id) {
        $error = '無効なリクエストです。';
    } else {
        if ($action === 'delete') {
            // 論理削除処理: delete_flag を 1 に更新 (user_id チェック追加)
            $sqlDeleteMaster = sprintf(
                "UPDATE `money_receipt_master` SET `delete_flag` = 1 WHERE `id` = %d AND `user_id` = %d",
                $receipt_id,
                $userId
            );
            $db->Dsql($sqlDeleteMaster);
            
            // 子テーブルも合わせて論理削除
            $sqlDeleteItem = sprintf(
                "UPDATE `money_receipt_items` SET `delete_flag` = 1 WHERE `receipt_id` = %d",
                $receipt_id
            );
            $db->Dsql($sqlDeleteItem);
            
            // 一覧へリダイレクト
            header('Location: index.php?msg=deleted');
            exit;
            
        } elseif ($action === 'update') {
            // 更新処理
            $storeName   = trim($_POST['store_name'] ?? '');
            $buyDate     = trim($_POST['date'] ?? '');
            $category    = trim($_POST['category'] ?? '');
            $totalAmount = filter_input(INPUT_POST, 'total_amount', FILTER_VALIDATE_INT) ?: 0;
            $taxAmount   = filter_input(INPUT_POST, 'tax_amount', FILTER_VALIDATE_INT) ?: 0;
            
            $itemNames  = $_POST['item_name'] ?? [];
            $itemPrices = $_POST['item_price'] ?? [];
            
            try {
                // 1. マスター情報を更新
                $sqlUpdateMaster = sprintf("
                    UPDATE `money_receipt_master` 
                    SET 
                        `store_name` = '%s',
                        `buy_date` = '%s',
                        `category` = '%s',
                        `total_amount` = %d,
                        `tax_amount` = %d
                    WHERE `id` = %d AND `user_id` = %d
                    ",
                    htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($buyDate, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($category, ENT_QUOTES, 'UTF-8'),
                    $totalAmount,
                    $taxAmount,
                    $receipt_id,
                    $userId
                );
                
                $db->Dsql($sqlUpdateMaster);
                
                // 2. 一度このレシートに紐づくアイテムをすべて[物理的ではなく]論理削除にするか、物理削除してから再作成するか。
                $sqlLogicalDelItems = sprintf(
                    "UPDATE `money_receipt_items` SET `delete_flag` = 1 WHERE `receipt_id` = %d",
                    $receipt_id
                );
                $db->Dsql($sqlLogicalDelItems);
                
                // 3. 送信されたアイテムを新規でINSERT
                $count = count($itemNames);
                for ($i = 0; $i < $count; $i++) {
                    $name = trim($itemNames[$i]);
                    $price = filter_var($itemPrices[$i], FILTER_VALIDATE_INT) ?: 0;
                    
                    if ($name !== '') {
                        $sqlInsertItem = sprintf("
                            INSERT INTO `money_receipt_items` (
                                `receipt_id`,
                                `item_name`,
                                `item_price`,
                                `delete_flag`
                            ) VALUES (
                                %d, '%s', %d, 0
                            )",
                            $receipt_id,
                            htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
                            $price
                        );
                        $db->Dsql($sqlInsertItem);
                    }
                }
                
                $success = '家計簿データが更新されました。';
                
            } catch (Exception $e) {
                $error = '更新中にエラーが発生しました: ' . $e->getMessage();
            }
        }
    }
}


// 表示データの取得 (GET時 または POST更新後)
$receipt_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'receipt_id', FILTER_VALIDATE_INT);

if (!$receipt_id) {
    die("不正なアクセスです。");
}

// 削除済(delete_flag=1)でないレシート情報を取得 (user_id チェック)
$sqlMaster = sprintf(
    "SELECT * FROM `money_receipt_master` WHERE `id` = %d AND `delete_flag` = 0 AND `user_id` = %d",
    $receipt_id,
    $userId
);
$masters = $db->select($sqlMaster);

if (empty($masters)) {
    die("指定されたデータが存在しないか、既に削除されています。");
}
$master = $masters[0];

// 紐づく商品情報を取得（delete_flag=0のものだけ）
$sqlItems = sprintf(
    "SELECT * FROM `money_receipt_items` WHERE `receipt_id` = %d AND `delete_flag` = 0 ORDER BY `id` ASC",
    $receipt_id
);
$items = $db->select($sqlItems);

// 保存された画像情報を取得
$sqlImages = sprintf(
    "SELECT file_name FROM `money_receipt_images` WHERE `receipt_id` = %d ORDER BY id ASC",
    $receipt_id
);
$savedImages = $db->select($sqlImages);

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
    <title>家計簿 詳細・編集</title>
    <link rel="stylesheet" href="../src/css/common.css">
</head>
<body>

<!-- モーダル用 -->
<div id="imageModal" class="modal">
    <span class="modal-close" onclick="document.getElementById('imageModal').style.display='none'">&times;</span>
    <img id="modalImage" class="modal-content" src="" alt="拡大画像">
</div>

<div class="user-info">
    👤 <?= htmlspecialchars($userEmail) ?> としてログイン中
    <a href="../auth/logout.php">ログアウト</a>
</div>

<div class="container overflow-hidden">
    <a href="index.php" class="btn btn-info btn-back" style="margin-bottom: 20px;">⬅️ 一覧に戻る</a>
    
    <h1 class="title-with-border">詳細・編集</h1>
    
    <?php if ($success): ?>
        <div class="message msg-success">✅ <?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="message msg-error">❌ <?= $error ?></div>
    <?php endif; ?>

    <!-- 保存された画像の表示 -->
    <?php if (!empty($savedImages)): ?>
        <div class="form-group">
            <label>保存されたレシート画像</label>
            <div class="image-gallery">
                <?php foreach ($savedImages as $img): ?>
                    <img src="../uploads/<?= htmlspecialchars($img['file_name']) ?>" 
                         alt="レシート画像" 
                         class="gallery-item"
                         onclick="showModal(this.src)">
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- 編集フォーム -->
    <form action="" method="post" id="edit-form">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="receipt_id" value="<?= htmlspecialchars($master['id']) ?>">
        
        <div class="form-group">
            <label>店舗名</label>
            <input type="text" name="store_name" value="<?= htmlspecialchars($master['store_name']) ?>" class="form-input" required>
        </div>
        
        <div class="form-group">
            <label>日付</label>
            <input type="date" name="date" value="<?= htmlspecialchars(date('Y-m-d', strtotime($master['buy_date']))) ?>" class="form-input" required>
        </div>

        <div class="form-group">
            <label>カテゴリ</label>
            <select name="category" class="form-select" required>
                <option value="">-- 選択してください --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat['category_name']) ?>" 
                        <?= $cat['category_name'] === $master['category'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['category_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>合計金額 (円)</label>
            <input type="number" name="total_amount" value="<?= htmlspecialchars($master['total_amount']) ?>" class="form-input" required>
        </div>

        <div class="form-group">
            <label>内、消費税 (円)</label>
            <input type="number" name="tax_amount" value="<?= htmlspecialchars($master['tax_amount']) ?>" class="form-input">
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
                    <?php if (empty($items)): ?>
                        <!-- 商品がない場合の空行 -->
                        <tr>
                            <td><input type="text" name="item_name[]" value="" placeholder="商品名" class="form-input" required></td>
                            <td><input type="number" name="item_price[]" value="" placeholder="金額" class="form-input" required></td>
                            <td style="text-align: center;"><button type="button" class="btn btn-danger btn-remove btn-remove-responsive" onclick="this.closest('tr').remove();" title="削除">×</button></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><input type="text" name="item_name[]" value="<?= htmlspecialchars($item['item_name']) ?>" placeholder="商品名" class="form-input" required></td>
                                <td><input type="number" name="item_price[]" value="<?= htmlspecialchars($item['item_price']) ?>" placeholder="金額" class="form-input" required></td>
                                <td style="text-align: center;"><button type="button" class="btn btn-danger btn-remove btn-remove-responsive" onclick="this.closest('tr').remove();" title="削除">×</button></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <button type="button" class="btn btn-warning" id="btn-add-item" style="margin-top: 10px;">＋ 行を追加</button>
        </div>

        <button type="submit" class="btn btn-success btn-full" style="margin-top: 15px;">この内容で更新する</button>
    </form>
    
    <hr style="margin: 40px 0 20px; border: 0; border-top: 1px solid #ddd;">
    
    <!-- 削除用フォーム -->
    <form action="" method="post" onsubmit="return confirm('本当にこの家計簿データを削除してもよろしいですか？');">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="receipt_id" value="<?= htmlspecialchars($master['id']) ?>">
        <button type="submit" class="btn btn-danger btn-full" style="opacity: 0.9;">🗑️ このレシートデータを削除する</button>
    </form>

</div>

<script src="../src/js/list_edit.js"></script>

</body>
</html>
