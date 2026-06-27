<?php

declare(strict_types=1);

$app = require __DIR__ . '/app/bootstrap.php';
$forecast = wc_today_forecast($app['activeLocation']);
$recommendation = wc_outfit_recommendation((float) $forecast['temp_max'], (float) $forecast['temp_min'], (string) $forecast['weather_group']);
$records = wc_get_records($app['pdo'], $app['user']);
$similar = wc_find_similar_record($records, $forecast);
$GLOBALS['wearcast_theme'] = $forecast['weather_group'];
$GLOBALS['wearcast_time'] = wc_time_period();

$tempMax = (int) round((float) $forecast['temp_max']);
$tempMin = (int) round((float) $forecast['temp_min']);
$rainPercent = (int) $forecast['precip'];
$weekday = ['日', '月', '火', '水', '木', '金', '土'][(int) date('w')];
$dateLabel = date('Y.m.d') . '（' . $weekday . '）';
$locationLabel = str_replace(' / ', wc_to_utf8('・'), wc_to_utf8((string) $forecast['location_label']));
$updatedAt = date('n/j H:i', strtotime((string) $forecast['report_datetime']));
$weatherText = wc_to_utf8((string) $forecast['weather_label']);
$outfitLabel = wc_to_utf8((string) $recommendation['label']);
$heroSummary = wc_to_utf8((string) $recommendation['detail']);

$weatherSummary = match ((string) $forecast['weather_group']) {
    'rainy' => wc_to_utf8('雨'),
    'cloudy' => wc_to_utf8('くもり中心'),
    default => wc_to_utf8('晴れ間あり'),
};

$rainRiskNote = '';
if ((string) $forecast['weather_group'] === 'rainy' && $rainPercent >= 50) {
    foreach (['午後', '昼過ぎ', '夕方', '夜', '雷', '激しく', '強く'] as $riskWord) {
        if (str_contains($weatherText, wc_to_utf8($riskWord))) {
            $rainRiskNote = wc_to_utf8('午後以降に変わる可能性');
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
        <p class="eyebrow">TODAY'S PICK</p>
        <h2 class="hero-card__title"><?= wc_h($outfitLabel) ?></h2>
        <p class="hero-card__summary"><?= wc_h($heroSummary) ?></p>
        <div class="hero-card__meta">
          <div>
            <div class="temp-display" style="font-size: 38px; line-height: 1.08; white-space: normal;"><?= wc_h('最高') ?> <?= wc_h((string) $tempMax) ?>° / <?= wc_h('最低') ?> <?= wc_h((string) $tempMin) ?>°</div>
            <p class="hero-card__weather"><?= wc_h($weatherSummary) ?></p>
          </div>
          <div class="hero-card__location">
            <p class="hero-card__date" style="margin: 0 0 8px; color: rgba(255, 255, 255, 0.92); font-size: 16px; font-weight: 700;"><?= wc_h($dateLabel) ?></p>
            <strong><?= wc_h($locationLabel) ?></strong>
          </div>
        </div>
      </section>

      <div class="panel-row">
        <article class="metric-card">
          <p class="metric-card__label">RECORD</p>
          <p class="metric-card__value"><?= wc_h('今日の服装') ?></p>
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
            <span class="rain-note__icon<?= $forecast['umbrella_needed'] ? '' : ' is-muted is-crossed' ?>" aria-label="<?= $forecast['umbrella_needed'] ? 'umbrella needed' : 'umbrella not needed' ?>">
              <svg viewBox="0 0 64 64" aria-hidden="true" focusable="false">
                <path d="M32 8C17.6 8 6 19.6 6 34h52C58 19.6 46.4 8 32 8Zm0 0c-4.6 5.6-7.1 14.2-7.1 26h14.2C39.1 22.2 36.6 13.6 32 8Z"/>
                <path d="M34.5 34v15.2c0 4.9-4 8.8-8.8 8.8-4.3 0-7.8-3.1-8.6-7.1-.3-1.4.7-2.7 2.1-3 1.4-.2 2.7.7 3 2.1.4 1.7 1.8 3 3.5 3 2.1 0 3.8-1.7 3.8-3.8V34h5Z"/>
              </svg>
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
                <?= wc_h(str_replace(' / ', wc_to_utf8('・'), wc_to_utf8((string) $location['label']))) ?>
              </a>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

      <?php if ((string) ($app['activeLocation']['id'] ?? '') === ''): ?>
        <section class="section-card">
          <p class="section-card__eyebrow">SETUP</p>
          <h2 class="section-card__title"><?= wc_h('地点を設定する') ?></h2>
          <p class="section-card__text"><?= wc_h('メイン地点を保存すると、天気と記録が同じ地点でそろいます。') ?></p>
          <div style="margin-top: 16px;">
            <a class="button button--ghost" href="<?= wc_h(wc_url('settings.php')) ?>"><?= wc_h('設定する') ?></a>
          </div>
        </section>
      <?php endif; ?>
    </div>

    <div class="stack">
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
        <?php else: ?>
          <div class="empty-state" style="margin-top: 16px;">
            <p class="muted"><?= wc_h('記録すると、似た気温の日の服装がここに表示されます。') ?></p>
          </div>
        <?php endif; ?>
        <div style="margin-top: 16px;">
          <a class="cta-link" href="<?= wc_h(wc_url('record.php')) ?>"><?= wc_h('今日の服装を記録する') ?></a>
        </div>
      </section>

      <section class="section-card">
        <p class="section-card__eyebrow">WEATHER</p>
        <h2 class="section-card__title"><?= wc_h('今日の天気') ?></h2>
        <div class="meta-grid" style="margin-top: 16px;">
          <div class="mini-panel">
            <p class="mini-panel__label"><?= wc_h('天気') ?></p>
            <p class="mini-panel__value"><?= wc_h($weatherSummary) ?></p>
          </div>
          <div class="mini-panel">
            <p class="mini-panel__label"><?= wc_h('最高 / 最低') ?></p>
            <p class="mini-panel__value"><?= wc_h((string) $tempMax) ?>° / <?= wc_h((string) $tempMin) ?>°</p>
          </div>
          <div class="mini-panel">
            <p class="mini-panel__label"><?= wc_h('更新') ?></p>
            <p class="mini-panel__value"><?= wc_h($updatedAt) ?></p>
          </div>
        </div>
        <p class="muted" style="margin: 16px 0 0;"><?= wc_h($weatherText) ?></p>
      </section>
    </div>
  </section>
<?php wc_render_shell_end();
