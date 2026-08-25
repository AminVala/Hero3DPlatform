<?php
/**
 * Template preset registry.
 *
 * @package ScrollHeroSequence
 */

declare(strict_types=1);

namespace ScrollHeroSequence\Templates;

use ScrollHeroSequence\Domain\HeroConfig;

final class TemplateRegistry {

	private const PRESETS_DIR = SHS_PLUGIN_DIR . 'templates/presets/';

	/** @var array<string, array<string, mixed>>|null */
	private static ?array $cache = null;

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function all(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		self::$cache = [];
		$files       = glob( self::PRESETS_DIR . '*.json' ) ?: [];

		foreach ( $files as $file ) {
			$raw = json_decode( (string) file_get_contents( $file ), true );
			if ( ! is_array( $raw ) || empty( $raw['slug'] ) ) {
				continue;
			}
			self::$cache[ $raw['slug'] ] = $raw;
		}

		return self::$cache;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get( string $slug ): ?array {
		$all = $this->all();
		return $all[ $slug ] ?? null;
	}

	public function get_default_slug(): string {
		return 'shahre-honar';
	}

	public function create_config_from_template( string $slug ): HeroConfig {
		$template = $this->get( $slug ) ?? $this->get( $this->get_default_slug() );

		if ( null === $template ) {
			return HeroConfig::empty();
		}

		$data                     = $template;
		$data['template_slug']    = $template['slug'];
		$data['master_image_id']  = 0;
		$data['generation_status'] = 'idle';

		return HeroConfig::from_array( $data );
	}

	/**
	 * @return list<array{slug: string, name: string, description: string, version: string}>
	 */
	public function list_for_admin(): array {
		$list = [];
		foreach ( $this->all() as $slug => $template ) {
			$list[] = [
				'slug'        => $slug,
				'name'        => (string) ( $template['name'] ?? $slug ),
				'description' => (string) ( $template['description'] ?? '' ),
				'version'     => (string) ( $template['version'] ?? '1.0.0' ),
			];
		}
		return $list;
	}
}
