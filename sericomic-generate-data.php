<?php

if ( ! defined( 'ABSPATH' ) ) die( 'No direct access allowed' );

require_once __DIR__ . '/sericomic-config.php';

function list_chapters( string $chaptersDir ): array {
	$dirs = array_filter( scandir( $chaptersDir ), fn ( $dir ) => strpos( $dir, '.' ) === false );
	sort( $dirs );
	return $dirs;
}

function extract_comic_data( string $chaptersDir ): array {
	$dirs = list_chapters( $chaptersDir );
	$chapters = [];

	$has_chapter_zero = null;
	foreach ( $dirs as $dir ) {
		$title = extract_chapter_title( $chaptersDir . '/' . $dir . '/index.md' );
		$srcDir = $chaptersDir . '/' . $dir;
		$files = scandir( $srcDir );
		$images = [];

		if ( $has_chapter_zero === null ) {
			$has_chapter_zero = !! preg_match( '/0[^0-9]*$/', $dir );
		}

		$has_page_zero = null;
		foreach ( $files as $file ) {
			if ( substr( $file, 0, 1 ) === '.' ) {
				continue;
			}
			if ( substr( $file, -4 ) === '.jpg' ) {
				if ( $has_page_zero === null ) {
					$has_page_zero = !! preg_match( '/0[^0-9]*$/', $file );
				}
				$filePath = $srcDir . '/' . $file;
				$size = getimagesize( $filePath );
				$images[] = [
					'file' => basename( $file ),
					'width' => $size[0],
					'height' => $size[1],
				];
			}
		}
		
		usort( $images, fn ($a, $b) => strcmp( $a['file'], $b['file'] ) );
		
		$chapters[] = [
			'title' => $title,
			'firstPage' => $has_page_zero ? 0 : 1,
			'pages' => $images,
			'dir' => $dir,
		];
	}

	return [
		'chapters' => $chapters,
		'firstChapter' => $has_chapter_zero ? 0 : 1,
		'uploadsUrl' => SERICOMIC_UPLOADS_URL,
		'comicUrl' => '/' . SERICOMIC_PAGENAME . '/',
		'lastUpdated' => time(),
	];
}

function extract_chapter_title( string $src ): string {
	$content = @file_get_contents( $src );

	if ( $content !== false && preg_match( '/# (.*)/', $content, $matches ) ) {
		return $matches[1];
	}

	return basename( dirname( $src ) );
}

function sericomic_update_data() {
	$uploads_dir = wp_upload_dir();
	$chapters_dir = $uploads_dir['basedir'] . '/sericomic/chapters';
	$old_dir = getcwd();
	chdir( $chapters_dir );
	exec( "git pull", $result, $errorCode );
	chdir( $old_dir );
	return [
		implode( "\n", $result ),
		$errorCode,
	];
}

function sericomic_generate_data() {
	$uploads_dir = wp_upload_dir();
	$chapters_dir = $uploads_dir['basedir'] . '/sericomic/chapters';
	$comic_data = extract_comic_data( $chapters_dir );

	file_put_contents(
		$uploads_dir['basedir'] . '/sericomic/sericomic-data.js',
		"'use strict';\nconst sericomicData = " . wp_json_encode( $comic_data, JSON_PRETTY_PRINT ) . ";\n"
	);
	
	file_put_contents(
		$uploads_dir['basedir'] . '/sericomic/sericomic-data.json',
		wp_json_encode( $comic_data, JSON_PRETTY_PRINT )
	);
}

