<?php
trait PM_Pet_Match_Forms_Trait {
  public static function handle_create_case() : void {
    if (!isset($_POST['pm_action']) || $_POST['pm_action'] !== 'create_case') return;
    $return_url = self::get_create_case_return_url();

    if (!self::can_current_user_create_case()) {
      self::log_blocked_create_attempt('handle_create_case');
      self::redirect_with_notice($return_url, 'Para publicar un caso necesitas iniciar sesion o crear una cuenta.');
    }

    $nonce = isset($_POST['pm_nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['pm_nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'pm_create_case')) {
      self::log_validation_failure('case_create', 'invalid_nonce');
      self::redirect_with_notice($return_url, 'No pudimos validar el formulario. Actualiza la pagina y volve a intentarlo.');
    }

    $desc = trim(sanitize_textarea_field(wp_unslash((string) ($_POST['pm_description'] ?? ''))));
    $date = self::get_valid_case_date($_POST['pm_date'] ?? '');
    $lat  = isset($_POST['pm_lat']) ? self::get_valid_coordinate(wp_unslash((string) $_POST['pm_lat'])) : null;
    $lng  = isset($_POST['pm_lng']) ? self::get_valid_coordinate(wp_unslash((string) $_POST['pm_lng'])) : null;
    $wa_raw = sanitize_text_field(wp_unslash((string) ($_POST['pm_whatsapp'] ?? '')));
    $wa_digits = self::get_valid_whatsapp($wa_raw);

    $pet_name = sanitize_text_field(wp_unslash((string) ($_POST['pm_pet_name'] ?? '')));
    $sex      = sanitize_text_field(wp_unslash((string) ($_POST['pm_sex'] ?? '')));
    $age      = sanitize_text_field(wp_unslash((string) ($_POST['pm_age'] ?? '')));
    $size     = sanitize_text_field(wp_unslash((string) ($_POST['pm_size'] ?? '')));
    $color    = sanitize_text_field(wp_unslash((string) ($_POST['pm_color'] ?? '')));
    $collar   = sanitize_text_field(wp_unslash((string) ($_POST['pm_collar'] ?? '')));
    $neutered = sanitize_text_field(wp_unslash((string) ($_POST['pm_neutered'] ?? '')));

    $type_term = self::get_valid_term_id(isset($_POST['pm_type']) ? intval($_POST['pm_type']) : 0, self::get_case_taxonomy('type'));
    $species_term = self::get_valid_term_id(isset($_POST['pm_species']) ? intval($_POST['pm_species']) : 0, self::get_case_taxonomy('species'));
    $zone_term = self::get_valid_term_id(isset($_POST['pm_zone']) ? intval($_POST['pm_zone']) : 0, self::get_case_taxonomy('zone'));

    $coords_valid = ($lat !== null && $lng !== null && $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180);
    if (!$coords_valid) {
      self::log_validation_failure('case_create', 'invalid_coordinates', ['lat' => $lat, 'lng' => $lng]);
      self::redirect_with_notice($return_url, 'Selecciona una ubicacion valida en el mapa.');
    }

    if ($desc === '') {
      self::log_validation_failure('case_create', 'missing_description');
      self::redirect_with_notice($return_url, 'Completa la descripcion del caso.');
    }

    if ($date === '') {
      self::log_validation_failure('case_create', 'invalid_date');
      self::redirect_with_notice($return_url, 'Ingresa una fecha valida.');
    }

    if (!$type_term || !$species_term || !$zone_term) {
      self::log_validation_failure('case_create', 'invalid_terms', [
        'type' => $type_term,
        'species' => $species_term,
        'zone' => $zone_term,
      ]);
      self::redirect_with_notice($return_url, 'Revisa tipo, especie y zona. Tienen que existir en el sistema.');
    }

    if ($wa_raw !== '' && $wa_digits === '') {
      self::log_validation_failure('case_create', 'invalid_whatsapp');
      self::redirect_with_notice($return_url, 'Ingresa un WhatsApp valido de 10 a 15 digitos o dejalo vacio.');
    }

    $valid_upload_indexes = self::validate_uploaded_images($_FILES['pm_photos'] ?? []);
    if (is_wp_error($valid_upload_indexes)) {
      self::log_validation_failure('case_create', $valid_upload_indexes->get_error_code());
      self::redirect_with_notice($return_url, $valid_upload_indexes->get_error_message());
    }

    $title = $pet_name ? $pet_name : wp_trim_words($desc, 8, '...');
    $post_id = wp_insert_post([
      'post_type'   => self::CPT,
      'post_status' => 'publish',
      'post_title'  => $title ?: __('Caso de mascota', 'pet-match'),
      'post_content'=> $desc,
      'post_author' => get_current_user_id() ?: 0,
    ], true);

    if (is_wp_error($post_id)) {
      self::log_validation_failure('case_create', 'insert_failed', ['error' => $post_id->get_error_code()]);
      self::redirect_with_notice($return_url, 'No pudimos publicar el caso en este momento. Guarda los datos y proba nuevamente en unos minutos.');
    }

    wp_set_object_terms($post_id, [$type_term], self::get_case_taxonomy('type'));
    wp_set_object_terms($post_id, [$species_term], self::get_case_taxonomy('species'));
    wp_set_object_terms($post_id, [$zone_term], self::get_case_taxonomy('zone'));

    self::update_case_meta($post_id, 'lat', $lat);
    self::update_case_meta($post_id, 'lng', $lng);
    self::update_case_meta($post_id, 'date', $date);
    self::update_case_status($post_id, 'open');
    if (!empty($wa_digits)) {
      self::update_case_meta($post_id, 'whatsapp', $wa_digits);
    }

    // Save extra meta if present
    if ($pet_name) self::update_case_meta($post_id, 'pet_name', $pet_name);
    if ($sex) self::update_case_meta($post_id, 'sex', $sex);
    if ($age) self::update_case_meta($post_id, 'age', $age);
    if ($size) self::update_case_meta($post_id, 'size', $size);
    if ($color) self::update_case_meta($post_id, 'color', $color);
    if ($collar) self::update_case_meta($post_id, 'collar', $collar);
    if ($neutered) self::update_case_meta($post_id, 'neutered', $neutered);

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $uploaded_ids = [];
    $files = $_FILES['pm_photos'];
    foreach ($valid_upload_indexes as $i) {
      $file_array = [
        'name'     => $files['name'][$i],
        'type'     => $files['type'][$i],
        'tmp_name' => $files['tmp_name'][$i],
        'error'    => $files['error'][$i],
        'size'     => $files['size'][$i],
      ];

      $key = 'pm_photo_single';
      $_FILES[$key] = $file_array;
      $attachment_id = media_handle_upload($key, $post_id);
      unset($_FILES[$key]);

      if (is_wp_error($attachment_id)) {
        self::log_event('ERROR', 'case.upload.failed', 'Attachment upload failed', [
          'post_id' => $post_id,
          'error_code' => $attachment_id->get_error_code(),
          'file_index' => $i,
        ]);
        continue;
      }

      $uploaded_ids[] = (int) $attachment_id;
    }

    if (empty($uploaded_ids)) {
      wp_delete_post($post_id, true);
      self::log_event('ERROR', 'case.upload.failed_all', 'All case uploads failed', [
        'post_id' => $post_id,
      ]);
      self::redirect_with_notice($return_url, 'No pudimos procesar las imagenes subidas. Proba con archivos JPG, PNG, GIF o WEBP validos y volve a intentar.');
    }

    set_post_thumbnail($post_id, $uploaded_ids[0]);
    self::update_case_meta($post_id, 'images', array_values(array_unique($uploaded_ids)));

    self::log_event('INFO', 'case.create.success', 'Case created successfully', [
      'post_id' => $post_id,
      'type_term_id' => $type_term,
      'species_term_id' => $species_term,
      'zone_term_id' => $zone_term,
      'status' => self::get_case_status($post_id),
      'images_count' => count($uploaded_ids),
      'has_whatsapp' => !empty($wa_digits) ? 1 : 0,
    ]);

    wp_safe_redirect(get_permalink($post_id));
    exit;
  }

