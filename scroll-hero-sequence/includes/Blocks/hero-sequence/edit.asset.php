<?php
/**
 * Dependency + version manifest for edit.js.
 *
 * block.json's `editorScript: file:./edit.js` makes WordPress look for a
 * sibling `edit.asset.php` to resolve script dependencies. Declaring them
 * here guarantees `window.wp.blocks`, `element`, `components`, `data` and
 * `blockEditor` are loaded before edit.js runs.
 *
 * @package ScrollHeroSequence
 */

return [
	'dependencies' => [
		'wp-blocks',
		'wp-element',
		'wp-components',
		'wp-data',
		'wp-block-editor',
		'wp-i18n',
	],
	'version' => '0.1.0',
];
