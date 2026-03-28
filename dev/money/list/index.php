<?php
require_once __DIR__ . '/../auth/check.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../class/DBC.php';

$db = new DBC();

// 表示対象の年月を取得（GETパラメータ ym がなければ現在の年月）
$ym = $_GET['ym'] ?? date('Y-m');

// 1. 各カテゴリの合計金額を取得 (user_id でフィルタ)
$sqlCategoryTotal = sprintf("
    SELECT category, SUM(total_amount) AS total 
    FROM money_receipt_master 
    WHERE buy_date LIKE '%s%%' 
      AND delete_flag = 0 
      AND user_id = %d
    GROUP BY category
", $ym, $userId);
$categoryTotals = $db->select($sqlCategoryTotal);

// 2. レシート一覧を取得（子テーブルの商品数もカウント, user_id でフィルタ）
$sqlReceipts = sprintf("
    SELECT 
        m.id, 
        m.store_name, 
        m.buy_date, 
        m.category, 
        m.total_amount, 
        m.tax_amount,
        (SELECT COUNT(*) FROM money_receipt_items i WHERE i.receipt_id = m.id AND i.delete_flag = 0) as item_count,
        (SELECT COUNT(*) FROM money_receipt_images img WHERE img.receipt_id = m.id) as image_count
    FROM money_receipt_master m 
    WHERE m.buy_date LIKE '%s%%' 
      AND m.delete_flag = 0 
      AND m.user_id = %d
    ORDER BY m.buy_date DESC, m.id DESC
", $ym, $userId);
$receipts = $db->select($sqlReceipts);

$msg = $_GET['msg'] ?? '';

// 前月・次月の計算
$prevMonth = date('Y-m', strtotime($ym . '-01 -1 month'));
$nextMonth = date('Y-m', strtotime($ym . '-01 +1 month'));
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>家計簿データ一覧</title>
    <link rel="stylesheet" href="../src/css/common.css">
</head>
<body>

<div class="user-info">
    👤 <?= htmlspecialchars($userEmail) ?> としてログイン中
    <a href="../auth/logout.php">ログアウト</a>
</div>

<div class="container">
    <h1 class="title-with-border">家計簿データ一覧</h1>
    
    <?php if ($msg === 'deleted'): ?>
        <div class="message msg-success">🗑️ レシートデータを削除しました。</div>
    <?php endif; ?>

    <div class="header-actions" style="display: flex; justify-content: flex-end; gap: 10px; margin-bottom: 20px;">
        <a href="../manual/index.php" class="btn btn-primary" style="font-size: 14px;">✍️ 手入力</a>
        <a href="../dashboard/index.php?ym=<?= $ym ?>" class="btn btn-accent" style="font-size: 14px;">📊 レポート</a>
        <a href="../index.php" class="btn btn-info" style="font-size: 14px;">＋ レシート登録</a>
    </div>

    <div class="month-selector">
        <a href="?ym=<?= $prevMonth ?>" class="btn btn-info" style="border-radius: 20px; font-size: 14px; padding: 6px 15px;">◀︎ 前月</a>
        <input type="month" value="<?= $ym ?>" class="month-input" onchange="location.href='?ym=' + this.value">
        <a href="?ym=<?= $nextMonth ?>" class="btn btn-info" style="border-radius: 20px; font-size: 14px; padding: 6px 15px;">次月 ▶︎</a>
    </div>

    <?php if (empty($receipts)): ?>
        <div class="no-data">
            <p>登録された家計簿データはまだありません。</p>
        </div>
    <?php else: ?>
        <table class="table table-hover table-responsive">
            <thead>
                <tr>
                    <th>日付</th>
                    <th>店舗名</th>
                    <th>カテゴリ</th>
                    <th style="text-align: center;">商品数</th>
                    <th style="text-align: right;">合計金額</th>
                    <th style="text-align: center;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($receipts as $r): ?>
                    <tr>
                        <td data-label="日付"><?= htmlspecialchars(date('Y/m/d', strtotime($r['buy_date']))) ?></td>
                        <td data-label="店舗名">
                            <?= htmlspecialchars($r['store_name']) ?>
                            <?php if ($r['image_count'] > 0): ?>
                                <span title="画像あり">📸</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="カテゴリ"><?= htmlspecialchars($r['category'] ?: '（未設定）') ?></td>
                        <td data-label="商品数" style="text-align: center;"><?= htmlspecialchars($r['item_count']) ?> 点</td>
                        <td data-label="合計金額" class="amount">¥ <?= number_format($r['total_amount']) ?></td>
                        <td data-label="操作" style="text-align: center;">
                            <a href="edit.php?id=<?= $r['id'] ?>" class="btn btn-success btn-responsive-full">詳細・編集</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</body>
</html>
