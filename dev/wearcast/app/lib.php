<?php

declare(strict_types=1);

function wc_root_path(string $path = ''): string
{
    $base = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
    return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

function wc_base_url(): string
{
    static $baseUrl;

    if ($baseUrl !== null) {
        return $baseUrl;
    }

    $rootPath = str_replace('\\', '/', wc_root_path());
    $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'])) : '';

    if ($documentRoot !== '' && str_starts_with($rootPath, $documentRoot)) {
        $baseUrl = rtrim(str_replace('\\', '/', substr($rootPath, strlen($documentRoot))), '/');
    } else {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/wearcast/index.php');
        $baseUrl = preg_replace('~/[^/]+(?:/[^/]+)?$~', '', $scriptName) ?: '/wearcast';
    }

    return $baseUrl === '' ? '/' : $baseUrl;
}

function wc_url(string $path = ''): string
{
    $baseUrl = rtrim(wc_base_url(), '/');
    $path = ltrim($path, '/');
    return $path === '' ? $baseUrl : $baseUrl . '/' . $path;
}

function wc_load_config(): array
{
    $configPath = wc_root_path('config.php');
    if (!is_file($configPath)) {
        return [];
    }

    $config = require $configPath;
    return is_array($config) ? $config : [];
}

function wc_connect_db(array $db): ?PDO
{
    if (empty($db['dsn']) || (!isset($db['user']) && !array_key_exists('user', $db))) {
        return null;
    }

    try {
        return new PDO(
            (string) $db['dsn'],
            (string) ($db['user'] ?? ''),
            (string) ($db['password'] ?? ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    } catch (Throwable) {
        return null;
    }
}

function wc_to_utf8(?string $value): string
{
    $value = (string) $value;
    if ($value === '' || !function_exists('mb_check_encoding') || mb_check_encoding($value, 'UTF-8')) {
        return $value;
    }

    $converted = @mb_convert_encoding($value, 'UTF-8', 'SJIS-win');
    return is_string($converted) ? $converted : $value;
}

function wc_h(?string $value): string
{
    return htmlspecialchars(wc_to_utf8($value), ENT_QUOTES, 'UTF-8');
}

function wc_flash(string $type, string $message): void
{
    $_SESSION['wearcast_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function wc_take_flash(): ?array
{
    $flash = $_SESSION['wearcast_flash'] ?? null;
    unset($_SESSION['wearcast_flash']);
    return is_array($flash) ? $flash : null;
}

function wc_csrf_token(): string
{
    if (empty($_SESSION['wearcast_csrf'])) {
        $_SESSION['wearcast_csrf'] = bin2hex(random_bytes(16));
    }

    return (string) $_SESSION['wearcast_csrf'];
}

function wc_require_csrf(): void
{
    $posted = $_POST['csrf_token'] ?? '';
    if (!hash_equals(wc_csrf_token(), (string) $posted)) {
        http_response_code(422);
        exit('Invalid CSRF token.');
    }
}

function wc_user_token(): string
{
    $cookieName = 'wearcast_user_token';
    $token = $_COOKIE[$cookieName] ?? '';

    if (!preg_match('/^[a-f0-9]{32}$/', (string) $token)) {
        $token = bin2hex(random_bytes(16));
        setcookie($cookieName, $token, [
            'expires' => time() + (86400 * 365 * 2),
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[$cookieName] = $token;
    }

    return (string) $token;
}

function wc_resolve_user(?PDO $pdo): array
{
    $token = wc_user_token();

    if (!$pdo) {
        $_SESSION['wearcast_demo_user'] = $_SESSION['wearcast_demo_user'] ?? [
            'id' => 1,
            'token' => $token,
            'email' => '',
        ];
        return $_SESSION['wearcast_demo_user'];
    }

    $statement = $pdo->prepare('SELECT * FROM wearcast_users WHERE token = :token LIMIT 1');
    $statement->execute(['token' => $token]);
    $user = $statement->fetch();
    if ($user) {
        return $user;
    }

    $insert = $pdo->prepare('INSERT INTO wearcast_users (token, email) VALUES (:token, NULL)');
    $insert->execute(['token' => $token]);

    return [
        'id' => (int) $pdo->lastInsertId(),
        'token' => $token,
        'email' => null,
    ];
}

function wc_default_location(): array
{
    return [
        'id' => 'demo-tokyo',
        'label' => '東京都 / 東京地方',
        'prefecture_name' => '東京都',
        'region_name' => '東京地方',
        'office_code' => '130000',
        'area_code' => '130010',
        'lat' => '35.6762',
        'lng' => '139.6503',
        'is_primary' => 1,
        'sort_order' => 1,
    ];
}

function wc_placeholder_location(): array
{
    $location = wc_default_location();
    $location['id'] = '';
    return $location;
}

function wc_get_locations(?PDO $pdo, array $user): array
{
    if (!$pdo) {
        if (empty($_SESSION['wearcast_demo_locations'])) {
            $_SESSION['wearcast_demo_locations'] = [wc_default_location()];
        }
        return array_values($_SESSION['wearcast_demo_locations']);
    }

    $statement = $pdo->prepare('SELECT * FROM wearcast_locations WHERE user_id = :user_id ORDER BY sort_order ASC, id ASC');
    $statement->execute(['user_id' => $user['id']]);
    $rows = $statement->fetchAll();

    return $rows ?: [wc_placeholder_location()];
}

function wc_get_active_location(array $locations): array
{
    $requested = (string) ($_GET['location'] ?? '');
    foreach ($locations as $location) {
        if ($requested !== '' && (string) $location['id'] === $requested) {
            return $location;
        }
    }

    foreach ($locations as $location) {
        if (!empty($location['is_primary'])) {
            return $location;
        }
    }

    return $locations[0] ?? wc_default_location();
}

function wc_storage_path(string $path = ''): string
{
    $base = wc_root_path('storage');
    if (!is_dir($base)) {
        @mkdir($base, 0775, true);
    }

    return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

function wc_fetch_json_cached(string $url, string $cacheKey, int $ttlSeconds = 1800): ?array
{
    $cacheDir = wc_storage_path('cache');
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0775, true);
    }

    $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . $cacheKey . '.json';
    if (is_file($cacheFile) && (time() - filemtime($cacheFile) < $ttlSeconds)) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached)) {
            return $cached;
        }
    }

    $body = null;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'Wearcast/1.0',
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'header' => "User-Agent: Wearcast/1.0\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
    }

    if (!is_string($body) || $body === '') {
        if (is_file($cacheFile)) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);
            return is_array($cached) ? $cached : null;
        }
        return null;
    }

    file_put_contents($cacheFile, $body);
    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : null;
}

