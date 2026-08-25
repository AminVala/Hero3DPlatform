<?php
/**
 * Server-side overlay renderer.
 *
 * Renders overlay steps as real, semantic HTML at request time so that:
 *  - search engines and social scrapers see the H1 / copy / links;
 *  - the hero degrades gracefully without JavaScript;
 *  - the scroll engine only toggles visibility (no innerHTML rebuild).
 *
 * @package ScrollHeroSequence
 */

declare(strict_types=1);

namespace ScrollHeroSequence\Frontend;

final class OverlayRenderer {

	/**
	 * @param array<int, array<string, mixed>> $steps
	 */
	public static function render( array $steps ): string {
		$html = '';

		foreach ( $steps as $step ) {
			$type    = (string) ( $step['element_type'] ?? 'text' );
			$trigger = (int) ( $step['frame_trigger'] ?? 0 );
			$anim    = (string) ( $step['animation'] ?? 'fade' );
			$content = (array) ( $step['content'] ?? [] );
			$pos     = (array) ( $step['position'] ?? [] );

			$style = '';
			if ( ! empty( $pos['x'] ) ) {
				$style .= 'left:' . esc_attr( (string) $pos['x'] ) . ';';
			}
			if ( ! empty( $pos['y'] ) ) {
				$style .= 'top:' . esc_attr( (string) $pos['y'] ) . ';';
			}

			$attrs = sprintf(
				'class="shs-overlay shs-overlay--%1$s shs-anim--%2$s" data-frame="%3$d" style="%4$s" aria-hidden="true"',
				esc_attr( $type ),
				esc_attr( $anim ),
				$trigger,
				esc_attr( $style )
			);

			$html .= '<div ' . $attrs . '>' . self::render_inner( $type, $content ) . '</div>';
		}

		return $html;
	}

	/**
	 * @param array<string, mixed> $content
	 */
	private static function render_inner( string $type, array $content ): string {
		switch ( $type ) {
			case 'h1':
				return '<h1 class="shs-overlay__h1">' . esc_html( (string) ( $content['text'] ?? '' ) ) . '</h1>';

			case 'subtitle':
				return '<p class="shs-overlay__subtitle">' . esc_html( (string) ( $content['text'] ?? '' ) ) . '</p>';

			case 'eyebrow':
				return '<span class="shs-overlay__eyebrow">' . esc_html( (string) ( $content['text'] ?? '' ) ) . '</span>';

			case 'text':
				return '<p>' . esc_html( (string) ( $content['text'] ?? '' ) ) . '</p>';

			case 'logo':
				return '<span class="shs-overlay__logo" role="img" aria-label="' . esc_attr( (string) ( $content['alt'] ?? '' ) ) . '"></span>';

			case 'cta':
				$out = '';
				foreach ( (array) ( $content['buttons'] ?? [] ) as $btn ) {
					$variant = in_array( ( $btn['variant'] ?? 'filled' ), [ 'filled', 'outlined', 'text' ], true ) ? $btn['variant'] : 'filled';
					$out    .= sprintf(
						'<a class="shs-btn shs-btn--%1$s" href="%2$s">%3$s</a>',
						esc_attr( $variant ),
						esc_url( (string) ( $btn['url'] ?? '#' ) ),
						esc_html( (string) ( $btn['label'] ?? '' ) )
					);
				}
				return $out;

			case 'nav':
				$out = '<nav aria-label="' . esc_attr__( 'Hero navigation', 'scroll-hero-sequence' ) . '">';
				foreach ( (array) ( $content['items'] ?? [] ) as $item ) {
					$out .= sprintf(
						'<a href="%1$s">%2$s</a>',
						esc_url( (string) ( $item['url'] ?? '#' ) ),
						esc_html( (string) ( $item['label'] ?? '' ) )
					);
				}
				return $out . '</nav>';

			case 'trust':
				$out = '';
				foreach ( (array) ( $content['items'] ?? [] ) as $item ) {
					$out .= '<span>' . esc_html( (string) ( $item['label'] ?? '' ) ) . '</span>';
				}
				return $out;

			default:
				return esc_html( (string) ( $content['text'] ?? '' ) );
		}
	}
}
