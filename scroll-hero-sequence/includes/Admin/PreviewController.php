<?php
/**
 * Full-page hero preview rendered outside the REST layer.
 *
 * REST responses are always JSON-encoded, so an HTML string returned from a
 * REST callback reaches the browser as escaped JSON. The preview therefore
 * lives on admin-post.php, where we can emit real text/html and exit.
 *
 * @package ScrollHeroSequence
 */

declare(strict_types=1);

namespace ScrollHeroSequence\Admin;

use ScrollHeroSequence\Admin\REST\HeroController;
use ScrollHeroSequence\PostType\HeroSequence;

final class PreviewController {

	public const ACTION = 'shs_preview';

	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, [ $this, 'render' ] );
	}

	/**
	 * Build the nonce-protected preview URL for a hero.
	 */
	public static function url( int $hero_id ): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=' . self::ACTION . '&hero=' . $hero_id ),
			self::ACTION . '_' . $hero_id
		);
	}

	public function render(): void {
		$hero_id = isset( $_GET['hero'] ) ? absint( wp_unslash( $_GET['hero'] ) ) : 0;

		if ( ! $hero_id ) {
			wp_die( esc_html__( 'Invalid hero.', 'scroll-hero-sequence' ) );
		}

		check_admin_referer( self::ACTION . '_' . $hero_id );

		if ( ! current_user_can( 'edit_post', $hero_id ) ) {
			wp_die(
				esc_html__( 'You are not allowed to preview this hero.', 'scroll-hero-sequence' ),
				esc_html__( 'Forbidden', 'scroll-hero-sequence' ),
				[ 'response' => 403 ]
			);
		}

		$post = get_post( $hero_id );
		if ( ! $post || HeroSequence::POST_TYPE !== $post->post_type ) {
			wp_die( esc_html__( 'Hero not found.', 'scroll-hero-sequence' ) );
		}

		$data    = ( new HeroController() )->serialize_hero( $post );
		$payload = (string) wp_json_encode( $data, JSON_UNESCAPED_UNICODE );

		$dir  = is_rtl() ? 'rtl' : 'ltr';
		$lang = esc_attr( str_replace( '_', '-', (string) get_bloginfo( 'language' ) ) );

		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );

		// Direct, escaped HTML output — not routed through REST/JSON.
		echo '<!DOCTYPE html><html dir="' . esc_attr( $dir ) . '" lang="' . esc_attr( $lang ) . '">';
		echo '<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
		echo '<title>' . esc_html__( 'Hero Preview', 'scroll-hero-sequence' ) . '</title>';
		echo '<link rel="stylesheet" href="' . esc_url( SHS_PLUGIN_URL . 'assets/public/scroll-engine.css' ) . '?ver=' . esc_attr( SHS_VERSION ) . '">';
		echo '</head><body class="shs-preview-body">';
		echo '<div id="shs-hero-root" class="shs-hero" data-config="' . esc_attr( $payload ) . '"></div>';
		echo '<script src="' . esc_url( SHS_PLUGIN_URL . 'assets/public/scroll-engine.js' ) . '?ver=' . esc_attr( SHS_VERSION ) . '"></script>';
		echo '</body></html>';

		exit;
	}
}