function wc_fetch_text_cached(string $url, string $cacheKey, int $ttlSeconds = 300): ?string
{
    $cacheDir = wc_storage_path('cache');
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0775, true);
    }

    $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . $cacheKey . '.txt';
    if (is_file($cacheFile) && (time() - filemtime($cacheFile) < $ttlSeconds)) {
        return (string) file_get_contents($cacheFile);
    }

    $body = null;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'Wearcast/1.0',
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'header' => "User-Agent: Wearcast/1.0\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
    }

    if (!is_string($body) || trim($body) === '') {
        return is_file($cacheFile) ? (string) file_get_contents($cacheFile) : null;
    }

    file_put_contents($cacheFile, $body);
    return $body;
}

function wc_degrees_from_jma_coord(mixed $coord): ?float
{
    if (is_array($coord) && isset($coord[0], $coord[1])) {
        return (float) $coord[0] + ((float) $coord[1] / 60.0);
    }
    if (is_numeric($coord)) {
        return (float) $coord;
    }
    return null;
}

function wc_amedas_map_timestamp(string $latestTime): ?string
{
    try {
        $date = new DateTimeImmutable(trim($latestTime));
        return $date->setTimezone(new DateTimeZone('Asia/Tokyo'))->format('YmdHi00');
    } catch (Throwable) {
        return null;
    }
}

