<?php
/**
 * Hero Sequence block server render.
 *
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 *
 * @package ScrollHeroSequence
 */

declare(strict_types=1);

use ScrollHeroSequence\Frontend\OverlayRenderer;
use ScrollHeroSequence\Frontend\ScrollEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hero_id = isset( $attributes['heroId'] ) ? (int) $attributes['heroId'] : 0;
if ( $hero_id <= 0 ) {
	return;
}

$config = ScrollEngine::get_public_config( $hero_id );
if ( null === $config ) {
	return;
}

ScrollEngine::enqueue_for_hero( $hero_id );

$json          = wp_json_encode( $config, JSON_UNESCAPED_UNICODE );
$poster        = esc_url( (string) ( $config['poster_url'] ?? '' ) );
$overlay_steps = $config['config']['overlay_steps'] ?? [];
$title         = (string) ( $config['title'] ?? __( 'Hero sequence', 'scroll-hero-sequence' ) );
$overlays_html = OverlayRenderer::render( (array) $overlay_steps );

$allowed = wp_kses_allowed_html( 'post' );
$allowed['nav'] = [ 'class' => true, 'aria-label' => true ];
foreach ( [ 'div', 'span', 'h1', 'p', 'a' ] as $tag ) {
	$allowed[ $tag ]              = $allowed[ $tag ] ?? [];
	$allowed[ $tag ]['class']     = true;
	$allowed[ $tag ]['style']     = true;
	$allowed[ $tag ]['data-frame'] = true;
	$allowed[ $tag ]['aria-hidden'] = true;
	$allowed[ $tag ]['role']      = true;
	$allowed[ $tag ]['aria-label'] = true;
}
?>
<div
	class="shs-hero"
	id="shs-hero-<?php echo esc_attr( (string) $hero_id ); ?>"
	data-hero-id="<?php echo esc_attr( (string) $hero_id ); ?>"
	data-config="<?php echo esc_attr( (string) $json ); ?>"
	role="region"
	aria-label="<?php echo esc_attr( $title ); ?>"
>
	<div class="shs-hero__canvas-wrap">
		<img
			class="shs-hero__frame"
			src="<?php echo esc_url( $poster ); ?>"
			alt="<?php echo esc_attr( $title ); ?>"
			width="1920"
			height="1080"
			decoding="async"
			fetchpriority="high"
		/>
	</div>
	<div class="shs-hero__overlays">
		<?php echo wp_kses( $overlays_html, $allowed ); ?>
	</div>
</div>
