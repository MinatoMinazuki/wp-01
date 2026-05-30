<?php
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json; charset=utf-8');

require_post();
require_csrf();

$userId = (int)$_SESSION['userId'];
$content = isset($_POST['content']) ? trim((string)$_POST['content']) : '';
$rawTags = isset($_POST['tags']) ? trim((string)$_POST['tags']) : '';

if ($content === '' && empty($_FILES['image']['name'])) {
    echo json_encode(['error' => 'No content provided.']);
    exit;
}

$imageFile = null;

if (!empty($_FILES['image']['name'])) {
    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['error' => 'Image upload failed.']);
        exit;
    }

    if ($_FILES['image']['size'] > 10 * 1024 * 1024) {
        echo json_encode(['error' => 'Images larger than 10MB cannot be uploaded.']);
        exit;
    }

    $mimeType = mime_content_type($_FILES['image']['tmp_name']);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    if (!isset($extensions[$mimeType])) {
        echo json_encode(['error' => 'Unsupported image type.']);
        exit;
    }

    $userUploadDir = $userId . '/';
    $uploadDir = __DIR__ . '/../uploads/' . $userUploadDir;

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)) {
        echo json_encode(['error' => 'Failed to create upload directory.']);
        exit;
    }

    $filename = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $extensions[$mimeType];
    $targetFile = $uploadDir . $filename;

    if (!move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
        echo json_encode(['error' => 'Failed to save uploaded image.']);
        exit;
    }

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
                $img->setImageCompressionQuality($isLarge ? 60 : 85);
                $img->writeImage($targetFile);
                $img->clear();
                $img->destroy();
            }
        }
    } catch (Exception $e) {
        // Keep the original uploaded image if optimization fails.
    }

    $imageFile = $userUploadDir . $filename;
}

$insertId = $dbc->insert(
    "
    INSERT INTO tweets (content, image_file, user_id, is_deleted)
    VALUES (:content, :image_file, :user_id, 0)
    ",
    [
        'content' => $content,
        'image_file' => $imageFile,
        'user_id' => $userId,
    ]
);

if (!$insertId) {
    echo json_encode(['error' => 'Failed to insert tweet.']);
    exit;
}

$tagList = [];

if ($rawTags !== '') {
    $normalizedTags = mb_convert_kana($rawTags, 's', 'UTF-8');
    $normalizedTags = str_replace(',', ' ', $normalizedTags);
    $tagsArray = preg_split('/[\s]+/', $normalizedTags, -1, PREG_SPLIT_NO_EMPTY);
    $uniqueTags = array_unique($tagsArray);

    foreach ($uniqueTags as $tagName) {
        $tagName = trim($tagName);
        if ($tagName === '') {
            continue;
        }

        $existingTag = $dbc->fetchOne(
            "SELECT id, name FROM tags WHERE name = :name",
            ['name' => $tagName]
        );

        if ($existingTag !== null) {
            $tagId = (int)$existingTag['id'];
            $tagList[] = $existingTag['name'];
        } else {
            $tagId = (int)$dbc->insert(
                "INSERT INTO tags (name) VALUES (:name)",
                ['name' => $tagName]
            );
            $tagList[] = $tagName;
        }

        if ($tagId > 0) {
            $dbc->execute(
                "
                INSERT IGNORE INTO tweet_tags (tweet_id, tag_id)
                VALUES (:tweet_id, :tag_id)
                ",
                [
                    'tweet_id' => (int)$insertId,
                    'tag_id' => $tagId,
                ]
            );
        }
    }
}

$tweet = $dbc->fetchOne(
    "
    SELECT
        id,
        content,
        image_file AS imageFile,
        user_id AS userId,
        created_at AS createdAt
    FROM tweets
    WHERE id = :id
    ",
    ['id' => (int)$insertId]
);

if ($tweet === null) {
    $tweet = [
        'id' => (int)$insertId,
        'content' => $content,
        'imageFile' => $imageFile,
        'userId' => $userId,
        'createdAt' => date('Y-m-d H:i:s'),
    ];
}

$tweet['tags'] = $tagList;

echo json_encode(['success' => true, 'tweet' => $tweet]);
