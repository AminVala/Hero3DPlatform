<?php
/**
 * Frontend scroll engine bootstrap.
 *
 * @package ScrollHeroSequence
 */

declare(strict_types=1);

namespace ScrollHeroSequence\Frontend;

use ScrollHeroSequence\Domain\HeroConfig;
use ScrollHeroSequence\PostType\HeroSequence;

final class ScrollEngine {

	public function register(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
		// Early detection: enqueue + preload as soon as we know the page uses a hero.
		add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_for_current_page' ], 20 );
	}

	public function register_assets(): void {
		wp_register_style(
			'shs-scroll-engine',
			SHS_PLUGIN_URL . 'assets/public/scroll-engine.css',
			[],
			SHS_VERSION
		);

		wp_register_script(
			'shs-scroll-engine',
			SHS_PLUGIN_URL . 'assets/public/scroll-engine.js',
			[],
			SHS_VERSION,
			true
		);
	}

	/**
	 * When the current singular post contains the hero block, enqueue the
	 * engine early (so CSS lands in <head>, avoiding FOUC) and preload the
	 * poster frame for a strong LCP.
	 */
	public function maybe_enqueue_for_current_page(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_post();
		if ( ! $post instanceof \WP_Post || ! has_block( 'scroll-hero-sequence/hero-sequence', $post ) ) {
			return;
		}

		wp_enqueue_style( 'shs-scroll-engine' );
		wp_enqueue_script( 'shs-scroll-engine' );

		// Preload the first/poster frame of the first hero block on the page.
		$blocks = parse_blocks( $post->post_content );
		foreach ( $blocks as $block ) {
			if ( 'scroll-hero-sequence/hero-sequence' !== ( $block['blockName'] ?? '' ) ) {
				continue;
			}
			$hero_id = absint( $block['attrs']['heroId'] ?? 0 );
			if ( ! $hero_id ) {
				continue;
			}
			$config = self::get_public_config( $hero_id );
			$poster = $config['poster_url'] ?? '';
			if ( $poster ) {
				add_action(
					'wp_head',
					static function () use ( $poster ): void {
						echo '<link rel="preload" as="image" fetchpriority="high" href="' . esc_url( (string) $poster ) . '">' . "\n";
					},
					1
				);
			}
			break;
		}
	}

	public static function enqueue_for_hero( int $hero_id ): void {
		// Safe to call from render.php as a fallback; the engine boots from the
		// in-DOM `.shs-hero[data-config]` markup, so no JS queue is needed.
		wp_enqueue_style( 'shs-scroll-engine' );
		wp_enqueue_script( 'shs-scroll-engine' );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function get_public_config( int $hero_id ): ?array {
		$post = get_post( $hero_id );
		if ( ! $post || HeroSequence::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return null;
		}

		$controller = new \ScrollHeroSequence\Admin\REST\HeroController();
		$request    = new \WP_REST_Request( 'GET', '/scroll-hero-sequence/v1/heroes/' . $hero_id );
		$request->set_param( 'id', $hero_id );
		$response = $controller->get_hero( $request );
		$data     = $response->get_data();

		return is_array( $data ) ? $data : null;
	}
}