  public static function append_case_meta_to_single(string $content) : string {
    if (!is_singular(self::CPT) || !in_the_loop() || !is_main_query()) return $content;

    $post_id = get_the_ID();
    $coords = self::get_valid_case_coordinates($post_id);
    $lat = $coords['lat'] ?? null;
    $lng = $coords['lng'] ?? null;
    $date = self::get_case_meta($post_id, 'date');
    $status = self::get_case_status($post_id);

    $type = wp_get_post_terms($post_id, self::get_case_taxonomy('type'), ['fields' => 'names']);
    $species = wp_get_post_terms($post_id, self::get_case_taxonomy('species'), ['fields' => 'names']);
    $zone = wp_get_post_terms($post_id, self::get_case_taxonomy('zone'), ['fields' => 'names']);

    $badge = '';
    $badge = self::get_case_status_badge_html($status);

    $meta = '<div class="pm-case-meta pm-card">';
    $meta .= '<div class="pm-case-meta-row"><strong>Estado:</strong> '.$badge.'</div>';
    $meta .= '<div class="pm-case-meta-row"><strong>Tipo:</strong> '.esc_html($type[0] ?? '-').'</div>';
    $meta .= '<div class="pm-case-meta-row"><strong>Especie:</strong> '.esc_html($species[0] ?? '-').'</div>';
    $meta .= '<div class="pm-case-meta-row"><strong>Zona:</strong> '.esc_html($zone[0] ?? '-').'</div>';
    $meta .= '<div class="pm-case-meta-row"><strong>Fecha:</strong> '.esc_html($date ?: '-').'</div>';

    // Extra quick facts (optional)
    $pet_name = self::get_case_meta($post_id, 'pet_name');
    $sex      = self::get_case_meta($post_id, 'sex');
    $age      = self::get_case_meta($post_id, 'age');
    $size     = self::get_case_meta($post_id, 'size');
    $color    = self::get_case_meta($post_id, 'color');
    $collar   = self::get_case_meta($post_id, 'collar');
    $neutered = self::get_case_meta($post_id, 'neutered');

    $sex_label = $sex === 'male' ? 'Macho' : ($sex === 'female' ? 'Hembra' : '');
    $age_map = array('baby' => 'Cachorro', 'young' => 'Joven', 'adult' => 'Adulto', 'senior' => 'Senior');
    $size_map = array('xs' => 'Muy chico', 's' => 'Chico', 'm' => 'Mediano', 'l' => 'Grande', 'xl' => 'Muy grande');
    $yn_map = array('yes' => 'Si', 'no' => 'No');

    if (!empty($pet_name)) $meta .= '<div class="pm-case-meta-row"><strong>Nombre:</strong> '.esc_html($pet_name).'</div>';
    if (!empty($sex_label)) $meta .= '<div class="pm-case-meta-row"><strong>Sexo:</strong> '.esc_html($sex_label).'</div>';
    if (!empty($age) && isset($age_map[$age])) $meta .= '<div class="pm-case-meta-row"><strong>Edad:</strong> '.esc_html($age_map[$age]).'</div>';
    if (!empty($size) && isset($size_map[$size])) $meta .= '<div class="pm-case-meta-row"><strong>Tamano:</strong> '.esc_html($size_map[$size]).'</div>';
    if (!empty($color)) $meta .= '<div class="pm-case-meta-row"><strong>Color:</strong> '.esc_html($color).'</div>';
    if (!empty($collar) && isset($yn_map[$collar])) $meta .= '<div class="pm-case-meta-row"><strong>Collar:</strong> '.esc_html($yn_map[$collar]).'</div>';
    if (!empty($neutered) && isset($yn_map[$neutered])) $meta .= '<div class="pm-case-meta-row"><strong>Castrado/a:</strong> '.esc_html($yn_map[$neutered]).'</div>';

    $meta .= '</div>';

    // Lightweight map preview for single (optional)
    $map = '';
    if ($coords) {
      $map = '<div class="pm-field"><label>Ubicacion aproximada</label><div id="pm-map-single" class="pm-map"></div></div>';
    }

    $report = '';
    $report .= '<div class="pm-case-report pm-card">'
      . '<div class="pm-case-report-head">Lo viste? Ayuda reportando un avistaje</div>'
      . '<button type="button" class="pm-btn pm-btn-primary" data-pm-toggle="pm-sighting">Reportar avistaje</button>'
      . '<div class="pm-case-report-panel" data-pm-panel="pm-sighting" hidden>'
        . do_shortcode('[pm_report_sighting case_id="'.intval($post_id).'"]')
      . '</div>'
    . '</div>';

    $imgs = self::get_case_meta($post_id, 'images');
    $main_id = (int) get_post_thumbnail_id($post_id);
    if (!$main_id && !empty($imgs)) { $main_id = (int) $imgs[0]; }

    $gallery = '';
    if ($main_id) {
      $gallery_ids = array_values(array_unique(array_filter(array_merge([$main_id], array_map('intval', $imgs)))));
      $main_large = wp_get_attachment_image_url($main_id, 'large');
      $main_full = wp_get_attachment_image_url($main_id, 'full');
      $main_alt = get_post_meta($main_id, '_wp_attachment_image_alt', true);
      if ($main_alt === '') {
        $main_alt = self::get_case_display_title($post_id);
      }

      $main_button = '<button type="button" class="pm-case-gallery-main-btn" data-pm-gallery-open data-pm-gallery-src="' . esc_url($main_full ? $main_full : $main_large) . '" data-pm-gallery-alt="' . esc_attr($main_alt) . '" aria-label="Ver imagen en grande">';
      $main_button .= wp_get_attachment_image($main_id, 'large', false, array('loading' => 'eager', 'class' => 'pm-case-gallery-main-image', 'alt' => $main_alt));
      $main_button .= '</button>';

      $thumbs = '';
      foreach ($gallery_ids as $index => $tid) {
        $thumb_url = wp_get_attachment_image_url($tid, 'thumbnail');
        $large_url = wp_get_attachment_image_url($tid, 'large');
        $full_url = wp_get_attachment_image_url($tid, 'full');
        if (!$thumb_url || !$large_url) {
          continue;
        }
        $thumb_alt = get_post_meta($tid, '_wp_attachment_image_alt', true);
        if ($thumb_alt === '') {
          $thumb_alt = self::get_case_display_title($post_id);
        }

        $thumbs .= '<button type="button" class="pm-case-gallery-thumb' . ($tid === $main_id ? ' is-active' : '') . '"';
        $thumbs .= ' data-pm-gallery-thumb';
        $thumbs .= ' data-pm-gallery-large="' . esc_url($large_url) . '"';
        $thumbs .= ' data-pm-gallery-full="' . esc_url($full_url ? $full_url : $large_url) . '"';
        $thumbs .= ' data-pm-gallery-alt="' . esc_attr($thumb_alt) . '"';
        $thumbs .= ' aria-label="Ver foto ' . esc_attr((string) ($index + 1)) . '">';
        $thumbs .= wp_get_attachment_image($tid, 'thumbnail', false, array('loading' => 'lazy', 'alt' => $thumb_alt));
        $thumbs .= '</button>';
      }

      $thumbs_html = $thumbs ? '<div class="pm-case-gallery-thumbs" role="list">' . $thumbs . '</div>' : '';
      $gallery = '<div class="pm-case-gallery" data-pm-gallery><div class="pm-case-gallery-main" data-pm-gallery-stage>' . $main_button . '</div>' . $thumbs_html . '</div>';
      $gallery .= '<div class="pm-gallery-lightbox" data-pm-gallery-lightbox hidden><button type="button" class="pm-gallery-lightbox-close" data-pm-gallery-close aria-label="Cerrar visor">&times;</button><img class="pm-gallery-lightbox-image" data-pm-gallery-lightbox-image src="" alt=""></div>';
    } else {
      $gallery = '<div class="pm-case-gallery"><div class="pm-case-gallery-main pm-case-gallery-empty" style="padding:22px;">Sin imagenes cargadas.</div></div>';
    }

    $hero = '<div class="pm-case-wrap"><div class="pm-case-hero">' . $gallery . $meta . '</div></div>';

    return $content . $hero . $map . $report;
  }

  
  
