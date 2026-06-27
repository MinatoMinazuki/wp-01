<?php
require_once __DIR__ . '/../auth/check.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../class/DBC.php';

$db = new DBC();

// 表示する年月を決定 (デフォルトは当月)
$ym = filter_input(INPUT_GET, 'ym', FILTER_SANITIZE_SPECIAL_CHARS);
if (!$ym || !preg_match('/^\d{4}-\d{2}$/', $ym)) {
    $ym = date('Y-m');
}

// 次月・前月計算
$ts = strtotime($ym . "-01");
$prevMonth = date('Y-m', strtotime('-1 month', $ts));
$nextMonth = date('Y-m', strtotime('+1 month', $ts));

// 1. 指定年月のカテゴリ別支出合計を取得 (user_id チェック追加)
$sqlCategorySummary = sprintf("
    SELECT 
        m.category, 
        SUM(m.total_amount) as amount 
    FROM `money_receipt_master` m
    WHERE m.buy_date LIKE '%s-%%' 
      AND m.delete_flag = 0 
      AND m.user_id = %d
    GROUP BY m.category
    ORDER BY amount DESC
", $ym, $userId);

$categoryData = $db->select($sqlCategorySummary);

// 2. 月間総支出
$totalExpense = 0;
foreach ($categoryData as $cat) {
    if (!$cat['category']) {
        // カテゴリ未設定分も集計には含めるが、表示名は「未設定」に
    }
    $totalExpense += (int)$cat['amount'];
}

// カテゴリ名が空の場合のケア
foreach ($categoryData as &$cat) {
    if (empty($cat['category'])) {
        $cat['category'] = '未設定';
    }
}
unset($cat);

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>家計簿支出レポート</title>
    <link rel="stylesheet" href="../src/css/common.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="../src/js/dashboard.js"></script>
    <script src="../src/js/month_selector.js"></script>
</head>
<body>

<div class="user-info">
    👤 <?= htmlspecialchars($userEmail) ?> としてログイン中
    <a href="../auth/logout.php">ログアウト</a>
</div>

<div class="container overflow-hidden">
    <h1 class="title-with-border">家計簿支出レポート</h1>
    
    <div class="text-right mb-20">
        <a href="../index.php" class="btn btn-info btn-back mb-20">⬅️ 戻る</a>
    </div>

    <div class="month-selector">
        <a href="?ym=<?= $prevMonth ?>" class="btn btn-info btn-rounded font-14 py-1 px-3">◀︎ 前月</a>
        <input type="month" value="<?= $ym ?>" class="month-input js-month-input">
        <button type="button" class="btn btn-primary month-apply-mobile js-month-apply">Go</button>
        <a href="?ym=<?= $nextMonth ?>" class="btn btn-info btn-rounded font-14 py-1 px-3">次月 ▶︎</a>
    </div>

    <div class="summary-box">
        <p class="m-0 text-muted font-14"><?= date('Y年n月', strtotime($ym.'-01')) ?> の総支出</p>
        <p class="summary-amount">¥ <?= number_format($totalExpense) ?></p>
    </div>

    <?php if ($totalExpense > 0): ?>
        <div class="chart-container">
            <canvas id="expenseChart"></canvas>
        </div>

        <h3 class="text-primary border-bottom-accent mb-15">カテゴリ別内訳</h3>
        <table class="table table-hover table-responsive">
            <thead>
                <tr>
                    <th>カテゴリ</th>
                    <th class="text-right">支出額</th>
                    <th class="text-right">割合</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categoryData as $cat): ?>
                    <tr>
                        <td data-label="カテゴリ"><?= htmlspecialchars($cat['category']) ?></td>
                        <td data-label="支出額" class="amount">¥ <?= number_format($cat['amount']) ?></td>
                        <td data-label="割合" class="text-right text-muted font-13">
                            <?= number_format(($cat['amount'] / $totalExpense) * 100, 1) ?> %
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="no-data">
            <p>この月の支出データはありません。</p>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($totalExpense > 0): ?>
        initExpenseChart(
            <?= json_encode(array_column($categoryData, 'category')) ?>,
            <?= json_encode(array_column($categoryData, 'amount')) ?>,
            [
                '#3498db', '#2ecc71', '#e74c3c', '#f1c40f', '#9b59b6', 
                '#1abc9c', '#e67e22', '#34495e', '#ecf0f1', '#95a5a6'
            ]
        );
        <?php endif; ?>
    });
</script>

</body>
</html>
