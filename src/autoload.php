<?php

spl_autoload_register( function( $name ) {

	$prefix = 'WordPress\\YandexCloudAiProvider\\';

	if ( strncmp( $name, $prefix, strlen( $prefix ) ) !== 0 ) {
		return;
	}

	$file = __DIR__ . '/' . str_replace( '\\', '/', substr( $name, strlen( $prefix ) ) ) . '.php';

	if ( file_exists( $file ) ) {
		require $file;
	}

} );
