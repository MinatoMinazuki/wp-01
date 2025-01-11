<?php
/**
 * WordPress の基本設定
 *
 * このファイルは、インストール時に wp-config.php 作成ウィザードが利用します。
 * ウィザードを介さずにこのファイルを "wp-config.php" という名前でコピーして
 * 直接編集して値を入力してもかまいません。
 *
 * このファイルは、以下の設定を含みます。
 *
 * * MySQL 設定
 * * 秘密鍵
 * * データベーステーブル接頭辞
 * * ABSPATH
 *
 * @link http://wpdocs.osdn.jp/wp-config.php_%E3%81%AE%E7%B7%A8%E9%9B%86
 *
 * @package WordPress
 */

// 注意:
// Windows の "メモ帳" でこのファイルを編集しないでください !
// 問題なく使えるテキストエディタ
// (http://wpdocs.osdn.jp/%E7%94%A8%E8%AA%9E%E9%9B%86#.E3.83.86.E3.82.AD.E3.82.B9.E3.83.88.E3.82.A8.E3.83.87.E3.82.A3.E3.82.BF 参照)
// を使用し、必ず UTF-8 の BOM なし (UTF-8N) で保存してください。

// ** MySQL 設定 - この情報はホスティング先から入手してください。 ** //

if( $_SERVER['SERVER_NAME'] === "redcastle.jp" ){

    /** WordPress のためのデータベース名 */
    define('DB_NAME', 'wp');

    /** MySQL データベースのユーザー名 */
    define('DB_USER', 'root');

    /** MySQL データベースのパスワード */
    define('DB_PASSWORD', 'akatsuki005');

    /** MySQL のホスト名 */
    define('DB_HOST', 'localhost');

    /** データベースのテーブルを作成する際のデータベースの文字セット */
    define('DB_CHARSET', 'utf8mb4');

    /** データベースの照合順序 (ほとんどの場合変更する必要はありません) */
    define('DB_COLLATE', '');

} else {

    /** WordPress のためのデータベース名 */
    define('DB_NAME', 'wp');

    /** MySQL データベースのユーザー名 */
    define('DB_USER', 'root');

    /** MySQL データベースのパスワード */
    define('DB_PASSWORD', '');

    /** MySQL のホスト名 */
    define('DB_HOST', 'localhost');

    /** データベースのテーブルを作成する際のデータベースの文字セット */
    define('DB_CHARSET', 'utf8mb4');

    /** データベースの照合順序 (ほとんどの場合変更する必要はありません) */
    define('DB_COLLATE', '');

    define('WP_HOME', 'http://l-redcastle.jp/');
    define('WP_SITEURL', 'http://l-redcastle.jp/');

}

/**#@+
 * 認証用ユニークキー
 *
 * それぞれを異なるユニーク (一意) な文字列に変更してください。
 * {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org の秘密鍵サービス} で自動生成することもできます。
 * 後でいつでも変更して、既存のすべての cookie を無効にできます。これにより、すべてのユーザーを強制的に再ログインさせることになります。
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'C}<oOxV9C~PK-}}cFY0mF@K>]Wf!/rGP3eMjEMnxwqGXDjQ)Er]YVPu<*og~[;yf');
define('SECURE_AUTH_KEY',  'ntfd}+:M}^3U5kj#b!b]?=qT24XmB$( Y),q`jJE!WgYhBN<|&[Mt)GW]sA&Gjqk');
define('LOGGED_IN_KEY',    'HE|bn1+XJ4$OOK1*6L8uyO>?)$16DnN%qcm|`HFh^,Xzih&-06rlk.$Z0jPab3XO');
define('NONCE_KEY',        ';1&E%>OG>twLU}9Ax]BR=GoZF0&3Zi<|,$b #oy:)C(6K0k35)7`Y<I8|oh8RBid');
define('AUTH_SALT',        'x-R|SR7-2+Vu=W;k}wYuMLHHX2c HW2+]XZ>%)-a5n@Q)Z.*)K Zj)XRG0G|5JQS');
define('SECURE_AUTH_SALT', '&6OSH rOJlrO8qrtRdJ%WYmzhp?c12Ux`e$X@= #AsCr8&#LUYP3m%0xHK+rk1I&');
define('LOGGED_IN_SALT',   'sTpk4SOwE}roeE(|7RLSE>utj@+b*] Ky0}#Jo1Puc|xQk`v[8 ELV:MKT|!TfE[');
define('NONCE_SALT',       'Nuj* _^m2RWe26|pMP^iRTC@d}y@}r2iM1>D/Fhh$Rs|ET3$b^nFUG^p-24Xn;Y0');

/**#@-*/

/**
 * WordPress データベーステーブルの接頭辞
 *
 * それぞれにユニーク (一意) な接頭辞を与えることで一つのデータベースに複数の WordPress を
 * インストールすることができます。半角英数字と下線のみを使用してください。
 */
$table_prefix  = 'wp_';

/**
 * 開発者へ: WordPress デバッグモード
 *
 * この値を true にすると、開発中に注意 (notice) を表示します。
 * テーマおよびプラグインの開発者には、その開発環境においてこの WP_DEBUG を使用することを強く推奨します。
 *
 * その他のデバッグに利用できる定数については Codex をご覧ください。
 *
 * @link http://wpdocs.osdn.jp/WordPress%E3%81%A7%E3%81%AE%E3%83%87%E3%83%90%E3%83%83%E3%82%B0
 */
define('WP_DEBUG', false);

/* 編集が必要なのはここまでです ! WordPress でブログをお楽しみください。 */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
    define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
