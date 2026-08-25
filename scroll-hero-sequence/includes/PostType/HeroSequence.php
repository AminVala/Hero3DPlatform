<?php
/**
 * Hero Sequence custom post type.
 *
 * @package ScrollHeroSequence
 */

declare(strict_types=1);

namespace ScrollHeroSequence\PostType;

use ScrollHeroSequence\Domain\HeroConfig;
use ScrollHeroSequence\Templates\TemplateRegistry;

final class HeroSequence {

	public const POST_TYPE = 'hero_sequence';

	/** Frame-set meta keys. */
	public const META_FRAMES_DESKTOP = '_shs_frames_desktop';
	public const META_FRAMES_MOBILE  = '_shs_frames_mobile';

	/** Upper bounds on the uploaded frame sets (keyframe-first model). */
	public const MAX_FRAMES_DESKTOP_FREE = 24;
	public const MAX_FRAMES_DESKTOP_PRO  = 36;
	public const MAX_FRAMES_MOBILE       = 24;

	public static function register(): void {
		register_post_type(
			self::POST_TYPE,
			[
				'labels'              => [
					'name'               => __( 'Hero Sequences', 'scroll-hero-sequence' ),
					'singular_name'      => __( 'Hero Sequence', 'scroll-hero-sequence' ),
					'add_new'            => __( 'Add Hero', 'scroll-hero-sequence' ),
					'add_new_item'       => __( 'Add New Hero Sequence', 'scroll-hero-sequence' ),
					'edit_item'          => __( 'Edit Hero Sequence', 'scroll-hero-sequence' ),
					'new_item'           => __( 'New Hero Sequence', 'scroll-hero-sequence' ),
					'view_item'          => __( 'View Hero Sequence', 'scroll-hero-sequence' ),
					'search_items'       => __( 'Search Hero Sequences', 'scroll-hero-sequence' ),
					'not_found'          => __( 'No hero sequences found.', 'scroll-hero-sequence' ),
					'not_found_in_trash' => __( 'No hero sequences found in Trash.', 'scroll-hero-sequence' ),
					'menu_name'          => __( 'Scroll Heroes', 'scroll-hero-sequence' ),
				],
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'menu_icon'           => 'dashicons-images-alt2',
				'menu_position'       => 25,
				'capability_type'     => 'hero_sequence',
				'map_meta_cap'        => true,
				'hierarchical'        => false,
				'supports'            => [ 'title' ],
				'show_in_rest'        => true,
				'rest_base'           => 'hero-sequences',
				'has_archive'         => false,
				'exclude_from_search' => true,
			]
		);

		add_action( 'save_post_' . self::POST_TYPE, [ self::class, 'maybe_apply_default_template' ], 10, 3 );
	}

	/**
	 * Grant hero capabilities to the administrator role.
	 *
	 * Call ONLY on plugin activation — never on every `init`, because
	 * WP_Role::add_cap() writes to the options table on each call.
	 */
	public static function register_capabilities(): void {
		$admin = get_role( 'administrator' );
		if ( ! $admin ) {
			return;
		}

		$caps = [
			'edit_hero_sequence',
			'read_hero_sequence',
			'delete_hero_sequence',
			'edit_hero_sequences',
			'edit_others_hero_sequences',
			'publish_hero_sequences',
			'read_private_hero_sequences',
			'delete_hero_sequences',
			'delete_private_hero_sequences',
			'delete_published_hero_sequences',
			'delete_others_hero_sequences',
			'edit_private_hero_sequences',
			'edit_published_hero_sequences',
			'create_hero_sequences',
		];

		foreach ( $caps as $cap ) {
			$admin->add_cap( $cap );
		}
	}

	public static function maybe_apply_default_template( int $post_id, \WP_Post $post, bool $update ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( $update ) {
			return;
		}

		$existing = get_post_meta( $post_id, HeroConfig::META_KEY, true );
		if ( ! empty( $existing ) ) {
			return;
		}

		$registry = new TemplateRegistry();
		$config   = $registry->create_config_from_template( $registry->get_default_slug() );
		$config->save_to_post( $post_id );

		update_post_meta( $post_id, '_shs_template_slug', $registry->get_default_slug() );
	}

	/**
	 * @return int[]
	 */
	public static function get_frame_attachment_ids( int $post_id ): array {
		$frames = get_post_meta( $post_id, self::META_FRAMES_DESKTOP, true );
		return is_array( $frames ) ? array_map( 'intval', $frames ) : [];
	}

	/**
	 * @return int[]
	 */
	public static function get_frame_attachment_ids_mobile( int $post_id ): array {
		$frames = get_post_meta( $post_id, self::META_FRAMES_MOBILE, true );
		return is_array( $frames ) ? array_map( 'intval', $frames ) : [];
	}
}
