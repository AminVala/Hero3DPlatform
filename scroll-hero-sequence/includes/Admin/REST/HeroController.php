<?php
/**
 * REST API for hero sequences.
 *
 * @package ScrollHeroSequence
 */

declare(strict_types=1);

namespace ScrollHeroSequence\Admin\REST;

use ScrollHeroSequence\Domain\HeroConfig;
use ScrollHeroSequence\PostType\HeroSequence;
use ScrollHeroSequence\Templates\TemplateRegistry;
use WP_REST_Request;
use WP_REST_Response;

final class HeroController {

	private const NAMESPACE = 'scroll-hero-sequence/v1';

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/templates',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'list_templates' ],
				'permission_callback' => [ $this, 'can_manage' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/heroes/(?P<id>\d+)',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_hero' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/heroes/(?P<id>\d+)/apply-template',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'apply_template' ],
				'permission_callback' => [ $this, 'can_edit_hero' ],
				'args'                => [
					'slug' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					],
				],
			]
		);
	}

	public function can_manage(): bool {
		return current_user_can( 'edit_hero_sequences' );
	}

	public function can_edit_hero( WP_REST_Request $request ): bool {
		$post_id = (int) $request['id'];
		return current_user_can( 'edit_post', $post_id );
	}

	public function list_templates(): WP_REST_Response {
		return new WP_REST_Response( ( new TemplateRegistry() )->list_for_admin() );
	}

	public function get_hero( WP_REST_Request $request ): WP_REST_Response {
		$post = get_post( (int) $request['id'] );
		if ( ! $post || HeroSequence::POST_TYPE !== $post->post_type ) {
			return new WP_REST_Response( [ 'message' => 'Not found' ], 404 );
		}

		if ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_REST_Response( [ 'message' => 'Forbidden' ], 403 );
		}

		return new WP_REST_Response( $this->serialize_hero( $post ) );
	}

	public function apply_template( WP_REST_Request $request ): WP_REST_Response {
		$post_id = (int) $request['id'];
		$slug    = (string) $request['slug'];

		$config = ( new TemplateRegistry() )->create_config_from_template( $slug );
		$config->save_to_post( $post_id );
		update_post_meta( $post_id, '_shs_template_slug', $slug );

		return new WP_REST_Response( [ 'success' => true, 'config' => $config->to_array() ] );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function serialize_hero( \WP_Post $post ): array {
		$config    = HeroConfig::from_post( $post );
		$master_id = (int) get_post_meta( $post->ID, '_shs_master_image_id', true ) ?: $config->master_image_id;
		$frames    = HeroSequence::get_frame_attachment_ids( $post->ID );

		$frame_urls = [];
		foreach ( $frames as $i => $attachment_id ) {
			$url = wp_get_attachment_image_url( $attachment_id, 'full' );
			if ( $url ) {
				$frame_urls[ $i + 1 ] = $url;
			}
		}

		$frames_mobile     = get_post_meta( $post->ID, '_shs_frames_mobile', true );
		$frames_mobile     = is_array( $frames_mobile ) ? array_map( 'intval', $frames_mobile ) : [];
		$frame_urls_mobile = [];
		foreach ( $frames_mobile as $i => $attachment_id ) {
			$url = wp_get_attachment_image_url( $attachment_id, 'full' );
			if ( $url ) {
				$frame_urls_mobile[ $i + 1 ] = $url;
			}
		}

		$master_url = $master_id ? wp_get_attachment_image_url( $master_id, 'full' ) : '';
		$has_frames = ! empty( $frame_urls );

		// With no uploaded frames, synthesize a static sequence from the master
		// so a published hero still renders (poster everywhere up to the master
		// frame index).
		if ( $master_url && ! $has_frames ) {
			for ( $f = 1; $f <= $config->master_frame_index; $f++ ) {
				$frame_urls[ $f ] = $master_url;
			}
		}

		// Lock zone: only FILL gaps. Never clobber a real uploaded frame — the
		// uploaded set is the source of truth once it exists.
		$lock        = $config->lock_zone;
		$lock_source = $master_url;
		if ( $has_frames && isset( $frame_urls[ $lock->start_frame ] ) ) {
			$lock_source = $frame_urls[ $lock->start_frame ];
		}
		if ( $lock_source ) {
			for ( $f = $lock->start_frame; $f <= $lock->end_frame; $f++ ) {
				if ( ! isset( $frame_urls[ $f ] ) ) {
					$frame_urls[ $f ] = $lock_source;
				}
			}
		}

		// Poster = first real frame when available, else the master image.
		$poster_url = $has_frames && isset( $frame_urls[1] ) ? $frame_urls[1] : $master_url;

		// Total frames must cover every supplied frame key so none are unreachable
		// when the engine maps scroll progress -> frame number.
		$max_desktop_key = $frame_urls ? max( array_keys( $frame_urls ) ) : 0;
		$total_desktop   = max( (int) $config->total_frames_desktop, $max_desktop_key );
		$max_mobile_key  = $frame_urls_mobile ? max( array_keys( $frame_urls_mobile ) ) : 0;
		$total_mobile    = max( (int) $config->total_frames_mobile, $max_mobile_key );

		return [
			'id'                  => $post->ID,
			'title'               => $post->post_title,
			'template'            => get_post_meta( $post->ID, '_shs_template_slug', true ) ?: $config->template_slug,
			'config'              => $config->to_array(),
			'master_url'          => $master_url,
			'poster_url'          => $poster_url,
			'frame_urls'          => $frame_urls,
			'frame_urls_mobile'   => $frame_urls_mobile,
			'total_frames'        => $total_desktop,
			'total_frames_mobile' => $total_mobile,
			'lock_zone'           => $lock->to_array(),
		];
	}
}