function wc_current_temperature(array $location): ?float
{
    $lat = isset($location['lat']) && is_numeric($location['lat']) ? (float) $location['lat'] : null;
    $lng = isset($location['lng']) && is_numeric($location['lng']) ? (float) $location['lng'] : null;
    if ($lat === null || $lng === null) {
        return null;
    }

    $latestTime = wc_fetch_text_cached('https://www.jma.go.jp/bosai/amedas/data/latest_time.txt', 'amedas_latest_time', 300);
    $timestamp = $latestTime ? wc_amedas_map_timestamp($latestTime) : null;
    if ($timestamp === null) {
        return null;
    }

    $map = wc_fetch_json_cached(
        'https://www.jma.go.jp/bosai/amedas/data/map/' . rawurlencode($timestamp) . '.json',
        'amedas_map_' . $timestamp,
        300
    );
    $stations = wc_fetch_json_cached('https://www.jma.go.jp/bosai/amedas/const/amedastable.json', 'amedas_table', 86400);
    if (!$map || !$stations) {
        return null;
    }

    $nearestTemp = null;
    $nearestDistance = PHP_FLOAT_MAX;
    foreach ($stations as $code => $station) {
        $temp = $map[$code]['temp'][0] ?? null;
        if (!is_numeric($temp)) {
            continue;
        }

        $stationLat = wc_degrees_from_jma_coord($station['lat'] ?? null);
        $stationLng = wc_degrees_from_jma_coord($station['lon'] ?? null);
        if ($stationLat === null || $stationLng === null) {
            continue;
        }

        $distance = (($stationLat - $lat) ** 2) + ((($stationLng - $lng) * cos(deg2rad($lat))) ** 2);
        if ($distance < $nearestDistance) {
            $nearestDistance = $distance;
            $nearestTemp = (float) $temp;
        }
    }

    return $nearestTemp;
}

function wc_office_options(): array
{
    $data = wc_fetch_json_cached('https://www.jma.go.jp/bosai/common/const/area.json', 'areas', 86400);
    if (!$data || empty($data['offices']) || !is_array($data['offices'])) {
        return [
            ['code' => '130000', 'name' => '東京都'],
            ['code' => '270000', 'name' => '大阪府'],
            ['code' => '400000', 'name' => '福岡県'],
        ];
    }

    $offices = [];
    foreach ($data['offices'] as $code => $office) {
        $offices[] = [
            'code' => (string) $code,
            'name' => (string) ($office['name'] ?? $code),
        ];
    }

    usort($offices, static fn (array $a, array $b): int => strcmp($a['code'], $b['code']));
    return $offices;
}

function wc_area_options(string $officeCode): array
{
    if ($officeCode === '') {
        return [];
    }

    $forecast = wc_fetch_json_cached(
        'https://www.jma.go.jp/bosai/forecast/data/forecast/' . rawurlencode($officeCode) . '.json',
        'forecast_' . $officeCode,
        1800
    );

    $areas = $forecast[0]['timeSeries'][0]['areas'] ?? [];
    if (!$areas) {
        $fallback = [
            '130000' => [
                ['code' => '130010', 'name' => '東京地方', 'index' => 0],
            ],
            '270000' => [
                ['code' => '270000', 'name' => '大阪府', 'index' => 0],
            ],
            '400000' => [
                ['code' => '400010', 'name' => '福岡地方', 'index' => 0],
                ['code' => '400020', 'name' => '北九州地方', 'index' => 1],
                ['code' => '400030', 'name' => '筑豊地方', 'index' => 2],
                ['code' => '400040', 'name' => '筑後地方', 'index' => 3],
            ],
        ];
        return $fallback[$officeCode] ?? [];
    }

    $results = [];
    foreach ($areas as $index => $row) {
        $results[] = [
            'code' => (string) ($row['area']['code'] ?? ''),
            'name' => (string) ($row['area']['name'] ?? ''),
            'index' => $index,
        ];
    }

    return $results;
}

