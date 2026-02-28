<?php
require_once __DIR__ . '/config.php';

// 設定: レスポンスはJSONのみで返すためヘッダー指定
header('Content-Type: application/json; charset=utf-8');

// POSTされたファイルが存在するかどうかのチェック
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['receipt_image'])) {
    http_response_code(400);
    echo json_encode(['error' => '画像ファイルがアップロードされていません']);
    exit;
}

$file = $_FILES['receipt_image'];

// エラーチェック
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'ファイルのアップロードに失敗しました (Error code: ' . $file['error'] . ')']);
    exit;
}

// 画像のMIMEタイプを判別
$mimeType = mime_content_type($file['tmp_name']);
if (!preg_match('/^image\//', $mimeType)) {
    http_response_code(400);
    echo json_encode(['error' => '画像ファイル以外のファイルが指定されました']);
    exit;
}

// ファイルをBase64文字列にエンコード
$imageData = file_get_contents($file['tmp_name']);
$base64Image = base64_encode($imageData);

$apiKey = OPEN_AI_API_KEY;

// OpenAIのVision APIに送るデータ構築
// gpt-4o-miniはVision (画像解析) にも対応しています
$requestData = [
    "model" => "gpt-4o-mini",
    "messages" => [
        [
            "role" => "system",
            "content" => [
                ["type" => "text", "text" => "あなたは家計簿の入力スタッフです。渡されたレシートの画像から、必要な情報を読み取ってJSON形式で返答してください。"]
            ]
        ],
        [
            "role" => "user",
            "content" => [
                [
                    "type" => "text",
                    "text" => "以下の項目を抽出し、キーを英語にしたJSON形式で出力してください。\n・店舗名 (store_name)\n・日付 (date, YYYY-MM-DD形式)\n・合計金額 (total_amount, 数値のみ)\n・購入した商品のリスト (items, 各要素に name: 商品名と price: 価格を持たせる)\n・カテゴリ (category, スーパー、コンビニ、飲食店など推測して設定)\nJSON以外のテキストを含まないでください。"
                ],
                [
                    "type" => "image_url",
                    "image_url" => [
                        "url" => "data:{$mimeType};base64,{$base64Image}"
                    ]
                ]
            ]
        ]
    ],
    "max_tokens" => 800,
    // JSON形式での出力を強制 (モデルがサポートしている場合)
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

// APIから返ってきた文字列(JSONのはず)をそのまま出力
echo $aiContent;
