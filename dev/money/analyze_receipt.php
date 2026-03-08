<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/class/DBC.php';

// 設定: レスポンスはJSONのみで返すためヘッダー指定
ob_clean(); // バッファにある余計な出力をクリア
header('Content-Type: application/json; charset=utf-8');

// セッションチェック (AJAX用なのでリダイレクトせずエラーを返す)
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'セッションがタイムアウトしました。再ログインしてください']);
    exit;
}

// POSTされたファイルが存在するかどうかのチェック
$uploadedFiles = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['receipt_images'])) {
        // multiple 指定時は配列形式でくる
        $files = $_FILES['receipt_images'];
        if (is_array($files['name'])) {
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $uploadedFiles[] = [
                        'tmp_name' => $files['tmp_name'][$i],
                        'type' => $files['type'][$i]
                    ];
                }
            }
        }
    } elseif (isset($_FILES['receipt_image'])) {
        // 従来通りの単一アップロード対応
        if ($_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
            $uploadedFiles[] = [
                'tmp_name' => $_FILES['receipt_image']['tmp_name'],
                'type' => $_FILES['receipt_image']['type']
            ];
        }
    }
}

if (empty($uploadedFiles)) {
    http_response_code(400);
    echo json_encode(['error' => '画像ファイルがアップロードされていないか、エラーが発生しました']);
    exit;
}

// 簡易的な形式チェック（全ファイル）
foreach ($uploadedFiles as $f) {
    $mimeType = mime_content_type($f['tmp_name']);
    if (!preg_match('/^image\//', $mimeType)) {
        http_response_code(400);
        echo json_encode(['error' => '画像ファイル以外のファイルが含まれています']);
        exit;
    }
}

// カテゴリマスターから一覧を取得（エラー回避のためテーブルがなければ空配列としてフォールバック）
$categoryListString = "食費, 日用品, 交通費, 交際費, その他"; // デフォルト
try {
    $db = new DBC();
    $categories = $db->select("
        SELECT
        `category_name`
        FROM
        `money_category_master`
        WHERE
        `delete_flag` = 0
        ORDER BY
        `sort_order` ASC"
    );
    if (!empty($categories)) {
        $categoryNames = array_column($categories, 'category_name');
        $categoryListString = implode(', ', $categoryNames);
    }
} catch (Exception $e) {
    // テーブルが存在しない場合はデフォルトのままで進める
}

// 全ファイルをBase64文字列にエンコードし、APIリクエスト用の content 配列を作成
$messageContents = [
    [
        "type" => "text",
        "text" => "以下の項目を抽出し、キーを英語にしたJSON形式で出力してください。\n・店舗名 (store_name)\n・日付 (date, YYYY-MM-DD形式)\n・合計金額 (total_amount, 数値のみ。税込の総合計)\n・消費税 (tax_amount, 数値のみ。レシートに含まれる消費税額の合計)\n・購入した商品のリスト (items, 各要素に name: 商品名と price: 価格を持たせる)\n・カテゴリ (category, 以下のマスターから最も適当なものを1つ選んでください: [ " . $categoryListString . " ])\n【重要】\n- 提供されたすべての画像（分割して撮影された1枚のレシート）から情報を統合して解析してください。\n- 「小計」「消費税」「8%対象」「軽減税率」「お釣り」「合計」「内税」などの行は、購入した商品リスト(items)の中に絶対に含めないでください。純粋な購入商品のみを出力してください。\n- JSON以外のテキストを含まないでください。"
    ]
];

foreach ($uploadedFiles as $f) {
    $imageData = file_get_contents($f['tmp_name']);
    $base64Image = base64_encode($imageData);
    $mimeType = mime_content_type($f['tmp_name']);
    
    $messageContents[] = [
        "type" => "image_url",
        "image_url" => [
            "url" => "data:{$mimeType};base64,{$base64Image}"
        ]
    ];
}

$apiKey = OPEN_AI_API_KEY;

// OpenAIのVision APIに送るデータ構築
$requestData = [
    "model" => "gpt-4o-mini",
    "messages" => [
        [
            "role" => "system",
            "content" => [
                ["type" => "text", "text" => "あなたは家計簿の入力スタッフです。渡されたレシートの画像（複数枚に分かれている場合があります）から、情報を正確に読み取って統合し、JSON形式で返答してください。"]
            ]
        ],
        [
            "role" => "user",
            "content" => $messageContents
        ]
    ],
    "max_tokens" => 1200, // 複数枚分析のため少し増やす
    "response_format" => ["type" => "json_object"]
];

$ch = curl_init("https://api.openai.com/v1/chat/completions");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer " . $apiKey
]);
curl_setopt($ch, CURLOPT_POST, true);
// タイムアウトを少し長めに設定(画像解析のため)
curl_setopt($ch, CURLOPT_TIMEOUT, 60); 
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    $error_msg = curl_error($ch);
    http_response_code(500);
    echo json_encode(['error' => 'APIリクエストに失敗しました: ' . $error_msg]);
    curl_close($ch);
    exit;
}
curl_close($ch);

if ($httpCode !== 200) {
    http_response_code($httpCode);
    echo json_encode([
        'error' => "OpenAI APIエラーが発生しました (HTTP {$httpCode})",
        'details' => json_decode($response, true)
    ]);
    exit;
}

$result = json_decode($response, true);
$aiContent = $result['choices'][0]['message']['content'] ?? '';

// AIのレスポンス（JSONテキスト）をデコード
$data = json_decode($aiContent, true);

// $data が null の場合のフォールバック
if (!$data || !is_array($data)) {
    $data = [
        'store_name' => '',
        'date' => date('Y-m-d'),
        'category' => '',
        'total_amount' => 0,
        'tax_amount' => 0,
        'items' => [],
        'error_parsing_ai' => true
    ];
}

// 画像保存が有効な場合
$savedImages = [];
if (isset($_POST['save_image']) && $_POST['save_image'] === '1') {
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }

    foreach ($uploadedFiles as $f) {
        $ext = pathinfo($f['tmp_name'], PATHINFO_EXTENSION);
        if (!$ext) {
            // mime_content_type から拡張子を推測
            $mime = mime_content_type($f['tmp_name']);
            $ext = str_replace('image/', '', $mime);
            if ($ext === 'jpeg') $ext = 'jpg';
        }
        $fileName = bin2hex(random_bytes(16)) . '.' . $ext;
        $destPath = $uploadDir . $fileName;
        
        if (@copy($f['tmp_name'], $destPath)) {
            $savedImages[] = $fileName;
        } else {
            // 書き込み失敗時はエラーログに残すか、レスポンスに含める
            $data['upload_warning'] = 'uploadsフォルダへの書き込み権限がありません。';
        }
    }
}

// 保存された画像名をデータに統合
if (!empty($savedImages)) {
    $data['saved_images'] = $savedImages;
}

// 加工したデータをJSONで出力
echo json_encode($data);