function wc_weather_group(?string $weatherCode, string $weatherText = ''): string
{
    $code = trim((string) $weatherCode);
    if ($code !== '') {
        $head = $code[0];
        if ($head === '1') {
            return 'sunny';
        }
        if ($head === '2') {
            return 'cloudy';
        }
        if ($head === '3' || $head === '4') {
            return 'rainy';
        }
    }

    if (str_contains($weatherText, wc_to_utf8('晴'))) {
        return 'sunny';
    }
    if (str_contains($weatherText, wc_to_utf8('雨')) || str_contains($weatherText, wc_to_utf8('雪'))) {
        return 'rainy';
    }

    return 'cloudy';
}

function wc_time_period(): string
{
    $hour = (int) date('G');
    if ($hour >= 5 && $hour <= 10) {
        return 'morning';
    }
    if ($hour >= 11 && $hour <= 16) {
        return 'day';
    }

    return 'night';
}

function wc_today_forecast(array $location): array
{
    $officeCode = (string) ($location['office_code'] ?? '');
    $areaCode = (string) ($location['area_code'] ?? '');
    if ($officeCode === '' || $areaCode === '') {
        return wc_demo_forecast($location);
    }

    $forecast = wc_fetch_json_cached(
        'https://www.jma.go.jp/bosai/forecast/data/forecast/' . rawurlencode($officeCode) . '.json',
        'forecast_' . $officeCode,
        1800
    );
    if (!$forecast || empty($forecast[0]['timeSeries'])) {
        return wc_demo_forecast($location);
    }

    $series = $forecast[0]['timeSeries'];
    $weatherAreas = $series[0]['areas'] ?? [];
    $popAreas = $series[1]['areas'] ?? [];
    $tempAreas = $series[2]['areas'] ?? [];
    $weatherIndex = 0;
    $weatherRow = $weatherAreas[0] ?? null;

    foreach ($weatherAreas as $index => $row) {
        if ((string) ($row['area']['code'] ?? '') === $areaCode) {
            $weatherIndex = $index;
            $weatherRow = $row;
            break;
        }
    }
    if (!$weatherRow) {
        return wc_demo_forecast($location);
    }

    $popRow = null;
    foreach ($popAreas as $row) {
        if ((string) ($row['area']['code'] ?? '') === $areaCode) {
            $popRow = $row;
            break;
        }
    }
    $popRow = $popRow ?: ($popAreas[$weatherIndex] ?? null);
    $tempRow = $tempAreas[$weatherIndex] ?? ($tempAreas[0] ?? null);

    $weatherCode = (string) ($weatherRow['weatherCodes'][0] ?? '');
    $weatherText = (string) ($weatherRow['weathers'][0] ?? wc_to_utf8('くもり'));
    $pops = array_map('intval', array_filter($popRow['pops'] ?? [], static fn ($value): bool => $value !== ''));
    $precip = $pops ? max(array_slice($pops, 0, 4)) : 20;
    $temps = array_values(array_map('floatval', array_filter($tempRow['temps'] ?? [], static fn ($value): bool => $value !== '')));
    $todayTemps = array_slice($temps, 0, 4);
    $tempMin = $todayTemps ? min($todayTemps) : 18.0;
    $tempMax = $todayTemps ? max($todayTemps) : max($tempMin + 4.0, 24.0);
    $weatherGroup = wc_weather_group($weatherCode, $weatherText);

    return [
        'location_label' => (string) ($location['label'] ?? (($location['prefecture_name'] ?? '') . ' / ' . ($location['region_name'] ?? ''))),
        'report_datetime' => (string) ($forecast[0]['reportDatetime'] ?? date(DATE_ATOM)),
        'weather_code' => $weatherCode,
        'weather_label' => $weatherText,
        'weather_group' => $weatherGroup,
        'temp_current' => wc_current_temperature($location),
        'temp_min' => min($tempMin, $tempMax),
        'temp_max' => max($tempMin, $tempMax),
        'precip' => $precip,
        'umbrella_needed' => $precip >= 40 || $weatherGroup === 'rainy',
    ];
}

