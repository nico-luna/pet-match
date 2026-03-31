<?php
/**
 * Plugin Name: Pet Match (MVP)
 * Description: MVP para casos de mascotas perdidas/encontradas con mapa Leaflet (OpenStreetMap) + shortcode de alta.
 * Version: 0.5.3.20
 * Author: Fluxo Studios (Internal)
 * Text Domain: pet-match
 */

if (!defined('ABSPATH')) exit;


// -------------------------------
// Logger (writes to uploads/pet-match/pet-match.log)
// -------------------------------
if (!class_exists('PM_Logger')) {
  class PM_Logger {
    const OPTION_ENABLED = 'pm_log_enabled';
    const MAX_BYTES = 1048576; // 1MB

    public static function enabled() : bool {
      $val = get_option(self::OPTION_ENABLED, '1');
      return $val === '1' || $val === 1 || $val === true;
    }

    public static function log(string $level, string $message, array $context = []) : void {
      if (!function_exists('wp_upload_dir')) return;
      if (!self::enabled()) return;

      try {
        $upload = wp_upload_dir();
        $dir = trailingslashit($upload['basedir']) . 'pet-match';
        if (!is_dir($dir)) {
          wp_mkdir_p($dir);
        }
        $file = trailingslashit($dir) . 'pet-match.log';

        // rotate if too big
        if (file_exists($file) && filesize($file) > self::MAX_BYTES) {
          $rot = trailingslashit($dir) . 'pet-match-' . date('Ymd-His') . '.log';
          @rename($file, $rot);
        }

        $ts = date('Y-m-d H:i:s');
        $ctx = '';
        if (!empty($context)) {
          $ctx = ' ' . wp_json_encode($context);
        }
        $line = "[$ts][$level] $message$ctx\n";
        @file_put_contents($file, $line, FILE_APPEND);
      } catch (\Throwable $e) {
        // ignore
      }
    }

    public static function path() : string {
      $upload = wp_upload_dir();
      return trailingslashit($upload['basedir']) . 'pet-match/pet-match.log';
    }

    public static function read_tail(int $max_lines = 400) : string {
      $file = self::path();
      if (!file_exists($file)) return '';
      $lines = @file($file, FILE_IGNORE_NEW_LINES);
      if (!$lines) return '';
      $slice = array_slice($lines, max(0, count($lines) - $max_lines));
      return implode("\n", $slice);
    }

    public static function clear() : void {
      $file = self::path();
      if (file_exists($file)) {
        @file_put_contents($file, '');
      }
    }
  }
}

// Capture fatals early
if (function_exists('register_shutdown_function')) {
  register_shutdown_function(function(){
    $err = error_get_last();
    if (!$err) return;
    $fatal_types = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (in_array($err['type'], $fatal_types, true)) {
      if (class_exists('PM_Logger')) {
        PM_Logger::log('FATAL', $err['message'], ['file'=>$err['file'], 'line'=>$err['line']]);
      }
    }
  });
}

final class PM_Pet_Match {
  const VERSION = "0.5.3.15";
  const CPT = 'pet_case';

  public static function init() : void {
    if (class_exists('PM_Logger')) { PM_Logger::log('INFO', 'PM init'); }

    // Prevent 404 on /casos/{slug} after installs/updates by flushing rewrite rules once.
    add_action('init', [__CLASS__, 'maybe_flush_rewrite'], 20);
    add_action('init', [__CLASS__, 'register_cpt']);
    add_action('init', [__CLASS__, 'register_taxonomies']);
    // Ensure taxonomy slugs exist and prevent cases without type.
    if ( method_exists(__CLASS__, 'ensure_case_type_terms') ) {
      add_action('init', [__CLASS__, 'ensure_case_type_terms'], 30);
    }

    add_action('save_post_' . self::CPT, [__CLASS__, 'ensure_case_type_on_save'], 20, 3);
    if (is_admin()) {
      add_action('admin_post_pm_set_case_type', [__CLASS__, 'handle_admin_set_case_type']);
    }
    add_action('wp_enqueue_scripts', [__CLASS__, 'register_assets']);
    add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_conditional_assets']);
    add_shortcode('pm_create_case', [__CLASS__, 'shortcode_create_case']);
    add_shortcode('pm_home_cards', [__CLASS__, 'shortcode_home_cards']);
    add_shortcode('pm_search', [__CLASS__, 'shortcode_search']);
    add_shortcode('pm_cases_slider', [__CLASS__, 'shortcode_cases_slider']);
    add_shortcode('pm_metrics', [__CLASS__, 'shortcode_metrics']);
    add_shortcode('pm_create_alert', [__CLASS__, 'shortcode_create_alert']);
    add_shortcode('pm_report_sighting', [__CLASS__, 'shortcode_report_sighting']);
    add_action('init', [__CLASS__, 'handle_create_case']);
    if (method_exists(__CLASS__,'pm_resolve_case')) { add_action('init', [__CLASS__, 'pm_resolve_case']); }
    add_action('save_post', [__CLASS__, 'notify_alerts']);
    add_action('register_form', [__CLASS__, 'render_register_shelter_field']);
    add_filter('registration_errors', [__CLASS__, 'validate_register_shelter_field'], 10, 3);
    add_action('user_register', [__CLASS__, 'save_register_shelter_field']);
    add_filter('the_content', [__CLASS__, 'append_case_meta_to_single'], 20);

    // Admin UI
    add_action('admin_menu', [__CLASS__, 'register_admin_menu']);
    add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
    add_filter('parent_file', [__CLASS__, 'highlight_admin_menu']);

    // Admin actions (bulk-like)
    add_action('admin_post_pm_case_action', [__CLASS__, 'pm_handle_admin_action']);
    add_action('admin_post_pm_shelter_action', [__CLASS__, 'pm_handle_shelter_action']);

    // Keep author meta cached for ordering (shelter verified, etc.)
    add_action('save_post', [__CLASS__, 'pm_sync_case_author_meta'], 10, 3);
  }

  /**
   * Ensure required taxonomy terms exist.
   *
   * Hooked after register_taxonomies().
   */
  public static function ensure_case_type_terms() : void {
    // Taxonomy must be registered first.
    if (!taxonomy_exists('pm_case_type')) {
      return;
    }
    self::maybe_seed_terms();
  }

  public static function activate() : void {
    // Ensure CPT/tax are registered before flushing.
    self::register_cpt();
    self::register_taxonomies();
    // Seed basic taxonomy terms once.
    self::maybe_seed_terms();
    flush_rewrite_rules(false);
    update_option('pm_pet_match_version', self::VERSION);
  }

  public static function deactivate() : void {
    flush_rewrite_rules(false);
  }

  public static function maybe_flush_rewrite() : void {
    $stored = get_option('pm_pet_match_version');
    if ($stored !== self::VERSION) {
      flush_rewrite_rules(false);
      update_option('pm_pet_match_version', self::VERSION);
    }
  }

  public static function register_assets() : void {
    // Register Leaflet from CDN (loaded only when shortcode renders)
    wp_register_style(
      'pm-leaflet',
      'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
      [],
      '1.9.4'
    );

    wp_register_script(
      'pm-leaflet',
      'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
      [],
      '1.9.4',
      true
    );

    wp_register_script(
      'pm-map',
      plugins_url('assets/js/pm-map.js', __FILE__),
      ['pm-leaflet'],
      self::VERSION,
      true
    );

    wp_register_style(
      'pm-font',
      'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap',
      [],
      self::VERSION
    );

    wp_register_style(
      'pm-style',
      plugins_url('assets/css/pm-style.css', __FILE__),
      ['pm-font'],
      self::VERSION
    );
  
    // Swiper (slider) from CDN (loaded only when slider shortcode renders)
    wp_register_style(
      'pm-swiper',
      'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css',
      [],
      '11.0.0'
    );

    wp_register_script(
      'pm-swiper',
      'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js',
      [],
      '11.0.0',
      true
    );

    wp_register_script(
      'pm-ui',
      plugins_url('assets/js/pm-ui.js', __FILE__),
      ['pm-swiper'],
      self::VERSION,
      true
    );

    wp_register_script(
      'pm-search-map',
      plugins_url('assets/js/pm-search-map.js', __FILE__),
      ['pm-leaflet'],
      self::VERSION,
      true
    );
}


  public static function enqueue_conditional_assets() : void {
    // Ensure base styles on case single for consistent UI
    if (!is_singular(self::CPT)) {
      return;
    }

    wp_enqueue_style('pm-font');
    wp_enqueue_style('pm-style');

    // Only load map libs when this case has coordinates
    $post_id = get_the_ID();
    $lat = $post_id ? get_post_meta($post_id, '_pm_lat', true) : '';
    $lng = $post_id ? get_post_meta($post_id, '_pm_lng', true) : '';

    $show_map = ($lat !== '' && $lng !== '');

    /**
     * Filter: allow overriding map visibility on single.
     * @param bool $show_map
     * @param int $post_id
     */
    $show_map = apply_filters('pm_show_map_on_single', $show_map, (int)$post_id);

    if ($show_map) {
      wp_enqueue_style('pm-leaflet');
      wp_enqueue_script('pm-leaflet');
      wp_enqueue_script('pm-search-map');
    }
  }

  public static function register_cpt() : void {
    $labels = [
      'name'               => 'Casos',
      'singular_name'      => 'Caso',
      'add_new'            => 'Agregar nuevo',
      'add_new_item'       => 'Agregar caso',
      'edit_item'          => 'Editar caso',
      'new_item'           => 'Nuevo caso',
      'view_item'          => 'Ver caso',
      'search_items'       => 'Buscar casos',
      'not_found'          => 'No se encontraron casos',
      'not_found_in_trash' => 'No se encontraron casos en la papelera',
      'menu_name'          => 'Mascotas (Casos)',
    ];

    register_post_type('pm_alert', [
      'label' => 'Alertas',
      'public' => false,
      'show_ui' => false,
      'show_in_menu' => false,
      'supports' => ['title'],
    ]);

    register_post_type('pm_sighting', [
      'label' => 'Avistajes',
      'public' => false,
      'show_ui' => false,
      'show_in_menu' => false,
      'supports' => ['title','editor'],
    ]);

    register_post_type(self::CPT, [
      'labels'             => $labels,
      'public' => true,
      'show_ui' => false,
      'show_in_menu' => false,
      'show_in_admin_bar' => false,
      // Archivo: /casos/
      'has_archive'        => 'casos',
      // Single: /caso/{slug} (evita colisión si existe una página "casos")
      'rewrite'            => ['slug' => 'caso', 'with_front' => false],
      'show_in_rest'       => true,
      'supports'           => ['title', 'editor', 'author', 'thumbnail'],
      ]);
  }

  public static function register_taxonomies() : void {
    // Tipo: Perdí / Encontré
    register_taxonomy('pm_case_type', self::CPT, [
      'label'        => 'Tipo',
      'public'       => true,
      'show_in_rest' => true,
      'rewrite'      => ['slug' => 'tipo'],
      'hierarchical' => false,
    ]);

    // Especie: Perro / Gato / Otro
    register_taxonomy('pm_species', self::CPT, [
      'label'        => 'Especie',
      'public'       => true,
      'show_in_rest' => true,
      'rewrite'      => ['slug' => 'especie'],
      'hierarchical' => false,
    ]);

    // Zona / Barrio
    register_taxonomy('pm_zone', self::CPT, [
      'label'        => 'Zona',
      'public'       => true,
      'show_in_rest' => true,
      'rewrite'      => ['slug' => 'zona'],
      'hierarchical' => true,
    ]);

	  // Seed básico: se ejecuta en activación para evitar side-effects en cada request.
  }


