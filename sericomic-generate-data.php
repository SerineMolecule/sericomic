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

	$first_chapter = null;
	foreach ( $dirs as $chapter_i => $dir ) {
		$title = extract_chapter_title( $chaptersDir . '/' . $dir . '/index.md' );
		$srcDir = $chaptersDir . '/' . $dir;
		$files = scandir( $srcDir );
		$images = [];

		if ( $first_chapter === null ) {
			$first_chapter = preg_match( '/0[^0-9]*$/', $dir ) ? 0 : 1;
		}
		$chapter_id = 'ch' . ( $chapter_i + $first_chapter );

		$first_page = null;
		$page_i = 0;
		foreach ( $files as $file ) {
			if ( substr( $file, 0, 1 ) === '.' ) {
				continue;
			}
			if ( substr( $file, -4 ) === '.jpg' ) {
				if ( $first_page === null ) {
					$first_page = preg_match( '/0[^0-9]*$/', $file ) ? 0 : 1;
				}
				$page_id = 'p' . ( $page_i + $first_page );
				if ( $page_id === 'p0' ) $page_id = '';
				$page_i++;

				$filePath = $srcDir . '/' . $file;
				$size = getimagesize( $filePath );
				$images[] = [
					'file' => $file,
					'img' => SERICOMIC_UPLOADS_URL . 'chapters/' . $dir . '/' . $file,
					'width' => $size[0],
					'height' => $size[1],
					'lastUpdated' => filemtime( $filePath ),
					'id' => $chapter_id . '/' . $page_id,
				];
			}
		}

		usort( $images, fn ($a, $b) => strcmp( $a['file'], $b['file'] ) );

		$chapters[] = [
			'title' => $title,
			'firstPage' => $first_page,
			'pages' => $images,
			'dir' => $dir,
		];
	}

	return [
		'chapters' => $chapters,
		'firstChapter' => $first_chapter,
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