  public static function notify_alerts(int $post_id) : void {
    if (get_post_type($post_id) !== self::CPT) return;
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) return;
    if (self::get_case_status($post_id) !== 'open') return;

    $case_context = self::get_case_alert_context($post_id);
    $case_context = apply_filters('pm_alert_match_context', $case_context, $post_id);

    if (empty($case_context['type'])) {
      self::log_validation_failure('alert_notify', 'missing_case_type', ['case_id' => $post_id]);
      return;
    }

    $alerts = get_posts([
      'post_type' => 'pm_alert',
      'posts_per_page' => -1,
      'post_status' => 'publish',
      'fields' => 'ids',
    ]);

    $matched_count = 0;
    $skip_counts = [];

    foreach ($alerts as $alert_id) {
      $alert = self::get_alert_meta((int) $alert_id);
      [$matched, $reasons] = self::match_alert_to_case($alert, $case_context);

      if (!$matched) {
        foreach ($reasons as $reason) {
          $skip_counts[$reason] = ($skip_counts[$reason] ?? 0) + 1;
        }
        continue;
      }

      $species_name = '';
      if (!empty($case_context['species_id'])) {
        $species_term = get_term((int) $case_context['species_id'], self::get_case_taxonomy('species'));
        if ($species_term && !is_wp_error($species_term)) {
          $species_name = $species_term->name;
        }
      }

      $zone_name = '';
      if (!empty($case_context['zone_id'])) {
        $zone_term = get_term((int) $case_context['zone_id'], self::get_case_taxonomy('zone'));
        if ($zone_term && !is_wp_error($zone_term)) {
          $zone_name = $zone_term->name;
        }
      }

      $type_labels = self::get_alert_type_options();
      $type_label = $type_labels[$case_context['type']] ?? $case_context['type'];
      $subject = sprintf('Pet Match: nuevo caso de %s que coincide con tu alerta', strtolower($type_label));
      $subject = apply_filters('pm_alert_email_subject', $subject, $alert, $case_context);

      $summary = [
        'Titulo: ' . ($case_context['title'] ?: self::get_case_display_title($post_id)),
        'Tipo: ' . $type_label,
      ];
      if ($species_name !== '') {
        $summary[] = 'Especie: ' . $species_name;
      }
      if ($zone_name !== '') {
        $summary[] = 'Zona: ' . $zone_name;
      }
      if (!empty($case_context['date'])) {
        $summary[] = 'Fecha: ' . $case_context['date'];
      }
      $summary[] = 'Ver caso: ' . $case_context['permalink'];

      $body = "Encontramos un nuevo caso que coincide con tu alerta.

" . implode("
", $summary);
      $body = apply_filters('pm_alert_email_message', $body, $alert, $case_context, $summary);

      if (wp_mail($alert['email'], $subject, $body)) {
        $matched_count++;
      } else {
        $skip_counts['mail_failed'] = ($skip_counts['mail_failed'] ?? 0) + 1;
      }
    }

    self::log_event('INFO', 'alert.notify.batch', 'Alert matching completed', [
      'case_id' => $post_id,
      'checked' => count($alerts),
      'matches' => $matched_count,
      'skip_counts' => $skip_counts,
      'case_type' => $case_context['type'],
      'species_id' => $case_context['species_id'],
      'zone_id' => $case_context['zone_id'],
    ]);
  }

public static function pm_resolve_case() : void {
    if (!isset($_POST['pm_resolve_nonce'], $_POST['case_id'])) return;
    if (!wp_verify_nonce(sanitize_text_field($_POST['pm_resolve_nonce']), 'pm_resolve')) return;

    $id = intval($_POST['case_id']);
    if (!$id || get_post_type($id) !== self::CPT) return;

    // Only author or admin can resolve
    $author_id = (int) get_post_field('post_author', $id);
    $current = get_current_user_id();
    if (!self::can_resolve_case($id)) return;

    self::update_case_status($id, 'resolved');
    self::log_event('INFO', 'case.status.changed', 'Case status updated', [
      'case_id' => $id,
      'from_status' => 'open',
      'to_status' => 'resolved',
      'actor_id' => $current,
    ]);
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
}

