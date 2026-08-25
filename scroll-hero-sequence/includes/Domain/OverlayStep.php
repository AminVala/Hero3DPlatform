<?php
/**
 * Overlay step synced to scroll frame.
 *
 * @package ScrollHeroSequence
 */

declare(strict_types=1);

namespace ScrollHeroSequence\Domain;

final class OverlayStep {

	/**
	 * @param array<string, mixed> $content
	 * @param array<string, mixed> $position
	 */
	public function __construct(
		public readonly int $frame_trigger,
		public readonly string $element_type,
		public readonly array $content,
		public readonly string $animation = 'fade',
		public readonly array $position = [],
		public readonly string $style_token = '',
	) {}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function from_array( array $data ): self {
		return new self(
			(int) ( $data['frame_trigger'] ?? 0 ),
			(string) ( $data['element_type'] ?? 'text' ),
			(array) ( $data['content'] ?? [] ),
			(string) ( $data['animation'] ?? 'fade' ),
			(array) ( $data['position'] ?? [] ),
			(string) ( $data['style_token'] ?? '' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'frame_trigger' => $this->frame_trigger,
			'element_type'  => $this->element_type,
			'content'       => $this->content,
			'animation'     => $this->animation,
			'position'      => $this->position,
			'style_token'   => $this->style_token,
		];
	}
}
