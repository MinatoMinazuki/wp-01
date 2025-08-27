<?php

phpinfo();

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