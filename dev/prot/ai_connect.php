<?php

require_once "config.php";

$apiKey = OPEN_AI_API_KEY; // OpenAIのAPIキー

$word = isset($_POST["input"]) ? esc($_POST["input"]) : "楽しい";
$num = isset($_POST["num"]) ? esc($_POST["num"]) : "6";

$data = [
    "model" => "gpt-4o-mini",
    "messages" => [
        ["role" => "system", "content" => "あなたは日本語の類語辞典のように振る舞ってください"],
        ["role" => "system", "content" => "単語のみを返してください"],
        ["role" => "system", "content" => "送り仮名はいりません。できるだけ簡潔に返してください"],
        ["role" => "system", "content" => "区切りは[・]を用いてください"],
        ["role" => "user", "content" => "次の類語を{$num}個挙げてください:[{$word}]"]
    ]
];


$ch = curl_init("https://api.openai.com/v1/chat/completions");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer $apiKey"
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
$synonyms = !empty( $result["choices"][0]["message"]["content"] ) ? $result["choices"][0]["message"]["content"] : "単語または熟語で入力してください";

echo $synonyms;

function esc( $word ){
    return htmlspecialchars( $word );
}

// echo "波・浜辺・潮風・船・青";

?>