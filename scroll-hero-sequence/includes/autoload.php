<?php
/**
 * PSR-4 autoloader for Scroll Hero Sequence.
 *
 * @package ScrollHeroSequence
 */

declare(strict_types=1);

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'ScrollHeroSequence\\';
		$base   = SHS_PLUGIN_DIR . 'includes/';

		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$file     = $base . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);
