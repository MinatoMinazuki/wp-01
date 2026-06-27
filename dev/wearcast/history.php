<?php

declare(strict_types=1);

$app = require __DIR__ . '/app/bootstrap.php';
$forecast = wc_today_forecast($app['activeLocation']);
$GLOBALS['wearcast_theme'] = $forecast['weather_group'];
$GLOBALS['wearcast_time'] = wc_time_period();
$records = wc_get_records($app['pdo'], $app['user']);
$comfortLabels = [
    'cold' => '寒かった',
    'just' => 'ちょうどよかった',
    'hot' => '暑かった',
];

wc_render_head('Wearcast History', $forecast['weather_group'], $GLOBALS['wearcast_time']);
wc_render_shell_start($app, 'history');
?>
  <section class="panel-card">
    <p class="section-card__eyebrow">HISTORY</p>
    <h2 class="page-title">記録一覧</h2>
    <?php if (!$records): ?>
      <div class="empty-state" style="margin-top: 18px;">
        <p class="muted">まだ記録がありません。まずは今日の服装を一件残してください。</p>
      </div>
    <?php else: ?>
      <div class="record-list" style="margin-top: 18px;">
        <?php foreach ($records as $record): ?>
          <article class="section-card record-item">
            <?php if (!empty($record['image_path'])): ?>
              <div class="record-item__photo" style="background-image:url('<?= wc_h(wc_record_image_url($record['image_path'])) ?>');"></div>
            <?php else: ?>
              <div class="record-item__photo"></div>
            <?php endif; ?>
            <div class="record-item__body">
              <p class="record-item__meta"><?= wc_h((string) $record['record_date']) ?> / <?= wc_h((string) ($record['location_label'] ?? '地点未設定')) ?></p>
              <h3 class="record-item__headline"><?= wc_h($record['outfit_category']) ?></h3>
              <p class="record-item__detail muted">
                <?= wc_h($record['weather_label']) ?> / 最高 <?= wc_h((string) (int) round((float) $record['temp_max'])) ?>° / 最低 <?= wc_h((string) (int) round((float) $record['temp_min'])) ?>°
                <br>感想: <?= wc_h($comfortLabels[$record['comfort_vote']] ?? (string) $record['comfort_vote']) ?>
                <?php if (!empty($record['comment_text'])): ?><br><?= wc_h($record['comment_text']) ?><?php endif; ?>
                <?php if (!empty($record['free_note'])): ?><br><?= wc_h($record['free_note']) ?><?php endif; ?>
              </p>
            </div>
            <div class="record-item__actions">
              <?php if ((string) $record['record_date'] === date('Y-m-d')): ?>
                <form action="<?= wc_h(wc_url('actions/delete_record.php')) ?>" method="post">
                  <input type="hidden" name="csrf_token" value="<?= wc_h(wc_csrf_token()) ?>">
                  <input type="hidden" name="record_id" value="<?= wc_h((string) $record['id']) ?>">
                  <button class="button button--danger" type="submit">今日の記録を削除</button>
                </form>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
<?php wc_render_shell_end();
