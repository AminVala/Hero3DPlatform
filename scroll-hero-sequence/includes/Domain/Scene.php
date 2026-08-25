<?php
/**
 * Scene value object.
 *
 * @package ScrollHeroSequence
 */

declare(strict_types=1);

namespace ScrollHeroSequence\Domain;

final class Scene {

	/**
	 * @param Beat[] $beats
	 */
	public function __construct(
		public readonly int $index,
		public readonly int $frame_start,
		public readonly int $frame_end,
		public readonly string $label,
		public readonly array $beats,
		public readonly string $generation_mode = 'ai',
	) {}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function from_array( array $data ): self {
		$beats = [];
		foreach ( $data['beats'] ?? [] as $beat ) {
			$beats[] = Beat::from_array( $beat );
		}

		return new self(
			(int) ( $data['index'] ?? 0 ),
			(int) ( $data['frame_start'] ?? 0 ),
			(int) ( $data['frame_end'] ?? 0 ),
			(string) ( $data['label'] ?? '' ),
			$beats,
			(string) ( $data['generation_mode'] ?? 'ai' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'index'            => $this->index,
			'frame_start'      => $this->frame_start,
			'frame_end'        => $this->frame_end,
			'label'            => $this->label,
			'beats'            => array_map( static fn ( Beat $b ) => $b->to_array(), $this->beats ),
			'generation_mode'  => $this->generation_mode,
		];
	}
}
