<?php
/**
 * Uninstall cleanup for Scroll Hero Sequence.
 *
 * Removes plugin post meta, custom capabilities and options so nothing is
 * left behind after deletion. Runs only via the WordPress uninstall hook.
 *
 * @package ScrollHeroSequence
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// 1. Delete all hero_sequence posts and their meta.
$hero_ids = get_posts(
	[
		'post_type'      => 'hero_sequence',
		'post_status'    => 'any',
		'numberposts'    => -1,
		'fields'         => 'ids',
		'suppress_filters' => true,
	]
);

foreach ( (array) $hero_ids as $hero_id ) {
	wp_delete_post( (int) $hero_id, true );
}

// 2. Remove orphaned post meta (defensive).
$meta_keys = [ '_shs_config', '_shs_master_image_id', '_shs_template_slug', '_shs_frames_desktop', '_shs_frames_mobile' ];
foreach ( $meta_keys as $meta_key ) {
	delete_post_meta_by_key( $meta_key );
}

// 3. Strip custom capabilities from all roles.
$caps = [
	'edit_hero_sequence',
	'read_hero_sequence',
	'delete_hero_sequence',
	'edit_hero_sequences',
	'edit_others_hero_sequences',
	'publish_hero_sequences',
	'read_private_hero_sequences',
	'delete_hero_sequences',
	'delete_private_hero_sequences',
	'delete_published_hero_sequences',
	'delete_others_hero_sequences',
	'edit_private_hero_sequences',
	'edit_published_hero_sequences',
	'create_hero_sequences',
];

foreach ( wp_roles()->roles as $role_slug => $role_data ) {
	$role = get_role( $role_slug );
	if ( ! $role ) {
		continue;
	}
	foreach ( $caps as $cap ) {
		$role->remove_cap( $cap );
	}
}

// 4. Clean transients.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_shs\_%' OR option_name LIKE '\_transient\_timeout\_shs\_%'" );
