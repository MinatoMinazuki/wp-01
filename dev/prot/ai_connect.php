<?php

require_once "config.php";
require_once __DIR__."/class/DBC.php";

$dbc = new DBC();

$apiKey = OPEN_AI_API_KEY; // OpenAIのAPIキー

$word = isset($_POST["input"]) ? esc($_POST["input"]) : "楽しい";
$num = isset($_POST["num"]) ? esc($_POST["num"]) : "6";

$data = [
    "model" => "gpt-4o-mini",
    "messages" => [
        ["role" => "system", "content" => "あなたは日本語の類語辞典のように振る舞ってください"],
        ["role" => "system", "content" => "単語と一文の解説を返してください"],
        ["role" => "system", "content" => "送り仮名はいりません。できるだけ簡潔に返してください"],
        ["role" => "system", "content" => "単語のはじめに[・]を、単語と解説の区切りは[：]を用いてください"],
        ["role" => "system", "content" => "単語ごとに改行を入れてください"],
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

$promptTokens = $result["usage"]["prompt_tokens"];
$completionTokens = $result["usage"]["completion_tokens"];

$promptTokens = 125;
$completionTokens = 125;

$promptRate = PROMPT_RATE;
$completionRate = COMPLETION_RATE;

$cost = ($promptTokens / 1000 * $promptRate) + ($completionTokens / 1000 * $completionRate);

$resSql = sprintf("
        INSERT INTO `ai_response_log`
            (
                `response_id`,
                `object_type`,
                `response_created`,
                `ai_model`,
                `raw_request`,
                `raw_response`,
                `prompt_tokens`,
                `completion_tokens`,
                `total_tokens`,
                `system_fingerprint`,
                `cost`
            )
        VALUES
            (
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s'
            )
        ",
        $result["id"],
        $result["object"],
        date('Y-m-d H:i:s', $result["created"]),
        $result["model"],
        json_encode($data),
        json_encode($result["choices"]),
        $result["usage"]["prompt_tokens"],
        $result["usage"]["completion_tokens"],
        $result["usage"]["total_tokens"],
        $result["system_fingerprint"],
        number_format($cost, 8)
    );

$dbc->Dsql($resSql);

foreach ($result["choices"] as $key => $choice) {
    $choicesSql = sprintf("
            INSERT INTO `ai_choices`
                (
                    `request_id`,
                    `choices_index`,
                    `role`,
                    `content`,
                    `finish_reason`
                )
            VALUES
                (
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s'
                )
            ",
            $result["id"],
            $choice["index"],
            $choice["message"]["role"],
            $choice["message"]["content"],
            $choice["finish_reason"]
        );

    $dbc->Dsql($choicesSql);
}


$synonyms = !empty( $result["choices"][0]["message"]["content"] ) ? $result["choices"][0]["message"]["content"] : "単語または熟語で入力してください";

echo $synonyms;

function esc( $word ){
    return htmlspecialchars( $word );
}

// echo "波・浜辺・潮風・船・青";

?>