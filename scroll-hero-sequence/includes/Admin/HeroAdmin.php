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
					'selectFrames'    => __( 'Select Frame Sequence', 'scroll-hero-sequence' ),
					'addFrames'       => __( 'Add Frames', 'scroll-hero-sequence' ),
					'confirmClear'    => __( 'Remove all frames from this set?', 'scroll-hero-sequence' ),
					'frameCap'        => __( 'Frame limit reached for your plan. Extra frames were not added.', 'scroll-hero-sequence' ),
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

		// Persist the ordered frame sets (CSV of attachment IDs from the strips).
		$limiter     = new PlanLimiter();
		$max_desktop = $limiter->is_pro()
			? HeroSequence::MAX_FRAMES_DESKTOP_PRO
			: HeroSequence::MAX_FRAMES_DESKTOP_FREE;

		if ( isset( $_POST['shs_frames_desktop'] ) ) {
			$desktop = $this->parse_frame_ids( wp_unslash( (string) $_POST['shs_frames_desktop'] ), $max_desktop );
			update_post_meta( $post_id, HeroSequence::META_FRAMES_DESKTOP, $desktop );
		}

		if ( isset( $_POST['shs_frames_mobile'] ) ) {
			$mobile = $this->parse_frame_ids( wp_unslash( (string) $_POST['shs_frames_mobile'] ), HeroSequence::MAX_FRAMES_MOBILE );
			update_post_meta( $post_id, HeroSequence::META_FRAMES_MOBILE, $mobile );
		}
	}

	/**
	 * Turn the hidden-field CSV of attachment IDs into a clean, ordered,
	 * de-duplicated, capped, image-only list.
	 *
	 * @return int[]
	 */
	private function parse_frame_ids( string $csv, int $max ): array {
		$csv = trim( $csv );
		if ( '' === $csv ) {
			return [];
		}

		$ids   = array_map( 'absint', explode( ',', $csv ) );
		$clean = [];
		foreach ( $ids as $id ) {
			if ( $id <= 0 || in_array( $id, $clean, true ) ) {
				continue;
			}
			// Only accept real image attachments — never trust the client list.
			if ( 'attachment' !== get_post_type( $id ) || ! wp_attachment_is_image( $id ) ) {
				continue;
			}
			$clean[] = $id;
			if ( count( $clean ) >= $max ) {
				break;
			}
		}

		return $clean;
	}

	/**
	 * Sanitize a scene's content-animation list (Scrollsequence-parity).
	 *
	 * Only #id / .class selectors survive; animation types are whitelisted and
	 * numeric values/durations are bounded. Untrusted client data never reaches
	 * the runtime without validation.
	 *
	 * @param array<int, mixed> $animations
	 * @return array<int, array<string, mixed>>
	 */
	private function sanitize_content_animations( array $animations ): array {
		$allowed_types = [ 'fade', 'move_vertical', 'move_horizontal', 'scale' ];

		$clean_tween = static function ( $tween ) use ( $allowed_types ): array {
			$type = (string) ( $tween['type'] ?? 'fade' );
			if ( ! in_array( $type, $allowed_types, true ) ) {
				$type = 'fade';
			}
			return [
				'type'     => $type,
				'value'    => (float) ( $tween['value'] ?? 0 ),
				'duration' => max( 0, absint( $tween['duration'] ?? 0 ) ),
			];
		};

		$clean = [];
		foreach ( $animations as $anim ) {
			$selector = (string) ( $anim['selector'] ?? '' );
			// Drop anything that is not a single #id or .class token.
			if ( ! preg_match( '/^[#.][A-Za-z0-9_-]+$/', $selector ) ) {
				continue;
			}
			$clean[] = [
				'selector' => $selector,
				'start'    => max( 0, absint( $anim['start'] ?? 0 ) ),
				'end'      => max( 0, absint( $anim['end'] ?? 0 ) ),
				'from'     => array_map( $clean_tween, (array) ( $anim['from'] ?? [] ) ),
				'to'       => array_map( $clean_tween, (array) ( $anim['to'] ?? [] ) ),
			];
		}

		return $clean;
	}

	/**
	 * Sanitize a scene's runtime settings (Scrollsequence-parity).
	 *
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed>
	 */
	private function sanitize_scene_settings( array $settings ): array {
		$enum = static function ( $val, array $allowed, string $default ): string {
			$val = (string) $val;
			return in_array( $val, $allowed, true ) ? $val : $default;
		};

		$align = static function ( $raw ): array {
			$raw   = (array) $raw;
			$scale = in_array( ( $raw['scale'] ?? 'fill' ), [ 'fit', 'fill' ], true ) ? $raw['scale'] : 'fill';
			return [
				'scale'   => $scale,
				'h_align' => min( 100, max( 0, absint( $raw['h_align'] ?? 50 ) ) ),
				'v_align' => min( 100, max( 0, absint( $raw['v_align'] ?? 50 ) ) ),
			];
		};

		return [
			'position'      => $enum( $settings['position'] ?? '', [ 'sticky', 'absolute', 'static' ], 'sticky' ),
			'start_trigger' => $enum( $settings['start_trigger'] ?? '', [ 'sooner', 'default' ], 'default' ),
			'end_trigger'   => $enum( $settings['end_trigger'] ?? '', [ 'default', 'later' ], 'default' ),
			'scroll_delay'  => min( 3.5, max( 0.0, (float) ( $settings['scroll_delay'] ?? 0.75 ) ) ),
			'image_width'   => $enum( $settings['image_width'] ?? '', [ 'content', 'full' ], 'content' ),
			'image_opacity' => min( 1.0, max( 0.0, (float) ( $settings['image_opacity'] ?? 1.0 ) ) ),
			'custom_css'    => $this->sanitize_scoped_css( (string) ( $settings['custom_css'] ?? '' ) ),
			'portrait'      => $align( $settings['portrait'] ?? [] ),
			'landscape'     => $align( $settings['landscape'] ?? [] ),
		];
	}

	/**
	 * Strip dangerous constructs from user CSS before it is stored/echoed.
	 * Removes tags, @import, expression(), javascript: and behaviour hooks.
	 */
	private function sanitize_scoped_css( string $css ): string {
		$css = wp_strip_all_tags( $css );
		$css = preg_replace( '/@import[^;]+;?/i', '', $css ) ?? '';
		$css = preg_replace( '/expression\s*\(/i', '', $css ) ?? '';
		$css = preg_replace( '/javascript\s*:/i', '', $css ) ?? '';
		$css = preg_replace( '/behaviou?r\s*:/i', '', $css ) ?? '';
		$css = str_replace( [ '<', '>' ], '', $css );
		return trim( $css );
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

		// Scenes (structural + Scrollsequence-parity content/animation/settings).
		$clean['scenes'] = [];
		foreach ( (array) ( $data['scenes'] ?? [] ) as $scene ) {
			$clean['scenes'][] = [
				'index'              => absint( $scene['index'] ?? 0 ),
				'frame_start'        => absint( $scene['frame_start'] ?? 0 ),
				'frame_end'          => absint( $scene['frame_end'] ?? 0 ),
				'label'              => sanitize_text_field( (string) ( $scene['label'] ?? '' ) ),
				'generation_mode'    => in_array( ( $scene['generation_mode'] ?? 'ai' ), [ 'ai', 'manual', 'locked' ], true ) ? $scene['generation_mode'] : 'ai',
				'fixed_content'      => wp_kses_post( (string) ( $scene['fixed_content'] ?? '' ) ),
				'image_sequence'     => $this->parse_frame_ids(
					implode( ',', array_map( 'absint', (array) ( $scene['image_sequence'] ?? [] ) ) ),
					500
				),
				'content_animations' => $this->sanitize_content_animations( (array) ( $scene['content_animations'] ?? [] ) ),
				'settings'           => $this->sanitize_scene_settings( (array) ( $scene['settings'] ?? [] ) ),
				'beats'              => array_map(
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
