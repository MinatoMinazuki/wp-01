<?php
if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $uploadedFile = $_FILES['file']['tmp_name'];
    $fileName = pathinfo($_FILES['file']['name'], PATHINFO_FILENAME);
    $outputFile = "uploads/{$fileName}.jpg";

    try {
        // Imagickインスタンスの生成
        $imagick = new Imagick();

        // アップロードされたHEIC画像を読み込む
        $imagick->readImage($uploadedFile);

        // 画像フォーマットをJPGに変換
        $imagick->setImageFormat('jpg');

        // 出力画像を保存
        $imagick->writeImage($outputFile);

        echo "Conversion successful! <a href='$outputFile'>Download JPG</a>";
    } catch (ImagickException $e) {
        // Imagickでエラーが発生した場合の処理
        echo "Error during conversion: " . $e->getMessage();
    }
} else {
    echo "No file uploaded or upload error.";
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title></title>
  <link rel="stylesheet" href="">
</head>
<body>
  <form action="convert.php" method="post" enctype="multipart/form-data">
    <label for="file">Upload HEIC Image:</label>
    <input type="file" name="file" id="file" accept="image/heic">
    <button type="submit">Upload and Convert</button>
  </form>
</body>
</html>

