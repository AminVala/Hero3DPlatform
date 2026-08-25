<?php
/**
 * Admin UI hooks.
 *
 * @package ScrollHeroSequence
 */

declare(strict_types=1);

namespace ScrollHeroSequence\Admin;

use ScrollHeroSequence\Domain\HeroConfig;
use ScrollHeroSequence\Limits\PlanLimiter;
use ScrollHeroSequence\PostType\HeroSequence;
use ScrollHeroSequence\Templates\TemplateRegistry;

final class HeroAdmin {

	public function register(): void {
		add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'save_post_' . HeroSequence::POST_TYPE, [ $this, 'save_meta' ], 10, 2 );
		add_filter( 'manage_' . HeroSequence::POST_TYPE . '_posts_columns', [ $this, 'columns' ] );
		add_action( 'manage_' . HeroSequence::POST_TYPE . '_posts_custom_column', [ $this, 'column_content' ], 10, 2 );
	}

	public function register_meta_boxes(): void {
		add_meta_box(
			'shs-hero-editor',
			__( 'Hero Storyboard', 'scroll-hero-sequence' ),
			[ $this, 'render_meta_box' ],
			HeroSequence::POST_TYPE,
			'normal',
			'high'
		);
	}

	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'shs_save_hero', 'shs_hero_nonce' );

		$config   = HeroConfig::from_post( $post );
		$registry = new TemplateRegistry();
		$limiter  = new PlanLimiter();
		$frames   = HeroSequence::get_frame_attachment_ids( $post->ID );

		include SHS_PLUGIN_DIR . 'includes/Admin/views/hero-editor.php';
	}

	public function enqueue_assets( string $hook ): void {
		global $post_type;

		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		if ( HeroSequence::POST_TYPE !== $post_type ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style(
			'shs-admin',
			SHS_PLUGIN_URL . 'assets/admin/admin.css',
			[],
			SHS_VERSION
		);
		wp_enqueue_script(
			'shs-admin',
			SHS_PLUGIN_URL . 'assets/admin/admin.js',
			[ 'jquery', 'wp-i18n' ],
			SHS_VERSION,
			true
		);

		wp_localize_script(
			'shs-admin',
			'shsAdmin',
			[
				'restUrl'   => rest_url( 'scroll-hero-sequence/v1/' ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'templates' => ( new TemplateRegistry() )->list_for_admin(),
				'isPro'     => ( new PlanLimiter() )->is_pro(),
				'i18n'      => [
					'selectMaster'    => __( 'Select Master Frame', 'scroll-hero-sequence' ),
					'preview'         => __( 'Preview', 'scroll-hero-sequence' ),
					'confirmTemplate' => __( 'Applying a template will replace the current storyboard and overlays. Continue?', 'scroll-hero-sequence' ),
				],
			]
		);
	}

	public function save_meta( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['shs_hero_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['shs_hero_nonce'] ) ), 'shs_save_hero' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$raw_config = isset( $_POST['shs_config'] ) ? wp_unslash( $_POST['shs_config'] ) : '';
		if ( is_string( $raw_config ) && '' !== $raw_config ) {
			$decoded = json_decode( $raw_config, true );
			if ( is_array( $decoded ) ) {
				$decoded = $this->sanitize_config( $decoded );
				$config  = HeroConfig::from_array( $decoded );
				$config->save_to_post( $post_id );
			}
		}

		if ( isset( $_POST['shs_master_image_id'] ) ) {
			update_post_meta( $post_id, '_shs_master_image_id', absint( $_POST['shs_master_image_id'] ) );
		}

		if ( isset( $_POST['shs_template_slug'] ) ) {
			update_post_meta( $post_id, '_shs_template_slug', sanitize_key( wp_unslash( $_POST['shs_template_slug'] ) ) );
		}
	}

	/**
	 * Deep-sanitize the decoded hero config before persisting.
	 *
	 * Untrusted values reach this from the editor JSON payload. Overlay text,
	 * URLs and positions are echoed on the frontend, so everything is cleaned
	 * and the overlay count is capped to the current plan.
	 *
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	private function sanitize_config( array $data ): array {
		$allowed_types      = [ 'text', 'h1', 'subtitle', 'eyebrow', 'cta', 'nav', 'trust', 'logo' ];
		$allowed_anim       = [ 'fade', 'slide_up', 'scale', 'none' ];
		$allowed_variants   = [ 'filled', 'outlined', 'text' ];
		$overlay_limit      = ( new PlanLimiter() )->overlay_limit();

		$sanitize_url = static function ( $url ): string {
			$url = esc_url_raw( (string) $url, [ 'http', 'https', 'mailto', 'tel' ] );
			return $url;
		};

		$sanitize_pos = static function ( $val ): string {
			$val = (string) $val;
			return preg_match( '/^-?\d{1,4}(\.\d+)?(%|px)?$/', $val ) ? $val : '';
		};

		$clean = [];

		$clean['template_slug']        = sanitize_key( (string) ( $data['template_slug'] ?? '' ) );
		$clean['master_frame_index']   = absint( $data['master_frame_index'] ?? 14 );
		$clean['total_frames_desktop'] = absint( $data['total_frames_desktop'] ?? 24 );
		$clean['total_frames_mobile']  = absint( $data['total_frames_mobile'] ?? 16 );
		$clean['master_image_id']      = absint( $data['master_image_id'] ?? 0 );
		$clean['prompt_start']         = sanitize_textarea_field( (string) ( $data['prompt_start'] ?? '' ) );
		$clean['generation_status']    = sanitize_key( (string) ( $data['generation_status'] ?? 'idle' ) );

		// Scenes (structural only — no user free-text rendered as HTML).
		$clean['scenes'] = [];
		foreach ( (array) ( $data['scenes'] ?? [] ) as $scene ) {
			$clean['scenes'][] = [
				'index'           => absint( $scene['index'] ?? 0 ),
				'frame_start'     => absint( $scene['frame_start'] ?? 0 ),
				'frame_end'       => absint( $scene['frame_end'] ?? 0 ),
				'label'           => sanitize_text_field( (string) ( $scene['label'] ?? '' ) ),
				'generation_mode' => in_array( ( $scene['generation_mode'] ?? 'ai' ), [ 'ai', 'manual', 'locked' ], true ) ? $scene['generation_mode'] : 'ai',
				'beats'           => array_map(
					static fn ( $b ) => [
						'index'       => absint( $b['index'] ?? 0 ),
						'frame'       => absint( $b['frame'] ?? 0 ),
						'description' => sanitize_text_field( (string) ( $b['description'] ?? '' ) ),
						'is_keyframe' => (bool) ( $b['is_keyframe'] ?? true ),
					],
					(array) ( $scene['beats'] ?? [] )
				),
			];
		}

		// Overlay steps — capped to plan + fully sanitized.
		$clean['overlay_steps'] = [];
		$steps                  = array_slice( (array) ( $data['overlay_steps'] ?? [] ), 0, $overlay_limit );
		foreach ( $steps as $step ) {
			$type    = in_array( ( $step['element_type'] ?? 'text' ), $allowed_types, true ) ? $step['element_type'] : 'text';
			$content = (array) ( $step['content'] ?? [] );
			$clean_content = [];

			if ( isset( $content['text'] ) ) {
				$clean_content['text'] = sanitize_text_field( (string) $content['text'] );
			}
			if ( isset( $content['alt'] ) ) {
				$clean_content['alt'] = sanitize_text_field( (string) $content['alt'] );
			}
			if ( ! empty( $content['buttons'] ) && is_array( $content['buttons'] ) ) {
				$clean_content['buttons'] = array_map(
					static fn ( $btn ) => [
						'label'   => sanitize_text_field( (string) ( $btn['label'] ?? '' ) ),
						'url'     => $sanitize_url( $btn['url'] ?? '' ),
						'variant' => in_array( ( $btn['variant'] ?? 'filled' ), $allowed_variants, true ) ? $btn['variant'] : 'filled',
					],
					$content['buttons']
				);
			}
			if ( ! empty( $content['items'] ) && is_array( $content['items'] ) ) {
				$clean_content['items'] = array_map(
					static fn ( $item ) => [
						'label' => sanitize_text_field( (string) ( $item['label'] ?? '' ) ),
						'url'   => $sanitize_url( $item['url'] ?? '' ),
						'icon'  => sanitize_key( (string) ( $item['icon'] ?? '' ) ),
					],
					$content['items']
				);
			}

			$position = (array) ( $step['position'] ?? [] );

			$clean['overlay_steps'][] = [
				'frame_trigger' => absint( $step['frame_trigger'] ?? 0 ),
				'element_type'  => $type,
				'content'       => $clean_content,
				'animation'     => in_array( ( $step['animation'] ?? 'fade' ), $allowed_anim, true ) ? $step['animation'] : 'fade',
				'position'      => [
					'x'      => $sanitize_pos( $position['x'] ?? '' ),
					'y'      => $sanitize_pos( $position['y'] ?? '' ),
					'anchor' => sanitize_key( (string) ( $position['anchor'] ?? '' ) ),
				],
				'style_token'   => sanitize_html_class( (string) ( $step['style_token'] ?? '' ) ),
			];
		}

		// Lock zone.
		$lock = (array) ( $data['lock_zone'] ?? [] );
		$clean['lock_zone'] = [
			'start_frame'          => absint( $lock['start_frame'] ?? 14 ),
			'end_frame'            => absint( $lock['end_frame'] ?? 24 ),
			'source_attachment_id' => absint( $lock['source_attachment_id'] ?? 0 ),
		];

		// Scroll config (whitelisted scalars).
		$scroll = (array) ( $data['scroll_config'] ?? [] );
		$clean['scroll_config'] = [
			'pin_duration'   => preg_match( '/^\d{1,4}(%|px|vh)$/', (string) ( $scroll['pin_duration'] ?? '300%' ) ) ? $scroll['pin_duration'] : '300%',
			'easing'         => sanitize_key( (string) ( $scroll['easing'] ?? 'none' ) ),
			'mobile_disable' => (bool) ( $scroll['mobile_disable'] ?? false ),
			'reduced_motion' => sanitize_key( (string) ( $scroll['reduced_motion'] ?? 'poster' ) ),
		];

		return $clean;
	}

	/**
	 * @param string[] $columns
	 * @return string[]
	 */
	public function columns( array $columns ): array {
		$new = [];
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['shs_template'] = __( 'Template', 'scroll-hero-sequence' );
				$new['shs_status']   = __( 'Status', 'scroll-hero-sequence' );
			}
		}
		return $new;
	}

	public function column_content( string $column, int $post_id ): void {
		if ( 'shs_template' === $column ) {
			echo esc_html( (string) get_post_meta( $post_id, '_shs_template_slug', true ) ?: 'shahre-honar' );
		}
		if ( 'shs_status' === $column ) {
			$post = get_post( $post_id );
			if ( $post instanceof \WP_Post ) {
				echo esc_html( HeroConfig::from_post( $post )->generation_status );
			}
		}
	}
}