	/** 
	 * Ensure a taxonomy term exists with a specific slug.
	 * Returns the term_id on success, 0 on failure.
	 */
  public static function ensure_term_with_slug( $taxonomy, $name, $slug ) {
		$taxonomy = sanitize_key( $taxonomy );
		$slug     = sanitize_title( $slug );
		$name     = sanitize_text_field( $name );

		if ( empty( $taxonomy ) || empty( $slug ) || empty( $name ) ) {
			return 0;
		}

		// Try by slug first.
		$existing = term_exists( $slug, $taxonomy );
		if ( is_array( $existing ) && ! empty( $existing['term_id'] ) ) {
			return (int) $existing['term_id'];
		}
		if ( is_int( $existing ) && $existing > 0 ) {
			return (int) $existing;
		}

		// Fallback: sometimes term_exists works better by name.
		$existing_by_name = term_exists( $name, $taxonomy );
		if ( is_array( $existing_by_name ) && ! empty( $existing_by_name['term_id'] ) ) {
			return (int) $existing_by_name['term_id'];
		}
		if ( is_int( $existing_by_name ) && $existing_by_name > 0 ) {
			return (int) $existing_by_name;
		}

		$created = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
		if ( is_wp_error( $created ) ) {
			return 0;
		}
		if ( is_array( $created ) && ! empty( $created['term_id'] ) ) {
			return (int) $created['term_id'];
		}
		return 0;
	}

  public static function maybe_seed_terms() : void {
  // Defensive guardrails: never let term seeding break the site.
  if ( ! function_exists( 'taxonomy_exists' ) || ! taxonomy_exists( 'pm_case_type' ) ) {
    return;
  }
  if ( ! method_exists( __CLASS__, 'ensure_term_with_slug' ) ) {
    return;
  }
  // Create the canonical slugs we use in shortcodes/UI.
  // Also migrate legacy Spanish slugs (perdi/encontre/adopcion) to the canonical ones (lost/found/adoption).
  $map = [
    ['name' => 'Perdí',      'slug' => 'lost',       'legacy_slugs' => ['perdi','perdí']],
    ['name' => 'Encontré',   'slug' => 'found',      'legacy_slugs' => ['encontre','encontré']],
    ['name' => 'Adopción',   'slug' => 'adoption',   'legacy_slugs' => ['adopcion','adopción']],
    ['name' => 'Sin definir','slug' => 'sin-definir','legacy_slugs' => ['sin-definir']],
  ];

  foreach ( $map as $t ) {
    $tid = self::ensure_term_with_slug( 'pm_case_type', $t['name'], $t['slug'] );
    if ( ! $tid ) {
      $maybe = term_exists( $t['slug'], 'pm_case_type' );
      if ( ! $maybe ) {
        wp_insert_term( $t['name'], 'pm_case_type', [ 'slug' => $t['slug'] ] );
      }
    }

    foreach ( (array) $t['legacy_slugs'] as $legacy ) {
      if ( $legacy === $t['slug'] ) { continue; }

      $legacy_term = term_exists( $legacy, 'pm_case_type' );
      $new_term    = term_exists( $t['slug'], 'pm_case_type' );

      if ( $legacy_term && $new_term ) {
        $legacy_id = is_array( $legacy_term ) ? (int) $legacy_term['term_id'] : (int) $legacy_term;
        $new_id    = is_array( $new_term ) ? (int) $new_term['term_id'] : (int) $new_term;

        $posts = get_posts( [
          'post_type'      => self::CPT,
          'post_status'    => 'any',
          'fields'         => 'ids',
          'posts_per_page' => -1,
          'tax_query'      => [
            [
              'taxonomy' => 'pm_case_type',
              'field'    => 'term_id',
              'terms'    => [ $legacy_id ],
            ],
          ],
        ] );

        foreach ( $posts as $pid ) {
          wp_set_object_terms( $pid, [ $new_id ], 'pm_case_type', false );
        }
      }
    }
  }

  if ( false === get_option( 'pm_default_case_type', false ) ) {
    add_option( 'pm_default_case_type', 'sin-definir' );
  }
}


  /**
   * Normalize the case type attribute used in shortcodes / UI into a list of taxonomy slugs.
   * Accepts canonical slugs (lost/found/adoption) and legacy Spanish slugs (perdi/encontre/adopcion).
   *
   * @param string $type
   * @return array
   */
  public static function normalize_case_type_terms( string $type ) : array {
    $type = sanitize_title( (string) $type );

    if ( $type === '' ) {
      return [];
    }

    // Canonical mapping + legacy compatibility.
    // NOTE: keep buckets strict (lost != found) so sliders/filters are predictable.
    $map = [
      'lost'     => ['lost', 'perdi', 'perdido', 'perdida', 'perdidos', 'perdidas', 'busco', 'buscando'],
      'found'    => ['found', 'encontre', 'encontrado', 'encontrada', 'encontrados', 'encontradas'],
      'adoption' => ['adoption', 'adopcion', 'adopciones', 'adoptar', 'adopta'],
    ];

    if ( isset( $map[ $type ] ) ) {
      $out = [];
      foreach ( $map[ $type ] as $slug ) {
        $out[] = sanitize_title( (string) $slug );
      }
      // De-dup + remove empties
      $out = array_values( array_filter( array_unique( $out ) ) );
      return $out;
    }

    return [ $type ];
  }

  /**
   * Returns counts of published cases grouped by case type term slug.
   * Useful for debugging empty sliders (taxonomy mismatch).
   */
  public static function get_case_type_counts() : array {
    if ( ! taxonomy_exists('pm_case_type') ) { return []; }
    $terms = get_terms([
      'taxonomy'   => 'pm_case_type',
      'hide_empty' => false,
    ]);
    if ( is_wp_error($terms) || empty($terms) ) { return []; }

    $out = [];
    foreach ($terms as $t) {
      // Count only published cases.
      $q = new \WP_Query([
        'post_type'      => self::CPT,
        'post_status'    => ['publish'],
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'tax_query'      => [[
          'taxonomy' => 'pm_case_type',
          'field'    => 'term_id',
          'terms'    => [(int) $t->term_id],
        ]],
      ]);
      $out[$t->slug] = (int) $q->found_posts;
    }
    return $out;
  }

