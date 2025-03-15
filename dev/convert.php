<?php

ini_set('display_errors', "On");
ini_set('error_reporting', E_ALL);

ini_set('post_max_size', '4M');

if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {

    var_dump(mime_content_type($_FILES['file']['tmp_name']));
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

