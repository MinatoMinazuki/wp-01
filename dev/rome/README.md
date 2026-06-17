# Roman Territorial Simulator

ローマ建国の紀元前753年から西ローマ帝国の終点として扱う西暦476年まで、支配領域の変化を年単位で見るための試作アプリです。

背景地図は OpenFreeMap の公開タイルを MapLibre GL JS で表示し、ローマ領域はその上に SVG オーバーレイとして重ねています。APIキーや課金設定は不要です。

## URL

XAMPP の VirtualHost 設定では次のURLで開けます。

```text
http://l-redcastle.jp/dev/rome/
```

年を指定して直接開く場合:

```text
http://l-redcastle.jp/dev/rome/?year=117
http://l-redcastle.jp/dev/rome/?year=476
```

## Files

```text
index.php                 画面本体
assets/app.js             年次シミュレーション、MapLibre初期化、SVG領域描画
assets/styles.css         レイアウトとビジュアル
api/territory.php         JSONデータ返却API
data/rome_timeline.json   年表、領域、支配状態、簡略ポリゴン
```

## Map Choice

- OpenFreeMap: 無料公開インスタンス、登録なし、APIキーなし。
- MapLibre GL JS: オープンソースのブラウザ地図描画ライブラリ。
- ローマ領域は自前データなので、背景地図を変えてもシミュレーション部分は維持できます。

## Reference Links

- 領域をクリックすると詳細パネルに短い解説と外部検索リンクを表示します。
- 節目をクリックするとその年へ移動し、選択中の節目カードに本文と外部検索リンクを表示します。
- 外部リンクは固定URLではなく、項目名から日本語Wikipedia検索とコトバンク検索を生成します。

## Current Limits

- 領域ポリゴンは試作用の概略形状です。史料精査済みの精密境界 GeoJSON ではありません。
- 月単位、戦役単位、行政区分単位の変化はまだ入れていません。
- OpenFreeMap の公開インスタンスと CDN に依存します。完全オフライン化する場合は、Natural Earth や自前タイルに差し替える想定です。

## Verified

- PHP syntax: `index.php`, `api/territory.php`
- JSON parse: `data/rome_timeline.json`
- HTTP page: `http://l-redcastle.jp/dev/rome/?year=476`
- HTTP API: `http://l-redcastle.jp/dev/rome/api/territory.php?year=476`
- Screenshots:
  - `screenshot-final-117.png`
  - `screenshot-final-476.png`
  - `screenshot-final-mobile-117-clean.png`
