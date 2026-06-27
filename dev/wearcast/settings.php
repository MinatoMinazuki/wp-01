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

wc_render_head('Wearcast Settings', $forecast['weather_group'], $GLOBALS['wearcast_time']);
wc_render_shell_start($app, 'settings');
?>
  <section class="page-grid">
    <div class="stack">
      <section class="panel-card">
        <p class="section-card__eyebrow">SETUP</p>
        <h2 class="page-title">保存設定</h2>
        <p class="section-card__text">未認証のまま cookie でユーザー識別します。メールアドレスは任意で、通知用途ではなく識別の控えとして保存する前提です。</p>
        <div class="helper-note" style="margin-top: 16px;">
          地点は最大2件まで。1件目がメイン地点になり、Today 画面の初期表示に使います。
        </div>
      </section>
    </div>

    <section class="record-card">
      <form class="form-grid" action="<?= wc_h(wc_url('actions/save_settings.php')) ?>" method="post">
        <input type="hidden" name="csrf_token" value="<?= wc_h(wc_csrf_token()) ?>">

        <label class="field">
          <span class="field-label">メールアドレス（任意）</span>
          <input type="email" name="email" value="<?= wc_h((string) ($app['user']['email'] ?? '')) ?>" placeholder="name@example.com">
        </label>

        <div class="location-grid">
          <?php foreach ([0, 1] as $index): $location = $locations[$index]; ?>
            <section class="location-card" data-location-card>
              <p class="location-card__eyebrow">LOCATION <?= $index + 1 ?><?= $index === 0 ? ' / MAIN' : ' / SUB' ?></p>
              <div class="field-row" style="margin-top: 12px;">
                <label class="field">
                  <span class="field-label">都道府県・地方</span>
                  <select name="locations[<?= $index ?>][office_code]" data-office-select data-selected-area="<?= wc_h((string) ($location['area_code'] ?? '')) ?>">
                    <option value="">選択してください</option>
                    <?php foreach ($offices as $office): ?>
                      <option value="<?= wc_h($office['code']) ?>" <?= (string) $office['code'] === (string) ($location['office_code'] ?? '') ? 'selected' : '' ?>>
                        <?= wc_h($office['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </label>
                <label class="field">
                  <span class="field-label">地域</span>
                  <select name="locations[<?= $index ?>][area_code]" data-area-select>
                    <option value="">地域を選択</option>
                  </select>
                </label>
              </div>

              <input type="hidden" name="locations[<?= $index ?>][prefecture_name]" value="<?= wc_h((string) ($location['prefecture_name'] ?? '')) ?>" data-prefecture-name>
              <input type="hidden" name="locations[<?= $index ?>][region_name]" value="<?= wc_h((string) ($location['region_name'] ?? '')) ?>" data-region-name>

              <div class="field-row">
                <label class="field">
                  <span class="field-label">緯度（任意）</span>
                  <input type="text" name="locations[<?= $index ?>][lat]" value="<?= wc_h((string) ($location['lat'] ?? '')) ?>" placeholder="35.6762">
                </label>
                <label class="field">
                  <span class="field-label">経度（任意）</span>
                  <input type="text" name="locations[<?= $index ?>][lng]" value="<?= wc_h((string) ($location['lng'] ?? '')) ?>" placeholder="139.6503">
                </label>
              </div>

              <div style="margin-top: 12px;">
                <button class="button button--ghost" type="button" data-current-location>現在地の緯度・経度を入れる</button>
              </div>
            </section>
          <?php endforeach; ?>
        </div>

        <div style="display:flex; gap: 12px; flex-wrap: wrap;">
          <button class="button" type="submit">設定を保存する</button>
          <a class="button button--ghost" href="<?= wc_h(wc_url('index.php')) ?>">Today に戻る</a>
        </div>
      </form>
    </section>
  </section>
<?php wc_render_shell_end();
