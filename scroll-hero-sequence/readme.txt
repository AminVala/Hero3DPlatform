=== Scroll Hero Sequence ===
Contributors: aminvala
Tags: hero, scroll, animation, image sequence, scrollytelling
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Cinematic scroll-driven hero sequences with HTML overlays and template presets.

== Description ==

Scroll Hero Sequence creates Apple-style scroll animations for WordPress homepages and landing pages. Upload a master frame, apply a storyboard template, and publish a responsive hero with HTML overlays synced to scroll position.

**Features (v0.1.0)**

* Custom post type: Hero Sequences
* Template preset system with Shahre Honar default storyboard (24 keyframes, 4 scenes, 12 beats)
* Reference-preserving lock zone: static image + HTML overlays
* Master frame upload
* Gutenberg block
* Preview mode
* Free plan: 1 hero | Pro plan: up to 15 heroes (via filter)

**Default Template: Shahre Honar**

A reference-preserving scroll hero for art supply / creative commerce sites. Evolves from blank canvas to full studio. All UI (logo, nav, headings, CTAs) rendered as HTML — never baked into images.

== Installation ==

1. Upload the `scroll-hero-sequence` folder to `/wp-content/plugins/`
2. Activate through the **Plugins** screen
3. Go to **Scroll Heroes → Add Hero**
4. Upload your Master Frame (studio image without text/UI)
5. Insert the **Scroll Hero Sequence** block and select your hero

== Frequently Asked Questions ==

= Does this require WooCommerce? =

No. WooCommerce integration is planned as an optional Pro feature.

= Where is AI frame generation? =

Planned for Pro (Sprint 4) with Bring Your Own API Key.

= How do I add more templates? =

Add a JSON file to `templates/presets/` following the `shahre-honar.json` schema.

== Changelog ==

= 0.1.0 =
* Initial release: CPT, template system, Shahre Honar preset, block, preview, lock zone architecture.
