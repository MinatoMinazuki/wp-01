<?php

declare(strict_types=1);

$app = require __DIR__ . '/app/bootstrap.php';
$forecast = wc_today_forecast($app['activeLocation']);
$recommendation = wc_outfit_recommendation((float) $forecast['temp_max'], (float) $forecast['temp_min'], (string) $forecast['weather_group']);
$records = wc_get_records($app['pdo'], $app['user']);
$hasRecords = count($records) > 0;
$similar = $hasRecords ? wc_find_similar_record($records, $forecast) : null;
$GLOBALS['wearcast_theme'] = $forecast['weather_group'];
$GLOBALS['wearcast_time'] = wc_time_period();

$tempMax = (int) round((float) $forecast['temp_max']);
$tempMin = (int) round((float) $forecast['temp_min']);
$tempCurrent = is_numeric($forecast['temp_current'] ?? null) ? (int) round((float) $forecast['temp_current']) : null;
$currentTempLabel = $tempCurrent === null ? '--' : (string) $tempCurrent;
$rainPercent = (int) $forecast['precip'];
$weekday = ['日', '月', '火', '水', '木', '金', '土'][(int) date('w')];
$dateLabel = date('Y.m.d') . '（' . $weekday . '）';
$locationLabel = str_replace(' / ', '・', wc_to_utf8((string) $forecast['location_label']));
$updatedAt = date('H:i', strtotime((string) $forecast['report_datetime'])) . '更新';
$weatherText = wc_to_utf8((string) $forecast['weather_label']);
$outfitLabel = wc_to_utf8((string) $recommendation['label']);
$heroSummary = match ((string) ($recommendation['key'] ?? '')) {
    'short-sleeve-light' => '半袖に薄手の羽織を合わせると安心です。',
    'short-sleeve' => '日中は半袖で軽く過ごせます。',
    'long-sleeve' => '長袖一枚で過ごしやすい気温です。',
    'light-outer' => '薄手の上着があると朝晩も安定します。',
    'coat' => '外ではコート前提で考える気温です。',
    'down-coat' => '防寒優先で、ダウンまで見ておきたい日です。',
    default => wc_to_utf8((string) $recommendation['detail']),
};

$weatherSummary = match ((string) $forecast['weather_group']) {
    'rainy' => '雨',
    'cloudy' => 'くもり中心',
    default => '晴れ間あり',
};

$umbrellaState = $rainPercent >= 50 ? 'open' : ($rainPercent >= 30 ? 'folded' : 'folded-crossed');
$umbrellaClass = ' rain-note__icon--' . $umbrellaState . ($umbrellaState === 'folded-crossed' ? ' is-crossed' : '');
$umbrellaIcon = $umbrellaState === 'open' ? 'icon-umbrella-open.png' : 'icon-umbrella-folded.png';
$rainRiskNote = '';
if ((string) $forecast['weather_group'] === 'rainy' && $rainPercent >= 50) {
    foreach (['午後', '昼過ぎ', '夕方', '夜', '雷', '激しく', '強い'] as $riskWord) {
        if (str_contains($weatherText, $riskWord)) {
            $rainRiskNote = '午後以降に変わる可能性';
            break;
        }
    }
}

