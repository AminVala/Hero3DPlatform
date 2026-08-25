<?php
/**
 * Hero editor meta box view.
 *
 * @var \WP_Post $post
 * @var \ScrollHeroSequence\Domain\HeroConfig $config
 * @var \ScrollHeroSequence\Templates\TemplateRegistry $registry
 * @var \ScrollHeroSequence\Limits\PlanLimiter $limiter
 * @var int[] $frames
 *
 * @package ScrollHeroSequence
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$master_id     = (int) get_post_meta( $post->ID, '_shs_master_image_id', true );
$template_slug = (string) get_post_meta( $post->ID, '_shs_template_slug', true ) ?: $registry->get_default_slug();
$master_url    = $master_id ? wp_get_attachment_image_url( $master_id, 'medium' ) : '';
?>
<div class="shs-editor" dir="rtl">
	<div class="shs-editor__header">
		<p class="shs-editor__plan">
			<?php
			printf(
				esc_html__( 'Plan: %1$s — %2$d / %3$d heroes', 'scroll-hero-sequence' ),
				$limiter->is_pro() ? 'Pro' : 'Free',
				$limiter->count_heroes(),
				$limiter->hero_limit()
			);
			?>
		</p>
	</div>

	<div class="shs-editor__section">
		<label for="shs_template_slug"><strong><?php esc_html_e( 'Template Preset', 'scroll-hero-sequence' ); ?></strong></label>
		<select name="shs_template_slug" id="shs_template_slug" class="widefat">
			<?php foreach ( $registry->list_for_admin() as $template ) : ?>
				<option value="<?php echo esc_attr( $template['slug'] ); ?>" <?php selected( $template_slug, $template['slug'] ); ?>>
					<?php echo esc_html( $template['name'] ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description"><?php esc_html_e( 'Default: Shahre Honar — reference-preserving keyframe storyboard (24 frames, 4 scenes).', 'scroll-hero-sequence' ); ?></p>
	</div>

	<div class="shs-editor__section shs-editor__master">
		<label><strong><?php esc_html_e( 'Master Frame — Final studio image without UI/text', 'scroll-hero-sequence' ); ?></strong></label>
		<div class="shs-master-preview">
			<?php if ( $master_url ) : ?>
				<img src="<?php echo esc_url( $master_url ); ?>" alt="" />
			<?php else : ?>
				<div class="shs-master-preview__placeholder"><?php esc_html_e( 'No master frame uploaded yet.', 'scroll-hero-sequence' ); ?></div>
			<?php endif; ?>
		</div>
		<input type="hidden" name="shs_master_image_id" id="shs_master_image_id" value="<?php echo esc_attr( (string) $master_id ); ?>" />
		<button type="button" class="button button-primary" id="shs-select-master"><?php esc_html_e( 'Upload Master Frame', 'scroll-hero-sequence' ); ?></button>
	</div>

	<div class="shs-editor__section">
		<label for="shs_prompt_start"><strong><?php esc_html_e( 'Start Prompt (first frame)', 'scroll-hero-sequence' ); ?></strong></label>
		<textarea name="shs_prompt_start_field" id="shs_prompt_start" class="widefat" rows="3" readonly><?php echo esc_textarea( $config->prompt_start ); ?></textarea>
		<p class="description"><?php esc_html_e( 'Defined by template. Editable in Pro when creating custom templates.', 'scroll-hero-sequence' ); ?></p>
	</div>

	<div class="shs-editor__section">
		<h3><?php esc_html_e( 'Scenes & Lock Zone', 'scroll-hero-sequence' ); ?></h3>
		<ul class="shs-scene-list">
			<?php foreach ( $config->scenes as $scene ) : ?>
				<li class="shs-scene-list__item shs-scene-list__item--<?php echo esc_attr( $scene->generation_mode ); ?>">
					<span class="shs-scene-list__label"><?php echo esc_html( $scene->label ); ?></span>
					<span class="shs-scene-list__range"><?php echo esc_html( sprintf( 'Frames %03d–%03d', $scene->frame_start, $scene->frame_end ) ); ?></span>
					<?php if ( 'locked' === $scene->generation_mode ) : ?>
						<span class="shs-badge"><?php esc_html_e( 'HTML overlays only', 'scroll-hero-sequence' ); ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>

	<div class="shs-editor__section">
		<h3><?php esc_html_e( 'Overlay Steps (review points)', 'scroll-hero-sequence' ); ?></h3>
		<table class="widefat shs-overlay-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Frame', 'scroll-hero-sequence' ); ?></th>
					<th><?php esc_html_e( 'Type', 'scroll-hero-sequence' ); ?></th>
					<th><?php esc_html_e( 'Content', 'scroll-hero-sequence' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $config->overlay_steps as $step ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $step->frame_trigger ); ?></td>
						<td><?php echo esc_html( $step->element_type ); ?></td>
						<td>
							<?php
							$preview = $step->content['text'] ?? $step->content['alt'] ?? '';
							if ( ! $preview && ! empty( $step->content['buttons'] ) ) {
								$preview = implode( ', ', array_column( $step->content['buttons'], 'label' ) );
							}
							echo esc_html( (string) $preview );
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<input type="hidden" name="shs_config" id="shs_config" value="<?php echo esc_attr( wp_json_encode( $config->to_array() ) ); ?>" />

	<div class="shs-editor__actions">
		<a class="button" href="<?php echo esc_url( \ScrollHeroSequence\Admin\PreviewController::url( $post->ID ) ); ?>" target="_blank" rel="noopener">
			<?php esc_html_e( 'Preview', 'scroll-hero-sequence' ); ?>
		</a>
		<?php if ( $limiter->is_pro() ) : ?>
			<button type="button" class="button" disabled><?php esc_html_e( 'Generate Frames (Pro)', 'scroll-hero-sequence' ); ?></button>
		<?php endif; ?>
	</div>
</div>