  /**
   * Ensure each saved case has a pm_case_type term (prevents empty tax_query results and legacy data issues).
   * Hooked to save_post_{CPT}.
   *
   * @param int      $post_id
   * @param \WP_Post $post
   * @param bool     $update
   * @return void
   */
  public static function ensure_case_type_on_save( int $post_id, $post, bool $update ) : void {
    // Guardrails
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) { return; }
    if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) { return; }
    if ( ! $post || ! isset( $post->post_type ) || $post->post_type !== self::CPT ) { return; }
    if ( ! taxonomy_exists('pm_case_type') ) { return; }

    // If already has a term, do nothing.
    $current = wp_get_object_terms( $post_id, 'pm_case_type', [ 'fields' => 'ids' ] );
    if ( ! is_wp_error( $current ) && ! empty( $current ) ) {
      return;
    }

    // Determine default type:
    // 1) From request pm_mode (create_case form)
    // 2) From option pm_default_case_type
    $mode = '';
    if ( isset($_POST['pm_mode']) ) {
      $mode = sanitize_text_field( wp_unslash( $_POST['pm_mode'] ) );
    } elseif ( isset($_REQUEST['pm_mode']) ) {
      $mode = sanitize_text_field( wp_unslash( $_REQUEST['pm_mode'] ) );
    }
    $mode = sanitize_title( (string) $mode );

    $default_slug = 'sin-definir';
    if ( $mode === 'lost' || $mode === 'perdi' ) { $default_slug = 'lost'; }
    elseif ( $mode === 'found' || $mode === 'encontre' ) { $default_slug = 'found'; }
    elseif ( $mode === 'adoption' || $mode === 'adopcion' ) { $default_slug = 'adoption'; }
    else {
      $opt = get_option( 'pm_default_case_type', '' );
      if ( is_string($opt) && $opt !== '' ) {
        $default_slug = sanitize_title( $opt );
      }
    }

    $term = get_term_by( 'slug', $default_slug, 'pm_case_type' );
    if ( ! $term ) {
      // Make sure terms exist (won't throw).
      self::maybe_seed_terms();
      $term = get_term_by( 'slug', $default_slug, 'pm_case_type' );
    }

    if ( $term && ! is_wp_error($term) ) {
      wp_set_object_terms( $post_id, [ (int) $term->term_id ], 'pm_case_type', false );
    }
  }

  private static function ensure_term(string $name, string $taxonomy, int $parent = 0) {
    $existing = term_exists($name, $taxonomy);
    if ($existing) return $existing;
    return wp_insert_term($name, $taxonomy, $parent ? ['parent' => $parent] : []);
  }

  public static function shortcode_create_case($atts = []) : string {
    static $rendered = false;
    // Prevent accidental double render when the shortcode is inserted twice in the same page/template.
    if ($rendered) { return ''; }
    $rendered = true;

    $atts = shortcode_atts([
      'mode' => '', // lost | found | adoption
      'hide_type' => '0',
    ], $atts, 'pm_create_case');

    // Assets only when shortcode used
    wp_enqueue_style('pm-leaflet');
    wp_enqueue_script('pm-leaflet');
    wp_enqueue_script('pm-map');
    wp_enqueue_style('pm-font');
    wp_enqueue_style('pm-style');

    // Default map center (Avellaneda)
    wp_localize_script('pm-map', 'PM_MAP', [
      'defaultLat' => -34.6630,
      'defaultLng' => -58.3660,
      'defaultZoom' => 13,
      'i18n' => [
        'geoFail' => 'No pudimos obtener tu ubicación. Mové el pin en el mapa.',
      ],
    ]);

    $nonce = wp_create_nonce('pm_create_case');

    // Taxonomy terms for selects
    $types = get_terms(['taxonomy' => 'pm_case_type', 'hide_empty' => false]);
    $mode = sanitize_text_field($atts['mode']);
    // "hide_type" is kept for backwards compatibility, but we only hide the selector when:
    // - hide_type=1 AND
    // - mode is provided AND
    // - we can resolve a default term.
    $hide_type = ($atts['hide_type'] === '1');
    $default_type_term_id = 0;
    if ($mode === 'lost') { $term = get_term_by('name', 'Perdí', 'pm_case_type'); if ($term) $default_type_term_id = (int)$term->term_id; }
    elseif ($mode === 'found') { $term = get_term_by('name', 'Encontré', 'pm_case_type'); if ($term) $default_type_term_id = (int)$term->term_id; }
    elseif ($mode === 'adoption') { $term = get_term_by('name', 'Adopción', 'pm_case_type'); if ($term) $default_type_term_id = (int)$term->term_id; }

    $species = get_terms(['taxonomy' => 'pm_species', 'hide_empty' => false]);
    $zones = get_terms(['taxonomy' => 'pm_zone', 'hide_empty' => false]);

    $success_notice = '';


    ob_start(); ?>
      <div class="pm-wrap pm-elementor pm-app">
        <?php echo $success_notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <form method="post" enctype="multipart/form-data" class="pm-form">
          <input type="hidden" name="pm_action" value="create_case">
          <input type="hidden" name="pm_nonce" value="<?php echo esc_attr($nonce); ?>">

          <div class="pm-grid">
            <?php $can_hide_type = $hide_type && !empty($mode) && !empty($default_type_term_id); ?>
            <?php if (!$can_hide_type): ?>
            <div class="pm-field">
              <label for="pm_type">Tipo</label>
              <select id="pm_type" name="pm_type" required class="pm-input elementor-field">
                <option value="">Seleccionar…</option>
                <?php foreach ($types as $t): ?>
                  <option value="<?php echo esc_attr($t->term_id); ?>" <?php selected($default_type_term_id ?: '', $t->term_id); ?>>
                    <?php echo esc_html($t->name); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php else: ?>
              <input type="hidden" name="pm_type" value="<?php echo esc_attr($default_type_term_id); ?>">
            <?php endif; ?>

            <div class="pm-field">
              <label for="pm_species">Especie</label>
              <select id="pm_species" name="pm_species" required class="pm-input elementor-field">
                <option value="">Seleccionar…</option>
                <?php foreach ($species as $s): ?>
                  <option value="<?php echo esc_attr($s->term_id); ?>"><?php echo esc_html($s->name); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="pm-field">
              <label for="pm_zone">Zona / Barrio</label>
              <select id="pm_zone" name="pm_zone" required class="pm-input elementor-field">
                <option value="">Seleccionar…</option>
                <?php foreach ($zones as $z): ?>
                  <option value="<?php echo esc_attr($z->term_id); ?>"><?php echo esc_html($z->name); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="pm-field">
              <label for="pm_date">Fecha aproximada</label>
              <input id="pm_date" name="pm_date" type="date" required class="pm-input elementor-field">
            </div>
          </div>

          <div class="pm-field">
            <label for="pm_description">Descripción</label>
            <textarea id="pm_description" name="pm_description" required rows="4" class="pm-input elementor-field" placeholder="Ej: Perro mediano negro con collar rojo, se perdió cerca de…"></textarea>
          </div>

          <div class="pm-grid">
            <div class="pm-field">
              <label for="pm_pet_name">Nombre (si lo sabés)</label>
              <input id="pm_pet_name" name="pm_pet_name" type="text" class="pm-input elementor-field" placeholder="Ej: Mora">
            </div>

            <div class="pm-field">
              <label for="pm_sex">Sexo</label>
              <select id="pm_sex" name="pm_sex" class="pm-input elementor-field">
                <option value="">No lo sé</option>
                <option value="male">Macho</option>
                <option value="female">Hembra</option>
              </select>
            </div>

            <div class="pm-field">
              <label for="pm_age">Edad aproximada</label>
              <select id="pm_age" name="pm_age" class="pm-input elementor-field">
                <option value="">No lo sé</option>
                <option value="baby">Cachorro</option>
                <option value="young">Joven</option>
                <option value="adult">Adulto</option>
                <option value="senior">Senior</option>
              </select>
            </div>

            <div class="pm-field">
              <label for="pm_size">Tamaño</label>
              <select id="pm_size" name="pm_size" class="pm-input elementor-field">
                <option value="">No lo sé</option>
                <option value="xs">Muy chico</option>
                <option value="s">Chico</option>
                <option value="m">Mediano</option>
                <option value="l">Grande</option>
                <option value="xl">Muy grande</option>
              </select>
            </div>

            <div class="pm-field">
              <label for="pm_color">Color / marcas</label>
              <input id="pm_color" name="pm_color" type="text" class="pm-input elementor-field" placeholder="Ej: atigrado gris, mancha blanca en pecho">
            </div>

            <div class="pm-field">
              <label for="pm_collar">¿Tiene collar?</label>
              <select id="pm_collar" name="pm_collar" class="pm-input elementor-field">
                <option value="">No lo sé</option>
                <option value="yes">Sí</option>
                <option value="no">No</option>
              </select>
            </div>

            <div class="pm-field">
              <label for="pm_neutered">¿Está castrado/a?</label>
              <select id="pm_neutered" name="pm_neutered" class="pm-input elementor-field">
                <option value="">No lo sé</option>
                <option value="yes">Sí</option>
                <option value="no">No</option>
              </select>
            </div>
          </div>

          <div class="pm-field">
            <label for="pm_photos">Fotos (mínimo 1)</label>
            <input id="pm_photos" name="pm_photos[]" type="file" accept="image/*" multiple required class="pm-input elementor-field">
            <small class="pm-help">Tip: la primera foto suele ser la que más se ve. Elegí una bien clara.</small>
          </div>

          <div class="pm-field">
            <label for="pm_whatsapp">WhatsApp (recomendado)</label>
            <input id="pm_whatsapp" name="pm_whatsapp" type="text" class="pm-input elementor-field" placeholder="Ej: +54 9 11 1234-5678">
            <small class="pm-help">Se usará para que te escriban con un botón directo. No mostramos tu email.</small>
          </div>

          <div class="pm-field">
            <label>Ubicación (zona aproximada)</label>
            <div id="pm-map" class="pm-map"></div>
            <?php
              // Default to CABA so the form can be submitted even if the user doesn't move the pin.
              // User can still adjust by clicking the map (recommended).
              $default_lat = -34.6037;
              $default_lng = -58.3816;
            ?>
            <input type="hidden" id="pm_lat" name="pm_lat" value="<?php echo esc_attr($default_lat); ?>" required>
            <input type="hidden" id="pm_lng" name="pm_lng" value="<?php echo esc_attr($default_lng); ?>" required>
            <div class="pm-actions">
              <!-- Geolocalización removida: el usuario ajusta su ubicación haciendo click en el mapa -->
            </div>
              <p class="pm-help">Tip: hacé click en el mapa para mover el pin. No mostramos dirección exacta.</p>
          </div>

          <div class="pm-actions">
            <button type="submit" class="pm-btn pm-btn-primary elementor-button elementor-size-md">Publicar caso</button>
          </div>
        </form>
      </div>
    <?php
    return ob_get_clean();
  }


  public static function shortcode_home_cards($atts = []) : string {
    $atts = shortcode_atts([
      'search_url'  => site_url('/buscar'),
      'publish_url' => site_url('/publicar'),
    ], $atts);

    // Enqueue frontend assets for this shortcode
    wp_enqueue_style('pm-font');
    wp_enqueue_style('pm-style');
    wp_enqueue_script('pm-ui');

    ob_start(); ?>
    <section class="pm-home pm-app">
      <div class="pm-home-grid">
        <a class="pm-home-card pm-home-card--search" href="<?php echo esc_url($atts['search_url']); ?>">
          <div class="pm-home-card-top">
            <div class="pm-home-card-title">Estoy buscando</div>
            <div class="pm-home-card-desc">Perdí a mi mascota o quiero adoptar.</div>
          </div>
          <div class="pm-home-card-hover" aria-hidden="true">
            <div class="pm-home-card-cta">Ir al buscador →</div>
            <div class="pm-home-card-hint">Filtrá por especie, zona y fecha.</div>
          </div>
        </a>

        <a class="pm-home-card pm-home-card--publish" href="<?php echo esc_url($atts['publish_url']); ?>">
          <div class="pm-home-card-top">
            <div class="pm-home-card-title">Quiero publicar</div>
            <div class="pm-home-card-desc">Encontré una mascota o quiero dar en adopción.</div>
          </div>
          <div class="pm-home-card-hover" aria-hidden="true">
            <div class="pm-home-card-cta">Cargar un caso →</div>
            <div class="pm-home-card-hint">Foto + ubicación aproximada. En 1 minuto.</div>
          </div>
        </a>
      </div>
    </section>
    <?php return ob_get_clean();
  }

  public static function shortcode_cases_slider($atts = []) : string {
    $atts = shortcode_atts([
      'type'  => 'lost',
      'title' => '',
      'limit' => 12,
      'debug' => 0,
    ], $atts);

    // Enqueue frontend assets for this shortcode
    wp_enqueue_style('pm-font');
    wp_enqueue_style('pm-style');
    wp_enqueue_script('pm-ui');

    $type  = sanitize_text_field($atts['type']);
    $limit = max(1, (int) $atts['limit']);
    $terms = self::normalize_case_type_terms($type);

    $q = new \WP_Query([
      'post_type'      => self::CPT,
      'post_status'    => ['publish'],
      'posts_per_page' => $limit,
      'orderby'        => 'date',
      'order'          => 'DESC',
      'no_found_rows'  => false,
      'tax_query'      => [[
        'taxonomy' => 'pm_case_type',
        'field'    => 'slug',
        'terms'    => $terms,
      ]],
    ]);

    $title = trim((string) $atts['title']);
    if ($title === '') {
      $title = ($type === 'adoption') ? 'Últimas adopciones' : 'Últimos perdidos';
    }

    // Optional debug (prints to console + HTML comment)
    $debug = ((int) $atts['debug'] === 1) || (defined('PM_DEBUG') && PM_DEBUG) || (defined('WP_DEBUG') && WP_DEBUG && isset($_GET['pm_debug']));
    if ($debug) {
      $payload = [
        'shortcode'        => 'pm_cases_slider',
        'type'             => $type,
        'normalized_terms' => $terms,
        'found_posts'      => (int) $q->found_posts,
        'counts_by_type'   => self::get_case_type_counts(),
      ];
      PM_Logger::log('DEBUG', 'Slider debug', $payload);
      echo "<!-- PM slider debug: " . esc_html(wp_json_encode($payload)) . " -->\n";
      echo "<script>window.console&&console.log('[PetMatch slider]'," . wp_json_encode($payload) . ");</script>\n";
    }

    ob_start(); ?>
    <section class="pm-slider pm-app">
      <div class="pm-slider-head">
        <h3><?php echo esc_html($title); ?></h3>
        <div class="pm-slider-controls">
          <button class="pm-slider-btn" type="button" data-dir="prev" aria-label="Anterior">‹</button>
          <button class="pm-slider-btn" type="button" data-dir="next" aria-label="Siguiente">›</button>
        </div>
      </div>
      <div class="pm-slider-track" role="list" tabindex="0">
        <?php if ($q->have_posts()): while ($q->have_posts()): $q->the_post(); ?>
          <?php echo self::render_case_card(get_the_ID()); ?>
        <?php endwhile; wp_reset_postdata(); else: ?>
          <div class="pm-empty pm-empty-card">
            <div class="pm-empty-title">Todavía no hay casos para mostrar</div>
            <div class="pm-empty-desc">Publicá el primer caso o empezá buscando por tu zona.</div>
            <div class="pm-empty-actions">
              <a class="pm-btn pm-btn-primary" href="<?php echo esc_url(home_url('/publicar/')); ?>">Publicar un caso</a>
              <a class="pm-btn" href="<?php echo esc_url(home_url('/buscar/')); ?>">Ir al buscador</a>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </section>
    <?php
    return ob_get_clean();
  }

  public static function shortcode_search($atts = []) : string {
    $atts = shortcode_atts([
      'show_types' => 1,
      'limit'      => 24,
    ], $atts);

    // Enqueue frontend assets for this shortcode
    wp_enqueue_style('pm-font');
    wp_enqueue_style('pm-style');
    wp_enqueue_script('pm-ui');

    $type = isset($_GET['pm_type']) ? sanitize_text_field($_GET['pm_type']) : '';
  $status = isset($_GET['pm_status']) ? sanitize_key($_GET['pm_status']) : '';
  $allowed_status = array('open','resolved','closed');
  if (!in_array($status, $allowed_status, true)) { $status = ''; }
    $s    = isset($_GET['pm_q']) ? sanitize_text_field($_GET['pm_q']) : '';

    $args = [
      'post_type'      => self::CPT,
      'post_status'    => ['publish'],
      'posts_per_page' => max(1, intval($atts['limit'])),
      's'              => $s,
      'orderby'        => 'date',
      'order'          => 'DESC',
    ];
    if ($type) {
      $args['tax_query'] = [[
        'taxonomy' => 'pm_case_type',
        'field'    => 'slug',
        'terms'    => self::normalize_case_type_terms($type),
      ]];
    }
    if ($status) {
      $args['meta_query'] = [[
        'key'   => '_pm_status',
        'value' => $status,
      ]];
    }
    $q = new \WP_Query($args);

    ob_start(); ?>
    <section class="pm-search pm-app">
      <form method="get" class="pm-search-form pm-searchbar">
        <div class="pm-field">
          <input class="pm-input" type="text" name="pm_q" value="<?php echo esc_attr($s); ?>" placeholder="Buscar por palabra clave (ej: caniche, negro, collar rojo)">
        </div>
        <?php if (intval($atts['show_types']) === 1): ?>
          <select class="pm-select" name="pm_type">
            <option value="">Todos</option>
            <option value="lost" <?php selected($type,'lost'); ?>>Perdidos</option>
            <option value="found" <?php selected($type,'found'); ?>>Encontrados</option>
            <option value="adoption" <?php selected($type,'adoption'); ?>>Adopción</option>
          </select>
        <?php endif; ?>
        <button class="pm-btn-icon" type="submit" aria-label="Buscar">
          <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" stroke="currentColor" stroke-width="2"/>
            <path d="M21 21l-4.3-4.3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </button>

      </form>

      <?php
        $base_args = array();
        if ($keyword) { $base_args['pm_q'] = $keyword; }
        if ($type) { $base_args['pm_type'] = $type; }
        $current = $status ?: 'all';
        $items = array(
          'all'      => array('label' => 'Todos',     'value' => ''),
          'open'     => array('label' => 'Activos',   'value' => 'open'),
          'resolved' => array('label' => 'Resueltos', 'value' => 'resolved'),
          'closed'   => array('label' => 'Cerrados',  'value' => 'closed'),
        );
      ?>
      <div class="pm-status-filters" role="navigation" aria-label="Filtrar por estado">
        <?php foreach ($items as $k => $it):
          $args = $base_args;
          if (!empty($it['value'])) { $args['pm_status'] = $it['value']; }
          $url = add_query_arg($args, get_permalink());
          $cls = 'pm-status-badge' . ($current === $k ? ' is-active' : '');
        ?>
          <a class="<?php echo esc_attr($cls); ?>" href="<?php echo esc_url($url); ?>">
            <?php echo esc_html($it['label']); ?>
          </a>
        <?php endforeach; ?>
      </div>

      <div class="pm-cards-grid">

        <?php if ($q->have_posts()): while ($q->have_posts()): $q->the_post(); ?>
          <?php echo self::render_case_card(get_the_ID()); ?>
        <?php endwhile; wp_reset_postdata(); else: ?>
          <div class="pm-empty">No hay resultados.</div>
        <?php endif; ?>
      </div>
    </section>
    <?php return ob_get_clean();
  }

  public static function shortcode_metrics() : string {
    // Enqueue frontend assets for this shortcode
    wp_enqueue_style('pm-font');
    wp_enqueue_style('pm-style');
    wp_enqueue_script('pm-ui');
    $q = new \WP_Query([
      'post_type'      => self::CPT,
      'post_status'    => ['publish'],
      'meta_key'       => '_pm_status',
      'meta_value'     => 'resolved',
      'posts_per_page' => -1
    ]);
    return '<div class="pm-metrics">✅ '.intval($q->found_posts).' mascotas reunidas o adoptadas</div>';
  }

  public static function shortcode_create_alert($atts = []) : string {
    $atts = shortcode_atts([
      'type' => 'lost',
    ], $atts);

    // Enqueue frontend assets for this shortcode
    wp_enqueue_style('pm-font');
    wp_enqueue_style('pm-style');
    wp_enqueue_script('pm-ui');

    if (isset($_POST['pm_alert_email']) && isset($_POST['pm_alert_nonce']) && wp_verify_nonce(sanitize_text_field($_POST['pm_alert_nonce']), 'pm_alert')) {
      $email = sanitize_email($_POST['pm_alert_email']);
      if ($email) {
        $pid = wp_insert_post(['post_type'=>'pm_alert','post_title'=>'Alerta '.$email,'post_status'=>'publish']);
        update_post_meta($pid,'email',$email);
        update_post_meta($pid,'type',sanitize_text_field($_POST['pm_alert_type'] ?? $atts['type']));
        if (class_exists('PM_Logger')) { PM_Logger::log('INFO','Alert created',['email'=>$email,'alert_id'=>$pid]); }
        return '<div class="pm-ok">Alerta creada ✅ Te avisaremos si aparece un caso similar.</div>';
      }
      return '<div class="pm-error">Ingresá un email válido.</div>';
    }

    ob_start(); ?>
      <form method="post" class="pm-alert-form">
        <input type="hidden" name="pm_alert_nonce" value="<?php echo esc_attr(wp_create_nonce('pm_alert')); ?>">
        <input type="email" name="pm_alert_email" placeholder="Tu email" required>
        <select name="pm_alert_type">
          <option value="lost" <?php selected($atts['type'],'lost'); ?>>Perdidos</option>
          <option value="adoption" <?php selected($atts['type'],'adoption'); ?>>Adopción</option>
        </select>
        <button type="submit">Crear alerta</button>
      </form>
    <?php return ob_get_clean();
  }

  public static function shortcode_report_sighting($atts = []) : string {
    $atts = shortcode_atts(['case_id'=>0], $atts);

    // Enqueue frontend assets for this shortcode
    wp_enqueue_style('pm-font');
    wp_enqueue_style('pm-style');
    wp_enqueue_script('pm-ui');
    $case_id = intval($atts['case_id']);

    // If used without a specific case (e.g. placed on /buscar), do not render.
    if ($case_id <= 0) {
      return '';
    }


    if (isset($_POST['pm_sighting_msg'], $_POST['pm_sighting_nonce']) && wp_verify_nonce(sanitize_text_field($_POST['pm_sighting_nonce']), 'pm_sighting')) {
      $cid = intval($_POST['case_id']);
      $msg = sanitize_textarea_field($_POST['pm_sighting_msg']);
      $email = sanitize_email($_POST['pm_sighting_email'] ?? '');

      $pid = wp_insert_post(['post_type'=>'pm_sighting','post_title'=>'Avistaje '.$cid,'post_status'=>'publish']);
      update_post_meta($pid,'case_id',$cid);
      update_post_meta($pid,'email',$email);
      update_post_meta($pid,'message',$msg);

      // Notify author + admin
      $author_id = (int) get_post_field('post_author', $cid);
      $author_email = $author_id ? get_the_author_meta('user_email', $author_id) : '';
      $admin_email = get_option('admin_email');

      $subject = 'Nuevo avistaje / información';
      $body = "Caso: ".get_permalink($cid)."\n\nMensaje:\n".$msg."\n\nEmail: ".$email;

      if ($author_email) wp_mail($author_email, $subject, $body);
      if ($admin_email) wp_mail($admin_email, $subject, $body);

      if (class_exists('PM_Logger')) { PM_Logger::log('INFO','Sighting submitted',['case_id'=>$cid,'sighting_id'=>$pid]); }
      return '<div class="pm-ok">Gracias 🙌 Ya enviamos tu info al dueño y al admin.</div>';
    }

    ob_start();
    $wa = get_post_meta($case_id, '_pm_whatsapp', true);
    $wa = preg_replace('/\D+/', '', (string) $wa);
?>
<div class="pm-wa-cta" data-pm-wa="<?php echo esc_attr($wa); ?>">
  <label class="pm-label" for="pm_wa_msg_<?php echo esc_attr($case_id); ?>">¿Dónde lo viste? Contá detalles (zona, hora, referencia, etc.)</label>
  <textarea id="pm_wa_msg_<?php echo esc_attr($case_id); ?>" class="pm-input elementor-field" rows="4" placeholder="Escribí tu mensaje..."></textarea>

  <div class="pm-wa-actions">
    <a class="pm-btn pm-btn--wa pm-wa-btn" href="#" target="_blank" rel="noopener" <?php echo empty($wa) ? 'aria-disabled="true"' : ''; ?>>
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M20.5 11.9a8.5 8.5 0 1 1-15.9 4.1L3 21l5.1-1.6a8.47 8.47 0 0 1-3.1-6.6A8.5 8.5 0 0 1 20.5 11.9Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
        <path d="M8.7 9.4c.2-.4.4-.4.6-.4h.5c.1 0 .3 0 .4.3l.7 1.6c.1.2.1.4 0 .6l-.4.5c-.1.2-.1.3 0 .4.4.7 1 1.4 1.6 1.9.7.6 1.5 1 2.4 1.2.2 0 .3 0 .4-.1l.6-.7c.2-.2.3-.2.6-.1l1.7.8c.2.1.3.3.3.4 0 .6-.2 1.2-.6 1.6-.4.4-1.2.9-2.6.6-1.4-.3-2.9-1-4.3-2.4-1.4-1.3-2.3-2.9-2.6-4.2-.3-1.4.2-2.1.5-2.5Z" fill="currentColor"/>
      </svg>
      Enviar WhatsApp
    </a>
    <?php if (empty($wa)): ?>
      <span class="pm-help">Este caso no tiene WhatsApp cargado todavía.</span>
    <?php else: ?>
      <span class="pm-help">Se abre WhatsApp con tu mensaje listo para enviar ✅</span>
    <?php endif; ?>
  </div>
</div>
<?php
    return ob_get_clean();
}




  private static function render_case_card(int $post_id) : string {
    $title = get_the_title($post_id);
    $url = get_permalink($post_id);
    $thumb = get_the_post_thumbnail_url($post_id, 'medium');
    if (!$thumb) $thumb = 'data:image/svg+xml;charset=utf-8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="600" height="400"><rect width="100%" height="100%" fill="#f3f4f6"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="#9ca3af" font-family="Arial" font-size="22">Sin foto</text></svg>');
    $status = get_post_meta($post_id, '_pm_status', true);
    $badge = $status === 'resolved' ? 'Resuelto' : 'Activo';
    return '<a class="pm-card" href="'.esc_url($url).'">'.
            '<div class="pm-card-img" style="background-image:url('.esc_url($thumb).')"></div>'.
            '<div class="pm-card-body"><div class="pm-badge">'.esc_html($badge).'</div><div class="pm-card-title">'.esc_html($title).'</div></div>'.
           '</a>';
  }



  public static function handle_create_case() : void {
    if (!isset($_POST['pm_action']) || $_POST['pm_action'] !== 'create_case') return;

    $nonce = $_POST['pm_nonce'] ?? '';
    if (!wp_verify_nonce($nonce, 'pm_create_case')) {
      wp_die('Nonce inválido.');
    }

    // Basic validation
    $desc = sanitize_textarea_field($_POST['pm_description'] ?? '');
    $date = sanitize_text_field($_POST['pm_date'] ?? '');
    $lat  = isset($_POST['pm_lat']) ? floatval($_POST['pm_lat']) : null;
    $lng  = isset($_POST['pm_lng']) ? floatval($_POST['pm_lng']) : null;
    $wa_raw = sanitize_text_field($_POST['pm_whatsapp'] ?? '');
    $wa_digits = preg_replace('/\D+/', '', $wa_raw);

    // Extra fields (all optional)
    $pet_name = sanitize_text_field($_POST['pm_pet_name'] ?? '');
    $sex      = sanitize_text_field($_POST['pm_sex'] ?? '');
    $age      = sanitize_text_field($_POST['pm_age'] ?? '');
    $size     = sanitize_text_field($_POST['pm_size'] ?? '');
    $color    = sanitize_text_field($_POST['pm_color'] ?? '');
    $collar   = sanitize_text_field($_POST['pm_collar'] ?? '');
    $neutered = sanitize_text_field($_POST['pm_neutered'] ?? '');

    $type_term = isset($_POST['pm_type']) ? intval($_POST['pm_type']) : 0;
    $species_term = isset($_POST['pm_species']) ? intval($_POST['pm_species']) : 0;
    $zone_term = isset($_POST['pm_zone']) ? intval($_POST['pm_zone']) : 0;

    $has_photo = !empty($_FILES['pm_photos']['name']) && is_array($_FILES['pm_photos']['name']) && !empty(array_filter($_FILES['pm_photos']['name']));
    if (!$desc || !$date || $lat === null || $lng === null || !$type_term || !$species_term || !$zone_term || !$has_photo) {
      wp_die('Faltan datos obligatorios.');
    }

    // Create post
    $title = $pet_name ? $pet_name : wp_trim_words($desc, 8, '…');
    $post_id = wp_insert_post([
      'post_type'   => self::CPT,
      'post_status' => 'publish',
      'post_title'  => $title ?: 'Caso de mascota',
      'post_content'=> $desc,
      'post_author' => get_current_user_id() ?: 0,
    ], true);

    if (is_wp_error($post_id)) {
      wp_die('Error creando el caso: ' . esc_html($post_id->get_error_message()));
    }

    // Attach terms
    wp_set_object_terms($post_id, [$type_term], 'pm_case_type');
    wp_set_object_terms($post_id, [$species_term], 'pm_species');
    wp_set_object_terms($post_id, [$zone_term], 'pm_zone');

    // Save meta
    update_post_meta($post_id, '_pm_lat', $lat);
    update_post_meta($post_id, '_pm_lng', $lng);
    update_post_meta($post_id, '_pm_date', $date);
    update_post_meta($post_id, '_pm_status', 'open');
    if (!empty($wa_digits)) {
      update_post_meta($post_id, '_pm_whatsapp', $wa_digits);
    }

    // Save extra meta if present
    if ($pet_name) update_post_meta($post_id, '_pm_pet_name', $pet_name);
    if ($sex) update_post_meta($post_id, '_pm_sex', $sex);
    if ($age) update_post_meta($post_id, '_pm_age', $age);
    if ($size) update_post_meta($post_id, '_pm_size', $size);
    if ($color) update_post_meta($post_id, '_pm_color', $color);
    if ($collar) update_post_meta($post_id, '_pm_collar', $collar);
    if ($neutered) update_post_meta($post_id, '_pm_neutered', $neutered);

    // Handle photo uploads (multiple) -> featured image + gallery meta
    if (!empty($_FILES['pm_photos']['name']) && is_array($_FILES['pm_photos']['name'])) {
      require_once ABSPATH . 'wp-admin/includes/file.php';
      require_once ABSPATH . 'wp-admin/includes/media.php';
      require_once ABSPATH . 'wp-admin/includes/image.php';

      $uploaded_ids = array();
      $files = $_FILES['pm_photos'];
      $count = count($files['name']);
      for ($i = 0; $i < $count; $i++) {
        if (empty($files['name'][$i])) { continue; }

        // Build a single-file array for media_handle_upload
        $file_array = array(
          'name'     => $files['name'][$i],
          'type'     => $files['type'][$i],
          'tmp_name' => $files['tmp_name'][$i],
          'error'    => $files['error'][$i],
          'size'     => $files['size'][$i],
        );

        $key = 'pm_photo_single';
        $_FILES[$key] = $file_array;
        $attachment_id = media_handle_upload($key, $post_id);
        unset($_FILES[$key]);

        if (!is_wp_error($attachment_id)) {
          $uploaded_ids[] = (int) $attachment_id;
        }
      }

      if (!empty($uploaded_ids)) {
        // First image as featured by default
        set_post_thumbnail($post_id, $uploaded_ids[0]);

        // Persist gallery ids (used by single view)
        update_post_meta($post_id, '_pm_images', array_values(array_unique($uploaded_ids)));
      }
    }

    wp_safe_redirect(get_permalink($post_id));
    exit;
  }

  public static function append_case_meta_to_single(string $content) : string {
    if (!is_singular(self::CPT) || !in_the_loop() || !is_main_query()) return $content;

    $post_id = get_the_ID();
    $lat = get_post_meta($post_id, '_pm_lat', true);
    $lng = get_post_meta($post_id, '_pm_lng', true);
    $date = get_post_meta($post_id, '_pm_date', true);
    $status = get_post_meta($post_id, '_pm_status', true);

    $type = wp_get_post_terms($post_id, 'pm_case_type', ['fields' => 'names']);
    $species = wp_get_post_terms($post_id, 'pm_species', ['fields' => 'names']);
    $zone = wp_get_post_terms($post_id, 'pm_zone', ['fields' => 'names']);

    $badge = '';
    if ($status === 'resolved') $badge = '<span class="pm-badge pm-badge-green">Resuelto</span>';
    elseif ($status === 'contact') $badge = '<span class="pm-badge pm-badge-amber">En contacto</span>';
    else $badge = '<span class="pm-badge pm-badge-blue">Abierto</span>';

    $meta = '<div class="pm-case-meta pm-card">';
    $meta .= '<div class="pm-case-meta-row"><strong>Estado:</strong> '.$badge.'</div>';
    $meta .= '<div class="pm-case-meta-row"><strong>Tipo:</strong> '.esc_html($type[0] ?? '-').'</div>';
    $meta .= '<div class="pm-case-meta-row"><strong>Especie:</strong> '.esc_html($species[0] ?? '-').'</div>';
    $meta .= '<div class="pm-case-meta-row"><strong>Zona:</strong> '.esc_html($zone[0] ?? '-').'</div>';
    $meta .= '<div class="pm-case-meta-row"><strong>Fecha:</strong> '.esc_html($date ?: '-').'</div>';

    // Extra quick facts (optional)
    $pet_name = get_post_meta($post_id, '_pm_pet_name', true);
    $sex      = get_post_meta($post_id, '_pm_sex', true);
    $age      = get_post_meta($post_id, '_pm_age', true);
    $size     = get_post_meta($post_id, '_pm_size', true);
    $color    = get_post_meta($post_id, '_pm_color', true);
    $collar   = get_post_meta($post_id, '_pm_collar', true);
    $neutered = get_post_meta($post_id, '_pm_neutered', true);

    $sex_label = $sex === 'male' ? 'Macho' : ($sex === 'female' ? 'Hembra' : '');
    $age_map = array('baby' => 'Cachorro', 'young' => 'Joven', 'adult' => 'Adulto', 'senior' => 'Senior');
    $size_map = array('xs' => 'Muy chico', 's' => 'Chico', 'm' => 'Mediano', 'l' => 'Grande', 'xl' => 'Muy grande');
    $yn_map = array('yes' => 'Sí', 'no' => 'No');

    if (!empty($pet_name)) $meta .= '<div class="pm-case-meta-row"><strong>Nombre:</strong> '.esc_html($pet_name).'</div>';
    if (!empty($sex_label)) $meta .= '<div class="pm-case-meta-row"><strong>Sexo:</strong> '.esc_html($sex_label).'</div>';
    if (!empty($age) && isset($age_map[$age])) $meta .= '<div class="pm-case-meta-row"><strong>Edad:</strong> '.esc_html($age_map[$age]).'</div>';
    if (!empty($size) && isset($size_map[$size])) $meta .= '<div class="pm-case-meta-row"><strong>Tamaño:</strong> '.esc_html($size_map[$size]).'</div>';
    if (!empty($color)) $meta .= '<div class="pm-case-meta-row"><strong>Color:</strong> '.esc_html($color).'</div>';
    if (!empty($collar) && isset($yn_map[$collar])) $meta .= '<div class="pm-case-meta-row"><strong>Collar:</strong> '.esc_html($yn_map[$collar]).'</div>';
    if (!empty($neutered) && isset($yn_map[$neutered])) $meta .= '<div class="pm-case-meta-row"><strong>Castrado/a:</strong> '.esc_html($yn_map[$neutered]).'</div>';

    $meta .= '</div>';

    // Lightweight map preview for single (optional)
    $map = '';
    if ($lat && $lng) {
      // enqueue map assets for single view
      wp_enqueue_style('pm-leaflet');
      wp_enqueue_script('pm-leaflet');
      wp_enqueue_script('pm-map');
      wp_enqueue_style('pm-style');

      wp_localize_script('pm-map', 'PM_MAP', [
        'single' => true,
        'singleLat' => floatval($lat),
        'singleLng' => floatval($lng),
        'defaultZoom' => 15,
        'i18n' => ['geoFail' => ''],
      ]);

      $map = '<div class="pm-field"><label>Ubicación aproximada</label><div id="pm-map-single" class="pm-map"></div></div>';
    }

    $report = '';
    $report .= '<div class="pm-case-report pm-card">'
      . '<div class="pm-case-report-head">¿Lo viste? Ayudá reportando un avistaje 👀</div>'
      . '<button type="button" class="pm-btn pm-btn-primary" data-pm-toggle="pm-sighting">Reportar avistaje</button>'
      . '<div class="pm-case-report-panel" data-pm-panel="pm-sighting" hidden>'
        . do_shortcode('[pm_report_sighting case_id="'.intval($post_id).'"]')
      . '</div>'
    . '</div>';

    $imgs = get_post_meta($post_id, '_pm_images', true);
    if (!is_array($imgs)) { $imgs = array(); }
    $imgs = array_values(array_unique(array_filter(array_map('intval', $imgs))));
    $main_id = (int) get_post_thumbnail_id($post_id);
    if (!$main_id && !empty($imgs)) { $main_id = (int) $imgs[0]; }

    $gallery = '';
    if ($main_id) {
      $main_img = wp_get_attachment_image($main_id, 'large', false, array('loading' => 'lazy'));
      $thumbs = '';
      $count = 0;
      foreach ($imgs as $tid) {
        if ($tid === $main_id) { continue; }
        $thumbs .= wp_get_attachment_image($tid, 'thumbnail', false, array('loading' => 'lazy'));
        $count++;
        if ($count >= 8) { break; }
      }
      $thumbs_html = $thumbs ? '<div class="pm-case-gallery-thumbs">' . $thumbs . '</div>' : '';
      $gallery = '<div class="pm-case-gallery"><div class="pm-case-gallery-main">' . $main_img . '</div>' . $thumbs_html . '</div>';
    } else {
      $gallery = '<div class="pm-case-gallery"><div class="pm-case-gallery-main" style="padding:22px;">Sin imágenes cargadas.</div></div>';
    }

    $hero = '<div class="pm-case-wrap"><div class="pm-case-hero">' . $gallery . $meta . '</div></div>';

    return $content . $hero . $map . $report;
  }

  
  
  public static function notify_alerts(int $post_id) : void {
    if (get_post_type($post_id) !== self::CPT) return;

    // avoid loops on autosave/revisions
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) return;

    $type = wp_get_post_terms($post_id, 'pm_case_type', ['fields' => 'slugs'])[0] ?? '';
    $zone_id = wp_get_post_terms($post_id, 'pm_zone', ['fields' => 'ids'])[0] ?? 0;

    $alerts = get_posts([
      'post_type' => 'pm_alert',
      'posts_per_page' => -1,
      'post_status' => 'publish',
      'fields' => 'ids',
    ]);

    foreach ($alerts as $aid) {
      $email = sanitize_email(get_post_meta($aid, 'email', true));
      if (!$email) continue;

      $atype = sanitize_text_field(get_post_meta($aid, 'type', true)); // lost|adoption
      $azone = intval(get_post_meta($aid, 'zone', true));

      $match = false;
      if ($atype === 'lost' && $type === 'lost') $match = true;
      if ($atype === 'adoption' && $type === 'adoption') $match = true;

      if (!$match) continue;
      if ($azone && $zone_id && $azone !== $zone_id) continue;

      $subject = 'Nueva coincidencia en PetMatch';
      $body = "Se publicó un nuevo caso que coincide con tu alerta:\n" . get_permalink($post_id);
      wp_mail($email, $subject, $body);
    }

    if (class_exists('PM_Logger')) { PM_Logger::log('INFO', 'Alerts notified', ['case_id'=>$post_id, 'alerts'=>count($alerts)]); }
  }

