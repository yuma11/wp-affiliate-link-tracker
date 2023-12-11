<?php

/**
 * Plugin Name: WP Affiliate Link Tracker
 * Description: ASP広告掲載URL抽出用プラグイン
 * Version: 0.1.3
 * Plugin URI: 
 * Author: 
 * Author URI: 
 * License: GPL2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-affiliate-link-tracker
 * Domain Path: /languages
 * Update URI: wp-affiliate-link-tracker
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/inc/update.php';

/**
 * menu 追加
 */
add_action('admin_menu', function () {
	add_submenu_page(
		'tools.php', // 親メニューのスラッグ
		'広告URL抽出', // head内のtitleタグに出力される文字列
		'広告URL抽出', // 管理画面左側のメニュー欄に表示される文字列
		'manage_options', // メニュー欄に表示するためのユーザー権限
		'wp-affiliate-link-tracker', // このメニューを参照するスラッグ名
		'wp_afilt__dashboard', // メニューに紐づく画面を描画するcallback関数
		'', // アイコン see: https://developer.wordpress.org/resource/dashicons/#awards
		99, // 表示位置のオフセット
	);
});

function wp_afilt__checknonce()
{
	return wp_verify_nonce($_POST['wp-afilt_nonce'], 'wp-afilt');
}

function wp_afilt__dashboard()
{
	require_once __DIR__ . '/inc/get_all_links.php';
	$cache = false;
	if (isset($_POST['wp-afilt-delete-cache']) && wp_afilt__checknonce()) {
		delete_transient('wp_afilt_data_cache');
	}

?>
	<style>
		.wrap {
			padding-block: 2rem
		}

		.toolBtns {
			margin-block: 1.5em;
			display: flex;
			gap: 1em;
		}

		.confArea {
			padding: 1.5em;
			background: #fff;
			border: solid 1px #ddd;
		}

		.confTable :is(td, th) {
			padding: 2px 6px;
			border: solid 1px gray
		}
	</style>
<?php
	echo '<div class="wrap">';

	$cache = get_transient('wp_afilt_data_cache');
	$has_cache = !!$cache;
	$ext_label = $has_cache ? 'リンクを再抽出' : 'リンクを抽出';
	$table_caption = $has_cache ? 'キャッシュ済みデータ (24時間保存されます。)' : '新規抽出データ';

	echo '<form method="post">';
	wp_nonce_field('wp-afilt', 'wp-afilt_nonce');

	echo '<div class="toolBtns">';
	echo '<button type="submit" name="wp-afilt-check" class="button button-primary">' . $ext_label . '</button>';
	echo '<button type="submit" name="wp-afilt-download" class="button">CSVダウンロード</button>';
	echo '</div>';


	if ($has_cache) {
		$link_list = $cache;
	}

	if (isset($_POST['wp-afilt-check']) && wp_afilt__checknonce()) {
		delete_transient('wp_afilt_data_cache');
		try {
			$link_list = \OPR\AfiLT\get_a8links();
			$table_caption = '新規抽出データ';
			if (empty($link_list)) {
				echo '<p>リンクは見つかりませんでした。</p>';
			}
		} catch (\Throwable $th) {
			echo '<p>エラーが発生しました</p>';
			echo '<p>' . $th->getMessage() . '</p>';
		}
	}


	if (!empty($link_list)) {
		echo '<div class="confArea">';


		echo '<table class="confTable">';
		if ($table_caption) {
			echo '<caption>' . $table_caption . '</caption>';
		}
		echo '<tr><th>記事url</th><th>プログラムID</th><th>使用リンク</th></tr>';
		foreach ($link_list as $link) {
			echo '<tr>';
			foreach ($link as $value) {
				echo '<td>' . $value . '</td>';
			}
			echo '</tr>';
		}
		echo '</table>';
		echo '</div>';


		if ($has_cache) {
			echo '<div class="toolBtns">';
			echo '<button type="submit" name="wp-afilt-delete-cache" class="button">キャッシュ削除</button>';
			echo '</div>';
		}
		echo '</form>';
	}

	echo '</div>';
}


add_action('admin_init', function () {
	if (!isset($_POST['wp-afilt-download'])) return;
	if (!wp_afilt__checknonce()) return;

	try {
		require_once __DIR__ . '/inc/get_all_links.php';
		$link_list = \OPR\AfiLT\get_a8links();
	} catch (\Throwable $th) {
		echo '<p>エラーが発生しました</p>';
		echo '<p>' . $th->getMessage() . '</p>';
	}

	require_once __DIR__ . '/inc/array_to_csv.php';
	$csv = \OPR\AfiLT\array_to_csv($link_list, ['記事url', 'プログラムID', '使用リンク']);

	// 出力
	$filename = 'a8_links_' . wp_date('Ymd') . '.csv';
	header('Content-Type: application/csv');
	header('Content-Disposition: attachment; filename="' . $filename . '";');
	echo $csv;
	exit;
});