wc_render_head('Wearcast Today', $forecast['weather_group'], $GLOBALS['wearcast_time']);
wc_render_shell_start($app, 'today');
?>
  <section class="page-grid">
    <div class="stack">
      <section class="hero-card">
        <p class="eyebrow hero-card__eyebrow">TODAY'S PICK</p>
        <h2 class="hero-card__title"><?= wc_h($outfitLabel) ?></h2>
        <p class="hero-card__summary"><?= wc_h($heroSummary) ?></p>
        <div class="hero-card__meta">
          <div class="hero-temp">
            <p class="hero-temp__label"><?= wc_h('現在') ?></p>
            <div class="hero-temp__current"><?= wc_h($currentTempLabel) ?>°</div>
            <p class="hero-temp__range"><?= wc_h('最高') ?> <?= wc_h((string) $tempMax) ?>° / <?= wc_h('最低') ?> <?= wc_h((string) $tempMin) ?>°</p>
            <p class="hero-card__weather"><?= wc_h($weatherSummary) ?></p>
          </div>
          <div class="hero-card__location">
            <p class="hero-card__date"><?= wc_h($dateLabel) ?></p>
            <strong><?= wc_h($locationLabel) ?></strong>
          </div>
        </div>
      </section>

      <div class="panel-row">
        <article class="metric-card">
          <p class="metric-card__label">RECORD</p>
          <p class="metric-card__value"><?= wc_h('今日の服装') ?></p>
          <?php if (!$hasRecords): ?>
            <p class="metric-card__sub"><?= wc_h('まだ記録がありません') ?></p>
          <?php endif; ?>
          <div style="margin-top: 14px;">
            <a class="button" href="<?= wc_h(wc_url('record.php')) ?>"><?= wc_h('記録する') ?></a>
          </div>
        </article>
        <article class="mini-panel rain-note-card">
          <p class="mini-panel__label">RAIN NOTE</p>
          <div class="rain-note__main">
            <div>
              <p class="rain-note__caption"><?= wc_h('降水確率') ?></p>
              <p class="rain-note__percent"><?= wc_h((string) $rainPercent) ?>%</p>
            </div>
            <span class="rain-note__icon<?= wc_h($umbrellaClass) ?>" aria-label="<?= wc_h('umbrella state') ?>">
              <img src="<?= wc_h(wc_url('assets/' . $umbrellaIcon)) ?>" alt="" aria-hidden="true">
            </span>
          </div>
          <?php if ($rainRiskNote !== ''): ?>
            <p class="rain-note__risk"><?= wc_h($rainRiskNote) ?></p>
          <?php endif; ?>
        </article>
      </div>

      <?php if (count($app['locations']) > 1): ?>
        <section class="section-card">
          <p class="section-card__eyebrow">LOCATION</p>
          <h2 class="section-card__title"><?= wc_h('表示地点') ?></h2>
          <div class="location-switch" style="margin-top: 16px;">
            <?php foreach ($app['locations'] as $location): ?>
              <?php $locationHref = wc_url('index.php?location=' . rawurlencode((string) $location['id'])); ?>
              <a class="location-pill<?= (string) $location['id'] === (string) $app['activeLocation']['id'] ? ' is-active' : '' ?>" href="<?= wc_h($locationHref) ?>">
                <?= wc_h(str_replace(' / ', '・', wc_to_utf8((string) $location['label']))) ?>
              </a>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

      <?php if ((string) ($app['activeLocation']['office_code'] ?? '') === '' || (string) ($app['activeLocation']['area_code'] ?? '') === ''): ?>
        <section class="section-card">
          <p class="section-card__eyebrow">SETUP</p>
          <h2 class="section-card__title"><?= wc_h('地点を設定する') ?></h2>
          <p class="section-card__text"><?= wc_h('メイン地点を保存すると、天気と記録を同じ地点でそろえます。') ?></p>
          <div style="margin-top: 16px;">
            <a class="button button--ghost" href="<?= wc_h(wc_url('settings.php')) ?>"><?= wc_h('設定する') ?></a>
          </div>
        </section>
      <?php endif; ?>
    </div>

    <div class="stack">
      <?php if ($hasRecords): ?>
        <section class="section-card">
          <p class="section-card__eyebrow">SIMILAR DAY</p>
          <h2 class="section-card__title"><?= wc_h('似た日の記録') ?></h2>
          <?php if ($similar): ?>
            <?php
            $similarDate = date('Y.m.d', strtotime((string) $similar['record_date']));
            $similarMax = (int) round((float) $similar['temp_max']);
            $similarMin = (int) round((float) $similar['temp_min']);
            ?>
            <div class="similar-card<?= empty($similar['image_path']) ? ' similar-card--fallback' : '' ?>">
              <?php if (!empty($similar['image_path'])): ?>
                <div class="similar-card__photo" style="background-image:url('<?= wc_h(wc_record_image_url($similar['image_path'])) ?>');"></div>
              <?php endif; ?>
              <div class="similar-card__body">
                <div>
                  <p class="record-item__meta"><?= wc_h($similarDate) ?></p>
                  <h3 class="similar-card__headline"><?= wc_h($similar['outfit_category']) ?></h3>
                  <p class="similar-card__detail">
                    <?= wc_h('最高') ?> <?= wc_h((string) $similarMax) ?>° / <?= wc_h('最低') ?> <?= wc_h((string) $similarMin) ?>°
                    <br><?= wc_h($similar['weather_label']) ?>
                    <?php if (!empty($similar['comment_text'])): ?><br><?= wc_h('メモ') ?>: <?= wc_h($similar['comment_text']) ?><?php endif; ?>
                  </p>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </section>
      <?php else: ?>
        <aside class="similar-hint">
          <strong><?= wc_h('似た日の記録') ?></strong>
          <span><?= wc_h('記録が増えると、近い気温の日の服装をここに表示します。') ?></span>
        </aside>
      <?php endif; ?>

      <section class="section-card">
        <p class="section-card__eyebrow">WEATHER</p>
        <h2 class="section-card__title"><?= wc_h('今日の天気') ?></h2>
        <div class="weather-grid" style="margin-top: 16px;">
          <div class="mini-panel">
            <p class="mini-panel__label"><?= wc_h('天気') ?></p>
            <p class="mini-panel__value"><?= wc_h($weatherSummary) ?></p>
          </div>
          <div class="mini-panel">
            <p class="mini-panel__label"><?= wc_h('天気の推移') ?></p>
            <p class="mini-panel__value mini-panel__value--text"><?= wc_h($weatherText) ?></p>
          </div>
          <div class="mini-panel">
            <p class="mini-panel__label"><?= wc_h('気温') ?></p>
            <p class="mini-panel__value"><?= wc_h('現在') ?> <?= wc_h($currentTempLabel) ?>°</p>
            <p class="mini-panel__sub"><?= wc_h('最高') ?> <?= wc_h((string) $tempMax) ?>° / <?= wc_h('最低') ?> <?= wc_h((string) $tempMin) ?>°</p>
          </div>
          <div class="mini-panel">
            <p class="mini-panel__label"><?= wc_h('更新') ?></p>
            <p class="mini-panel__value"><?= wc_h($updatedAt) ?></p>
          </div>
        </div>
      </section>
    </div>
  </section>
<?php wc_render_shell_end();
