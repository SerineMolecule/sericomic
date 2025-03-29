<?php

if ( ! defined( 'ABSPATH' ) ) die( 'No direct access allowed' );

echo '<div class="wrap">';
echo '<h1>Sericomic</h1>';
echo '<p>If you\'ve set up the Git repository correctly, updates will happen automatically. But if something broke, this button is here for you:</p>';
echo '<form method="post" action="">';
echo '<button name="update">Update comic</button>';
echo '</form>';
if ( isset( $_POST['update'] ) ) {
	require_once __DIR__ . '/sericomic-generate-data.php';
	[ $result, $code ] = sericomic_update_data();
	echo '<pre>' . htmlspecialchars( $result ) . '</pre>';
	echo ( $code !== 0 ? '<p>Git update failed.</p>' : '<p>Git update done.</p>' );

	sericomic_generate_data();
	echo '<p>Comic data updated.</p>';
}
echo '</div>';
