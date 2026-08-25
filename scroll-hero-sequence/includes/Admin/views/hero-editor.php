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

// Frame sequence sets (desktop is passed in as $frames; mobile read here).
$frames_desktop = is_array( $frames ) ? array_map( 'intval', $frames ) : [];
$frames_mobile  = get_post_meta( $post->ID, '_shs_frames_mobile', true );
$frames_mobile  = is_array( $frames_mobile ) ? array_map( 'intval', $frames_mobile ) : [];

$max_desktop = $limiter->is_pro() ? \ScrollHeroSequence\PostType\HeroSequence::MAX_FRAMES_DESKTOP_PRO : \ScrollHeroSequence\PostType\HeroSequence::MAX_FRAMES_DESKTOP_FREE;
$max_mobile  = \ScrollHeroSequence\PostType\HeroSequence::MAX_FRAMES_MOBILE;

/**
 * Render an ordered, editable thumbnail strip for a frame set.
 *
 * @param int[]  $ids       Attachment IDs in frame order.
 * @param string $input_id  Hidden input id/name that stores the CSV of IDs.
 * @param string $strip_id  Container id for the JS to target.
 */
$shs_render_strip = static function ( array $ids, string $input_id, string $strip_id ): void {
	?>
	<div class="shs-frame-strip" id="<?php echo esc_attr( $strip_id ); ?>" data-input="<?php echo esc_attr( $input_id ); ?>" data-empty="<?php esc_attr_e( 'No frames yet.', 'scroll-hero-sequence' ); ?>">
		<?php foreach ( $ids as $i => $attachment_id ) : ?>
			<?php $thumb = wp_get_attachment_image_url( (int) $attachment_id, 'thumbnail' ); ?>
			<?php if ( ! $thumb ) { continue; } ?>
			<figure class="shs-frame-strip__item" data-id="<?php echo esc_attr( (string) (int) $attachment_id ); ?>" draggable="true">
				<span class="shs-frame-strip__num"><?php echo esc_html( sprintf( '%03d', $i + 1 ) ); ?></span>
				<img src="<?php echo esc_url( $thumb ); ?>" alt="" />
				<button type="button" class="shs-frame-strip__remove" aria-label="<?php esc_attr_e( 'Remove frame', 'scroll-hero-sequence' ); ?>">&times;</button>
			</figure>
		<?php endforeach; ?>
	</div>
	<input type="hidden" name="<?php echo esc_attr( $input_id ); ?>" id="<?php echo esc_attr( $input_id ); ?>" value="<?php echo esc_attr( implode( ',', $ids ) ); ?>" />
	<?php
};
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

	<div class="shs-editor__section shs-editor__frames">
		<h3><?php esc_html_e( 'Frame Sequence (Desktop)', 'scroll-hero-sequence' ); ?></h3>
		<p class="description">
			<?php
			printf(
				/* translators: %d: maximum desktop frames for the current plan */
				esc_html__( 'Upload the ordered rendered frames (max %d). Drag to reorder; the first frame is the poster. Frames left empty fall back to the master image.', 'scroll-hero-sequence' ),
				(int) $max_desktop
			);
			?>
		</p>
		<?php $shs_render_strip( $frames_desktop, 'shs_frames_desktop', 'shs-strip-desktop' ); ?>
		<p class="shs-frames__meta">
			<span class="shs-frames__count" data-strip="shs-strip-desktop" data-max="<?php echo esc_attr( (string) $max_desktop ); ?>">
				<?php echo esc_html( sprintf( '%1$d / %2$d', count( $frames_desktop ), $max_desktop ) ); ?>
			</span>
		</p>
		<button type="button" class="button shs-add-frames" data-strip="shs-strip-desktop" data-max="<?php echo esc_attr( (string) $max_desktop ); ?>">
			<?php esc_html_e( 'Add / Replace Desktop Frames', 'scroll-hero-sequence' ); ?>
		</button>
		<button type="button" class="button shs-clear-frames" data-strip="shs-strip-desktop">
			<?php esc_html_e( 'Clear', 'scroll-hero-sequence' ); ?>
		</button>
	</div>

	<div class="shs-editor__section shs-editor__frames">
		<h3><?php esc_html_e( 'Frame Sequence (Mobile — optional)', 'scroll-hero-sequence' ); ?></h3>
		<p class="description">
			<?php
			printf(
				/* translators: %d: maximum mobile frames */
				esc_html__( 'Optional lighter set for phones (max %d). If empty, the desktop set is used.', 'scroll-hero-sequence' ),
				(int) $max_mobile
			);
			?>
		</p>
		<?php $shs_render_strip( $frames_mobile, 'shs_frames_mobile', 'shs-strip-mobile' ); ?>
		<p class="shs-frames__meta">
			<span class="shs-frames__count" data-strip="shs-strip-mobile" data-max="<?php echo esc_attr( (string) $max_mobile ); ?>">
				<?php echo esc_html( sprintf( '%1$d / %2$d', count( $frames_mobile ), $max_mobile ) ); ?>
			</span>
		</p>
		<button type="button" class="button shs-add-frames" data-strip="shs-strip-mobile" data-max="<?php echo esc_attr( (string) $max_mobile ); ?>">
			<?php esc_html_e( 'Add / Replace Mobile Frames', 'scroll-hero-sequence' ); ?>
		</button>
		<button type="button" class="button shs-clear-frames" data-strip="shs-strip-mobile">
			<?php esc_html_e( 'Clear', 'scroll-hero-sequence' ); ?>
		</button>
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
