<?php
require_once __DIR__ . '/bootstrap.php';

require_post();
require_csrf();

$userId = current_user_id();
$content = isset($_POST['content']) ? trim((string)$_POST['content']) : '';
$rawTags = isset($_POST['tags']) ? trim((string)$_POST['tags']) : '';

if ($content === '' && empty($_FILES['image']['name'])) {
    json_response(['error' => 'No content provided.'], 400);
}

$imageFile = null;

if (!empty($_FILES['image']['name'])) {
    $imageFile = save_uploaded_image($_FILES['image'], $userId);
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
    json_response(['error' => 'Failed to insert tweet.'], 500);
}

$tagList = save_tags_for_tweet($dbc, (int)$insertId, $rawTags, $userId);

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

json_response(['success' => true, 'tweet' => $tweet]);

function save_uploaded_image(array $file, int $userId): ?string
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        json_response(['error' => 'Image upload failed.'], 400);
    }

    if ($file['size'] > 10 * 1024 * 1024) {
        json_response(['error' => 'Images larger than 10MB cannot be uploaded.'], 400);
    }

    $mimeType = mime_content_type($file['tmp_name']) ?: '';
    $isHeic = is_heic_upload($file['tmp_name'], $file['name'], $mimeType);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    if (!$isHeic && !isset($extensions[$mimeType])) {
        json_response(['error' => 'Unsupported image type.'], 400);
    }

    $userUploadDir = $userId . '/';
    $uploadDir = __DIR__ . '/../uploads/' . $userUploadDir;

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)) {
        json_response(['error' => 'Failed to create upload directory.'], 500);
    }

    $extension = $isHeic ? 'heic' : $extensions[$mimeType];
    $filename = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $targetFile = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetFile)) {
        json_response(['error' => 'Failed to save uploaded image.'], 500);
    }

    if ($isHeic) {
        $convertedFile = convert_heic_to_jpg($targetFile, $uploadDir, $filename);
        if ($convertedFile === null) {
            @unlink($targetFile);
            json_response(['error' => 'Failed to convert HEIC image.'], 500);
        }

        @unlink($targetFile);
        $filename = basename($convertedFile);
        $targetFile = $convertedFile;
    }

    $optimizedFile = optimize_image($targetFile);
    if ($optimizedFile !== $targetFile) {
        $filename = basename($optimizedFile);
    }

    return $userUploadDir . $filename;
}

function is_heic_upload(string $path, string $name, string $mimeType): bool
{
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (in_array($extension, ['heic', 'heif'], true) || in_array($mimeType, ['image/heic', 'image/heif'], true)) {
        return true;
    }

    $libraryPath = __DIR__ . '/../../php-heic-to-jpg-maestro/src/HeicToJpg.php';
    if (is_file($libraryPath)) {
        require_once $libraryPath;
        return \Maestroerror\HeicToJpg::isHeic($path);
    }

    return false;
}

function convert_heic_to_jpg(string $sourcePath, string $uploadDir, string $filename): ?string
{
    $libraryPath = __DIR__ . '/../../php-heic-to-jpg-maestro/src/HeicToJpg.php';
    if (!is_file($libraryPath)) {
        return null;
    }

    require_once $libraryPath;

    $jpgName = preg_replace('/\.[^.]+$/', '.jpg', $filename);
    $jpgPath = $uploadDir . $jpgName;

    try {
        \Maestroerror\HeicToJpg::convert($sourcePath)->saveAs($jpgPath);
        return is_file($jpgPath) ? $jpgPath : null;
    } catch (Throwable $e) {
        error_log('HEIC conversion failed: ' . $e->getMessage());
        return null;
    }
}

function optimize_image(string $targetFile): string
{
    try {
        if (!extension_loaded('imagick')) {
            return $targetFile;
        }

        $isLarge = filesize($targetFile) >= 1048576;
        $ext = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
        $isPng = ($ext === 'png');

        if (!$isLarge && !$isPng) {
            return $targetFile;
        }

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
            $newTargetFile = preg_replace('/\.[^.]+$/', '.jpg', $targetFile);

            if ($newTargetFile !== $targetFile) {
                @unlink($targetFile);
                $targetFile = $newTargetFile;
            }
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
    } catch (Throwable $e) {
        error_log('Image optimization failed: ' . $e->getMessage());
    }

    return $targetFile;
}

function save_tags_for_tweet(DBC $dbc, int $tweetId, string $rawTags, int $userId): array
{
    if ($rawTags === '') {
        return [];
    }

    $normalizedTags = mb_convert_kana($rawTags, 's', 'UTF-8');
    $normalizedTags = str_replace(',', ' ', $normalizedTags);
    $tagsArray = preg_split('/[\s]+/', $normalizedTags, -1, PREG_SPLIT_NO_EMPTY);
    $uniqueTags = array_unique($tagsArray);
    $tagList = [];
    $hasTagOwner = tags_have_user_id($dbc);

    foreach ($uniqueTags as $tagName) {
        $tagName = trim($tagName);
        if ($tagName === '') {
            continue;
        }

        $existingTag = find_tag($dbc, $tagName, $userId);

        if ($existingTag !== null) {
            $tagId = (int)$existingTag['id'];
            $tagList[] = $existingTag['name'];
        } else {
            if ($hasTagOwner) {
                $tagId = (int)$dbc->insert(
                    "INSERT INTO tags (name, user_id) VALUES (:name, :user_id)",
                    ['name' => $tagName, 'user_id' => $userId]
                );
            } else {
                $tagId = (int)$dbc->insert(
                    "INSERT INTO tags (name) VALUES (:name)",
                    ['name' => $tagName]
                );
            }
            $tagList[] = $tagName;
        }

        if ($tagId > 0) {
            $dbc->execute(
                "
                INSERT IGNORE INTO tweet_tags (tweet_id, tag_id)
                VALUES (:tweet_id, :tag_id)
                ",
                [
                    'tweet_id' => $tweetId,
                    'tag_id' => $tagId,
                ]
            );
        }
    }

    return $tagList;
}

function find_tag(DBC $dbc, string $tagName, int $userId): ?array
{
    if (tags_have_user_id($dbc)) {
        return $dbc->fetchOne(
            "SELECT id, name FROM tags WHERE name = :name AND user_id = :user_id",
            ['name' => $tagName, 'user_id' => $userId]
        );
    }

    return $dbc->fetchOne(
        "SELECT id, name FROM tags WHERE name = :name",
        ['name' => $tagName]
    );
}
