<?php
/**
 * Plugin Name:       Sericomic
 * Plugin URI:        https://github.com/SerineMolecule/sericomic
 * Description:       Comic viewer plugin. Use [sericomic] shortcode to display a comic in uploads.
 * Version:           0.0.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            SerineMolecule
 * Author URI:        https://github.com/SerineMolecule
 * License:           MIT
 * License URI:       https://opensource.org/license/mit
 * Text Domain:       sericomic
 */

if ( ! defined( 'ABSPATH' ) ) die( 'No direct access allowed' );

require_once __DIR__ . '/sericomic-config.php';
define( 'SERICOMIC_PAGENAME', 'comic' );

function sericomic_shortcode( $atts ) {
	$upload_dir = wp_upload_dir(); 
	$page = sericomic_current_page();
	$chapter_html = null;
	$cachebuster = '?v=' . $page['lastUpdated'];
	if ( $page ) {
		$dir = $page['chapter']['dir'];
		$file = $page['file'];
		$url = "/wp-content/uploads/sericomic/chapters/$dir/$file";
		$chapter_html = '<img class="sericomic-image" src="' . $url . '" width="' . $page['width'] . '" height="' . $page['height'] . '" alt="comic page" />';
	}

	return '<style>' . file_get_contents( __DIR__ . '/style.css' ) . '</style>' . "\n" .
		'<div class="sericomic">' . ($chapter_html ?? '<div class="sericomic-buttonbar"><p><select class="sericomic-chapters" value="ch1"><option value="ch1" selected="">Chapter 1 - Chapter</option></select> <select class="sericomic-pages" value="ch1"><option value="ch1" selected="">Page 1</option></select></p><button disabled="" class="sericomic-disabledbutton"><i aria-hidden="true" class="fas fa-chevron-left"></i> Previous Page</button> <button class="sericomic-button" data-page="ch1/p2">Next Page <i aria-hidden="true" class="fas fa-chevron-right"></i></button></div><p>[Comic goes here]</p>') . '</div>' . "\n" .
		'<script src="' . esc_url( str_replace( 'http:', '', $upload_dir['baseurl'] ) . '/sericomic/sericomic-data.js' . $cachebuster ) . '"></script>' . "\n" .
		'<script src="' . esc_url( plugins_url( '/sericomic-viewer.js?', __FILE__ ) ) . '"></script>' . "\n" .
		'<script>const sericomic = new Sericomic(document.querySelector(\'.sericomic\'), sericomicData);</script>';
}

function sericomic_admin_menu() {
	add_management_page(
		'Sericomic',
		'Sericomic',
		'manage_options',
		'sericomic',
		'sericomic_admin_page'
	);
}

function sericomic_admin_page() {
	require __DIR__ . '/sericomic-admin-page.php';
}

function sericomic_plugin_links( $links ) {
	$url = add_query_arg( 'page', 'sericomic', admin_url( 'tools.php' ) );
	array_unshift( $links, '<a href="' . esc_url( $url ) . '">Update comic</a>' );

	return $links;
}

$sericomic_data = null;

function sericomic_get( ?string $comicpageid ): ?array {
	global $sericomic_data;
	if ( ! $sericomic_data ) {
		$upload_dir = wp_upload_dir(); 
		$json_text = file_get_contents( $upload_dir['basedir'] . '/sericomic/sericomic-data.json' );
		$sericomic_data = json_decode( $json_text, true );
	}

	if ( $comicpageid === '' ) {
		$comicpageid = 'ch0';
	} else if ( ! $comicpageid ) {
		return null;
	}

	$parts = explode( '/', $comicpageid );
	$chapter = $parts[0] ?? '';
	$page = $parts[1] ?? '';
	if ($page === '') $page = 'p0';

	if ( substr( $chapter, 0, 2 ) !== 'ch' || substr( $page, 0, 1 ) !== 'p' ) {
		return null;
	}

	$chapter_i = intval( substr( $chapter, 2 ) ) - $sericomic_data['firstChapter'];
	if ( $chapter_i === -$sericomic_data['firstChapter'] ) $chapter_i = 0;
	$chapter_data = $sericomic_data['chapters'][ $chapter_i ] ?? null;
	if ( ! $chapter_data ) {
		return null;
	}

	$page_i = intval( substr( $page, 1 ) ) - $chapter_data['firstPage'];
	if ( $page_i === -$chapter_data['firstPage'] ) $page_i = 0;
	$page_data = $chapter_data['pages'][ $page_i ] ?? null;
	if ( ! $page_data ) {
		return null;
	}

	$page_data['chapter'] = $chapter_data;
	$page_data['lastUpdated'] = $sericomic_data['lastUpdated'];

	return $page_data;
}

function sericomic_current_page(): ?array {
	$prefix = '/' . SERICOMIC_PAGENAME . '/';

	if ( $prefix === substr( $_SERVER['REQUEST_URI'], 0, strlen( $prefix ) ) ) {
		$pageid = substr( $_SERVER['REQUEST_URI'], strlen( $prefix ) );
		return sericomic_get( $pageid );
	}

	return null;
}

function sericomic_rewrite_request( array $qv ): array {
	if ( sericomic_current_page() !== null ) {
		$qv = [ 'pagename' => SERICOMIC_PAGENAME ];
	}

	return $qv;
}


function sericomic_rest_update( WP_REST_Request $request ) {
	if ( $request->get_param( 'key' ) !== SERICOMIC_KEY ) {
		return new WP_REST_Response( false, 403 );
	}
	require_once __DIR__ . '/sericomic-generate-data.php';
	$git_output = sericomic_update_data();
	sericomic_generate_data();

	return new WP_REST_Response( [ 'git_output' => $git_output ], 200 );
}

add_shortcode( 'sericomic', 'sericomic_shortcode' );

add_action( 'admin_menu', 'sericomic_admin_menu' );

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'sericomic_plugin_links' );

add_filter( 'request', 'sericomic_rewrite_request' );

add_action( 'rest_api_init', function () {
  register_rest_route( 'sericomic', '/update', [
    'methods' => [ 'GET', 'POST' ],
    'callback' => 'sericomic_rest_update',
    'args' => [
      'key' => [
				'required' => true,
			],
		],
    'permission_callback' => '__return_true',
  ] );
} );
