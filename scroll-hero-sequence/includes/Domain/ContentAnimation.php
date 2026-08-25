<?php
/**
 * Content animation bound to an HTML element (Scrollsequence-parity).
 *
 * Scrollsequence animates HTML elements by image-frame number rather than by
 * time. Each animation targets a CSS selector (#id or .class), becomes active
 * between a start and end frame, and carries "from" and "to" tween lists that
 * drive the element as the sequence plays forward and backward.
 *
 * @package ScrollHeroSequence
 */

declare(strict_types=1);

namespace ScrollHeroSequence\Domain;

final class ContentAnimation {

	/**
	 * @param AnimationTween[] $from Tweens anchored to the start frame (forward).
	 * @param AnimationTween[] $to   Tweens anchored to the end frame (reverse).
	 */
	public function __construct(
		public readonly string $selector,
		public readonly int $start,
		public readonly int $end,
		public readonly array $from = [],
		public readonly array $to = [],
	) {}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function from_array( array $data ): self {
		$from = [];
		foreach ( (array) ( $data['from'] ?? [] ) as $tween ) {
			$from[] = AnimationTween::from_array( (array) $tween );
		}

		$to = [];
		foreach ( (array) ( $data['to'] ?? [] ) as $tween ) {
			$to[] = AnimationTween::from_array( (array) $tween );
		}

		return new self(
			(string) ( $data['selector'] ?? '' ),
			max( 0, (int) ( $data['start'] ?? 0 ) ),
			max( 0, (int) ( $data['end'] ?? 0 ) ),
			$from,
			$to,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'selector' => $this->selector,
			'start'    => $this->start,
			'end'      => $this->end,
			'from'     => array_map( static fn ( AnimationTween $t ) => $t->to_array(), $this->from ),
			'to'       => array_map( static fn ( AnimationTween $t ) => $t->to_array(), $this->to ),
		];
	}

	/**
	 * A selector is valid only if it starts with '#' or '.' and has a body.
	 * Used by sanitization to drop garbage selectors before persistence.
	 */
	public function has_valid_selector(): bool {
		return (bool) preg_match( '/^[#.][A-Za-z0-9_-]+$/', $this->selector );
	}
}
