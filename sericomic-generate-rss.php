<?php

if ( ! defined( 'ABSPATH' ) ) die( 'No direct access allowed' );

require_once __DIR__ . '/sericomic-config.php';

header( 'Content-Type: application/rss+xml; charset=' . get_option( 'blog_charset' ), true );

$upload_dir = wp_upload_dir();
$json_text = file_get_contents( $upload_dir['basedir'] . '/sericomic/sericomic-data.json' );
$sericomic_data = json_decode( $json_text, true );

$entries = [];

$prev_pub_date = null;
$last_pub_date = null;
for ($chapter_i = count($sericomic_data['chapters']) - 1; $chapter_i >= 0; $chapter_i--) {
	$chapter = $sericomic_data['chapters'][$chapter_i];

	for ($page_i = count($chapter['pages']) - 1; $page_i >= 0; $page_i--) {
		$page = $chapter['pages'][$page_i];

		$pub_date = $page['lastUpdated'];
		if ($prev_pub_date !== null && $pub_date > $prev_pub_date) {
			$pub_date = $prev_pub_date;
		}
		if ($last_pub_date === null) {
			$last_pub_date = $pub_date;
		}
		$prev_pub_date = $pub_date;

		$url = home_url( '/' . SERICOMIC_PAGENAME . '/' . $page['id'] );
		$img_url = home_url( $page['img'] );
		$page_html = '<img src="' . $img_url . '" width="' . $page['width'] . '" height="' . $page['height'] . '" alt="comic page" />';
		$page_num = $page_i + $chapter['firstPage'];
		$title = $chapter['title'] . ($page_num !== 0 ? '' : ' - Page ' . $page_num);

		$entries[] = [
			'link' => $url,
			'title' => $title,
			'description' => $page_html,
			'guid' => $url,
			'pubDate' => date('D, d M Y H:i:s O', $pub_date),
			'featured_image' => $img_url,
		];

		if (count($entries) >= 10) {
			break 2;
		}
	}
}

echo '<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0">
<channel>
 <title>' . esc_html(SERICOMIC_TITLE) . '</title>
 <description>Comic updates for ' . esc_html(SERICOMIC_TITLE) . '</description>
 <link>' . home_url() . '</link>
 <lastBuildDate>' . date('D, d M Y H:i:s O', $last_pub_date) . '</lastBuildDate>
 <ttl>60</ttl>
 <language>en-US</language>
 <generator>Sericomic v0.0.1</generator>

';

foreach ($entries as $entry) {
	echo ' <item>
  <title>' . esc_html($entry['title']) . '</title>
  <description>' . esc_html($entry['description']) . '</description>
  <link>' . esc_url($entry['link']) . '</link>
  <guid isPermaLink="true">' . esc_html($entry['guid']) . '</guid>
  <pubDate>' . esc_html($entry['pubDate']) . '</pubDate>
  <featured_image>' . esc_url($entry['featured_image']) . '</featured_image>
 </item>

';
}

echo '</channel>
</rss>
';
