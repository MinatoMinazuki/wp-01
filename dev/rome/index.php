<?php
$dataPath = __DIR__ . '/data/rome_timeline.json';
$rawData = is_readable($dataPath) ? file_get_contents($dataPath) : '{}';
$data = json_decode($rawData, true) ?: [];
$title = $data['meta']['title'] ?? 'Roman Territorial Simulator';
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ローマ支配領域シミュレーター</title>
  <link rel="stylesheet" href="https://unpkg.com/maplibre-gl/dist/maplibre-gl.css">
  <link rel="stylesheet" href="assets/styles.css?v=20260606-10">
  <script>
    window.ROME_DATA_URL = 'api/territory.php';
    window.ROME_MAP_STYLE = 'https://tiles.openfreemap.org/styles/liberty';
  </script>
  <script src="https://unpkg.com/maplibre-gl/dist/maplibre-gl.js"></script>
  <script src="assets/app.js?v=20260606-10" defer></script>
</head>
<body>
  <div class="app-shell">
    <header class="topbar">
      <div class="title-group">
        <span class="eyebrow">Roman Territorial Simulator</span>
        <h1>ローマ支配領域</h1>
      </div>
      <div class="topbar-meta">
        <span id="eraChip" class="era-chip">読み込み中</span>
        <span class="range-chip">紀元前753年 - 西暦476年</span>
      </div>
    </header>

    <main class="workspace">
      <section class="map-panel" aria-label="地中海地図">
        <div class="map-toolbar">
          <button id="playButton" class="icon-button primary" type="button" aria-label="再生" title="再生">
            <span id="playIcon" aria-hidden="true">&#9654;</span>
          </button>
          <button id="prevEventButton" class="icon-button" type="button" aria-label="前の節目" title="前の節目">
            <span aria-hidden="true">&#8249;</span>
          </button>
          <label class="slider-frame">
            <span id="yearLabel" class="year-label">紀元前753年</span>
            <input id="yearSlider" type="range" min="0" max="1228" value="0" step="1">
          </label>
          <button id="nextEventButton" class="icon-button" type="button" aria-label="次の節目" title="次の節目">
            <span aria-hidden="true">&#8250;</span>
          </button>
          <div class="control-cluster">
            <label class="select-label">
              <span>速度</span>
              <select id="speedSelect">
                <option value="1">1年</option>
                <option value="5" selected>5年</option>
                <option value="10">10年</option>
                <option value="25">25年</option>
              </select>
            </label>
            <label class="toggle-label">
              <input id="lostToggle" type="checkbox" checked>
              <span>喪失地</span>
            </label>
          </div>
        </div>

        <div class="map-stage">
          <div id="map" class="real-map" role="application" aria-label="OpenFreeMapを背景にしたローマ支配領域地図"></div>
          <svg id="territoryOverlay" class="territory-overlay" aria-label="ローマ支配領域オーバーレイ"></svg>
          <div id="mapMessage" class="map-message" hidden></div>
        </div>
      </section>

      <aside class="inspector" aria-label="選択年の詳細">
        <section class="summary-panel">
          <div>
            <span class="panel-kicker">Current Year</span>
            <strong id="summaryYear">紀元前753年</strong>
          </div>
          <div class="metric-grid">
            <div>
              <span>支配</span>
              <strong id="controlledCount">0</strong>
            </div>
            <div>
              <span>係争</span>
              <strong id="contestedCount">0</strong>
            </div>
            <div>
              <span>喪失</span>
              <strong id="lostCount">0</strong>
            </div>
          </div>
        </section>

        <section class="legend-panel">
          <div class="panel-heading">
            <h2>凡例</h2>
          </div>
          <div id="legendList" class="legend-list"></div>
        </section>

        <section class="event-panel">
          <div class="panel-heading">
            <h2>節目</h2>
            <span id="eventCount"></span>
          </div>
          <div id="eventList" class="event-list"></div>
        </section>

        <section class="region-panel">
          <div class="panel-heading">
            <h2>領域</h2>
            <span id="selectedRegionBadge">未選択</span>
          </div>
          <div id="regionDetail" class="region-detail"></div>
          <div id="regionList" class="region-list"></div>
        </section>
      </aside>
    </main>
  </div>

  <noscript>
    <div class="noscript">JavaScriptを有効にしてください。</div>
  </noscript>
</body>
</html>
