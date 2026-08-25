<?php
/**
 * Scene value object.
 *
 * A scene groups an ordered image sequence with its fixed HTML content,
 * content animations and runtime settings (Scrollsequence-parity), while
 * retaining the keyframe-first storyboard fields (beats, generation_mode)
 * from the original hero model for backward compatibility.
 *
 * @package ScrollHeroSequence
 */

declare(strict_types=1);

namespace ScrollHeroSequence\Domain;

final class Scene {

	/**
	 * @param Beat[]             $beats
	 * @param int[]              $image_sequence     Attachment IDs, frame order (0-indexed per scene).
	 * @param ContentAnimation[] $content_animations
	 */
	public function __construct(
		public readonly int $index,
		public readonly int $frame_start,
		public readonly int $frame_end,
		public readonly string $label,
		public readonly array $beats,
		public readonly string $generation_mode = 'ai',
		public readonly string $fixed_content = '',
		public readonly array $image_sequence = [],
		public readonly array $content_animations = [],
		public readonly ?SceneSettings $settings = null,
	) {}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function from_array( array $data ): self {
		$beats = [];
		foreach ( $data['beats'] ?? [] as $beat ) {
			$beats[] = Beat::from_array( $beat );
		}

		$image_sequence = array_values(
			array_filter(
				array_map( 'intval', (array) ( $data['image_sequence'] ?? [] ) ),
				static fn ( int $id ): bool => $id > 0
			)
		);

		$content_animations = [];
		foreach ( (array) ( $data['content_animations'] ?? [] ) as $anim ) {
			$content_animations[] = ContentAnimation::from_array( (array) $anim );
		}

		$settings = isset( $data['settings'] ) && is_array( $data['settings'] )
			? SceneSettings::from_array( $data['settings'] )
			: null;

		return new self(
			(int) ( $data['index'] ?? 0 ),
			(int) ( $data['frame_start'] ?? 0 ),
			(int) ( $data['frame_end'] ?? 0 ),
			(string) ( $data['label'] ?? '' ),
			$beats,
			(string) ( $data['generation_mode'] ?? 'ai' ),
			(string) ( $data['fixed_content'] ?? '' ),
			$image_sequence,
			$content_animations,
			$settings,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'index'              => $this->index,
			'frame_start'        => $this->frame_start,
			'frame_end'          => $this->frame_end,
			'label'              => $this->label,
			'beats'              => array_map( static fn ( Beat $b ) => $b->to_array(), $this->beats ),
			'generation_mode'    => $this->generation_mode,
			'fixed_content'      => $this->fixed_content,
			'image_sequence'     => $this->image_sequence,
			'content_animations' => array_map(
				static fn ( ContentAnimation $a ) => $a->to_array(),
				$this->content_animations
			),
			'settings'           => ( $this->settings ?? new SceneSettings() )->to_array(),
		];
	}
}
