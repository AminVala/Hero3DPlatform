<?php
/**
 * Hero sequence configuration aggregate.
 *
 * @package ScrollHeroSequence
 */

declare(strict_types=1);

namespace ScrollHeroSequence\Domain;

final class HeroConfig {

	public const META_KEY = '_shs_config';

	/**
	 * @param Scene[]        $scenes
	 * @param OverlayStep[]  $overlay_steps
	 * @param array<string, mixed> $scroll_config
	 */
	public function __construct(
		public readonly string $template_slug,
		public readonly int $master_frame_index,
		public readonly int $total_frames_desktop,
		public readonly int $total_frames_mobile,
		public readonly int $master_image_id,
		public readonly string $prompt_start,
		public readonly array $scenes,
		public readonly array $overlay_steps,
		public readonly LockZone $lock_zone,
		public readonly array $scroll_config,
		public readonly string $generation_status = 'idle',
	) {}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function from_array( array $data ): self {
		$scenes = [];
		foreach ( $data['scenes'] ?? [] as $scene ) {
			$scenes[] = Scene::from_array( $scene );
		}

		$overlays = [];
		foreach ( $data['overlay_steps'] ?? [] as $step ) {
			$overlays[] = OverlayStep::from_array( $step );
		}

		return new self(
			(string) ( $data['template_slug'] ?? '' ),
			(int) ( $data['master_frame_index'] ?? 14 ),
			(int) ( $data['total_frames_desktop'] ?? 24 ),
			(int) ( $data['total_frames_mobile'] ?? 16 ),
			(int) ( $data['master_image_id'] ?? 0 ),
			(string) ( $data['prompt_start'] ?? '' ),
			$scenes,
			$overlays,
			LockZone::from_array( (array) ( $data['lock_zone'] ?? [] ) ),
			(array) ( $data['scroll_config'] ?? [] ),
			(string) ( $data['generation_status'] ?? 'idle' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'template_slug'         => $this->template_slug,
			'master_frame_index'    => $this->master_frame_index,
			'total_frames_desktop'  => $this->total_frames_desktop,
			'total_frames_mobile'   => $this->total_frames_mobile,
			'master_image_id'       => $this->master_image_id,
			'prompt_start'          => $this->prompt_start,
			'scenes'                => array_map( static fn ( Scene $s ) => $s->to_array(), $this->scenes ),
			'overlay_steps'         => array_map( static fn ( OverlayStep $o ) => $o->to_array(), $this->overlay_steps ),
			'lock_zone'             => $this->lock_zone->to_array(),
			'scroll_config'         => $this->scroll_config,
			'generation_status'     => $this->generation_status,
		];
	}

	public static function from_post( \WP_Post $post ): self {
		$raw = get_post_meta( $post->ID, self::META_KEY, true );
		if ( ! is_array( $raw ) || empty( $raw ) ) {
			return self::empty();
		}

		return self::from_array( $raw );
	}

	public static function empty(): self {
		return new self(
			'',
			14,
			24,
			16,
			0,
			'',
			[],
			[],
			new LockZone( 14, 24, 0 ),
			[
				'pin_duration'     => '300%',
				'easing'           => 'none',
				'mobile_disable'   => false,
				'reduced_motion'   => 'poster',
			],
		);
	}

	public function save_to_post( int $post_id ): void {
		update_post_meta( $post_id, self::META_KEY, $this->to_array() );
	}
}
