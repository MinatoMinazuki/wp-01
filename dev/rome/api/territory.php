<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$dataPath = __DIR__ . '/../data/rome_timeline.json';

if (!is_readable($dataPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Timeline data is not readable.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode((string) file_get_contents($dataPath), true);

if (!is_array($data)) {
    http_response_code(500);
    echo json_encode(['error' => 'Timeline data is invalid JSON.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_GET['year'])) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT);

if ($year === false || $year === null || $year === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Year must be an integer BCE/CE value, excluding 0.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$range = $data['meta']['range'] ?? ['start' => -753, 'end' => 476];
$start = (int) $range['start'];
$end = (int) $range['end'];

if (year_index($year) < year_index($start) || year_index($year) > year_index($end)) {
    http_response_code(400);
    echo json_encode(['error' => 'Year is outside the supported range.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$regions = [];

foreach (($data['regions'] ?? []) as $region) {
    $control = active_control($region['control'] ?? [], $year, $end);

    if ($control === null) {
        continue;
    }

    $regions[] = [
        'id' => $region['id'] ?? '',
        'name' => $region['name'] ?? '',
        'label' => $region['label'] ?? ($region['name'] ?? ''),
        'polygons' => $region['polygons'] ?? [],
        'activeControl' => $control,
    ];
}

$events = array_values(array_filter(
    $data['events'] ?? [],
    static fn (array $event): bool => isset($event['year']) && (int) $event['year'] === $year
));

echo json_encode([
    'year' => $year,
    'range' => $range,
    'statuses' => $data['statuses'] ?? [],
    'regions' => $regions,
    'events' => $events,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

function active_control(array $controls, int $year, int $defaultEnd): ?array
{
    foreach ($controls as $control) {
        $controlStart = (int) ($control['start'] ?? -753);
        $controlEnd = isset($control['end']) ? (int) $control['end'] : $defaultEnd;

        if (year_index($year) >= year_index($controlStart) && year_index($year) <= year_index($controlEnd)) {
            return $control;
        }
    }

    return null;
}

function year_index(int $year): int
{
    if ($year < 0) {
        return $year + 753;
    }

    return $year + 752;
}
