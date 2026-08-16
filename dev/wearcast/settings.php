<?php

declare(strict_types=1);

$app = require __DIR__ . '/app/bootstrap.php';
$forecast = wc_today_forecast($app['activeLocation']);
$GLOBALS['wearcast_theme'] = $forecast['weather_group'];
$GLOBALS['wearcast_time'] = wc_time_period();
$offices = wc_office_options();
$locations = array_values($app['locations']);
$locations[0] = $locations[0] ?? wc_default_location();
$locations[1] = $locations[1] ?? [
    'id' => '',
    'label' => '',
    'prefecture_name' => '',
    'region_name' => '',
    'office_code' => '',
    'area_code' => '',
    'lat' => '',
    'lng' => '',
];
$hasSubLocation = (string) ($locations[1]['office_code'] ?? '') !== '' && (string) ($locations[1]['area_code'] ?? '') !== '';
$settingsFlash = $app['flash'] ?? null;
$shellApp = $app;
if (($settingsFlash['type'] ?? '') === 'success') {
    $shellApp['flash'] = null;
}

wc_render_head('Wearcast Settings', $forecast['weather_group'], $GLOBALS['wearcast_time']);
wc_render_shell_start($shellApp, 'settings');
?>
  <section class="page-grid settings-page">
    <div class="stack">
      <section class="panel-card settings-intro">
        <p class="section-card__eyebrow">SETTINGS</p>
        <h2 class="page-title">地点設定</h2>
        <p class="section-card__text">この端末に地点と記録を保存します。<br>メイン地点は今日画面で使います。</p>
        <?php if (($settingsFlash['type'] ?? '') === 'success'): ?>
          <div class="settings-success">
            <strong>設定を保存しました。</strong>
            <a class="button" href="<?= wc_h(wc_url('index.php')) ?>">今日の服装を見る</a>
          </div>
        <?php endif; ?>
      </section>
    </div>

    <section class="record-card settings-card">
      <form class="form-grid" action="<?= wc_h(wc_url('actions/save_settings.php')) ?>" method="post" data-settings-form>
        <input type="hidden" name="csrf_token" value="<?= wc_h(wc_csrf_token()) ?>">

        <?php foreach ([0, 1] as $index): ?>
          <?php
          $location = $locations[$index];
          $isMain = $index === 0;
          $detailsOpen = $isMain || $hasSubLocation;
          $selectedOfficeCode = (string) ($location['office_code'] ?? '');
          $selectedAreaCode = (string) ($location['area_code'] ?? '');
          $areaOptions = $selectedOfficeCode !== '' ? wc_area_options($selectedOfficeCode) : [];
          ?>
          <?php if ($isMain): ?>
          <section class="location-card settings-location settings-location--main" data-location-card>
            <div class="settings-location__summary settings-location__summary--static">
              <span>
                <span class="location-card__eyebrow">メイン地点</span>
                <strong>今日画面で使う地点</strong>
              </span>
            </div>
          <?php else: ?>
          <details class="location-card settings-location settings-accordion" data-location-card <?= $detailsOpen ? 'open' : '' ?>>
            <summary class="settings-location__summary settings-accordion__summary">
              <span>
                <span class="location-card__eyebrow">サブ地点</span>
                <strong>サブ地点を追加する</strong>
              </span>
              <span class="settings-accordion__icon" aria-hidden="true"></span>
            </summary>
          <?php endif; ?>

            <div class="settings-location__body">
              <div class="field-row">
                <label class="field">
                  <span class="field-label">都道府県・地方 <span class="field-badge <?= $isMain ? 'field-badge--required' : '' ?>"><?= $isMain ? '必須' : '任意' ?></span></span>
                  <select name="locations[<?= $index ?>][office_code]" data-office-select data-selected-area="<?= wc_h((string) ($location['area_code'] ?? '')) ?>" <?= $isMain ? 'required' : '' ?>>
                    <option value="">選択してください</option>
                    <?php foreach ($offices as $office): ?>
                      <option value="<?= wc_h($office['code']) ?>" <?= (string) $office['code'] === (string) ($location['office_code'] ?? '') ? 'selected' : '' ?>>
                        <?= wc_h($office['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </label>
                <label class="field">
                  <span class="field-label">地域 <span class="field-badge <?= $isMain ? 'field-badge--required' : '' ?>"><?= $isMain ? '必須' : '任意' ?></span></span>
                  <select name="locations[<?= $index ?>][area_code]" data-area-select <?= $isMain ? 'required' : '' ?>>
                    <option value="">地域を選択</option>
                    <?php foreach ($areaOptions as $area): ?>
                      <option value="<?= wc_h($area['code']) ?>" <?= (string) $area['code'] === $selectedAreaCode ? 'selected' : '' ?>>
                        <?= wc_h($area['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </label>
              </div>

              <input type="hidden" name="locations[<?= $index ?>][prefecture_name]" value="<?= wc_h((string) ($location['prefecture_name'] ?? '')) ?>" data-prefecture-name>
              <input type="hidden" name="locations[<?= $index ?>][region_name]" value="<?= wc_h((string) ($location['region_name'] ?? '')) ?>" data-region-name>
            </div>
          <?php if ($isMain): ?>
          </section>
          <?php else: ?>
          </details>
          <?php endif; ?>
        <?php endforeach; ?>

        <details class="settings-detail settings-detail--optional settings-accordion">
          <summary class="settings-accordion__summary">
            <span class="settings-accordion__title">メールアドレスなど</span>
            <span class="settings-accordion__icon" aria-hidden="true"></span>
          </summary>
          <div class="settings-detail__body">
            <label class="field">
              <span class="field-label">メールアドレス（任意）</span>
              <input type="email" name="email" value="<?= wc_h((string) ($app['user']['email'] ?? '')) ?>" placeholder="name@example.com">
              <span class="field-help">未入力でも使えます。端末を変えたときの識別用です。</span>
            </label>
          </div>
        </details>

        <div class="settings-actions">
          <button class="button" type="submit">設定を保存する</button>
          <a class="button button--ghost" href="<?= wc_h(wc_url('index.php')) ?>">今日に戻る</a>
        </div>
      </form>
    </section>
  </section>
<?php wc_render_shell_end();