function wc_demo_forecast(array $location): array
{
    return [
        'location_label' => (string) ($location['label'] ?? wc_to_utf8('東京都 / 東京地方')),
        'report_datetime' => date(DATE_ATOM),
        'weather_code' => '101',
        'weather_label' => wc_to_utf8('晴れ 時々 くもり'),
        'weather_group' => 'sunny',
        'temp_current' => null,
        'temp_min' => 19.0,
        'temp_max' => 27.0,
        'precip' => 20,
        'umbrella_needed' => false,
    ];
}

function wc_outfit_recommendation(float $tempMax, float $tempMin, string $weatherGroup): array
{
    $anchor = (int) round((($tempMax + $tempMin) / 2) + ($weatherGroup === 'rainy' ? -1 : 0));

    if ($anchor >= 28) {
        return [
            'key' => 'short-sleeve',
            'label' => wc_to_utf8('半袖'),
            'headline' => wc_to_utf8('半袖'),
            'detail' => wc_to_utf8('日中は軽めで十分。冷房対策だけ足せると安心。'),
        ];
    }
    if ($anchor >= 24) {
        return [
            'key' => 'short-sleeve-light',
            'label' => wc_to_utf8('半袖 + 薄手の羽織'),
            'headline' => wc_to_utf8('半袖 + 薄手の羽織'),
            'detail' => wc_to_utf8('半袖を軸に、朝晩だけ羽織れる形が合います。'),
        ];
    }
    if ($anchor >= 20) {
        return [
            'key' => 'long-sleeve',
            'label' => wc_to_utf8('長袖'),
            'headline' => wc_to_utf8('長袖'),
            'detail' => wc_to_utf8('長袖一枚で過ごしやすい気温です。'),
        ];
    }
    if ($anchor >= 16) {
        return [
            'key' => 'light-outer',
            'label' => wc_to_utf8('長袖 + ライトアウター'),
            'headline' => wc_to_utf8('長袖 + ライトアウター'),
            'detail' => wc_to_utf8('薄手の上着があると夜まで安定します。'),
        ];
    }
    if ($anchor >= 11) {
        return [
            'key' => 'coat',
            'label' => wc_to_utf8('コート'),
            'headline' => wc_to_utf8('コート'),
            'detail' => wc_to_utf8('外ではコート前提。中は調整しやすく。'),
        ];
    }

    return [
        'key' => 'down-coat',
        'label' => wc_to_utf8('ダウンコート'),
        'headline' => wc_to_utf8('ダウンコート'),
        'detail' => wc_to_utf8('防寒優先。風がある日は小物も足したい日です。'),
    ];
}

function wc_get_records(?PDO $pdo, array $user): array
{
    if (!$pdo) {
        $_SESSION['wearcast_demo_records'] = $_SESSION['wearcast_demo_records'] ?? [];
        return array_values($_SESSION['wearcast_demo_records']);
    }

    $statement = $pdo->prepare(
        'SELECT r.*, l.label AS location_label
         FROM wearcast_records r
         LEFT JOIN wearcast_locations l ON l.id = r.location_id
         WHERE r.user_id = :user_id
         ORDER BY r.record_date DESC, r.updated_at DESC'
    );
    $statement->execute(['user_id' => $user['id']]);
    return $statement->fetchAll();
}

function wc_find_similar_record(array $records, array $forecast): ?array
{
    if (!$records) {
        return null;
    }

    $sameWeatherWithinOne = [];
    $withinOne = [];
    $sameWeather = [];

    foreach ($records as $record) {
        $maxDiff = abs((float) $record['temp_max'] - (float) $forecast['temp_max']);
        $minDiff = abs((float) $record['temp_min'] - (float) $forecast['temp_min']);
        $sameGroup = (string) $record['weather_group'] === (string) $forecast['weather_group'];
        $record['_score'] = $maxDiff + $minDiff;

        if ($sameGroup) {
            $sameWeather[] = $record;
        }
        if ($maxDiff <= 1.0 && $minDiff <= 1.0) {
            $withinOne[] = $record;
            if ($sameGroup) {
                $sameWeatherWithinOne[] = $record;
            }
        }
    }

    $pool = $sameWeatherWithinOne ?: ($withinOne ?: ($sameWeather ?: $records));
    usort($pool, static function (array $left, array $right): int {
        $score = ($left['_score'] <=> $right['_score']);
        return $score !== 0 ? $score : strcmp((string) $right['record_date'], (string) $left['record_date']);
    });

    return $pool[0] ?? null;
}

