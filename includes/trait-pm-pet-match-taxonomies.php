<?php
trait PM_Pet_Match_Taxonomies_Trait {
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
  $taxonomy = self::get_case_taxonomy('type');
  if ( ! function_exists( 'taxonomy_exists' ) || $taxonomy === '' || ! taxonomy_exists( $taxonomy ) ) {
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
    $tid = self::ensure_term_with_slug( $taxonomy, $t['name'], $t['slug'] );
    if ( ! $tid ) {
      $maybe = term_exists( $t['slug'], $taxonomy );
      if ( ! $maybe ) {
        wp_insert_term( $t['name'], $taxonomy, [ 'slug' => $t['slug'] ] );
      }
    }

    foreach ( (array) $t['legacy_slugs'] as $legacy ) {
      if ( $legacy === $t['slug'] ) { continue; }

      $legacy_term = term_exists( $legacy, $taxonomy );
      $new_term    = term_exists( $t['slug'], $taxonomy );

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
              'taxonomy' => $taxonomy,
              'field'    => 'term_id',
              'terms'    => [ $legacy_id ],
            ],
          ],
        ] );

        foreach ( $posts as $pid ) {
          wp_set_object_terms( $pid, [ $new_id ], $taxonomy, false );
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
    return self::get_case_type_slugs($type);
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
}
