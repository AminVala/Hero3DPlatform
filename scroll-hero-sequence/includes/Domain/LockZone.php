<?php
/**
 * Lock zone — static frames with HTML-only animation.
 *
 * @package ScrollHeroSequence
 */

declare(strict_types=1);

namespace ScrollHeroSequence\Domain;

final class LockZone {

	public function __construct(
		public readonly int $start_frame,
		public readonly int $end_frame,
		public readonly int $source_attachment_id = 0,
	) {}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function from_array( array $data ): self {
		return new self(
			(int) ( $data['start_frame'] ?? 14 ),
			(int) ( $data['end_frame'] ?? 24 ),
			(int) ( $data['source_attachment_id'] ?? 0 ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'start_frame'           => $this->start_frame,
			'end_frame'             => $this->end_frame,
			'source_attachment_id'  => $this->source_attachment_id,
		];
	}

	public function contains_frame( int $frame ): bool {
		return $frame >= $this->start_frame && $frame <= $this->end_frame;
	}

	/**
	 * Expand virtual frame list: unique frames before lock, then references.
	 *
	 * @param int[] $unique_frames Attachment IDs indexed 0..n-1 for frames 1..master.
	 * @return int[] Virtual frame map (1-indexed frame number => attachment ID).
	 */
	public function expand_virtual_frames( array $unique_frames, int $master_index = 14 ): array {
		$virtual = [];

		foreach ( $unique_frames as $i => $attachment_id ) {
			$virtual[ $i + 1 ] = $attachment_id;
		}

		$lock_source = $unique_frames[ $master_index - 1 ] ?? ( $this->source_attachment_id ?: 0 );

		for ( $f = $this->start_frame; $f <= $this->end_frame; $f++ ) {
			$virtual[ $f ] = $lock_source;
		}

		return $virtual;
	}
}
