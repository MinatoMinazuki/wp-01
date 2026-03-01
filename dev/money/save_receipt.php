<?php
require_once __DIR__ . '/auth/check.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/class/DBC.php';

header('Content-Type: application/json; charset=utf-8');

// POSTリクエストであるかチェック
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// JSONデータとして受け取る
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => '不正なデータが送信されました']);
    exit;
}

// 必須パラメータのバリデーション
$storeName   = trim($data['store_name'] ?? '');
$buyDate     = trim($data['date'] ?? '');
$category    = trim($data['category'] ?? '');
$totalAmount = filter_var($data['total_amount'] ?? '', FILTER_VALIDATE_INT) ?: 0;
$taxAmount   = filter_var($data['tax_amount'] ?? '', FILTER_VALIDATE_INT) ?: 0;
$items       = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];

if ($buyDate === '') {
    $buyDate = date('Y-m-d'); // 日付がない場合は今日
}

try {
    $db = new DBC();
    // 本来はDBC内にトランザクションメソッドがあるのが望ましいですが、
    // ここではPDOに直接アクセスしづらいかもしれないため1つずつ実行しエラーハンドリングを行います。

    // 1. レシートマスター情報を挿入
    $sql_master = sprintf("
        INSERT INTO `money_receipt_master` (
            `user_id`,
            `store_name`,
            `buy_date`,
            `category`,
            `total_amount`,
            `tax_amount`
        ) VALUES (
            %d, '%s', '%s', '%s', %d, %d
        )",
        $userId,
        htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($buyDate, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($category, ENT_QUOTES, 'UTF-8'),
        $totalAmount,
        $taxAmount
    );

    // Dsqlメソッドは INSERT の場合 lastInsertId を返す設計になっている
    $receipt_id = $db->Dsql($sql_master);

    if (!$receipt_id) {
        throw new Exception("レシートマスター情報の保存に失敗しました");
    }

    // 2. 紐づく商品アイテム情報を挿入
    $inserted_items = 0;
    foreach ($items as $item) {
        $itemName  = trim($item['name'] ?? '');
        $itemPrice = filter_var($item['price'] ?? '', FILTER_VALIDATE_INT) ?: 0;

        if ($itemName !== '') {
            $sql_item = sprintf("
                INSERT INTO `money_receipt_items` (
                    `receipt_id`,
                    `item_name`,
                    `item_price`
                ) VALUES (
                    %d, '%s', %d
                )",
                $receipt_id,
                htmlspecialchars($itemName, ENT_QUOTES, 'UTF-8'),
                $itemPrice
            );
            $res = $db->Dsql($sql_item);
            if ($res) {
                $inserted_items++;
            }
        }
    }

    // 3. 保存されたレシート画像の紐付け
    if (isset($data['saved_images']) && is_array($data['saved_images'])) {
        foreach ($data['saved_images'] as $fileName) {
            $sql_img = sprintf("
                INSERT INTO `money_receipt_images` (
                    `receipt_id`,
                    `file_name`
                ) VALUES (
                    %d, '%s'
                )",
                $receipt_id,
                $db->escape($fileName)
            );
            $db->Dsql($sql_img);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => '登録が完了しました',
        'receipt_id' => $receipt_id,
        'inserted_items' => $inserted_items
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'データベースエラー',
        'details' => $e->getMessage()
    ]);
}