function wc_save_locations(?PDO $pdo, array $user, array $payload, string $email): void
{
    $locations = [];
    foreach ($payload as $index => $row) {
        $officeCode = trim((string) ($row['office_code'] ?? ''));
        $areaCode = trim((string) ($row['area_code'] ?? ''));
        if ($officeCode === '' || $areaCode === '') {
            continue;
        }

        $prefectureName = trim((string) ($row['prefecture_name'] ?? ''));
        $regionName = trim((string) ($row['region_name'] ?? ''));
        $locations[] = [
            'label' => $prefectureName . ' / ' . $regionName,
            'prefecture_name' => $prefectureName,
            'region_name' => $regionName,
            'office_code' => $officeCode,
            'area_code' => $areaCode,
            'lat' => ($row['lat'] ?? '') !== '' ? $row['lat'] : null,
            'lng' => ($row['lng'] ?? '') !== '' ? $row['lng'] : null,
            'is_primary' => $index === 0 ? 1 : 0,
            'sort_order' => $index + 1,
        ];
    }

    if (!$locations) {
        $locations[] = wc_default_location();
    }

    if (!$pdo) {
        $_SESSION['wearcast_demo_user']['email'] = $email;
        $_SESSION['wearcast_demo_locations'] = $locations;
        return;
    }

    $pdo->beginTransaction();
    $userStatement = $pdo->prepare('UPDATE wearcast_users SET email = :email WHERE id = :id');
    $userStatement->execute([
        'email' => $email !== '' ? $email : null,
        'id' => $user['id'],
    ]);

    $deleteStatement = $pdo->prepare('DELETE FROM wearcast_locations WHERE user_id = :user_id');
    $deleteStatement->execute(['user_id' => $user['id']]);

    $insertStatement = $pdo->prepare(
        'INSERT INTO wearcast_locations
        (user_id, label, prefecture_name, region_name, office_code, area_code, lat, lng, is_primary, sort_order)
        VALUES
        (:user_id, :label, :prefecture_name, :region_name, :office_code, :area_code, :lat, :lng, :is_primary, :sort_order)'
    );

    foreach ($locations as $location) {
        $insertStatement->execute([
            'user_id' => $user['id'],
            'label' => $location['label'],
            'prefecture_name' => $location['prefecture_name'],
            'region_name' => $location['region_name'],
            'office_code' => $location['office_code'],
            'area_code' => $location['area_code'],
            'lat' => $location['lat'],
            'lng' => $location['lng'],
            'is_primary' => $location['is_primary'],
            'sort_order' => $location['sort_order'],
        ]);
    }

    $pdo->commit();
}

function wc_uploaded_image_path(string $token): ?string
{
    if (empty($_FILES['photo']) || !is_array($_FILES['photo']) || (int) $_FILES['photo']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES['photo'];
    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException(wc_to_utf8('画像のアップロードに失敗しました。'));
    }
    if ((int) $file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException(wc_to_utf8('画像は 2MB までです。'));
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($file['tmp_name']);
    $map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($map[$mime])) {
        throw new RuntimeException(wc_to_utf8('JPG / PNG / WebP のみ対応しています。'));
    }

    $relativeDir = 'uploads/' . $token;
    $absoluteDir = wc_storage_path($relativeDir);
    if (!is_dir($absoluteDir)) {
        mkdir($absoluteDir, 0775, true);
    }

    $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $map[$mime];
    $absolutePath = $absoluteDir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
        throw new RuntimeException(wc_to_utf8('画像を保存できませんでした。'));
    }

    return 'storage/' . $relativeDir . '/' . $filename;
}

