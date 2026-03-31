<?php
trait PM_Pet_Match_Assets_Trait {
  private static function get_vendor_asset_manifest() : array {
    return [
      'leaflet_style' => [
        'label' => 'Leaflet CSS',
        'type' => 'style',
        'handle' => 'pm-leaflet',
        'local_relative_path' => 'assets/vendor/leaflet/leaflet.css',
        'cdn_url' => 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
        'version' => '1.9.4',
      ],
      'leaflet_script' => [
        'label' => 'Leaflet JS',
        'type' => 'script',
        'handle' => 'pm-leaflet',
        'local_relative_path' => 'assets/vendor/leaflet/leaflet.js',
        'cdn_url' => 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
        'version' => '1.9.4',
      ],
      'swiper_style' => [
        'label' => 'Swiper CSS',
        'type' => 'style',
        'handle' => 'pm-swiper',
        'local_relative_path' => 'assets/vendor/swiper/swiper-bundle.min.css',
        'cdn_url' => 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css',
        'version' => '11.0.0',
      ],
      'swiper_script' => [
        'label' => 'Swiper JS',
        'type' => 'script',
        'handle' => 'pm-swiper',
        'local_relative_path' => 'assets/vendor/swiper/swiper-bundle.min.js',
        'cdn_url' => 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js',
        'version' => '11.0.0',
      ],
      'font_stylesheet' => [
        'label' => 'Google Fonts (Montserrat)',
        'type' => 'style',
        'handle' => 'pm-font',
        'local_relative_path' => 'assets/css/pm-font-local.css',
        'cdn_url' => 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap',
        'version' => self::get_plugin_version(),
      ],
    ];
  }

  private static function get_local_asset_path(string $relative_path) : string {
    return PM_PLUGIN_DIR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative_path);
  }

  private static function get_local_asset_url(string $relative_path) : string {
    return plugins_url(str_replace('\\', '/', $relative_path), PM_PLUGIN_FILE);
  }

  private static function has_local_asset(string $relative_path) : bool {
    $path = self::get_local_asset_path($relative_path);
    return is_readable($path) && filesize($path) > 0;
  }

  private static function log_missing_local_asset(string $asset_key, array $asset) : void {
    static $logged = [];

    if (isset($logged[$asset_key])) {
      return;
    }

    $logged[$asset_key] = true;

    self::log_event('WARN', 'assets.local_missing', 'Local vendor asset missing, falling back to CDN', [
      'asset_key' => $asset_key,
      'asset_label' => $asset['label'],
      'mode' => self::get_asset_delivery_mode(),
      'local_relative_path' => $asset['local_relative_path'],
    ]);
  }

  private static function resolve_vendor_asset_source(string $asset_key) : array {
    $manifest = self::get_vendor_asset_manifest();
    $asset = $manifest[$asset_key] ?? null;

    if (!is_array($asset)) {
      return [
        'url' => '',
        'source' => 'missing',
        'local_available' => false,
      ];
    }

    $mode = self::get_asset_delivery_mode();
    $local_available = self::has_local_asset($asset['local_relative_path']);

    if ($asset_key === 'font_stylesheet' && !self::allow_external_google_fonts()) {
      return [
        'url' => self::get_local_asset_url($asset['local_relative_path']),
        'source' => 'local-font-stack',
        'local_available' => true,
      ];
    }

    if (($mode === 'auto' || $mode === 'local') && $local_available) {
      return [
        'url' => self::get_local_asset_url($asset['local_relative_path']),
        'source' => 'local',
        'local_available' => true,
      ];
    }

    if ($mode === 'local' && !$local_available) {
      self::log_missing_local_asset($asset_key, $asset);
    }

    return [
      'url' => $asset['cdn_url'],
      'source' => 'cdn',
      'local_available' => $local_available,
    ];
  }

  private static function get_asset_dependency_status() : array {
    $manifest = self::get_vendor_asset_manifest();

    return [
      'delivery_mode' => self::get_asset_delivery_mode(),
      'external_google_fonts' => self::allow_external_google_fonts(),
      'leaflet' => [
        'label' => 'Leaflet',
        'local_available' => self::has_local_asset($manifest['leaflet_style']['local_relative_path']) && self::has_local_asset($manifest['leaflet_script']['local_relative_path']),
        'expected_paths' => [
          $manifest['leaflet_style']['local_relative_path'],
          $manifest['leaflet_script']['local_relative_path'],
        ],
      ],
      'swiper' => [
        'label' => 'Swiper',
        'local_available' => self::has_local_asset($manifest['swiper_style']['local_relative_path']) && self::has_local_asset($manifest['swiper_script']['local_relative_path']),
        'expected_paths' => [
          $manifest['swiper_style']['local_relative_path'],
          $manifest['swiper_script']['local_relative_path'],
        ],
      ],
      'font' => [
        'label' => 'Montserrat / stack local',
        'local_available' => self::has_local_asset($manifest['font_stylesheet']['local_relative_path']),
        'expected_paths' => [
          $manifest['font_stylesheet']['local_relative_path'],
        ],
      ],
    ];
  }

  public static function register_assets() : void {
    $version = self::get_plugin_version();
    $leaflet_style = self::resolve_vendor_asset_source('leaflet_style');
    $leaflet_script = self::resolve_vendor_asset_source('leaflet_script');
    $swiper_style = self::resolve_vendor_asset_source('swiper_style');
    $swiper_script = self::resolve_vendor_asset_source('swiper_script');
    $font_style = self::resolve_vendor_asset_source('font_stylesheet');

    wp_register_style(
      'pm-leaflet',
      $leaflet_style['url'],
      [],
      '1.9.4'
    );

    wp_register_script(
      'pm-leaflet',
      $leaflet_script['url'],
      [],
      '1.9.4',
      true
    );

    wp_register_script(
      'pm-map',
      plugins_url('assets/js/pm-map.js', PM_PLUGIN_FILE),
      ['pm-leaflet'],
      $version,
      true
    );

    wp_register_style(
      'pm-font',
      $font_style['url'],
      [],
      $version
    );

    wp_register_style(
      'pm-style',
      plugins_url('assets/css/pm-style.css', PM_PLUGIN_FILE),
      ['pm-font'],
      $version
    );

    wp_register_style(
      'pm-swiper',
      $swiper_style['url'],
      [],
      '11.0.0'
    );

    wp_register_script(
      'pm-swiper',
      $swiper_script['url'],
      [],
      '11.0.0',
      true
    );

    wp_register_script(
      'pm-ui',
      plugins_url('assets/js/pm-ui.js', PM_PLUGIN_FILE),
      ['pm-swiper'],
      $version,
      true
    );

    wp_register_script(
      'pm-search-map',
      plugins_url('assets/js/pm-search-map.js', PM_PLUGIN_FILE),
      ['pm-leaflet'],
      $version,
      true
    );
  }

  public static function enqueue_conditional_assets() : void {
    if (!is_singular(self::CPT)) {
      return;
    }

    wp_enqueue_style('pm-font');
    wp_enqueue_style('pm-style');

    $post_id = get_queried_object_id();
    $show_map = (bool) self::get_valid_case_coordinates((int) $post_id);
    $show_map = apply_filters('pm_show_map_on_single', $show_map, (int) $post_id);

    if ($show_map && self::localize_single_map_config((int) $post_id)) {
      wp_enqueue_style('pm-leaflet');
      wp_enqueue_script('pm-leaflet');
      wp_enqueue_script('pm-map');
    }
  }
}
