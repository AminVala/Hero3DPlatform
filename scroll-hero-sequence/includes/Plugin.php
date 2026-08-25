<?php
/**
 * Plugin bootstrap.
 *
 * @package ScrollHeroSequence
 */

declare(strict_types=1);

namespace ScrollHeroSequence;

use ScrollHeroSequence\Admin\HeroAdmin;
use ScrollHeroSequence\Admin\PreviewController;
use ScrollHeroSequence\Admin\REST\HeroController;
use ScrollHeroSequence\Frontend\ScrollEngine;
use ScrollHeroSequence\Limits\PlanLimiter;
use ScrollHeroSequence\PostType\HeroSequence;

final class Plugin {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function boot(): void {
		register_activation_hook( SHS_PLUGIN_FILE, [ $this, 'activate' ] );

		add_action( 'init', [ $this, 'init' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
	}

	public function activate(): void {
		HeroSequence::register();
		HeroSequence::register_capabilities();
		flush_rewrite_rules();
	}

	public function init(): void {
		load_plugin_textdomain( 'scroll-hero-sequence', false, dirname( SHS_PLUGIN_BASENAME ) . '/languages' );

		HeroSequence::register();
		( new HeroAdmin() )->register();
		( new PreviewController() )->register();
		( new ScrollEngine() )->register();
		( new PlanLimiter() )->register();

		if ( function_exists( 'register_block_type' ) ) {
			register_block_type( SHS_PLUGIN_DIR . 'includes/Blocks/hero-sequence' );
		}
	}

	public function register_rest_routes(): void {
		( new HeroController() )->register_routes();
	}
}
