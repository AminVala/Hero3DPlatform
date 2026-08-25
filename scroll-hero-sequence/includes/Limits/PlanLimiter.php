<?php
/**
 * Free / Pro plan limits.
 *
 * @package ScrollHeroSequence
 */

declare(strict_types=1);

namespace ScrollHeroSequence\Limits;

use ScrollHeroSequence\PostType\HeroSequence;

final class PlanLimiter {

	public const FREE_HERO_LIMIT = 1;
	public const PRO_HERO_LIMIT  = 15;
	public const FREE_OVERLAY_LIMIT = 5;
	public const PRO_OVERLAY_LIMIT  = 12;

	public function register(): void {
		// Correct filter: fires on every insert/update with ( $data, $postarr ).
		add_filter( 'wp_insert_post_data', [ $this, 'enforce_hero_limit' ], 10, 2 );
		// Hide "Add New" affordances + surface a friendly notice when at the cap.
		add_action( 'admin_notices', [ $this, 'maybe_render_limit_notice' ] );
		add_action( 'admin_head', [ $this, 'maybe_hide_add_new' ] );
	}

	public function is_pro(): bool {
		return (bool) apply_filters( 'shs_is_pro', false );
	}

	public function hero_limit(): int {
		return $this->is_pro() ? self::PRO_HERO_LIMIT : self::FREE_HERO_LIMIT;
	}

	public function overlay_limit(): int {
		return $this->is_pro() ? self::PRO_OVERLAY_LIMIT : self::FREE_OVERLAY_LIMIT;
	}

	public function can_create_hero(): bool {
		return $this->count_heroes() < $this->hero_limit();
	}

	public function count_heroes(): int {
		$counts = wp_count_posts( HeroSequence::POST_TYPE );
		if ( ! $counts ) {
			return 0;
		}

		$total = 0;
		foreach ( [ 'publish', 'draft', 'pending', 'private', 'future' ] as $status ) {
			$total += (int) ( $counts->{$status} ?? 0 );
		}

		return $total;
	}

	/**
	 * Block creation of a NEW hero once the plan cap is reached.
	 *
	 * Runs on `wp_insert_post_data`. We only intervene for brand-new,
	 * non-auto-draft heroes; updates to existing posts always pass through so
	 * users can keep editing what they already have.
	 *
	 * @param array<string, mixed> $data    Sanitized post data about to be saved.
	 * @param array<string, mixed> $postarr Raw post array (contains ID on updates).
	 * @return array<string, mixed>
	 */
	public function enforce_hero_limit( array $data, array $postarr ): array {
		if ( HeroSequence::POST_TYPE !== ( $data['post_type'] ?? '' ) ) {
			return $data;
		}

		// Existing post being updated -> never blocked.
		if ( ! empty( $postarr['ID'] ) ) {
			return $data;
		}

		// Ignore auto-drafts / revisions / autosaves; only count real creations.
		$status = (string) ( $data['post_status'] ?? '' );
		if ( in_array( $status, [ 'auto-draft', 'inherit', 'trash' ], true ) ) {
			return $data;
		}

		if ( $this->can_create_hero() ) {
			return $data;
		}

		// At the cap: demote the new post to a harmless draft and flag a notice,
		// instead of killing the whole request with wp_die().
		set_transient( 'shs_hero_limit_hit_' . get_current_user_id(), 1, 60 );
		$data['post_status'] = 'draft';
		$data['post_type']   = 'revision'; // Prevent it from ever appearing as a hero.
		$data['post_name']   = '';

		return $data;
	}

	/**
	 * Show a dismissible notice on hero screens when the cap is reached.
	 */
	public function maybe_render_limit_notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || HeroSequence::POST_TYPE !== $screen->post_type ) {
			return;
		}

		$user_id = get_current_user_id();
		$flag    = get_transient( 'shs_hero_limit_hit_' . $user_id );

		if ( ! $flag && $this->can_create_hero() ) {
			return;
		}

		if ( $flag ) {
			delete_transient( 'shs_hero_limit_hit_' . $user_id );
		}

		if ( $this->can_create_hero() ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %d: maximum hero count */
					__( 'Hero limit reached. Your plan allows %d hero sequence(s). Upgrade to Pro to create more.', 'scroll-hero-sequence' ),
					$this->hero_limit()
				)
			)
		);
	}

	/**
	 * Hide the "Add New" buttons when the cap is reached.
	 */
	public function maybe_hide_add_new(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || HeroSequence::POST_TYPE !== $screen->post_type ) {
			return;
		}

		if ( $this->can_create_hero() ) {
			return;
		}

		echo '<style>.page-title-action{display:none !important;}</style>';
	}
}