function wc_save_record(?PDO $pdo, array $user, array $payload): void
{
    $locationId = $payload['location_id'] ?? '';
    $locationId = ctype_digit((string) $locationId) ? (string) $locationId : null;
    $record = [
        'id' => $payload['id'] ?? '',
        'location_id' => $locationId,
        'record_date' => $payload['record_date'],
        'weather_group' => $payload['weather_group'],
        'weather_label' => $payload['weather_label'],
        'weather_code' => $payload['weather_code'],
        'temp_max' => (float) $payload['temp_max'],
        'temp_min' => (float) $payload['temp_min'],
        'outfit_category' => $payload['outfit_category'],
        'comfort_vote' => $payload['comfort_vote'],
        'comment_text' => $payload['comment_text'],
        'free_note' => $payload['free_note'],
        'image_path' => $payload['image_path'],
    ];

    if (!$pdo) {
        $_SESSION['wearcast_demo_records'] = $_SESSION['wearcast_demo_records'] ?? [];
        $updated = false;
        foreach ($_SESSION['wearcast_demo_records'] as $index => $existing) {
            if ((string) $existing['record_date'] === (string) $record['record_date']
                && (string) ($existing['location_id'] ?? '') === (string) ($record['location_id'] ?? '')
            ) {
                $record['id'] = $existing['id'];
                if (empty($record['image_path']) && !empty($existing['image_path'])) {
                    $record['image_path'] = $existing['image_path'];
                }
                $record['location_label'] = $existing['location_label'] ?? '';
                $_SESSION['wearcast_demo_records'][$index] = $record;
                $updated = true;
                break;
            }
        }
        if (!$updated) {
            $record['id'] = (string) (count($_SESSION['wearcast_demo_records']) + 1);
            $_SESSION['wearcast_demo_records'][] = $record;
        }
        return;
    }

    $statement = $pdo->prepare(
        'INSERT INTO wearcast_records
        (user_id, location_id, record_date, weather_group, weather_label, weather_code, temp_max, temp_min, outfit_category, comfort_vote, comment_text, free_note, image_path)
        VALUES
        (:user_id, :location_id, :record_date, :weather_group, :weather_label, :weather_code, :temp_max, :temp_min, :outfit_category, :comfort_vote, :comment_text, :free_note, :image_path)
        ON DUPLICATE KEY UPDATE
            weather_group = VALUES(weather_group),
            weather_label = VALUES(weather_label),
            weather_code = VALUES(weather_code),
            temp_max = VALUES(temp_max),
            temp_min = VALUES(temp_min),
            outfit_category = VALUES(outfit_category),
            comfort_vote = VALUES(comfort_vote),
            comment_text = VALUES(comment_text),
            free_note = VALUES(free_note),
            image_path = COALESCE(VALUES(image_path), image_path),
            updated_at = CURRENT_TIMESTAMP'
    );

    $statement->execute([
        'user_id' => $user['id'],
        'location_id' => $record['location_id'],
        'record_date' => $record['record_date'],
        'weather_group' => $record['weather_group'],
        'weather_label' => $record['weather_label'],
        'weather_code' => $record['weather_code'],
        'temp_max' => $record['temp_max'],
        'temp_min' => $record['temp_min'],
        'outfit_category' => $record['outfit_category'],
        'comfort_vote' => $record['comfort_vote'],
        'comment_text' => $record['comment_text'],
        'free_note' => $record['free_note'],
        'image_path' => $record['image_path'],
    ]);
}

function wc_delete_record(?PDO $pdo, array $user, string $recordId): bool
{
    if ($recordId === '') {
        return false;
    }

    if (!$pdo) {
        $_SESSION['wearcast_demo_records'] = array_values(array_filter(
            $_SESSION['wearcast_demo_records'] ?? [],
            static fn (array $record): bool => (string) $record['id'] !== $recordId || (string) $record['record_date'] !== date('Y-m-d')
        ));
        return true;
    }

    $statement = $pdo->prepare(
        'DELETE FROM wearcast_records
         WHERE id = :id AND user_id = :user_id AND record_date = :record_date'
    );

    return $statement->execute([
        'id' => $recordId,
        'user_id' => $user['id'],
        'record_date' => date('Y-m-d'),
    ]);
}

function wc_record_image_url(?string $relativePath): ?string
{
    return $relativePath ? wc_url($relativePath) : null;
}
