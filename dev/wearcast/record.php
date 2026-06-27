<?php

declare(strict_types=1);

$app = require __DIR__ . '/app/bootstrap.php';
$forecast = wc_today_forecast($app['activeLocation']);
$recommendation = wc_outfit_recommendation((float) $forecast['temp_max'], (float) $forecast['temp_min'], (string) $forecast['weather_group']);
$GLOBALS['wearcast_theme'] = $forecast['weather_group'];
$GLOBALS['wearcast_time'] = wc_time_period();

$outfits = [
    ['value' => '半袖', 'sub' => '真夏寄りの軽さを優先'],
    ['value' => '半袖 + 薄手の羽織', 'sub' => '朝晩と冷房まで拾う'],
    ['value' => '長袖', 'sub' => '一日通して標準的'],
    ['value' => '長袖 + ライトアウター', 'sub' => '夜まで見るなら安全'],
    ['value' => 'コート', 'sub' => '冷え込み対策を優先'],
    ['value' => 'ダウンコート', 'sub' => '防寒を最優先にする'],
];

wc_render_head('Wearcast Record', $forecast['weather_group'], $GLOBALS['wearcast_time']);
wc_render_shell_start($app, 'record');
?>
  <section class="page-grid">
    <div class="stack">
      <section class="hero-card">
        <p class="eyebrow">RECORD TODAY</p>
        <h2 class="hero-card__title"><?= wc_h($recommendation['label']) ?></h2>
        <p class="hero-card__summary">記録は similar day の材料になります。画像がある日は次回の提案にもそのまま写真を出します。</p>
        <div class="hero-card__meta">
          <div>
            <div class="temp-display"><?= wc_h((string) (int) round((float) $forecast['temp_max'])) ?>°</div>
            <p class="hero-card__weather"><?= wc_h($forecast['weather_label']) ?></p>
          </div>
          <div class="hero-card__location">
            <strong><?= wc_h($forecast['location_label']) ?></strong>
            <p class="hero-card__date"><?= wc_h(date('Y.m.d D')) ?></p>
          </div>
        </div>
      </section>
    </div>

    <section class="record-card">
      <p class="section-card__eyebrow">TODAY LOG</p>
      <h2 class="page-title">今日の服装を残す</h2>
      <form class="form-grid" action="<?= wc_h(wc_url('actions/save_record.php')) ?>" method="post" enctype="multipart/form-data" style="margin-top: 18px;">
        <input type="hidden" name="csrf_token" value="<?= wc_h(wc_csrf_token()) ?>">
        <input type="hidden" name="record_date" value="<?= wc_h(date('Y-m-d')) ?>">
        <input type="hidden" name="location_id" value="<?= wc_h((string) ($app['activeLocation']['id'] ?? '')) ?>">
        <input type="hidden" name="weather_group" value="<?= wc_h($forecast['weather_group']) ?>">
        <input type="hidden" name="weather_label" value="<?= wc_h($forecast['weather_label']) ?>">
        <input type="hidden" name="weather_code" value="<?= wc_h($forecast['weather_code']) ?>">
        <input type="hidden" name="temp_max" value="<?= wc_h((string) $forecast['temp_max']) ?>">
        <input type="hidden" name="temp_min" value="<?= wc_h((string) $forecast['temp_min']) ?>">

        <div class="field">
          <p class="field-label">服装カテゴリ</p>
          <div class="choice-grid">
            <?php foreach ($outfits as $outfit): ?>
              <label class="choice-card">
                <input type="radio" name="outfit_category" value="<?= wc_h($outfit['value']) ?>" <?= $outfit['value'] === $recommendation['label'] ? 'checked' : '' ?> required>
                <span class="choice-card__ui">
                  <span class="choice-card__label"><?= wc_h($outfit['value']) ?></span>
                  <span class="choice-card__sub"><?= wc_h($outfit['sub']) ?></span>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="field">
          <p class="field-label">感想</p>
          <div class="chip-row">
            <label class="chip-choice">
              <input type="radio" name="comfort_vote" value="cold" required>
              <span class="chip-choice__ui">寒かった</span>
            </label>
            <label class="chip-choice">
              <input type="radio" name="comfort_vote" value="just" checked required>
              <span class="chip-choice__ui">ちょうどよかった</span>
            </label>
            <label class="chip-choice">
              <input type="radio" name="comfort_vote" value="hot" required>
              <span class="chip-choice__ui">暑かった</span>
            </label>
          </div>
        </div>

        <div class="field-row">
          <label class="field">
            <span class="field-label">短いコメント</span>
            <input type="text" name="comment_text" maxlength="80" placeholder="例: 朝だけ少し寒い">
          </label>
          <label class="field">
            <span class="field-label">自由メモ</span>
            <input type="text" name="free_note" maxlength="140" placeholder="例: 電車の冷房が強かった">
          </label>
        </div>

        <div class="photo-drop">
          <label class="field">
            <span class="field-label">写真 1枚まで / 最大 2MB</span>
            <input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp" data-photo-input data-preview="#photo-preview">
          </label>
          <div class="photo-preview" id="photo-preview">画像を選ぶとここに表示されます</div>
        </div>

        <div style="display:flex; gap: 12px; flex-wrap: wrap;">
          <button class="button" type="submit">保存する</button>
          <a class="button button--ghost" href="<?= wc_h(wc_url('history.php')) ?>">履歴を見る</a>
        </div>
      </form>
    </section>
  </section>
<?php wc_render_shell_end();
