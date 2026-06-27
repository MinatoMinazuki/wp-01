<?php

declare(strict_types=1);

function wc_render_head(string $title, string $theme, string $timePeriod): void
{
    ?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= wc_h($title) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@500;700&family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= wc_h(wc_url('assets/app.css')) ?>">
</head>
<body class="theme-<?= wc_h($theme) ?> time-<?= wc_h($timePeriod) ?>">
<?php
}

function wc_render_shell_start(array $app, string $activeNav): void
{
    $flash = $app['flash'] ?? null;
    ?>
<main class="app-shell theme-<?= wc_h($GLOBALS['wearcast_theme'] ?? 'sunny') ?> time-<?= wc_h($GLOBALS['wearcast_time'] ?? 'day') ?>">
  <div class="chrome">
    <header class="masthead">
      <div>
        <p class="masthead__eyebrow">WEARCAST</p>
        <h1 class="masthead__title"><?= wc_h('天気と気温から、その日の一着を決める') ?></h1>
      </div>
    </header>

    <nav class="nav-tabs" aria-label="Primary">
      <?php
      $items = [
          'today' => ['label' => 'Today', 'href' => wc_url('index.php')],
          'record' => ['label' => 'Record', 'href' => wc_url('record.php')],
          'history' => ['label' => 'History', 'href' => wc_url('history.php')],
          'settings' => ['label' => 'Settings', 'href' => wc_url('settings.php')],
      ];
      foreach ($items as $key => $item): ?>
        <a class="nav-tab<?= $activeNav === $key ? ' is-active' : '' ?>" href="<?= wc_h($item['href']) ?>"><?= wc_h($item['label']) ?></a>
      <?php endforeach; ?>
    </nav>

    <?php if ($flash): ?>
      <div class="flash flash--<?= wc_h($flash['type']) ?>"><?= wc_h($flash['message']) ?></div>
    <?php endif; ?>
<?php
}

function wc_render_shell_end(): void
{
    ?>
  </div>
</main>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
window.WEARCAST = window.WEARCAST || {};
window.WEARCAST.baseUrl = <?= json_encode(wc_base_url(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= wc_h(wc_url('assets/app.js')) ?>"></script>
</body>
</html>
<?php
}
