<?php
trait PM_Pet_Match_Admin_Trait {
  public static function render_logs_page() : void {
    if (!self::can_manage_plugin()) return;

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

    $enabled = class_exists('PM_Logger') ? (PM_Logger::enabled() ? 'Si' : 'No') : 'No';
    $tail = class_exists('PM_Logger') ? PM_Logger::read_tail(600) : '';
    $path = class_exists('PM_Logger') ? PM_Logger::path() : '';
    ?>
    <div class="wrap">
      <h1>Pet Match / Logs</h1>
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
        Tip: si vuelve a salir un error critico, recarga esta pagina y copia las ultimas lineas.
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
    add_submenu_page('pet-match','Refugios','Refugios','manage_options','pet-match-shelters',[__CLASS__,'render_admin_shelters']);
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

    wp_enqueue_style('pm-admin', plugins_url('assets/css/pm-admin.css', PM_PLUGIN_FILE), [], self::get_plugin_version());
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
    if (!self::can_manage_plugin()) return;

    self::admin_header('Pet Match', 'Dashboard general del plugin');

    $cases_total = self::count_posts(self::CPT);
    $lost_total  = self::count_posts(self::CPT, ['tax_query'=>[['taxonomy'=>self::get_case_taxonomy('type'),'field'=>'slug','terms'=>self::normalize_case_type_terms('lost')]]]);
    $adopt_total = self::count_posts(self::CPT, ['tax_query'=>[['taxonomy'=>self::get_case_taxonomy('type'),'field'=>'slug','terms'=>self::normalize_case_type_terms('adoption')]]]);

    $sightings_total = self::count_posts('pm_sighting', ['post_status'=>['publish']]);
    $alerts_total    = self::count_posts('pm_alert', ['post_status'=>['publish']]);

    echo '<div class="pm-admin-grid">';
      echo '<a class="pm-admin-card" href="'.esc_url(admin_url('admin.php?page=pet-match-cases')).'"><div class="pm-admin-card-kpi">'.esc_html($cases_total).'</div><div class="pm-admin-card-label">Casos</div><div class="pm-admin-card-desc">Gestion? perdidos y adopci?n</div></a>';
      echo '<a class="pm-admin-card" href="'.esc_url(admin_url('admin.php?page=pet-match-sightings')).'"><div class="pm-admin-card-kpi">'.esc_html($sightings_total).'</div><div class="pm-admin-card-label">Avistajes</div><div class="pm-admin-card-desc">Reportes enviados por usuarios</div></a>';
      echo '<a class="pm-admin-card" href="'.esc_url(admin_url('admin.php?page=pet-match-alerts')).'"><div class="pm-admin-card-kpi">'.esc_html($alerts_total).'</div><div class="pm-admin-card-label">Alertas</div><div class="pm-admin-card-desc">Suscripciones por email</div></a>';
      echo '<div class="pm-admin-card pm-admin-card-soft"><div class="pm-admin-card-kpi">'.esc_html($lost_total).'</div><div class="pm-admin-card-label">Perdidos</div><div class="pm-admin-card-desc">Casos de b?squeda</div></div>';
      echo '<div class="pm-admin-card pm-admin-card-soft"><div class="pm-admin-card-kpi">'.esc_html($adopt_total).'</div><div class="pm-admin-card-label">Adopci?n</div><div class="pm-admin-card-desc">Publicaciones para adoptar</div></div>';
    echo '</div>';

    echo '<div class="pm-admin-panels">';
      echo '<div class="pm-admin-panel">';
        echo '<h2>Acciones r?pidas</h2>';
        echo '<div class="pm-admin-actions">';
          echo '<a class="button button-primary" href="'.esc_url(admin_url('admin.php?page=pet-match-cases')).'">Ver casos</a> ';
          echo '<a class="button" href="'.esc_url(admin_url('admin.php?page=pet-match-settings')).'">Ajustes</a> ';
          echo '<a class="button" href="'.esc_url(admin_url('admin.php?page=pet-match-logs')).'">Logs</a>';
        echo '</div>';
      echo '</div>';

      echo '<div class="pm-admin-panel">';
        echo '<h2>Tips de operaci?n</h2>';
        echo '<ul class="pm-admin-list">';
          echo '<li>Us? <b>Casos</b> para editar, resolver o revisar publicaciones.</li>';
          echo '<li>Los <b>Avistajes</b> son reportes que llegan al autor y al admin (copia).</li>';
          echo '<li>Si hay un error en el front, revis? <b>Logs</b> y peg? el stack trace.</li>';
        echo '</ul>';
      echo '</div>';
    echo '</div>';

    self::admin_footer();
  }