public static function pm_resolve_case() : void {
    if (!isset($_POST['pm_resolve_nonce'], $_POST['case_id'])) return;
    if (!wp_verify_nonce(sanitize_text_field($_POST['pm_resolve_nonce']), 'pm_resolve')) return;

    $id = intval($_POST['case_id']);
    if (!$id || get_post_type($id) !== self::CPT) return;

    // Only author or admin can resolve
    $author_id = (int) get_post_field('post_author', $id);
    $current = get_current_user_id();
    if ($current !== $author_id && !current_user_can('manage_options')) return;

    update_post_meta($id, '_pm_status', 'resolved');
    if (class_exists('PM_Logger')) { PM_Logger::log('INFO', 'Case resolved', ['case_id'=>$id, 'by'=>$current]); }
  }

public static function register_admin_pages() : void {
    add_menu_page(
      'Pet Match',
      'Pet Match',
      'manage_options',
      'pet-match',
      [__CLASS__, 'render_logs_page'],
      'dashicons-pets',
      56
    );
    add_submenu_page(
      'pet-match',
      'Logs',
      'Logs',
      'manage_options',
      'pet-match',
      [__CLASS__, 'render_logs_page']
    );
  }

  public static function render_logs_page() : void {
    if (!current_user_can('manage_options')) return;

    if (isset($_POST['pm_log_action']) && check_admin_referer('pm_logs')) {
      $action = sanitize_text_field($_POST['pm_log_action']);
      if ($action === 'clear' && class_exists('PM_Logger')) {
        PM_Logger::clear();
        PM_Logger::log('INFO', 'Logs cleared by admin');
      }
      if ($action === 'toggle' && class_exists('PM_Logger')) {
        $enabled = PM_Logger::enabled() ? '0' : '1';
        update_option(PM_Logger::OPTION_ENABLED, $enabled);
        PM_Logger::log('INFO', 'Logging toggled', ['enabled' => $enabled]);
      }
    }

    $enabled = class_exists('PM_Logger') ? (PM_Logger::enabled() ? 'Sí' : 'No') : 'No';
    $tail = class_exists('PM_Logger') ? PM_Logger::read_tail(600) : '';
    $path = class_exists('PM_Logger') ? PM_Logger::path() : '';
    ?>
    <div class="wrap">
      <h1>Pet Match — Logs</h1>
      <p><strong>Logging activo:</strong> <?php echo esc_html($enabled); ?></p>
      <p><strong>Archivo:</strong> <code><?php echo esc_html($path); ?></code></p>

      <form method="post" style="margin: 12px 0;">
        <?php wp_nonce_field('pm_logs'); ?>
        <button class="button button-secondary" name="pm_log_action" value="toggle" type="submit">
          Activar / Desactivar logging
        </button>
        <button class="button button-secondary" name="pm_log_action" value="clear" type="submit" style="margin-left:8px;">
          Vaciar logs
        </button>
      </form>

      <textarea readonly style="width:100%;min-height:420px;font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;"><?php echo esc_textarea($tail); ?></textarea>

      <p style="margin-top:10px;color:#666;">
        Tip: si vuelve a salir “error crítico”, recargá esta página y copiame las últimas líneas.
      </p>
    </div>
    <?php
  }


  // -------------------------------
  // Admin Menu + Screens (v0.5.0)
  // -------------------------------
  public static function register_admin_menu() : void {
    add_menu_page(
      'Pet Match',
      'Pet Match',
      'manage_options',
      'pet-match',
      [__CLASS__, 'render_admin_dashboard'],
      'dashicons-pets',
      56
    );

    add_submenu_page('pet-match','Dashboard','Dashboard','manage_options','pet-match',[__CLASS__,'render_admin_dashboard']);
    add_submenu_page('pet-match','Casos','Casos','manage_options','pet-match-cases',[__CLASS__,'render_admin_cases']);
    add_submenu_page('pet-match','Avistajes','Avistajes','manage_options','pet-match-sightings',[__CLASS__,'render_admin_sightings']);
    add_submenu_page('pet-match','Alertas','Alertas','manage_options','pet-match-alerts',[__CLASS__,'render_admin_alerts']);
    add_submenu_page('pet-match','Ajustes','Ajustes','manage_options','pet-match-settings',[__CLASS__,'render_admin_settings']);
    add_submenu_page('pet-match','Logs','Logs','manage_options','pet-match-logs',[__CLASS__,'render_logs_page']);

    // Hidden pages (no menu item)
    add_submenu_page(null,'Editar caso','Editar caso','manage_options','pet-match-edit-case',[__CLASS__,'render_admin_case_edit']);
  }

  public static function highlight_admin_menu(string $parent_file) : string {
    // Keep Pet Match menu selected on child pages
    if (!is_admin()) return $parent_file;
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ($screen && isset($_GET['page']) && is_string($_GET['page']) && (strpos($_GET['page'], 'pet-match') === 0)) {
      return 'pet-match';
    }
    return $parent_file;
  }

  public static function enqueue_admin_assets(string $hook) : void {
    if (empty($_GET['page']) || !is_string($_GET['page'])) return;
    if (strpos($_GET['page'], 'pet-match') !== 0) return;

    wp_enqueue_style('pm-admin', plugins_url('assets/css/pm-admin.css', __FILE__), [], self::VERSION);
  }

  private static function admin_header(string $title, string $subtitle = '') : void {
    echo '<div class="wrap pm-admin">';
    echo '<div class="pm-admin-head">';
    echo '<div class="pm-admin-title"><h1>'.esc_html($title).'</h1>';
    if ($subtitle) echo '<p class="description">'.esc_html($subtitle).'</p>';
    echo '</div>';
    echo '</div>';
  }

  private static function admin_footer() : void {
    echo '</div>';
  }

  private static function count_posts(string $post_type, array $args = []) : int {
    $q = new WP_Query(array_merge([
      'post_type' => $post_type,
      'post_status' => ['publish','draft','pending','private'],
      'posts_per_page' => 1,
      'fields' => 'ids',
    ], $args));
    return intval($q->found_posts);
  }

  public static function render_admin_dashboard() : void {
    if (!current_user_can('manage_options')) return;

    self::admin_header('Pet Match', 'Dashboard general del plugin');

    $cases_total = self::count_posts(self::CPT);
    $lost_total  = self::count_posts(self::CPT, ['tax_query'=>[['taxonomy'=>'pm_case_type','field'=>'slug','terms'=>['perdi','lost','perdido','perdidos']]]]);
    $adopt_total = self::count_posts(self::CPT, ['tax_query'=>[['taxonomy'=>'pm_case_type','field'=>'slug','terms'=>['adopcion','adopt','adoptar']]]]);

    $sightings_total = self::count_posts('pm_sighting', ['post_status'=>['publish']]);
    $alerts_total    = self::count_posts('pm_alert', ['post_status'=>['publish']]);

    echo '<div class="pm-admin-grid">';
      echo '<a class="pm-admin-card" href="'.esc_url(admin_url('admin.php?page=pet-match-cases')).'"><div class="pm-admin-card-kpi">'.esc_html($cases_total).'</div><div class="pm-admin-card-label">Casos</div><div class="pm-admin-card-desc">Gestioná perdidos y adopción</div></a>';
      echo '<a class="pm-admin-card" href="'.esc_url(admin_url('admin.php?page=pet-match-sightings')).'"><div class="pm-admin-card-kpi">'.esc_html($sightings_total).'</div><div class="pm-admin-card-label">Avistajes</div><div class="pm-admin-card-desc">Reportes enviados por usuarios</div></a>';
      echo '<a class="pm-admin-card" href="'.esc_url(admin_url('admin.php?page=pet-match-alerts')).'"><div class="pm-admin-card-kpi">'.esc_html($alerts_total).'</div><div class="pm-admin-card-label">Alertas</div><div class="pm-admin-card-desc">Suscripciones por email</div></a>';
      echo '<div class="pm-admin-card pm-admin-card-soft"><div class="pm-admin-card-kpi">'.esc_html($lost_total).'</div><div class="pm-admin-card-label">Perdidos</div><div class="pm-admin-card-desc">Casos de búsqueda</div></div>';
      echo '<div class="pm-admin-card pm-admin-card-soft"><div class="pm-admin-card-kpi">'.esc_html($adopt_total).'</div><div class="pm-admin-card-label">Adopción</div><div class="pm-admin-card-desc">Publicaciones para adoptar</div></div>';
    echo '</div>';

    echo '<div class="pm-admin-panels">';
      echo '<div class="pm-admin-panel">';
        echo '<h2>Acciones rápidas</h2>';
        echo '<div class="pm-admin-actions">';
          echo '<a class="button button-primary" href="'.esc_url(admin_url('admin.php?page=pet-match-cases')).'">Ver casos</a> ';
          echo '<a class="button" href="'.esc_url(admin_url('admin.php?page=pet-match-settings')).'">Ajustes</a> ';
          echo '<a class="button" href="'.esc_url(admin_url('admin.php?page=pet-match-logs')).'">Logs</a>';
        echo '</div>';
      echo '</div>';

      echo '<div class="pm-admin-panel">';
        echo '<h2>Tips de operación</h2>';
        echo '<ul class="pm-admin-list">';
          echo '<li>Usá <b>Casos</b> para editar, resolver o revisar publicaciones.</li>';
          echo '<li>Los <b>Avistajes</b> son reportes que llegan al autor y al admin (copia).</li>';
          echo '<li>Si hay un error en el front, revisá <b>Logs</b> y pegá el stack trace.</li>';
        echo '</ul>';
      echo '</div>';
    echo '</div>';

    self::admin_footer();
  }

  public static function render_admin_cases() : void {
    if (!current_user_can('manage_options')) return;

    $q = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
    $status = isset($_GET['post_status']) ? sanitize_text_field($_GET['post_status']) : '';
    $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';

    $args = [
      'post_type' => self::CPT,
      'post_status' => $status ? [$status] : ['publish','draft','pending','private'],
      'posts_per_page' => 30,
      'paged' => max(1, intval($_GET['paged'] ?? 1)),
      's' => $q ?: '',
      'orderby' => 'date',
      'order' => 'DESC',
    ];

    if ($type) {
      $args['tax_query'] = [[
        'taxonomy' => 'pm_case_type',
        'field' => 'slug',
        'terms' => [$type],
      ]];
    }

    $query = new WP_Query($args);

    self::admin_header('Casos', 'Listado centralizado (incluye borradores).');

    echo '<form method="get" class="pm-admin-filters">';
      echo '<input type="hidden" name="page" value="pet-match-cases" />';

      echo '<input class="pm-admin-input" type="text" name="q" value="'.esc_attr($q).'" placeholder="Buscar por título o descripción…" />';

      echo '<select class="pm-admin-select" name="post_status">';
        $opts = [
          '' => 'Todos los estados',
          'publish' => 'Publicados',
          'draft' => 'Borradores',
          'pending' => 'Pendientes',
          'private' => 'Privados',
        ];
        foreach ($opts as $k=>$label) {
          echo '<option value="'.esc_attr($k).'" '.selected($status,$k,false).'>'.esc_html($label).'</option>';
        }
      echo '</select>';

      echo '<select class="pm-admin-select" name="type">';
        echo '<option value="">Todos los tipos</option>';
        $terms = get_terms(['taxonomy'=>'pm_case_type','hide_empty'=>false]);
        if (!is_wp_error($terms)) {
          foreach ($terms as $t) {
            echo '<option value="'.esc_attr($t->slug).'" '.selected($type,$t->slug,false).'>'.esc_html($t->name).'</option>';
          }
        }
      echo '</select>';

      echo '<button class="button button-primary" type="submit">Filtrar</button>';
      echo '<a class="button" href="'.esc_url(admin_url('admin.php?page=pet-match-cases')).'">Reset</a>';
    echo '</form>';

    echo '<div class="pm-admin-table-wrap"><table class="widefat fixed striped pm-admin-table">';
      echo '<thead><tr>';
        echo '<th style="width:64px;">Foto</th>';
        echo '<th>Título</th>';
        echo '<th style="width:140px;">Tipo</th>';
        echo '<th style="width:140px;">Zona</th>';
        echo '<th style="width:120px;">Estado</th>';
        echo '<th style="width:160px;">Fecha</th>';
        echo '<th style="width:160px;">Acciones</th>';
      echo '</tr></thead><tbody>';

      if ($query->have_posts()) {
        while ($query->have_posts()) {
          $query->the_post();
          $pid = get_the_ID();

          $thumb = get_the_post_thumbnail_url($pid,'thumbnail');
          $thumb_html = $thumb ? '<img class="pm-admin-thumb" src="'.esc_url($thumb).'" alt="" />' : '<div class="pm-admin-thumb pm-admin-thumb-empty">—</div>';

          $type_terms = get_the_terms($pid,'pm_case_type');
          $type_name = (!is_wp_error($type_terms) && !empty($type_terms)) ? $type_terms[0]->name : '—';

          $zone_terms = get_the_terms($pid,'pm_zone');
          $zone_name = (!is_wp_error($zone_terms) && !empty($zone_terms)) ? $zone_terms[0]->name : '—';

          $pm_status = get_post_meta($pid,'_pm_status',true);
          $pm_status = $pm_status ?: 'open';

          $edit_url = admin_url('admin.php?page=pet-match-edit-case&case_id='.$pid);
          $view_url = get_permalink($pid);

          echo '<tr>';
            echo '<td>'.$thumb_html.'</td>';
            echo '<td><b>'.esc_html(get_the_title()).'</b><div class="pm-admin-muted">'.esc_html(wp_trim_words(get_the_content(),16,'…')).'</div></td>';
            echo '<td><span class="pm-admin-pill">'.esc_html($type_name).'</span></td>';
            echo '<td>'.esc_html($zone_name).'</td>';
            echo '<td><span class="pm-admin-status pm-admin-status-'.esc_attr($pm_status).'">'.esc_html($pm_status).'</span></td>';
            echo '<td>'.esc_html(get_the_date('Y-m-d H:i')).'</td>';
            echo '<td class="pm-admin-actions-col">';
              echo '<a class="button button-small" href="'.esc_url($edit_url).'">Editar</a> ';
              echo '<a class="button button-small" href="'.esc_url($view_url).'" target="_blank" rel="noopener">Ver</a> ';

              // Delete: move to Trash
              $trash_url = wp_nonce_url(
                admin_url('admin-post.php?action=pm_case_action&case_id='.$pid.'&do=trash'),
                'pm_case_action'
              );
              echo '<a class="button button-small" style="color:#b32d2e;border-color:#b32d2e" '
                . 'href="'.esc_url($trash_url).'" '
                . 'onclick="return confirm(\'¿Mover este caso a la papelera?\');">Eliminar</a>';
            echo '</td>';
          echo '</tr>';
        }
        wp_reset_postdata();
      } else {
        echo '<tr><td colspan="7"><div class="pm-admin-empty">No hay casos con esos filtros.</div></td></tr>';
      }

    echo '</tbody></table></div>';

    // Pagination
    $total_pages = intval($query->max_num_pages);
    $current = max(1, intval($_GET['paged'] ?? 1));
    if ($total_pages > 1) {
      $base = add_query_arg(array_merge($_GET, ['paged' => '%#%']), admin_url('admin.php'));
      echo '<div class="pm-admin-pagination">';
      echo paginate_links([
        'base' => $base,
        'format' => '',
        'current' => $current,
        'total' => $total_pages,
        'prev_text' => '‹',
        'next_text' => '›',
      ]);
      echo '</div>';
    }

    self::admin_footer();
  }

  public static function render_admin_case_edit() : void {
    if (!current_user_can('manage_options')) return;

    $pid = isset($_GET['case_id']) ? intval($_GET['case_id']) : 0;
    if (!$pid || get_post_type($pid) !== self::CPT) {
      self::admin_header('Editar caso', 'Caso inválido.');
      echo '<div class="pm-admin-empty">No se encontró el caso.</div>';
      self::admin_footer();
      return;
    }

    // Handle save
    if (isset($_POST['pm_admin_save_case']) && check_admin_referer('pm_admin_save_case')) {
      $title = sanitize_text_field($_POST['post_title'] ?? '');
      $desc  = sanitize_textarea_field($_POST['post_content'] ?? '');

      wp_update_post([
        'ID' => $pid,
        'post_title' => $title ?: get_the_title($pid),
        'post_content' => $desc,
      ]);

      $type_term = intval($_POST['pm_type'] ?? 0);
      $species_term = intval($_POST['pm_species'] ?? 0);
      $zone_term = intval($_POST['pm_zone'] ?? 0);

      if ($type_term) wp_set_object_terms($pid, [$type_term], 'pm_case_type');
      if ($species_term) wp_set_object_terms($pid, [$species_term], 'pm_species');
      if ($zone_term) wp_set_object_terms($pid, [$zone_term], 'pm_zone');

      update_post_meta($pid,'_pm_status', sanitize_text_field($_POST['pm_status'] ?? 'open'));
      update_post_meta($pid,'_pm_date', sanitize_text_field($_POST['pm_date'] ?? ''));
      update_post_meta($pid,'_pm_lat', isset($_POST['pm_lat']) ? floatval($_POST['pm_lat']) : '');
      update_post_meta($pid,'_pm_lng', isset($_POST['pm_lng']) ? floatval($_POST['pm_lng']) : '');

      echo '<div class="notice notice-success is-dismissible"><p>Guardado ✅</p></div>';
    }

    $post = get_post($pid);
    $pm_status = get_post_meta($pid,'_pm_status',true) ?: 'open';
    $pm_date = get_post_meta($pid,'_pm_date',true) ?: '';
    $pm_lat = get_post_meta($pid,'_pm_lat',true);
    $pm_lng = get_post_meta($pid,'_pm_lng',true);

    self::admin_header('Editar caso', 'Edición centralizada del caso.');

    echo '<div class="pm-admin-split">';
      echo '<div class="pm-admin-panel">';
        echo '<form method="post">';
          wp_nonce_field('pm_admin_save_case');
          echo '<input type="hidden" name="pm_admin_save_case" value="1" />';

          echo '<label class="pm-admin-label">Título</label>';
          echo '<input class="pm-admin-input full" type="text" name="post_title" value="'.esc_attr($post->post_title).'" />';

          echo '<label class="pm-admin-label">Descripción</label>';
          echo '<textarea class="pm-admin-textarea" name="post_content" rows="7">'.esc_textarea($post->post_content).'</textarea>';

          echo '<div class="pm-admin-row">';
            echo '<div>';
              echo '<label class="pm-admin-label">Tipo</label>';
              echo self::admin_terms_select('pm_case_type','pm_type',$pid);
            echo '</div>';
            echo '<div>';
              echo '<label class="pm-admin-label">Especie</label>';
              echo self::admin_terms_select('pm_species','pm_species',$pid);
            echo '</div>';
            echo '<div>';
              echo '<label class="pm-admin-label">Zona</label>';
              echo self::admin_terms_select('pm_zone','pm_zone',$pid);
            echo '</div>';
          echo '</div>';

          echo '<div class="pm-admin-row">';
            echo '<div>';
              echo '<label class="pm-admin-label">Estado</label>';
              echo '<select class="pm-admin-select full" name="pm_status">';
                foreach (['open'=>'open','resolved'=>'resolved','closed'=>'closed'] as $k=>$lbl) {
                  echo '<option value="'.esc_attr($k).'" '.selected($pm_status,$k,false).'>'.esc_html($lbl).'</option>';
                }
              echo '</select>';
            echo '</div>';
            echo '<div>';
              echo '<label class="pm-admin-label">Fecha</label>';
              echo '<input class="pm-admin-input full" type="date" name="pm_date" value="'.esc_attr($pm_date).'" />';
            echo '</div>';
          echo '</div>';

          echo '<div class="pm-admin-row">';
            echo '<div>';
              echo '<label class="pm-admin-label">Lat</label>';
              echo '<input class="pm-admin-input full" type="text" name="pm_lat" value="'.esc_attr($pm_lat).'" />';
            echo '</div>';
            echo '<div>';
              echo '<label class="pm-admin-label">Lng</label>';
              echo '<input class="pm-admin-input full" type="text" name="pm_lng" value="'.esc_attr($pm_lng).'" />';
            echo '</div>';
          echo '</div>';

          echo '<div class="pm-admin-actions">';
            echo '<button class="button button-primary" type="submit">Guardar cambios</button> ';
            echo '<a class="button" href="'.esc_url(admin_url('admin.php?page=pet-match-cases')).'">Volver</a> ';
            echo '<a class="button" target="_blank" rel="noopener" href="'.esc_url(get_permalink($pid)).'">Ver en front</a>';

            // Delete: move to Trash
            $trash_url = wp_nonce_url(
              admin_url('admin-post.php?action=pm_case_action&case_id='.$pid.'&do=trash'),
              'pm_case_action'
            );
            echo ' <a class="button" style="color:#b32d2e;border-color:#b32d2e" '
              . 'href="'.esc_url($trash_url).'" '
              . 'onclick="return confirm(\'¿Mover este caso a la papelera?\');">Eliminar caso</a>';
          echo '</div>';
        echo '</form>';
      echo '</div>';

      echo '<div class="pm-admin-panel">';
        echo '<h2>Imágenes</h2>';
        $imgs = get_post_meta($pid, '_pm_images', true);
        if (!is_array($imgs)) { $imgs = []; }
        $thumb = get_the_post_thumbnail_url($pid,'medium');
        if ($thumb) {
          echo '<div class="pm-admin-gallery">';
          echo '<img class="pm-admin-preview" src="'.esc_url($thumb).'" alt="" />';
          foreach ($imgs as $aid) {
            $aid = absint($aid);
            if (!$aid) { continue; }
            $url = wp_get_attachment_image_url($aid, 'medium');
            if ($url) {
              echo '<img class="pm-admin-preview" src="'.esc_url($url).'" alt="" />';
            }
          }
          echo '</div>';
        } else {
          if (!empty($imgs)) {
            echo '<div class="pm-admin-gallery">';
            foreach ($imgs as $aid) {
              $aid = absint($aid);
              if (!$aid) { continue; }
              $url = wp_get_attachment_image_url($aid, 'medium');
              if ($url) {
                echo '<img class="pm-admin-preview" src="'.esc_url($url).'" alt="" />';
              }
            }
            echo '</div>';
          } else {
            echo '<div class="pm-admin-empty">Sin imágenes.</div>';
          }
        }
        $wa = get_post_meta($pid, '_pm_whatsapp', true);
        if ($wa) {
          echo '<p><strong>WhatsApp:</strong> '.esc_html($wa).'</p>';
        }      echo '</div>';
    echo '</div>';

    self::admin_footer();
  }

  private static function admin_terms_select(string $taxonomy, string $field_name, int $post_id) : string {
    $terms = get_terms(['taxonomy'=>$taxonomy,'hide_empty'=>false]);
    if (is_wp_error($terms)) return '<select class="pm-admin-select full" name="'.esc_attr($field_name).'"><option value="">—</option></select>';

    $selected = 0;
    $current = get_the_terms($post_id, $taxonomy);
    if (!is_wp_error($current) && !empty($current)) $selected = intval($current[0]->term_id);

    $html = '<select class="pm-admin-select full" name="'.esc_attr($field_name).'">';
    $html .= '<option value="">—</option>';
    foreach ($terms as $t) {
      $html .= '<option value="'.esc_attr($t->term_id).'" '.selected($selected, intval($t->term_id), false).'>'.esc_html($t->name).'</option>';
    }
    $html .= '</select>';
    return $html;
  }

  public static function render_admin_sightings() : void {
    if (!current_user_can('manage_options')) return;

    $query = new WP_Query([
      'post_type' => 'pm_sighting',
      'post_status' => ['publish'],
      'posts_per_page' => 30,
      'orderby' => 'date',
      'order' => 'DESC',
    ]);

    self::admin_header('Avistajes', 'Reportes recientes de usuarios.');

    echo '<div class="pm-admin-table-wrap"><table class="widefat fixed striped pm-admin-table">';
      echo '<thead><tr>';
        echo '<th style="width:90px;">Fecha</th>';
        echo '<th>Mensaje</th>';
        echo '<th style="width:220px;">Email</th>';
        echo '<th style="width:160px;">Caso</th>';
      echo '</tr></thead><tbody>';

      if ($query->have_posts()) {
        while ($query->have_posts()) {
          $query->the_post();
          $pid = get_the_ID();
          $email = get_post_meta($pid,'email',true);
          $case_id = intval(get_post_meta($pid,'case_id',true));
          $case_link = $case_id ? '<a href="'.esc_url(admin_url('admin.php?page=pet-match-edit-case&case_id='.$case_id)).'">'.esc_html(get_the_title($case_id)).'</a>' : '—';

          echo '<tr>';
            echo '<td>'.esc_html(get_the_date('Y-m-d')).'</td>';
            echo '<td><b>'.esc_html(get_the_title()).'</b><div class="pm-admin-muted">'.esc_html(wp_trim_words(get_the_content(),22,'…')).'</div></td>';
            echo '<td>'.esc_html($email ?: '—').'</td>';
            echo '<td>'.$case_link.'</td>';
          echo '</tr>';
        }
        wp_reset_postdata();
      } else {
        echo '<tr><td colspan="4"><div class="pm-admin-empty">Todavía no hay avistajes.</div></td></tr>';
      }

    echo '</tbody></table></div>';

    self::admin_footer();
  }

  public static function render_admin_alerts() : void {
    if (!current_user_can('manage_options')) return;

    $query = new WP_Query([
      'post_type' => 'pm_alert',
      'post_status' => ['publish'],
      'posts_per_page' => 50,
      'orderby' => 'date',
      'order' => 'DESC',
    ]);

    self::admin_header('Alertas', 'Emails suscriptos para recibir avisos.');

    echo '<div class="pm-admin-table-wrap"><table class="widefat fixed striped pm-admin-table">';
      echo '<thead><tr>';
        echo '<th style="width:90px;">Fecha</th>';
        echo '<th style="width:260px;">Email</th>';
        echo '<th style="width:120px;">Tipo</th>';
        echo '<th>Notas</th>';
      echo '</tr></thead><tbody>';

      if ($query->have_posts()) {
        while ($query->have_posts()) {
          $query->the_post();
          $pid = get_the_ID();
          $email = get_post_meta($pid,'email',true);
          $type = get_post_meta($pid,'type',true);

          echo '<tr>';
            echo '<td>'.esc_html(get_the_date('Y-m-d')).'</td>';
            echo '<td><b>'.esc_html($email ?: get_the_title()).'</b></td>';
            echo '<td><span class="pm-admin-pill">'.esc_html($type ?: '—').'</span></td>';
            echo '<td class="pm-admin-muted">—</td>';
          echo '</tr>';
        }
        wp_reset_postdata();
      } else {
        echo '<tr><td colspan="4"><div class="pm-admin-empty">Todavía no hay alertas.</div></td></tr>';
      }

    echo '</tbody></table></div>';

    self::admin_footer();
  }

  public static function render_admin_settings() : void {
    if (!current_user_can('manage_options')) return;

    $opt_key = 'pm_settings';
    $defaults = [
      'admin_email' => get_option('admin_email'),
      'admin_whatsapp' => '',
      'default_radius_km' => 5,
      'require_login_create' => 0,
    ];

    $settings = get_option($opt_key, []);
    if (!is_array($settings)) $settings = [];
    $settings = array_merge($defaults, $settings);

    if (isset($_POST['pm_save_settings']) && check_admin_referer('pm_save_settings')) {
      $settings['admin_email'] = sanitize_email($_POST['admin_email'] ?? $settings['admin_email']);
      $settings['admin_whatsapp'] = sanitize_text_field($_POST['admin_whatsapp'] ?? '');
      $settings['default_radius_km'] = max(1, intval($_POST['default_radius_km'] ?? 5));
      $settings['require_login_create'] = isset($_POST['require_login_create']) ? 1 : 0;

      update_option($opt_key, $settings, false);
      echo '<div class="notice notice-success is-dismissible"><p>Ajustes guardados ✅</p></div>';
    }

    self::admin_header('Ajustes', 'Configuración del plugin.');

    echo '<form method="post" class="pm-admin-panel pm-admin-form">';
      wp_nonce_field('pm_save_settings');
      echo '<input type="hidden" name="pm_save_settings" value="1" />';

      echo '<div class="pm-admin-row">';
        echo '<div>';
          echo '<label class="pm-admin-label">Email admin (copias)</label>';
          echo '<input class="pm-admin-input full" type="email" name="admin_email" value="'.esc_attr($settings['admin_email']).'" />';
        echo '</div>';
        echo '<div>';
          echo '<label class="pm-admin-label">WhatsApp admin (opcional)</label>';
          echo '<input class="pm-admin-input full" type="text" name="admin_whatsapp" value="'.esc_attr($settings['admin_whatsapp']).'" placeholder="+54 9 ..." />';
        echo '</div>';
      echo '</div>';

      echo '<div class="pm-admin-row">';
        echo '<div>';
          echo '<label class="pm-admin-label">Radio de búsqueda por defecto (km)</label>';
          echo '<input class="pm-admin-input full" type="number" min="1" max="200" name="default_radius_km" value="'.esc_attr($settings['default_radius_km']).'" />';
        echo '</div>';
        echo '<div class="pm-admin-check">';
          echo '<label><input type="checkbox" name="require_login_create" '.checked($settings['require_login_create'],1,false).' /> Requerir login para publicar casos</label>';
          echo '<div class="pm-admin-muted">Por ahora el form público permite publicar sin login. Esto prepara el switch.</div>';
        echo '</div>';
      echo '</div>';

      echo '<div class="pm-admin-actions">';
        echo '<button class="button button-primary" type="submit">Guardar</button>';
      echo '</div>';
    echo '</form>';

    self::admin_footer();
  }




  public static function pm_should_auto_publish_case() : bool {
      // For now: only verified shelters auto-publish
      if (!is_user_logged_in()) return false;
      $uid = get_current_user_id();
      $is_shelter = get_user_meta($uid, 'pm_is_shelter', true);
      $is_verified = get_user_meta($uid, 'pm_shelter_verified', true);
      return ($is_shelter === '1' || $is_shelter === 1 || $is_shelter === true) && ($is_verified === '1' || $is_verified === 1 || $is_verified === true);
  }


  public static function pm_sync_case_author_meta($post_id, $post, $update){
      if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
      if (!$post_id || !$post) return;
      if ($post->post_type !== self::CPT) return;
  
      $author_id = (int) $post->post_author;
      if (!$author_id) return;
  
      $is_shelter = get_user_meta($author_id, 'pm_is_shelter', true);
      $is_verified = get_user_meta($author_id, 'pm_shelter_verified', true);
  
      $val = ((($is_shelter==='1'||$is_shelter===1||$is_shelter===true) && ($is_verified==='1'||$is_verified===1||$is_verified===true)) ? '1' : '0');
      update_post_meta($post_id, 'pm_author_shelter_verified', $val);
  }


  public static function pm_handle_admin_action(){
      if (!current_user_can('manage_options')) {
        wp_die('No autorizado');
      }
      check_admin_referer('pm_case_action');
  
      $case_id = isset($_GET['case_id']) ? (int) $_GET['case_id'] : 0;
      $do = isset($_GET['do']) ? sanitize_key($_GET['do']) : '';
  
      if (!$case_id || get_post_type($case_id) !== self::CPT) {
        wp_safe_redirect(admin_url('admin.php?page=pet-match-cases'));
        exit;
      }
  
      if ($do === 'resolve') {
        update_post_meta($case_id, 'pm_case_status', 'resolved');
      } elseif ($do === 'unresolve') {
        delete_post_meta($case_id, 'pm_case_status');
      } elseif ($do === 'feature') {
        update_post_meta($case_id, 'pm_featured', '1');
      } elseif ($do === 'unfeature') {
        delete_post_meta($case_id, 'pm_featured');
      } elseif ($do === 'publish') {
        wp_update_post(['ID' => $case_id, 'post_status' => 'publish']);
      } elseif ($do === 'draft') {
        wp_update_post(['ID' => $case_id, 'post_status' => 'draft']);
      } elseif ($do === 'approve') {
        wp_update_post(['ID' => $case_id, 'post_status' => 'publish']);
      } elseif ($do === 'reject') {
        wp_update_post(['ID' => $case_id, 'post_status' => 'draft']);
      } elseif ($do === 'trash') {
        // Safer than permanent delete
        if (current_user_can('delete_post', $case_id)) {
          wp_trash_post($case_id);
        }
      }
  
      // Sync cached author meta for ordering
      $p = get_post($case_id);
      if ($p) {
        self::pm_sync_case_author_meta($case_id, $p, true);
      }
  
      $back = wp_get_referer();
      wp_safe_redirect($back ? $back : admin_url('admin.php?page=pet-match-cases'));
      exit;
  }


  public static function pm_handle_shelter_action(){
      if (!current_user_can('manage_options')) {
        wp_die('No autorizado');
      }
      check_admin_referer('pm_shelter_action');
  
      $user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
      $do = isset($_GET['do']) ? sanitize_key($_GET['do']) : '';
  
      if (!$user_id) {
        wp_safe_redirect(admin_url('admin.php?page=pet-match-shelters'));
        exit;
      }
  
      if ($do === 'verify') {
        update_user_meta($user_id, 'pm_shelter_verified', '1');
      } elseif ($do === 'unverify') {
        delete_user_meta($user_id, 'pm_shelter_verified');
      }
  
      // Resync all cases by this author
      $q = new WP_Query([
        'post_type' => self::CPT,
        'post_status' => ['publish','pending','draft'],
        'posts_per_page' => -1,
        'author' => $user_id,
        'fields' => 'ids',
      ]);
      if ($q->have_posts()) {
        foreach ($q->posts as $pid) {
          $p = get_post($pid);
          if ($p) { self::pm_sync_case_author_meta($pid, $p, true); }
        }
      }
  
      $back = wp_get_referer();
      wp_safe_redirect($back ? $back : admin_url('admin.php?page=pet-match-shelters'));
      exit;
  }


  public static function render_admin_shelters(){
      if (!current_user_can('manage_options')) {
        wp_die('No autorizado');
      }
  
      echo '<div class="wrap pm-admin">';
      echo '<h1>Refugios</h1>';
      echo '<p class="description">Administrá usuarios que se registraron como refugio y su estado de verificación.</p>';
  
      $args = [
        'meta_key' => 'pm_is_shelter',
        'meta_value' => '1',
        'number' => 200,
        'orderby' => 'registered',
        'order' => 'DESC',
        'fields' => ['ID','user_login','user_email','display_name','user_registered'],
      ];
      $users = get_users($args);
  
      echo '<table class="widefat striped">';
      echo '<thead><tr><th>Usuario</th><th>Email</th><th>Nombre</th><th>Registrado</th><th>Verificado</th><th>Acciones</th></tr></thead><tbody>';
  
      foreach ($users as $u) {
        $verified = get_user_meta($u->ID, 'pm_shelter_verified', true);
        $is_verified = ($verified === '1' || $verified === 1 || $verified === true);
  
        $url_verify = wp_nonce_url(admin_url('admin-post.php?action=pm_shelter_action&do=verify&user_id='.$u->ID), 'pm_shelter_action');
        $url_unverify = wp_nonce_url(admin_url('admin-post.php?action=pm_shelter_action&do=unverify&user_id='.$u->ID), 'pm_shelter_action');
  
        echo '<tr>';
        echo '<td>'.esc_html($u->user_login).'</td>';
        echo '<td>'.esc_html($u->user_email).'</td>';
        echo '<td>'.esc_html($u->display_name).'</td>';
        echo '<td>'.esc_html($u->user_registered).'</td>';
        echo '<td>'.($is_verified ? '✅ Sí' : '—').'</td>';
        echo '<td>';
        if ($is_verified) {
          echo '<a class="button" href="'.esc_url($url_unverify).'">Quitar verificación</a>';
        } else {
          echo '<a class="button button-primary" href="'.esc_url($url_verify).'">Verificar</a>';
        }
        echo '</td>';
        echo '</tr>';
      }
  
      if (empty($users)) {
        echo '<tr><td colspan="6">No hay refugios registrados aún.</td></tr>';
      }
  
      echo '</tbody></table>';
      echo '</div>';
  }
}

// Activation hooks (flush rewrite rules, etc.)
register_activation_hook(__FILE__, ['PM_Pet_Match', 'activate']);
register_deactivation_hook(__FILE__, ['PM_Pet_Match', 'deactivate']);

try { PM_Pet_Match::init(); } catch (\Throwable $e) { if (class_exists('PM_Logger')) { PM_Logger::log('FATAL', 'Init exception: '.$e->getMessage(), ['file'=>$e->getFile(),'line'=>$e->getLine()]); } }