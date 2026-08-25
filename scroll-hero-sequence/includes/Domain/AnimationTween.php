<?php
/**
 * A single content-animation tween (Scrollsequence-parity).
 *
 * Mirrors one row of a "From Animation" or "To Animation" list in the
 * Scrollsequence editor: an animation type, a numeric value and a duration
 * expressed in image frames.
 *
 * @package ScrollHeroSequence
 */

declare(strict_types=1);

namespace ScrollHeroSequence\Domain;

final class AnimationTween {

	/** Whitelisted tween types (Scrollsequence-parity). */
	public const TYPE_FADE            = 'fade';
	public const TYPE_MOVE_VERTICAL   = 'move_vertical';
	public const TYPE_MOVE_HORIZONTAL = 'move_horizontal';
	public const TYPE_SCALE           = 'scale';

	public const TYPES = [
		self::TYPE_FADE,
		self::TYPE_MOVE_VERTICAL,
		self::TYPE_MOVE_HORIZONTAL,
		self::TYPE_SCALE,
	];

	public function __construct(
		public readonly string $type,
		public readonly float $value,
		public readonly int $duration,
	) {}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function from_array( array $data ): self {
		$type = (string) ( $data['type'] ?? self::TYPE_FADE );
		if ( ! in_array( $type, self::TYPES, true ) ) {
			$type = self::TYPE_FADE;
		}

		return new self(
			$type,
			(float) ( $data['value'] ?? 0 ),
			max( 0, (int) ( $data['duration'] ?? 0 ) ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'type'     => $this->type,
			'value'    => $this->value,
			'duration' => $this->duration,
		];
	}
}
