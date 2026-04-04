<?php
require_once __DIR__.'/../auth.php';
header('Content-Type: application/json; charset=utf-8');

$userId = (int)htmlspecialchars( $_SESSION['userId'] );

$content = isset($_POST['content']) ? htmlspecialchars( $_POST['content'] ) : '';
$content = trim( $content );

$rawTags = isset($_POST['tags']) ? htmlspecialchars( $_POST['tags'] ) : '';
$rawTags = trim( $rawTags );


if ($content === '' && empty($_FILES['image']['name'])) {
    echo json_encode(['error' => 'No content provided.']);
    exit;
}

$imageFile = null;
if (!empty($_FILES['image']['name'])) {
    if ($_FILES['image']['size'] > 10 * 1024 * 1024) {
        echo json_encode(['error' => '10MB以上の画像はアップロードできません。']);
        exit;
    }

    $uploadDir = __DIR__ . '/../uploads/';
    if(!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $filename = time() . '_' . basename($_FILES['image']['name']);
    $targetFile = $uploadDir . $filename;

    if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
        try {
            if (extension_loaded('imagick')) {
                $isLarge = filesize($targetFile) >= 1048576;
                $ext = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
                $isPng = ($ext === 'png');

                if ($isLarge || $isPng) {
                    $img = new Imagick($targetFile);
                    $format = strtoupper($img->getImageFormat());

                    if ($format === 'PNG' || $img->getImageAlphaChannel()) {
                        $bg = new Imagick();
                        $bg->newImage($img->getImageWidth(), $img->getImageHeight(), new ImagickPixel('white'));
                        $bg->compositeImage($img, Imagick::COMPOSITE_OVER, 0, 0);

                        $img->clear();
                        $img->destroy();
                        $img = $bg;

                        $img->setImageFormat('jpeg');

                        $newFilename = preg_replace('/\.[^.]+$/', '.jpg', $filename);
                        if ($newFilename === $filename) {
                            $newFilename .= '.jpg';
                        }
                        $newTargetFile = $uploadDir . $newFilename;

                        unlink($targetFile);

                        $filename = $newFilename;
                        $targetFile = $newTargetFile;
                    }

                    $img->stripImage();

                    $width = $img->getImageWidth();
                    $height = $img->getImageHeight();
                    if ($width > 1600 || $height > 1600) {
                        $img->resizeImage(1600, 1600, Imagick::FILTER_LANCZOS, 1, true);
                    }

                    $img->setImageCompression(Imagick::COMPRESSION_JPEG);
                    if ($isLarge) {
                        $img->setImageCompressionQuality(60);
                    } else {
                        $img->setImageCompressionQuality(85);
                    }

                    $img->writeImage($targetFile);
                    $img->clear();
                    $img->destroy();
                }
            }
        } catch (Exception $e) {
            // Error handling ignored to fallback
        }
        $imageFile = $filename;
    }
}

$contentEsc = $dbc->escape($content);
$imageFileEsc = $imageFile ? "'" . $dbc->escape($imageFile) . "'" : "NULL";

$sql = sprintf("
    INSERT INTO
    tweets
    (
        content,
        image_file,
        user_id,
        is_deleted,
        created_at
    ) VALUES (
        '%s',
        %s,
        %s,
        0
    )",
    $contentEsc,
    $imageFileEsc,
    $userId,
    0
);

$insertId = $dbc->Dsql($sql);

if( $insertId ){
    $tagList = [];

    if( $rawTags !== '' ){

        $rawTags = mb_convert_kana($rawTags, "s", 'UTF-8');
        $rawTags = str_replace(',', ' ', $rawTags);
        $tagsArray = preg_split('/[\s]+/', $rawTags, -1, PREG_SPLIT_NO_EMPTY);

        $uniqueTags = array_unique($tagsArray);

        foreach( $uniqueTags as $tagName ){
            $tagNameStr = trim($tagName);
            if ($tagNameStr === '') continue;

            $tagNameEsc = $dbc->escape($tagNameStr);

            $checkTagSql = sprintf("
                SELECT
                id,
                name
                FROM
                tags
                WHERE
                name = '%s'
                ",
                $tagNameEsc
            );
            $existingTag = $dbc->Dsql($checkTagSql);

            $tagId = 0;
            if( is_array($existingTag) && count($existingTag) > 0 ){
                $tagId = (int)$existingTag[0]['id'];
                $tagList[] = $existingTag[0]['name'];
            } else {
                $insertTagSql = sprintf("
                    INSERT INTO
                    tags
                    (
                        name
                    ) VALUES (
                        '%s'
                    )",
                    $tagNameEsc
                );

                $newTagId = $dbc->Dsql($insertTagSql);
                if ($newTagId) {
                    $tagId = (int)$newTagId;
                    $tagList[] = $tagNameStr;
                }
            }

            if ($tagId > 0) {
                $mappingSql = sprintf("
                    INSERT IGNORE INTO
                    tweet_tags
                    (
                        tweet_id,
                        tag_id
                    ) VALUES (
                        %s,
                        %s
                    )",
                    $insertId,
                    $tagId
                );

                $dbc->Dsql($mappingSql);
            }
        }
    }

    $selectSql = sprintf("
        SELECT
        id,
        content,
        image_file as imageFile,
        user_id as userId,
        created_at as createdAt
        FROM
        tweets
        WHERE
        id = %s
        "
        (int)$insertId
    );

    $tweet = $dbc->Dsql($selectSql);

    if( is_array($tweet) && count($tweet) > 0 ){
        $responseTweet = $tweet[0];
        $responseTweet['tags'] = $tagList;
        echo json_encode(['success' => true, 'tweet' => $responseTweet]);
    } else {
        echo json_encode(['success' => true, 'tweet' => [
            'id' => $insertId,
            'content' => $content,
            'imageFile' => $imageFile,
            'createdAt' => date('Y-m-d H:i:s'),
            'tags' => $tagList
        ]]);
    }
} else {
    echo json_encode(['error' => 'Failed to insert tweet.']);
}

?>