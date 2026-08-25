<?php
/**
 * Plugin Name:       Scroll Hero Sequence
 * Plugin URI:        https://github.com/AminVala/Hero3DPlatform
 * Description:       Cinematic scroll-driven hero sequences with HTML overlays, template presets, and optional AI frame generation (Pro).
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Amin Vala
 * Author URI:        https://github.com/AminVala
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       scroll-hero-sequence
 * Domain Path:       /languages
 *
 * @package ScrollHeroSequence
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SHS_VERSION', '0.1.0' );
define( 'SHS_PLUGIN_FILE', __FILE__ );
define( 'SHS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SHS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SHS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once SHS_PLUGIN_DIR . 'includes/autoload.php';

\ScrollHeroSequence\Plugin::instance()->boot();
