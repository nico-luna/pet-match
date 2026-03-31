<?php
trait PM_Pet_Match_Frontend_Trait {
  public static function shortcode_create_case($atts = []) : string {
    static $rendered = false;
    if ($rendered) {
      return '';
    }
    $rendered = true;

    $atts = shortcode_atts([
      'mode' => '',
      'hide_type' => '0',
    ], $atts, 'pm_create_case');

    if (!self::can_current_user_create_case()) {
      self::log_blocked_create_attempt('shortcode_create_case');
      wp_enqueue_style('pm-font');
      wp_enqueue_style('pm-style');
      return self::render_login_required_notice();
    }

    wp_enqueue_style('pm-leaflet');
    wp_enqueue_script('pm-leaflet');
    wp_enqueue_script('pm-map');
    wp_enqueue_style('pm-font');
    wp_enqueue_style('pm-style');

    self::localize_create_map_config();

    $nonce = wp_create_nonce('pm_create_case');
    $types = self::get_terms_for_taxonomy(self::get_case_taxonomy('type'));
    $species = self::get_terms_for_taxonomy(self::get_case_taxonomy('species'));
    $zones = self::get_terms_for_taxonomy(self::get_case_taxonomy('zone'));
    $mode = sanitize_text_field((string) $atts['mode']);
    $hide_type = ($atts['hide_type'] === '1');
    $default_type_term_id = self::get_case_type_term_id_by_slug($mode);
    $can_hide_type = $hide_type && !empty($mode) && !empty($default_type_term_id);
    $success_notice = self::get_request_feedback_notice('pm_notice');

    ob_start(); ?>
    <div class="pm-wrap pm-elementor pm-app pm-page-create">
      <?php echo $success_notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
      <form method="post" enctype="multipart/form-data" class="pm-form pm-create-form-shell" aria-label="Formulario para publicar un caso">
        <input type="hidden" name="pm_action" value="create_case">
        <input type="hidden" name="pm_nonce" value="<?php echo esc_attr($nonce); ?>">

        <div class="pm-form-intro">
          <span class="pm-section-kicker">Publicar un caso</span>
          <h2 class="pm-section-title">Carga la informacion esencial para que otras personas puedan ayudarte</h2>
          <p class="pm-section-lead">Cuanto mas clara sea la descripcion, la zona y la foto, mas facil va a ser encontrar coincidencias utiles.</p>
        </div>

        <fieldset class="pm-form-section">
          <legend>Datos principales</legend>
          <div class="pm-grid pm-grid--three">
          <?php if (!$can_hide_type): ?>
            <div class="pm-field">
              <label for="pm_type">Tipo</label>
              <select id="pm_type" name="pm_type" required class="pm-input elementor-field">
                <option value="">Seleccionar...</option>
                <?php foreach ($types as $term): ?>
                  <option value="<?php echo esc_attr($term->term_id); ?>" <?php selected($default_type_term_id ?: '', $term->term_id); ?>>
                    <?php echo esc_html($term->name); ?>
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
              <option value="">Seleccionar...</option>
              <?php foreach ($species as $term): ?>
                <option value="<?php echo esc_attr($term->term_id); ?>"><?php echo esc_html($term->name); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="pm-field">
            <label for="pm_zone">Zona o barrio</label>
            <select id="pm_zone" name="pm_zone" required class="pm-input elementor-field">
              <option value="">Seleccionar...</option>
              <?php foreach ($zones as $term): ?>
                <option value="<?php echo esc_attr($term->term_id); ?>"><?php echo esc_html($term->name); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="pm-field">
            <label for="pm_date">Fecha aproximada</label>
            <input id="pm_date" name="pm_date" type="date" required class="pm-input elementor-field">
          </div>
        </div>
        </fieldset>

        <fieldset class="pm-form-section">
          <legend>Descripcion y contacto</legend>
          <div class="pm-field">
            <label for="pm_description">Descripcion</label>
            <textarea id="pm_description" name="pm_description" required rows="4" class="pm-input elementor-field" placeholder="Ej: Perro mediano negro con collar rojo, visto por ultima vez cerca de Plaza Italia."></textarea>
            <small class="pm-help">Incluye referencias visuales, comportamiento, horario aproximado y cualquier detalle que ayude a reconocerlo.</small>
          </div>

          <div class="pm-grid pm-grid--three">
          <div class="pm-field">
            <label for="pm_pet_name">Nombre (si lo sabes)</label>
            <input id="pm_pet_name" name="pm_pet_name" type="text" class="pm-input elementor-field" placeholder="Ej: Luna">
          </div>

          <div class="pm-field">
            <label for="pm_whatsapp">WhatsApp</label>
            <input id="pm_whatsapp" name="pm_whatsapp" type="text" class="pm-input elementor-field" placeholder="Ej: 5491161234567" aria-describedby="pm-whatsapp-help">
            <small id="pm-whatsapp-help" class="pm-help">Se usara para que puedan escribirte si tienen informacion.</small>
          </div>

          <div class="pm-field">
            <label for="pm_sex">Sexo</label>
            <select id="pm_sex" name="pm_sex" class="pm-input elementor-field">
              <option value="">Seleccionar...</option>
              <option value="male">Macho</option>
              <option value="female">Hembra</option>
            </select>
          </div>

          <div class="pm-field">
            <label for="pm_age">Edad</label>
            <select id="pm_age" name="pm_age" class="pm-input elementor-field">
              <option value="">Seleccionar...</option>
              <option value="baby">Cachorro</option>
              <option value="young">Joven</option>
              <option value="adult">Adulto</option>
              <option value="senior">Senior</option>
            </select>
          </div>

          <div class="pm-field">
            <label for="pm_size">Tamano</label>
            <select id="pm_size" name="pm_size" class="pm-input elementor-field">
              <option value="">Seleccionar...</option>
              <option value="xs">Muy chico</option>
              <option value="s">Chico</option>
              <option value="m">Mediano</option>
              <option value="l">Grande</option>
              <option value="xl">Muy grande</option>
            </select>
          </div>

          <div class="pm-field">
            <label for="pm_color">Color</label>
            <input id="pm_color" name="pm_color" type="text" class="pm-input elementor-field" placeholder="Ej: Negro con pecho blanco">
          </div>
          </div>
        </fieldset>

        <fieldset class="pm-form-section">
          <legend>Senas particulares</legend>
          <div class="pm-grid pm-grid--three">
          <div class="pm-field">
            <label for="pm_collar">Tiene collar?</label>
            <select id="pm_collar" name="pm_collar" class="pm-input elementor-field">
              <option value="">Seleccionar...</option>
              <option value="yes">Si</option>
              <option value="no">No</option>
            </select>
          </div>

          <div class="pm-field">
            <label for="pm_neutered">Esta castrado/a?</label>
            <select id="pm_neutered" name="pm_neutered" class="pm-input elementor-field">
              <option value="">Seleccionar...</option>
              <option value="yes">Si</option>
              <option value="no">No</option>
            </select>
          </div>
        </div>
        </fieldset>

        <fieldset class="pm-form-section">
          <legend>Fotos y ubicacion</legend>
          <div class="pm-grid pm-grid--media">
            <div class="pm-field pm-field--media">
              <label for="pm_photos">Fotos (minimo 1)</label>
              <input id="pm_photos" name="pm_photos[]" type="file" accept="image/jpeg,image/png,image/gif,image/webp" multiple required class="pm-input elementor-field" aria-describedby="pm-photos-help">
              <small id="pm-photos-help" class="pm-help">Podes subir JPG, PNG, GIF o WEBP. Al menos una imagen valida es obligatoria.</small>
              <div class="pm-inline-note">Elige fotos donde se vea bien la cara, el cuerpo y cualquier rasgo facil de identificar.</div>
            </div>

            <div class="pm-field pm-field--map">
              <label>Ubicacion aproximada</label>
              <div id="pm-map" class="pm-map"></div>
              <div class="pm-map-actions">
                <button type="button" class="pm-btn pm-btn-secondary" id="pm_use_my_location">Usar mi ubicacion</button>
              </div>
              <small class="pm-help">Hace click en el mapa o move el pin para marcar el punto aproximado.</small>
              <input type="hidden" name="pm_lat" id="pm_lat" value="">
              <input type="hidden" name="pm_lng" id="pm_lng" value="">
            </div>
          </div>
        </fieldset>

        <div class="pm-actions pm-actions--submit">
          <button type="submit" class="pm-btn pm-btn-primary">Publicar caso</button>
          <p class="pm-actions-help">Antes de enviar, revisa que la zona, la fecha y el contacto esten completos.</p>
        </div>
      </form>
    </div>
    <?php
    return (string) ob_get_clean();
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
      <div class="pm-home-hero">
        <span class="pm-section-kicker">Pet Match</span>
        <h1 class="pm-title">Una sola puerta de entrada para buscar, publicar y seguir cada caso</h1>
        <p class="pm-home-lead">Elige la accion principal segun lo que necesites hoy. Las alertas y los avistajes aparecen dentro del buscador y de cada ficha individual.</p>
      </div>
      <div class="pm-home-grid">
        <a class="pm-home-card pm-home-card--search" href="<?php echo esc_url($atts['search_url']); ?>">
          <div class="pm-home-card-top">
            <div class="pm-home-card-title">Estoy buscando</div>
            <div class="pm-home-card-desc">Perdi a mi mascota o quiero adoptar.</div>
          </div>
          <div class="pm-home-card-hover" aria-hidden="true">
            <div class="pm-home-card-cta">Ir al buscador -></div>
            <div class="pm-home-card-hint">Filtra por texto, estado y tipo para encontrar un caso mas rapido.</div>
          </div>
        </a>

        <a class="pm-home-card pm-home-card--publish" href="<?php echo esc_url($atts['publish_url']); ?>">
          <div class="pm-home-card-top">
            <div class="pm-home-card-title">Quiero publicar</div>
            <div class="pm-home-card-desc">Encontre una mascota, perdi la mia o quiero dar en adopcion.</div>
          </div>
          <div class="pm-home-card-hover" aria-hidden="true">
            <div class="pm-home-card-cta">Cargar un caso -></div>
            <div class="pm-home-card-hint">Foto, descripcion y ubicacion aproximada. En un minuto lo publicas.</div>
          </div>
        </a>
      </div>
      <div class="pm-home-support">
        <div class="pm-home-support-item">
          <strong>Alertas por email</strong>
          <span>Activalas desde la busqueda para recibir nuevos casos relevantes.</span>
        </div>
        <div class="pm-home-support-item">
          <strong>Avistajes</strong>
          <span>Reportalos desde cada caso con zona, horario y referencias claras.</span>
        </div>
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
      $title = ($type === 'adoption') ? 'Ultimas adopciones' : 'Ultimos perdidos';
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
        <div class="pm-slider-copy">
          <span class="pm-section-kicker">Casos recientes</span>
          <h3 class="pm-slider-title"><?php echo esc_html($title); ?></h3>
        </div>
        <div class="pm-slider-controls">
          <button class="pm-slider-btn" type="button" data-dir="prev" aria-label="Anterior">&lt;</button>
          <button class="pm-slider-btn" type="button" data-dir="next" aria-label="Siguiente">&gt;</button>
        </div>
      </div>
      <div class="pm-slider-track" role="list" tabindex="0">
        <?php if ($q->have_posts()): while ($q->have_posts()): $q->the_post(); ?>
          <?php echo self::render_case_card(get_the_ID()); ?>
        <?php endwhile; wp_reset_postdata(); else: ?>
          <div class="pm-empty pm-empty-card">
            <div class="pm-empty-title">Todavia no hay casos para mostrar</div>
            <div class="pm-empty-desc">Todavia no hay publicaciones en esta seccion. Podes cargar la primera o empezar buscando por zona.</div>
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
      'limit' => 24,
      'default_radius' => 10,
      'show_map' => 1,
    ], $atts);

    wp_enqueue_style('pm-font');
    wp_enqueue_style('pm-style');
    wp_enqueue_script('pm-ui');
    if ((int) $atts['show_map'] === 1) {
      wp_enqueue_style('pm-leaflet');
      wp_enqueue_script('pm-leaflet');
      wp_enqueue_script('pm-search-map');
    }

    $type = isset($_GET['pm_type']) ? sanitize_text_field($_GET['pm_type']) : '';
    $status = isset($_GET['pm_status']) ? self::normalize_case_status((string) $_GET['pm_status']) : '';
    $allowed_status = self::get_valid_case_statuses();
    if (!in_array($status, $allowed_status, true)) {
      $status = '';
    }
    $s = isset($_GET['pm_q']) ? sanitize_text_field($_GET['pm_q']) : '';
    $radius = self::get_valid_radius_km($_GET['pm_radius'] ?? $atts['default_radius'], (int) $atts['default_radius']);
    $lat = isset($_GET['pm_lat']) ? self::get_valid_coordinate(wp_unslash((string) $_GET['pm_lat'])) : null;
    $lng = isset($_GET['pm_lng']) ? self::get_valid_coordinate(wp_unslash((string) $_GET['pm_lng'])) : null;
    $has_coords = ($lat !== null && $lng !== null && $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180);
    $geo_requested = isset($_GET['pm_lat']) || isset($_GET['pm_lng']) || isset($_GET['pm_radius']);
    $geo_error = '';

    if ($geo_requested && !$has_coords && (((string) ($_GET['pm_lat'] ?? '')) !== '' || ((string) ($_GET['pm_lng'] ?? '')) !== '')) {
      $geo_error = 'La ubicacion actual ya no es valida. Revisa latitud y longitud o elegi un punto en el mapa.';
    }

    $args = [
      'post_type' => self::CPT,
      'post_status' => ['publish'],
      'posts_per_page' => $has_coords ? -1 : max(1, intval($atts['limit'])),
      's' => $s,
      'orderby' => 'date',
      'order' => 'DESC',
    ];
    if ($type) {
      $args['tax_query'] = [[
        'taxonomy' => self::get_case_taxonomy('type'),
        'field' => 'slug',
        'terms' => self::normalize_case_type_terms($type),
      ]];
    }
    if ($status) {
      $args['meta_query'] = [[
        'key' => '_pm_status',
        'value' => self::get_case_status_meta_values($status),
        'compare' => 'IN',
      ]];
    }

    if ($has_coords) {
      $box = self::get_bounding_box((float) $lat, (float) $lng, $radius);
      $args['meta_query'] = isset($args['meta_query']) ? $args['meta_query'] : [];
      $args['meta_query'][] = [
        'key' => '_pm_lat',
        'value' => [$box['min_lat'], $box['max_lat']],
        'compare' => 'BETWEEN',
        'type' => 'NUMERIC',
      ];
      $args['meta_query'][] = [
        'key' => '_pm_lng',
        'value' => [$box['min_lng'], $box['max_lng']],
        'compare' => 'BETWEEN',
        'type' => 'NUMERIC',
      ];
    }

    $q = new \WP_Query($args);
    $results = [];
    $search_map_cases = [];

    if ($has_coords) {
      foreach ($q->posts as $post) {
        $post_id = (int) $post->ID;
        $coords = self::get_valid_case_coordinates($post_id);
        if (!$coords) {
          continue;
        }

        $distance = self::calculate_distance_km((float) $lat, (float) $lng, (float) $coords['lat'], (float) $coords['lng']);
        if ($distance > $radius) {
          continue;
        }

        $results[] = [
          'post_id' => $post_id,
          'distance' => $distance,
          'lat' => (float) $coords['lat'],
          'lng' => (float) $coords['lng'],
        ];
      }

      usort($results, static function(array $left, array $right) : int {
        if ($left['distance'] === $right['distance']) {
          return $right['post_id'] <=> $left['post_id'];
        }
        return $left['distance'] <=> $right['distance'];
      });

      $results = array_slice($results, 0, max(1, intval($atts['limit'])));
      foreach ($results as $item) {
        $search_map_cases[] = [
          'title' => get_the_title((int) $item['post_id']),
          'url' => get_permalink((int) $item['post_id']),
          'lat' => $item['lat'],
          'lng' => $item['lng'],
          'distance' => round((float) $item['distance'], 2),
        ];
      }
    } else {
      foreach ($q->posts as $post) {
        $post_id = (int) $post->ID;
        $coords = self::get_valid_case_coordinates($post_id);
        $results[] = [
          'post_id' => $post_id,
          'distance' => null,
          'lat' => $coords['lat'] ?? null,
          'lng' => $coords['lng'] ?? null,
        ];
        if ($coords) {
          $search_map_cases[] = [
            'title' => get_the_title($post_id),
            'url' => get_permalink($post_id),
            'lat' => (float) $coords['lat'],
            'lng' => (float) $coords['lng'],
          ];
        }
      }
    }

    ob_start(); ?>
    <section class="pm-search pm-app">
      <div class="pm-search-head">
        <div class="pm-search-copy">
          <span class="pm-section-kicker">Buscador</span>
          <h1 class="pm-title">Encuentra casos por tipo, estado, zona o cercania</h1>
          <p class="pm-search-lead">Puedes empezar con una palabra clave, filtrar por estado o usar el mapa para enfocarte en un area concreta.</p>
        </div>
      </div>
      <?php if ($geo_error !== ''): ?>
        <div class="pm-error"><?php echo esc_html($geo_error); ?></div>
      <?php elseif ($has_coords): ?>
        <div class="pm-callout">Mostrando <?php echo esc_html((string) count($results)); ?> casos dentro de <?php echo esc_html((string) $radius); ?> km del punto seleccionado.</div>
      <?php endif; ?>

      <form method="get" class="pm-search-form pm-searchbar">
        <div class="pm-field">
          <input class="pm-input" type="text" name="pm_q" value="<?php echo esc_attr($s); ?>" placeholder="Buscar por palabra clave (ej: caniche, negro, collar rojo)">
        </div>
        <?php if (intval($atts['show_types']) === 1): ?>
          <select class="pm-select" name="pm_type">
            <option value="">Todos</option>
            <option value="lost" <?php selected($type, 'lost'); ?>>Perdidos</option>
            <option value="found" <?php selected($type, 'found'); ?>>Encontrados</option>
            <option value="adoption" <?php selected($type, 'adoption'); ?>>Adopcion</option>
          </select>
        <?php endif; ?>
        <input class="pm-input pm-search-coord" type="number" step="0.000001" name="pm_lat" value="<?php echo esc_attr($has_coords ? (string) $lat : ''); ?>" placeholder="Latitud">
        <input class="pm-input pm-search-coord" type="number" step="0.000001" name="pm_lng" value="<?php echo esc_attr($has_coords ? (string) $lng : ''); ?>" placeholder="Longitud">
        <input class="pm-input pm-search-radius" type="number" min="1" max="200" name="pm_radius" value="<?php echo esc_attr((string) $radius); ?>" placeholder="Radio km">
        <button class="pm-btn-icon" type="submit" aria-label="Buscar">
          <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" stroke="currentColor" stroke-width="2"/>
            <path d="M21 21l-4.3-4.3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </button>
        <a class="pm-btn" href="<?php echo esc_url(get_permalink()); ?>">Limpiar</a>
      </form>

      <?php if ((int) $atts['show_map'] === 1): ?>
        <div class="pm-search-geo">
          <div>
            <div class="pm-search-map" data-center-lat="<?php echo esc_attr($has_coords ? (string) $lat : ''); ?>" data-center-lng="<?php echo esc_attr($has_coords ? (string) $lng : ''); ?>" data-radius-km="<?php echo esc_attr((string) $radius); ?>" data-cases="<?php echo esc_attr(wp_json_encode($search_map_cases)); ?>"></div>
            <p class="pm-help">Podes escribir latitud y longitud manualmente o hacer click en el mapa para elegir el centro de busqueda.</p>
          </div>
        </div>
      <?php endif; ?>

      <?php
        $base_args = array();
        if ($s) { $base_args['pm_q'] = $s; }
        if ($type) { $base_args['pm_type'] = $type; }
        if ($has_coords) {
          $base_args['pm_lat'] = $lat;
          $base_args['pm_lng'] = $lng;
          $base_args['pm_radius'] = $radius;
        }
        $current = $status ?: 'all';
        $items = array(
          'all' => array('label' => 'Todos', 'value' => ''),
        );
        foreach (self::get_valid_case_statuses() as $status_key) {
          $items[$status_key] = array(
            'label' => self::get_case_status_label($status_key, 'filter_label'),
            'value' => $status_key,
          );
        }
      ?>
      <div class="pm-status-filters" role="navigation" aria-label="Filtrar por estado">
        <?php foreach ($items as $k => $it):
          $filter_args = $base_args;
          if (!empty($it['value'])) { $filter_args['pm_status'] = $it['value']; }
          $url = add_query_arg($filter_args, get_permalink());
          $cls = 'pm-status-badge' . ($current === $k ? ' is-active' : '');
        ?>
          <a class="<?php echo esc_attr($cls); ?>" href="<?php echo esc_url($url); ?>">
            <?php echo esc_html($it['label']); ?>
          </a>
        <?php endforeach; ?>
      </div>

      <div class="pm-search-summary">
        <div class="pm-search-summary-copy">
          <strong><?php echo esc_html((string) count($results)); ?> resultados</strong>
          <span><?php echo $has_coords ? 'Ordenados por cercania al punto elegido.' : 'Ordenados por fecha de publicacion.'; ?></span>
        </div>
      </div>

      <div class="pm-cards-grid">
        <?php if (!empty($results)): foreach ($results as $item): ?>
          <div class="pm-search-card-wrap">
            <?php echo self::render_case_card((int) $item['post_id']); ?>
            <?php if ($has_coords && $item['distance'] !== null): ?>
              <div class="pm-search-distance">A <?php echo esc_html(number_format((float) $item['distance'], 1, ',', '.')); ?> km del punto elegido</div>
            <?php endif; ?>
          </div>
        <?php endforeach; else: ?>
          <div class="pm-empty pm-empty-search">
            <div class="pm-empty-title">No encontramos casos con esos filtros</div>
            <div class="pm-empty-desc">Prueba con menos palabras, cambia el estado o amplia el radio si estas buscando por cercania.</div>
            <div class="pm-empty-actions">
              <a class="pm-btn pm-btn-primary" href="<?php echo esc_url(get_permalink()); ?>">Limpiar busqueda</a>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </section>
    <?php return ob_get_clean();
  }
  public static function shortcode_metrics() : string {
    wp_enqueue_style('pm-font');
    wp_enqueue_style('pm-style');
    wp_enqueue_script('pm-ui');
    $q = new \WP_Query([
      'post_type'      => self::CPT,
      'post_status'    => ['publish'],
      'meta_query'     => [[
        'key'     => '_pm_status',
        'value'   => self::get_case_status_meta_values('resolved'),
        'compare' => 'IN',
      ]],
      'posts_per_page' => -1
    ]);
    return '<div class="pm-metrics">'.intval($q->found_posts).' mascotas reunidas o adoptadas</div>';
  }

  public static function shortcode_create_alert($atts = []) : string {
    $atts = shortcode_atts([
      'type' => 'lost',
    ], $atts);

    wp_enqueue_style('pm-font');
    wp_enqueue_style('pm-style');
    wp_enqueue_script('pm-ui');

    if (isset($_POST['pm_alert_email']) || isset($_POST['pm_alert_nonce'])) {
      $nonce = isset($_POST['pm_alert_nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['pm_alert_nonce'])) : '';
      if (!$nonce || !wp_verify_nonce($nonce, 'pm_alert')) {
        self::log_validation_failure('alert_create', 'invalid_nonce');
        return self::render_feedback_notice('No pudimos validar el formulario. Actualiza la pagina y volve a intentarlo.');
      }

      $email = sanitize_email(wp_unslash((string) ($_POST['pm_alert_email'] ?? '')));
      $type = self::normalize_alert_type((string) wp_unslash((string) ($_POST['pm_alert_type'] ?? $atts['type'])));
      $species_id = self::get_valid_term_id((int) ($_POST['pm_alert_species'] ?? 0), self::get_case_taxonomy('species'));
      $zone_id = self::get_valid_term_id((int) ($_POST['pm_alert_zone'] ?? 0), self::get_case_taxonomy('zone'));

      if (!$email || !is_email($email)) {
        self::log_validation_failure('alert_create', 'invalid_email');
        return self::render_feedback_notice('Ingresa un email valido para poder avisarte cuando aparezca un caso similar.');
      }

      if ($type === '') {
        self::log_validation_failure('alert_create', 'invalid_type');
        return self::render_feedback_notice('El tipo de alerta elegido no es valido. Revisalo y volve a intentar.');
      }

      $pid = wp_insert_post([
        'post_type' => 'pm_alert',
        'post_title' => 'Alerta ' . $email,
        'post_status' => 'publish'
      ], true);

      if (is_wp_error($pid)) {
        self::log_validation_failure('alert_create', 'insert_failed', ['error' => $pid->get_error_code()]);
        return self::render_feedback_notice('No pudimos crear la alerta en este momento. Proba nuevamente en unos minutos.');
      }

      update_post_meta($pid, 'email', $email);
      update_post_meta($pid, 'type', $type);
      if ($species_id > 0) {
        update_post_meta($pid, 'species_id', $species_id);
      }
      if ($zone_id > 0) {
        update_post_meta($pid, 'zone_id', $zone_id);
      }
      self::log_event('INFO', 'alert.create.success', 'Alert created', [
        'alert_id' => $pid,
        'type' => $type,
        'species_id' => $species_id,
        'zone_id' => $zone_id,
      ]);
      return self::render_feedback_notice('Alerta creada. Te vamos a avisar por email si aparece un caso que coincida con esos criterios.', 'success');
    }

    $types = self::get_alert_type_options();
    $species_terms = self::get_terms_for_taxonomy(self::get_case_taxonomy('species'));
    $zone_terms = self::get_terms_for_taxonomy(self::get_case_taxonomy('zone'));

    ob_start(); ?>
      <section class="pm-secondary-panel pm-secondary-panel--alert pm-app">
      <form method="post" class="pm-alert-form" aria-label="Crear alerta por email">
        <input type="hidden" name="pm_alert_nonce" value="<?php echo esc_attr(wp_create_nonce('pm_alert')); ?>">
        <div class="pm-form-intro pm-form-intro--compact">
          <span class="pm-section-kicker">Alertas por email</span>
          <h2 class="pm-section-title">Recibe avisos cuando aparezca un caso parecido</h2>
          <p class="pm-section-lead">Puedes dejar la alerta abierta solo para un tipo o afinarla con especie y zona.</p>
        </div>
        <div class="pm-grid pm-grid--two">
        <div class="pm-field">
          <label for="pm_alert_email">Email</label>
          <input id="pm_alert_email" type="email" name="pm_alert_email" placeholder="Tu email" required autocomplete="email">
        </div>
        <div class="pm-field">
          <label for="pm_alert_type">Tipo de alerta</label>
          <select id="pm_alert_type" name="pm_alert_type">
            <?php foreach ($types as $value => $label): ?>
              <option value="<?php echo esc_attr($value); ?>" <?php selected($atts['type'], $value); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="pm-field">
          <label for="pm_alert_species">Especie</label>
          <select id="pm_alert_species" name="pm_alert_species">
            <option value="">Cualquier especie</option>
            <?php foreach ($species_terms as $term): ?>
              <option value="<?php echo esc_attr($term->term_id); ?>"><?php echo esc_html($term->name); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="pm-field">
          <label for="pm_alert_zone">Zona</label>
          <select id="pm_alert_zone" name="pm_alert_zone">
            <option value="">Cualquier zona</option>
            <?php foreach ($zone_terms as $term): ?>
              <option value="<?php echo esc_attr($term->term_id); ?>"><?php echo esc_html($term->name); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        </div>
        <div class="pm-actions pm-actions--submit">
          <button type="submit" class="pm-btn pm-btn-primary">Crear alerta</button>
          <p class="pm-actions-help">Te avisaremos solo cuando haya coincidencias relevantes para esos criterios.</p>
        </div>
      </form>
      </section>
    <?php return ob_get_clean();
  }

  public static function shortcode_report_sighting($atts = []) : string {
    $atts = shortcode_atts(['case_id' => 0], $atts);

    wp_enqueue_style('pm-font');
    wp_enqueue_style('pm-style');
    wp_enqueue_script('pm-ui');
    $case_id = intval($atts['case_id']);

    if ($case_id <= 0) {
      return '';
    }

    if (isset($_POST['pm_sighting_msg']) || isset($_POST['pm_sighting_nonce'])) {
      $nonce = isset($_POST['pm_sighting_nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['pm_sighting_nonce'])) : '';
      if (!$nonce || !wp_verify_nonce($nonce, 'pm_sighting')) {
        self::log_validation_failure('sighting_create', 'invalid_nonce');
        return self::render_feedback_notice('No pudimos validar el formulario. Actualiza la pagina y volve a intentarlo.');
      }

      $cid = isset($_POST['case_id']) ? intval($_POST['case_id']) : 0;
      $msg = trim(sanitize_textarea_field(wp_unslash((string) ($_POST['pm_sighting_msg'] ?? ''))));
      $email = sanitize_email(wp_unslash((string) ($_POST['pm_sighting_email'] ?? '')));

      if ($cid !== $case_id || $cid <= 0 || get_post_type($cid) !== self::CPT) {
        self::log_validation_failure('sighting_create', 'invalid_case', ['case_id' => $cid]);
        return self::render_feedback_notice('No encontramos el caso que intentas reportar. Revisa el enlace y proba nuevamente.');
      }

      $msg_length = function_exists('mb_strlen') ? mb_strlen($msg) : strlen($msg);
      if ($msg === '' || $msg_length < 10) {
        self::log_validation_failure('sighting_create', 'message_too_short', ['case_id' => $cid]);
        return self::render_feedback_notice('Contanos al menos algunos detalles del avistaje para poder ayudar.');
      }

      if ($email !== '' && !is_email($email)) {
        self::log_validation_failure('sighting_create', 'invalid_email', ['case_id' => $cid]);
        return self::render_feedback_notice('Ingresa un email valido o deja ese campo vacio si preferis no compartirlo.');
      }

      $pid = wp_insert_post([
        'post_type' => 'pm_sighting',
        'post_title' => 'Avistaje ' . $cid,
        'post_status' => 'publish',
      ], true);

      if (is_wp_error($pid)) {
        self::log_validation_failure('sighting_create', 'insert_failed', ['case_id' => $cid, 'error' => $pid->get_error_code()]);
        return self::render_feedback_notice('No pudimos registrar el avistaje en este momento. Proba nuevamente en unos minutos.');
      }

      update_post_meta($pid, 'case_id', $cid);
      if ($email !== '') {
        update_post_meta($pid, 'email', $email);
      }
      update_post_meta($pid, 'message', $msg);

      $author_id = (int) get_post_field('post_author', $cid);
      $author_email = $author_id ? sanitize_email((string) get_the_author_meta('user_email', $author_id)) : '';
      $admin_email = self::get_notification_admin_email();

      $subject = 'Nuevo avistaje / informacion';
      $body = "Caso: " . get_permalink($cid) . "\n\nMensaje:\n" . $msg;
      if ($email !== '') {
        $body .= "\n\nEmail: " . $email;
      }

      if ($author_email && is_email($author_email)) {
        wp_mail($author_email, $subject, $body);
      }
      if ($admin_email) {
        wp_mail($admin_email, $subject, $body);
      }

      self::log_event('INFO', 'sighting.create.success', 'Sighting submitted', [
        'case_id' => $cid,
        'sighting_id' => $pid,
        'notified_author' => ($author_email && is_email($author_email)) ? 1 : 0,
        'notified_admin' => $admin_email ? 1 : 0,
      ]);
      return self::render_feedback_notice('Gracias. Ya enviamos tu informacion al responsable del caso para que pueda revisarla.', 'success');
    }

    ob_start();
    $case_whatsapp = self::get_case_meta($case_id, 'whatsapp');
    $admin_whatsapp = self::get_admin_whatsapp();
    $wa = $case_whatsapp !== '' ? $case_whatsapp : $admin_whatsapp;
    $wa_help = $case_whatsapp !== ''
      ? 'Se abre WhatsApp con tu mensaje listo para enviar al responsable del caso.'
      : ($admin_whatsapp !== '' ? 'Este caso no tiene WhatsApp propio. Usamos el contacto general configurado en Pet Match.' : 'Este caso todavia no tiene un WhatsApp de contacto cargado.');
?>
<div class="pm-wa-cta pm-app" data-pm-wa="<?php echo esc_attr($wa); ?>">
  <div class="pm-wa-copy">
    <span class="pm-section-kicker">Avistaje rapido</span>
    <h3 class="pm-wa-title">Comparte un mensaje listo para enviar por WhatsApp</h3>
    <p class="pm-wa-lead">Incluye zona, horario, direccion aproximada y cualquier detalle que ayude a verificar el dato.</p>
  </div>
  <label class="pm-label" for="pm_wa_msg_<?php echo esc_attr($case_id); ?>">Mensaje sugerido</label>
  <textarea id="pm_wa_msg_<?php echo esc_attr($case_id); ?>" class="pm-input elementor-field" rows="4" placeholder="Escribi tu mensaje..."></textarea>

  <div class="pm-wa-actions">
    <a class="pm-btn pm-btn--wa pm-wa-btn" href="#" target="_blank" rel="noopener" <?php echo empty($wa) ? 'aria-disabled="true"' : ''; ?>>
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M20.5 11.9a8.5 8.5 0 1 1-15.9 4.1L3 21l5.1-1.6a8.47 8.47 0 0 1-3.1-6.6A8.5 8.5 0 0 1 20.5 11.9Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
        <path d="M8.7 9.4c.2-.4.4-.4.6-.4h.5c.1 0 .3 0 .4.3l.7 1.6c.1.2.1.4 0 .6l-.4.5c-.1.2-.1.3 0 .4.4.7 1 1.4 1.6 1.9.7.6 1.5 1 2.4 1.2.2 0 .3 0 .4-.1l.6-.7c.2-.2.3-.2.6-.1l1.7.8c.2.1.3.3.3.4 0 .6-.2 1.2-.6 1.6-.4.4-1.2.9-2.6.6-1.4-.3-2.9-1-4.3-2.4-1.4-1.3-2.3-2.9-2.6-4.2-.3-1.4.2-2.1.5-2.5Z" fill="currentColor"/>
      </svg>
      Enviar WhatsApp
    </a>
    <?php if (empty($wa)): ?>
      <span class="pm-help">Todavia no hay un WhatsApp de contacto disponible para este caso.</span>
    <?php else: ?>
      <span class="pm-help"><?php echo esc_html($wa_help); ?></span>
    <?php endif; ?>
  </div>
</div>
<?php
    return ob_get_clean();
  }

  private static function render_case_card(int $post_id) : string {
    $title = self::get_case_display_title($post_id);
    $url = get_permalink($post_id);
    $thumb = get_the_post_thumbnail_url($post_id, 'medium');
    if (!$thumb) $thumb = 'data:image/svg+xml;charset=utf-8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="600" height="400"><rect width="100%" height="100%" fill="#f3f4f6"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="#9ca3af" font-family="Arial" font-size="22">Sin foto</text></svg>');
    $status = self::get_case_status($post_id);
    $badge = self::get_case_status_label($status);
    $type = self::get_case_term_label($post_id, self::get_case_taxonomy('type'), 'Sin tipo');
    $species = self::get_case_term_label($post_id, self::get_case_taxonomy('species'), 'Sin especie');
    $zone = self::get_case_term_label($post_id, self::get_case_taxonomy('zone'), 'Sin zona');
    $date = self::get_case_meta($post_id, 'date');
    $status_class = 'pm-status-chip pm-status-chip--' . sanitize_html_class($status);
    $meta_items = [$species, $zone];
    if ($date !== '') {
      $meta_items[] = $date;
    }

    $meta_html = '';
    foreach ($meta_items as $item) {
      $meta_html .= '<span>' . esc_html($item) . '</span>';
    }

    return '<a class="pm-card" href="'.esc_url($url).'" aria-label="'.esc_attr(sprintf('Ver caso: %s. Estado: %s', $title, $badge)).'">'.
            '<div class="pm-card-media"><div class="pm-card-img" style="background-image:url('.esc_url($thumb).')"></div><span class="'.esc_attr($status_class).'">'.esc_html($badge).'</span></div>'.
            '<div class="pm-card-body">'.
              '<div class="pm-card-overline">'.esc_html($type).'</div>'.
              '<div class="pm-card-title">'.esc_html($title).'</div>'.
              '<div class="pm-card-meta">'.$meta_html.'</div>'.
              '<span class="pm-card-cta">Ver ficha completa</span>'.
            '</div>'.
           '</a>';
  }
}
