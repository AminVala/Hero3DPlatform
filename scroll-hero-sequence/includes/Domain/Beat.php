<?php
/**
 * Beat value object.
 *
 * @package ScrollHeroSequence
 */

declare(strict_types=1);

namespace ScrollHeroSequence\Domain;

final class Beat {

	public function __construct(
		public readonly int $index,
		public readonly int $frame,
		public readonly string $description,
		public readonly bool $is_keyframe = true,
	) {}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function from_array( array $data ): self {
		return new self(
			(int) ( $data['index'] ?? 0 ),
			(int) ( $data['frame'] ?? 0 ),
			(string) ( $data['description'] ?? '' ),
			(bool) ( $data['is_keyframe'] ?? true ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'index'        => $this->index,
			'frame'        => $this->frame,
			'description'  => $this->description,
			'is_keyframe'  => $this->is_keyframe,
		];
	}
}