  public static function render_admin_cases() : void {
    if (!self::can_manage_plugin()) return;

    $q = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
    $status = isset($_GET['post_status']) ? sanitize_text_field($_GET['post_status']) : '';
    $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
    $author = isset($_GET['author']) ? absint($_GET['author']) : 0;

    $args = [
      'post_type' => self::CPT,
      'post_status' => $status ? [$status] : ['publish','draft','pending','private'],
      'posts_per_page' => 30,
      'paged' => max(1, intval($_GET['paged'] ?? 1)),
      's' => $q ?: '',
      'orderby' => 'date',
      'order' => 'DESC',
    ];

    if ($author > 0) {
      $args['author'] = $author;
    }

    if ($type) {
      $args['tax_query'] = [[
        'taxonomy' => self::get_case_taxonomy('type'),
        'field' => 'slug',
        'terms' => [$type],
      ]];
    }

    $query = new WP_Query($args);
    $author_user = $author > 0 ? get_user_by('id', $author) : false;

    $subtitle = 'Listado centralizado (incluye borradores).';
    if ($author_user instanceof \WP_User) {
      $subtitle = 'Listado centralizado para ' . $author_user->display_name . ' (incluye borradores).';
    }
    self::admin_header('Casos', $subtitle);

    echo '<form method="get" class="pm-admin-filters">';
      echo '<input type="hidden" name="page" value="pet-match-cases" />';
      if ($author > 0) {
        echo '<input type="hidden" name="author" value="'.esc_attr((string) $author).'" />';
      }

      echo '<input class="pm-admin-input" type="text" name="q" value="'.esc_attr($q).'" placeholder="Buscar por t?tulo o descripci?n..." />';

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
        foreach (self::get_terms_for_taxonomy(self::get_case_taxonomy('type')) as $t) {
            echo '<option value="'.esc_attr($t->slug).'" '.selected($type,$t->slug,false).'>'.esc_html($t->name).'</option>';
        }
      echo '</select>';

      echo '<button class="button button-primary" type="submit">Filtrar</button>';
      echo '<a class="button" href="'.esc_url(admin_url('admin.php?page=pet-match-cases')).'">Reset</a>';
    echo '</form>';

    echo '<div class="pm-admin-table-wrap"><table class="widefat fixed striped pm-admin-table">';
      echo '<thead><tr>';
        echo '<th style="width:64px;">Foto</th>';
        echo '<th>TÃƒÂ­tulo</th>';
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
          $thumb_html = $thumb ? '<img class="pm-admin-thumb" src="'.esc_url($thumb).'" alt="" />' : '<div class="pm-admin-thumb pm-admin-thumb-empty">?</div>';

          $type_terms = get_the_terms($pid, self::get_case_taxonomy('type'));
          $type_name = (!is_wp_error($type_terms) && !empty($type_terms)) ? $type_terms[0]->name : '?';

          $zone_terms = get_the_terms($pid, self::get_case_taxonomy('zone'));
          $zone_name = (!is_wp_error($zone_terms) && !empty($zone_terms)) ? $zone_terms[0]->name : '?';

          $pm_status = self::get_case_status($pid);
          $pm_status_label = self::get_case_status_label($pm_status);

          $edit_url = admin_url('admin.php?page=pet-match-edit-case&case_id='.$pid);
          $view_url = get_permalink($pid);

          echo '<tr>';
            echo '<td>'.$thumb_html.'</td>';
            echo '<td><b>'.esc_html(get_the_title()).'</b><div class="pm-admin-muted">'.esc_html(wp_trim_words(get_the_content(),16,'...')).'</div></td>';
            echo '<td><span class="pm-admin-pill">'.esc_html($type_name).'</span></td>';
            echo '<td>'.esc_html($zone_name).'</td>';
            echo '<td><span class="pm-admin-status pm-admin-status-'.esc_attr($pm_status).'">'.esc_html($pm_status_label).'</span></td>';
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
                . 'onclick="return confirm(\'?Mover este caso a la papelera?\');">Eliminar</a>';
            echo '</td>';
          echo '</tr>';
        }
        wp_reset_postdata();
      } else {
        echo '<tr><td colspan="7"><div class="pm-admin-empty">No encontramos casos con esos filtros. ProbÃ¡ ampliar la bÃºsqueda o limpiar algÃºn criterio.</div></td></tr>';
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
        'prev_text' => '?',
        'next_text' => '?',
      ]);
      echo '</div>';
    }

    self::admin_footer();
  }

  public static function render_admin_case_edit() : void {
    if (!self::can_manage_plugin()) return;

    $pid = isset($_GET['case_id']) ? intval($_GET['case_id']) : 0;
    if (!$pid || get_post_type($pid) !== self::CPT) {
      self::admin_header('Editar caso', 'Caso invÃƒÂ¡lido.');
      echo '<div class="pm-admin-empty">No encontramos ese caso. Puede haber sido eliminado o el enlace ya no es vÃ¡lido.</div>';
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

      $type_term = self::get_valid_term_id(intval($_POST['pm_type'] ?? 0), self::get_case_taxonomy('type'));
      $species_term = self::get_valid_term_id(intval($_POST['pm_species'] ?? 0), self::get_case_taxonomy('species'));
      $zone_term = self::get_valid_term_id(intval($_POST['pm_zone'] ?? 0), self::get_case_taxonomy('zone'));

      if ($type_term) wp_set_object_terms($pid, [$type_term], self::get_case_taxonomy('type'));
      if ($species_term) wp_set_object_terms($pid, [$species_term], self::get_case_taxonomy('species'));
      if ($zone_term) wp_set_object_terms($pid, [$zone_term], self::get_case_taxonomy('zone'));

      self::update_case_status($pid, sanitize_text_field($_POST['pm_status'] ?? 'open'));
      self::update_case_meta($pid,'date', $_POST['pm_date'] ?? '');
      $admin_lat = isset($_POST['pm_lat']) ? self::get_valid_coordinate(wp_unslash((string) $_POST['pm_lat'])) : null;
      $admin_lng = isset($_POST['pm_lng']) ? self::get_valid_coordinate(wp_unslash((string) $_POST['pm_lng'])) : null;
      if ($admin_lat !== null && $admin_lng !== null && $admin_lat >= -90 && $admin_lat <= 90 && $admin_lng >= -180 && $admin_lng <= 180) {
        self::update_case_meta($pid,'lat', $admin_lat);
        self::update_case_meta($pid,'lng', $admin_lng);
      } else {
        self::delete_case_meta($pid, 'lat');
        self::delete_case_meta($pid, 'lng');
        self::log_event('WARN', 'case.admin.invalid_coordinates', 'Admin case edit cleared invalid coordinates', ['post_id' => $pid]);
      }

      echo '<div class="notice notice-success is-dismissible"><p>Guardado correctamente.</p></div>';
    }

    $post = get_post($pid);
    $pm_status = self::get_case_status($pid);
    $pm_date = self::get_case_meta($pid,'date');
    $pm_lat = self::get_case_meta($pid,'lat');
    $pm_lng = self::get_case_meta($pid,'lng');

    self::admin_header('Editar caso', 'Edicion centralizada del caso.');

    echo '<div class="pm-admin-split">';
      echo '<div class="pm-admin-panel">';
        echo '<form method="post">';
          wp_nonce_field('pm_admin_save_case');
          echo '<input type="hidden" name="pm_admin_save_case" value="1" />';

          echo '<label class="pm-admin-label">Titulo</label>';
          echo '<input class="pm-admin-input full" type="text" name="post_title" value="'.esc_attr($post->post_title).'" />';

          echo '<label class="pm-admin-label">Descripcion</label>';
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
                foreach (self::get_valid_case_statuses() as $k) {
                  echo '<option value="'.esc_attr($k).'" '.selected($pm_status,$k,false).'>'.esc_html(self::get_case_status_label($k)).'</option>';
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
              . 'onclick="return confirm(\'Mover este caso a la papelera?\');">Eliminar caso</a>';
          echo '</div>';
        echo '</form>';
      echo '</div>';

      echo '<div class="pm-admin-panel">';
        echo '<h2>Imagenes</h2>';
        $imgs = self::get_case_meta($pid, 'images');
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
            echo '<div class="pm-admin-empty">Este caso todavÃ­a no tiene imÃ¡genes cargadas.</div>';
          }
        }
        $wa = self::get_case_meta($pid, 'whatsapp');
        if ($wa) {
          echo '<p><strong>WhatsApp:</strong> '.esc_html($wa).'</p>';
        }      echo '</div>';
    echo '</div>';

    self::admin_footer();
  }

  private static function admin_terms_select(string $taxonomy, string $field_name, int $post_id) : string {
    $terms = get_terms(['taxonomy'=>$taxonomy,'hide_empty'=>false]);
    if (is_wp_error($terms)) return '<select class="pm-admin-select full" name="'.esc_attr($field_name).'"><option value="">?</option></select>';

    $selected = 0;
    $current = get_the_terms($post_id, $taxonomy);
    if (!is_wp_error($current) && !empty($current)) $selected = intval($current[0]->term_id);

    $html = '<select class="pm-admin-select full" name="'.esc_attr($field_name).'">';
    $html .= '<option value=>?</option>';
    foreach ($terms as $t) {
      $html .= '<option value="'.esc_attr($t->term_id).'" '.selected($selected, intval($t->term_id), false).'>'.esc_html($t->name).'</option>';
    }
    $html .= '</select>';
    return $html;
  }

  public static function render_admin_sightings() : void {
    if (!self::can_manage_plugin()) return;

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
          $case_link = $case_id ? '<a href="'.esc_url(admin_url('admin.php?page=pet-match-edit-case&case_id='.$case_id)).'">'.esc_html(get_the_title($case_id)).'</a>' : '?';

          echo '<tr>';
            echo '<td>'.esc_html(get_the_date('Y-m-d')).'</td>';
            echo '<td><b>'.esc_html(get_the_title()).'</b><div class="pm-admin-muted">'.esc_html(wp_trim_words(get_the_content(),22,'...')).'</div></td>';
            echo '<td>'.esc_html($email ?: '?').'</td>';
            echo '<td>'.$case_link.'</td>';
          echo '</tr>';
        }
        wp_reset_postdata();
      } else {
        echo '<tr><td colspan="4"><div class="pm-admin-empty">TodavÃ­a no hay avistajes registrados. Cuando llegue el primero lo vas a ver acÃ¡.</div></td></tr>';
      }

    echo '</tbody></table></div>';

    self::admin_footer();
  }

  public static function render_admin_alerts() : void {
    if (!self::can_manage_plugin()) return;

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
            echo '<td><span class="pm-admin-pill">'.esc_html($type ?: '?').'</span></td>';
            echo '<td class="pm-admin-muted">?</td>';
          echo '</tr>';
        }
        wp_reset_postdata();
      } else {
        echo '<tr><td colspan="4"><div class="pm-admin-empty">TodavÃ­a no hay alertas creadas. Cuando alguien se suscriba por email aparecerÃ¡ en este listado.</div></td></tr>';
      }

    echo '</tbody></table></div>';

    self::admin_footer();
  }

  public static function render_admin_settings() : void {
    if (!self::can_manage_plugin()) return;

    $opt_key = 'pm_settings';
    $settings = self::get_settings();

    if (isset($_POST['pm_save_settings']) && check_admin_referer('pm_save_settings')) {
      $settings['admin_email'] = sanitize_email($_POST['admin_email'] ?? $settings['admin_email']);
      $settings['admin_whatsapp'] = self::get_valid_whatsapp($_POST['admin_whatsapp'] ?? '');
      $settings['require_login_create'] = isset($_POST['require_login_create']) ? 1 : 0;
      $settings['asset_delivery_mode'] = sanitize_key($_POST['asset_delivery_mode'] ?? $settings['asset_delivery_mode']);
      if (!in_array($settings['asset_delivery_mode'], ['auto', 'cdn', 'local'], true)) {
        $settings['asset_delivery_mode'] = 'auto';
      }
      $settings['enable_external_google_fonts'] = isset($_POST['enable_external_google_fonts']) ? 1 : 0;
      unset($settings['default_radius_km']);

      update_option($opt_key, $settings, false);
      echo '<div class="notice notice-success is-dismissible"><p>Ajustes guardados correctamente.</p></div>';
    }

    self::admin_header('Ajustes', 'ConfiguraciÃƒÂ³n operativa del plugin.');
    $dependency_status = self::get_asset_dependency_status();

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
          echo '<div class="pm-admin-muted">Se usa como contacto de respaldo en avistajes cuando el caso no tiene WhatsApp propio.</div>';
        echo '</div>';
      echo '</div>';

      echo '<div class="pm-admin-row">';
        echo '<div class="pm-admin-check">';
          echo '<label><input type="checkbox" name="require_login_create" '.checked($settings['require_login_create'],1,false).' /> Requerir login para publicar casos</label>';
          echo '<div class="pm-admin-muted">Bloquea el shortcode pÃƒÂºblico y tambiÃƒÂ©n el envÃƒÂ­o real del formulario del lado del servidor.</div>';
        echo '</div>';
        echo '<div class="pm-admin-check">';
          echo '<div class="pm-admin-muted">El ajuste de radio por defecto fue removido porque la bÃƒÂºsqueda geogrÃƒÂ¡fica por distancia todavÃƒÂ­a no estÃƒÂ¡ implementada en el plugin.</div>';
        echo '</div>';
      echo '</div>';

      echo '<div class="pm-admin-row">';
        echo '<div>';
          echo '<label class="pm-admin-label">Entrega de librerias externas</label>';
          echo '<select class="pm-admin-select full" name="asset_delivery_mode">';
            foreach ([
              'auto' => 'Auto (usar local si existe y CDN como respaldo)',
              'cdn' => 'Forzar CDN',
              'local' => 'Preferir local y caer a CDN si falta el archivo',
            ] as $value => $label) {
              echo '<option value="'.esc_attr($value).'" '.selected($settings['asset_delivery_mode'], $value, false).'>'.esc_html($label).'</option>';
            }
          echo '</select>';
          echo '<div class="pm-admin-muted">Afecta Leaflet y Swiper. En modo local el plugin busca archivos dentro de <code>assets/vendor/</code>.</div>';
        echo '</div>';
        echo '<div class="pm-admin-check">';
          echo '<label><input type="checkbox" name="enable_external_google_fonts" '.checked($settings['enable_external_google_fonts'],1,false).' /> Permitir Google Fonts externo para Montserrat</label>';
          echo '<div class="pm-admin-muted">Si lo desactivas, Pet Match usa el CSS local de fuente y deja de consultar <code>fonts.googleapis.com</code>.</div>';
        echo '</div>';
      echo '</div>';

      echo '<div class="pm-admin-row">';
        echo '<div>';
          echo '<label class="pm-admin-label">Estado de dependencias</label>';
          echo '<div class="pm-admin-muted">Modo actual: <strong>'.esc_html(strtoupper($dependency_status['delivery_mode'])).'</strong>.</div>';
          echo '<ul class="pm-admin-list">';
          foreach (['leaflet', 'swiper', 'font'] as $dependency_key) {
            $dependency = $dependency_status[$dependency_key];
            $availability = $dependency['local_available'] ? 'Disponible localmente' : 'No encontrado en local';
            $paths = array_map('esc_html', $dependency['expected_paths']);
            echo '<li><strong>'.esc_html($dependency['label']).':</strong> '.esc_html($availability).' <code>'.implode('</code>, <code>', $paths).'</code></li>';
          }
          echo '</ul>';
          echo '<div class="pm-admin-muted">Aunque elijas modo local, el plugin mantiene fallback a CDN para no romper el frontend si faltan archivos.</div>';
        echo '</div>';
      echo '</div>';

      echo '<div class="pm-admin-actions">';
        echo '<button class="button button-primary" type="submit">Guardar</button>';
      echo '</div>';
    echo '</form>';

    self::admin_footer();
  }

  public static function render_register_shelter_field() : void {
      ?>
      <p>
        <label for="pm_is_shelter">
          <input type="checkbox" name="pm_is_shelter" id="pm_is_shelter" value="1" <?php checked(!empty($_POST['pm_is_shelter'])); ?>>
          <?php esc_html_e('Me estoy registrando como refugio u organizaciÃƒÂ³n de rescate', 'pet-match'); ?>
        </label>
      </p>
      <?php
  }

  public static function validate_register_shelter_field($errors, $sanitized_user_login, $user_email) {
      if (!($errors instanceof WP_Error)) {
        $errors = new WP_Error();
      }
      return $errors;
  }

  public static function save_register_shelter_field(int $user_id) : void {
      if (!$user_id) {
        return;
      }
      $is_shelter = isset($_POST['pm_is_shelter']) ? '1' : '0';
      update_user_meta($user_id, 'pm_is_shelter', $is_shelter);
      if ($is_shelter !== '1') {
        delete_user_meta($user_id, 'pm_shelter_verified');
      }
  }

  private static function is_truthy_flag($value) : bool {
      return $value === '1' || $value === 1 || $value === true;
  }

  private static function is_user_shelter(int $user_id) : bool {
      return $user_id > 0 && self::is_truthy_flag(get_user_meta($user_id, 'pm_is_shelter', true));
  }

  private static function is_user_verified_shelter(int $user_id) : bool {
      return self::is_user_shelter($user_id) && self::is_truthy_flag(get_user_meta($user_id, 'pm_shelter_verified', true));
  }

  private static function get_shelter_status_label(bool $is_verified) : string {
      return $is_verified ? 'Verificado' : 'Pendiente';
  }

  private static function get_shelter_case_summary(int $user_id) : array {
      $summary = [
        'total' => 0,
        'published' => 0,
        'open' => 0,
        'resolved' => 0,
        'last_case_id' => 0,
        'last_case_date' => '',
      ];

      if ($user_id <= 0) {
        return $summary;
      }

      $base_args = [
        'post_type' => self::CPT,
        'author' => $user_id,
        'posts_per_page' => 1,
        'fields' => 'ids',
        'no_found_rows' => false,
      ];

      $summary['total'] = self::count_posts(self::CPT, [
        'author' => $user_id,
        'post_status' => ['publish', 'pending', 'draft', 'private'],
      ]);

      $summary['published'] = self::count_posts(self::CPT, [
        'author' => $user_id,
        'post_status' => ['publish'],
      ]);

      $summary['open'] = self::count_posts(self::CPT, [
        'author' => $user_id,
        'post_status' => ['publish', 'pending', 'draft', 'private'],
        'meta_query' => [[
          'key' => '_pm_status',
          'value' => self::get_case_status_meta_values('open'),
          'compare' => 'IN',
        ]],
      ]);

      $summary['resolved'] = self::count_posts(self::CPT, [
        'author' => $user_id,
        'post_status' => ['publish', 'pending', 'draft', 'private'],
        'meta_query' => [[
          'key' => '_pm_status',
          'value' => self::get_case_status_meta_values('resolved'),
          'compare' => 'IN',
        ]],
      ]);

      $latest_query = new WP_Query(array_merge($base_args, [
        'post_status' => ['publish', 'pending', 'draft', 'private'],
        'orderby' => 'date',
        'order' => 'DESC',
      ]));

      if ($latest_query->have_posts() && !empty($latest_query->posts[0])) {
        $summary['last_case_id'] = (int) $latest_query->posts[0];
        $summary['last_case_date'] = (string) get_the_date('Y-m-d H:i', $summary['last_case_id']);
      }

      return $summary;
  }

  private static function get_shelter_admin_row(\WP_User $user) : array {
      $user_id = (int) $user->ID;
      return [
        'user' => $user,
        'is_verified' => self::is_user_verified_shelter($user_id),
        'status_label' => self::get_shelter_status_label(self::is_user_verified_shelter($user_id)),
        'case_summary' => self::get_shelter_case_summary($user_id),
      ];
  }

  private static function get_shelter_admin_list(string $status = 'all', string $search = '') : array {
      $args = [
        'meta_key' => 'pm_is_shelter',
        'meta_value' => '1',
        'number' => 200,
        'orderby' => 'registered',
        'order' => 'DESC',
        'fields' => ['ID', 'user_login', 'user_email', 'display_name', 'user_registered'],
      ];

      if ($search !== '') {
        $args['search'] = '*' . $search . '*';
        $args['search_columns'] = ['user_login', 'user_email', 'display_name'];
      }

      $users = get_users($args);
      $rows = [];
      foreach ($users as $user) {
        $row = self::get_shelter_admin_row($user);
        if ($status === 'verified' && !$row['is_verified']) {
          continue;
        }
        if ($status === 'pending' && $row['is_verified']) {
          continue;
        }
        $rows[] = $row;
      }

      return $rows;
  }



  public static function pm_should_auto_publish_case() : bool {
      // For now: only verified shelters auto-publish
      if (!is_user_logged_in()) return false;
      $uid = get_current_user_id();
      return self::is_user_verified_shelter($uid);
  }


  public static function pm_sync_case_author_meta($post_id, $post, $update){
      if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
      if (!$post_id || !$post) return;
      if ($post->post_type !== self::CPT) return;
  
      $author_id = (int) $post->post_author;
      if (!$author_id) return;
  
      $val = self::is_user_verified_shelter($author_id) ? '1' : '0';
      update_post_meta($post_id, 'pm_author_shelter_verified', $val);
  }


  public static function pm_handle_admin_action(){
      if (!self::can_manage_plugin()) {
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
        $previous_status = self::get_case_status($case_id);
        self::update_case_status($case_id, 'resolved');
        self::log_event('INFO', 'case.status.changed', 'Case status updated from admin', ['case_id' => $case_id, 'from_status' => $previous_status, 'to_status' => 'resolved']);
      } elseif ($do === 'contact' || $do === 'in_contact') {
        $previous_status = self::get_case_status($case_id);
        self::update_case_status($case_id, 'in_contact');
        self::log_event('INFO', 'case.status.changed', 'Case status updated from admin', ['case_id' => $case_id, 'from_status' => $previous_status, 'to_status' => 'in_contact']);
      } elseif ($do === 'unresolve') {
        $previous_status = self::get_case_status($case_id);
        self::update_case_status($case_id, 'open');
        self::log_event('INFO', 'case.status.changed', 'Case status updated from admin', ['case_id' => $case_id, 'from_status' => $previous_status, 'to_status' => 'open']);
      } elseif ($do === 'close') {
        $previous_status = self::get_case_status($case_id);
        self::update_case_status($case_id, 'closed');
        self::log_event('INFO', 'case.status.changed', 'Case status updated from admin', ['case_id' => $case_id, 'from_status' => $previous_status, 'to_status' => 'closed']);
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
      if (!self::can_manage_plugin()) {
        wp_die('No autorizado');
      }
      check_admin_referer('pm_shelter_action');
  
      $user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
      $do = isset($_GET['do']) ? sanitize_key($_GET['do']) : '';
  
      if (!$user_id) {
        wp_safe_redirect(admin_url('admin.php?page=pet-match-shelters'));
        exit;
      }

      $notice = '';
      if ($do === 'verify') {
        update_user_meta($user_id, 'pm_shelter_verified', '1');
        $notice = 'Refugio verificado correctamente.';
      } elseif ($do === 'unverify') {
        delete_user_meta($user_id, 'pm_shelter_verified');
        $notice = 'Se quitÃƒÂ³ la verificaciÃƒÂ³n del refugio.';
      }

      if ($notice !== '') {
        self::log_event('INFO', 'shelter.admin.action', 'Shelter admin action executed', [
          'user_id' => $user_id,
          'action' => $do,
          'is_verified' => self::is_user_verified_shelter($user_id) ? '1' : '0',
        ]);
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
      if ($notice !== '') {
        $back = add_query_arg([
          'pm_notice' => $notice,
          'pm_notice_type' => 'success',
        ], $back ? $back : admin_url('admin.php?page=pet-match-shelters'));
      }
      wp_safe_redirect($back ? $back : admin_url('admin.php?page=pet-match-shelters'));
      exit;
  }


  public static function render_admin_shelters(){
      if (!self::can_manage_plugin()) {
        wp_die('No autorizado');
      }

      $status = isset($_GET['status']) ? sanitize_key((string) $_GET['status']) : 'all';
      if (!in_array($status, ['all', 'verified', 'pending'], true)) {
        $status = 'all';
      }
      $search = isset($_GET['q']) ? sanitize_text_field(wp_unslash((string) $_GET['q'])) : '';
      $rows = self::get_shelter_admin_list($status, $search);
      $all_rows = self::get_shelter_admin_list('all', '');

      $summary = [
        'total' => count($all_rows),
        'verified' => 0,
        'pending' => 0,
        'published_cases' => 0,
        'total_cases' => 0,
      ];

      foreach ($all_rows as $row) {
        if ($row['is_verified']) {
          $summary['verified']++;
        } else {
          $summary['pending']++;
        }
        $summary['published_cases'] += (int) $row['case_summary']['published'];
        $summary['total_cases'] += (int) $row['case_summary']['total'];
      }

      self::admin_header('Refugios', 'GestionÃƒÂ¡ usuarios registrados como refugios, su verificaciÃƒÂ³n y sus publicaciones.');

      $notice = self::get_request_feedback_notice('pm_notice');
      if ($notice !== '') {
        echo $notice;
      }

      echo '<div class="pm-admin-grid">';
        echo '<div class="pm-admin-card pm-admin-card-soft"><div class="pm-admin-card-kpi">'.esc_html($summary['total']).'</div><div class="pm-admin-card-label">Refugios</div><div class="pm-admin-card-desc">Usuarios registrados como refugio</div></div>';
        echo '<div class="pm-admin-card pm-admin-card-soft"><div class="pm-admin-card-kpi">'.esc_html($summary['verified']).'</div><div class="pm-admin-card-label">Verificados</div><div class="pm-admin-card-desc">Pueden autopublicar si el flujo lo usa</div></div>';
        echo '<div class="pm-admin-card pm-admin-card-soft"><div class="pm-admin-card-kpi">'.esc_html($summary['pending']).'</div><div class="pm-admin-card-label">Pendientes</div><div class="pm-admin-card-desc">Requieren revisiÃƒÂ³n administrativa</div></div>';
        echo '<div class="pm-admin-card pm-admin-card-soft"><div class="pm-admin-card-kpi">'.esc_html($summary['published_cases']).'</div><div class="pm-admin-card-label">Casos publicados</div><div class="pm-admin-card-desc">Publicaciones visibles de refugios</div></div>';
        echo '<div class="pm-admin-card pm-admin-card-soft"><div class="pm-admin-card-kpi">'.esc_html($summary['total_cases']).'</div><div class="pm-admin-card-label">Casos totales</div><div class="pm-admin-card-desc">Incluye borradores y pendientes</div></div>';
      echo '</div>';

      echo '<form method="get" class="pm-admin-filters">';
        echo '<input type="hidden" name="page" value="pet-match-shelters" />';
        echo '<input class="pm-admin-input" type="text" name="q" value="'.esc_attr($search).'" placeholder="Buscar por usuario, email o nombre..." />';
        echo '<select class="pm-admin-select" name="status">';
          echo '<option value="all" '.selected($status, 'all', false).'>Todos los estados</option>';
          echo '<option value="verified" '.selected($status, 'verified', false).'>Verificados</option>';
          echo '<option value="pending" '.selected($status, 'pending', false).'>Pendientes</option>';
        echo '</select>';
        echo '<button class="button button-primary" type="submit">Filtrar</button>';
        echo '<a class="button" href="'.esc_url(admin_url('admin.php?page=pet-match-shelters')).'">Reset</a>';
      echo '</form>';

      echo '<div class="pm-admin-table-wrap"><table class="widefat fixed striped pm-admin-table">';
      echo '<thead><tr><th>Refugio</th><th>Contacto</th><th>Registrado</th><th>Estado</th><th>Casos</th><th>ÃƒÅ¡ltimo caso</th><th>Acciones</th></tr></thead><tbody>';

      if (empty($rows)) {
        echo '<tr><td colspan="7"><div class="pm-admin-empty">No encontramos refugios con esos filtros. ProbÃ¡ cambiar el estado o limpiar la bÃºsqueda.</div></td></tr>';
      } else {
        foreach ($rows as $row) {
          $user = $row['user'];
          $user_id = (int) $user->ID;
          $case_summary = $row['case_summary'];
          $status_class = $row['is_verified'] ? 'pm-admin-status-open' : 'pm-admin-status-closed';
          $url_verify = wp_nonce_url(admin_url('admin-post.php?action=pm_shelter_action&do=verify&user_id='.$user_id), 'pm_shelter_action');
          $url_unverify = wp_nonce_url(admin_url('admin-post.php?action=pm_shelter_action&do=unverify&user_id='.$user_id), 'pm_shelter_action');
          $cases_url = add_query_arg([
            'page' => 'pet-match-cases',
            'author' => $user_id,
          ], admin_url('admin.php'));
          $edit_user_url = admin_url('user-edit.php?user_id='.$user_id);
          $display_name = $user->display_name !== '' ? $user->display_name : $user->user_login;

          echo '<tr>';
          echo '<td><strong>'.esc_html($display_name).'</strong><div class="pm-admin-muted">@'.esc_html($user->user_login).'</div></td>';
          echo '<td><a href="mailto:'.esc_attr($user->user_email).'">'.esc_html($user->user_email).'</a></td>';
          echo '<td>'.esc_html(mysql2date('Y-m-d H:i', $user->user_registered)).'</td>';
          echo '<td><span class="pm-admin-status '.esc_attr($status_class).'">'.esc_html($row['status_label']).'</span></td>';
          echo '<td>';
          echo '<div><strong>'.esc_html((string) $case_summary['total']).'</strong> totales</div>';
          echo '<div class="pm-admin-muted">'.esc_html((string) $case_summary['published']).' publicados, '.esc_html((string) $case_summary['open']).' abiertos, '.esc_html((string) $case_summary['resolved']).' resueltos</div>';
          echo '</td>';
          if (!empty($case_summary['last_case_id'])) {
            echo '<td><a href="'.esc_url(get_permalink((int) $case_summary['last_case_id'])).'" target="_blank" rel="noopener">'.esc_html(get_the_title((int) $case_summary['last_case_id'])).'</a><div class="pm-admin-muted">'.esc_html($case_summary['last_case_date']).'</div></td>';
          } else {
            echo '<td><span class="pm-admin-muted">TodavÃƒÂ­a sin casos publicados.</span></td>';
          }
          echo '<td class="pm-admin-actions-col">';
          if ($row['is_verified']) {
            echo '<a class="button" href="'.esc_url($url_unverify).'">Quitar verificaciÃƒÂ³n</a> ';
          } else {
            echo '<a class="button button-primary" href="'.esc_url($url_verify).'">Verificar</a> ';
          }
          echo '<a class="button button-small" href="'.esc_url($cases_url).'">Ver casos</a> ';
          echo '<a class="button button-small" href="'.esc_url($edit_user_url).'">Editar usuario</a>';
          echo '</td>';
          echo '</tr>';
        }
      }

      echo '</tbody></table></div>';

      self::admin_footer();
}
}

