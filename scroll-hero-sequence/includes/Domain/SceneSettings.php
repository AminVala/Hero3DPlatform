<?php
/**
 * Per-scene runtime settings (Scrollsequence-parity).
 *
 * Captures the "Scrollsequence Settings" and "Image Scale and Alignment"
 * panels: pin position, scroll triggers, scroll delay, image sizing/opacity,
 * custom CSS and independent portrait/landscape scale + alignment.
 *
 * @package ScrollHeroSequence
 */

declare(strict_types=1);

namespace ScrollHeroSequence\Domain;

final class SceneSettings {

	public const POSITION_STICKY   = 'sticky';
	public const POSITION_ABSOLUTE = 'absolute';
	public const POSITION_STATIC   = 'static';
	public const POSITIONS         = [ self::POSITION_STICKY, self::POSITION_ABSOLUTE, self::POSITION_STATIC ];

	public const START_SOONER  = 'sooner';
	public const START_DEFAULT = 'default';
	public const START_TRIGGERS = [ self::START_SOONER, self::START_DEFAULT ];

	public const END_DEFAULT = 'default';
	public const END_LATER   = 'later';
	public const END_TRIGGERS = [ self::END_DEFAULT, self::END_LATER ];

	public const WIDTH_CONTENT = 'content';
	public const WIDTH_FULL    = 'full';
	public const WIDTHS        = [ self::WIDTH_CONTENT, self::WIDTH_FULL ];

	public const SCALE_FIT  = 'fit';
	public const SCALE_FILL = 'fill';
	public const SCALES     = [ self::SCALE_FIT, self::SCALE_FILL ];

	/**
	 * @param array{scale:string,h_align:int,v_align:int} $portrait
	 * @param array{scale:string,h_align:int,v_align:int} $landscape
	 */
	public function __construct(
		public readonly string $position = self::POSITION_STICKY,
		public readonly string $start_trigger = self::START_DEFAULT,
		public readonly string $end_trigger = self::END_DEFAULT,
		public readonly float $scroll_delay = 0.75,
		public readonly string $image_width = self::WIDTH_CONTENT,
		public readonly float $image_opacity = 1.0,
		public readonly string $custom_css = '',
		public readonly array $portrait = [ 'scale' => self::SCALE_FILL, 'h_align' => 50, 'v_align' => 50 ],
		public readonly array $landscape = [ 'scale' => self::SCALE_FILL, 'h_align' => 50, 'v_align' => 50 ],
	) {}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function from_array( array $data ): self {
		$enum = static function ( $val, array $allowed, string $default ): string {
			$val = (string) $val;
			return in_array( $val, $allowed, true ) ? $val : $default;
		};

		$align = static function ( $data, string $key ): array {
			$raw   = (array) ( $data[ $key ] ?? [] );
			$scale = (string) ( $raw['scale'] ?? self::SCALE_FILL );
			if ( ! in_array( $scale, self::SCALES, true ) ) {
				$scale = self::SCALE_FILL;
			}
			return [
				'scale'   => $scale,
				'h_align' => min( 100, max( 0, (int) ( $raw['h_align'] ?? 50 ) ) ),
				'v_align' => min( 100, max( 0, (int) ( $raw['v_align'] ?? 50 ) ) ),
			];
		};

		return new self(
			$enum( $data['position'] ?? '', self::POSITIONS, self::POSITION_STICKY ),
			$enum( $data['start_trigger'] ?? '', self::START_TRIGGERS, self::START_DEFAULT ),
			$enum( $data['end_trigger'] ?? '', self::END_TRIGGERS, self::END_DEFAULT ),
			min( 3.5, max( 0.0, (float) ( $data['scroll_delay'] ?? 0.75 ) ) ),
			$enum( $data['image_width'] ?? '', self::WIDTHS, self::WIDTH_CONTENT ),
			min( 1.0, max( 0.0, (float) ( $data['image_opacity'] ?? 1.0 ) ) ),
			(string) ( $data['custom_css'] ?? '' ),
			$align( $data, 'portrait' ),
			$align( $data, 'landscape' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'position'      => $this->position,
			'start_trigger' => $this->start_trigger,
			'end_trigger'   => $this->end_trigger,
			'scroll_delay'  => $this->scroll_delay,
			'image_width'   => $this->image_width,
			'image_opacity' => $this->image_opacity,
			'custom_css'    => $this->custom_css,
			'portrait'      => $this->portrait,
			'landscape'     => $this->landscape,
		];
	}
}
