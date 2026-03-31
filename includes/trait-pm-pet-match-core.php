<?php
trait PM_Pet_Match_Core_Trait {
  private static function get_case_taxonomies() : array {
    return [
      'type' => 'pm_case_type',
      'species' => 'pm_species',
      'zone' => 'pm_zone',
    ];
  }

  private static function get_case_taxonomy(string $key) : string {
    $taxonomies = self::get_case_taxonomies();
    return $taxonomies[$key] ?? '';
  }

  private static function get_case_type_groups() : array {
    return [
      'lost' => ['lost', 'perdi', 'perdido', 'perdida', 'perdidos', 'perdidas', 'busco', 'buscando'],
      'found' => ['found', 'encontre', 'encontrado', 'encontrada', 'encontrados', 'encontradas'],
      'adoption' => ['adoption', 'adopcion', 'adopciones', 'adoptar', 'adopta'],
    ];
  }

  private static function get_case_type_canonical_slug(string $slug) : string {
    $slug = sanitize_title($slug);
    if ($slug === '') {
      return '';
    }

    foreach (self::get_case_type_groups() as $canonical => $aliases) {
      if (in_array($slug, $aliases, true)) {
        return $canonical;
      }
    }

    return $slug;
  }

  private static function get_case_type_slugs(string $slug = '') : array {
    if ($slug === '') {
      return [];
    }

    $canonical = self::get_case_type_canonical_slug($slug);
    $groups = self::get_case_type_groups();
    if (!isset($groups[$canonical])) {
      return [$canonical];
    }

    return array_values(array_filter(array_unique(array_map('sanitize_title', $groups[$canonical]))));
  }

  private static function get_case_type_term_id_by_slug(string $slug) : int {
    $taxonomy = self::get_case_taxonomy('type');
    if ($taxonomy === '') {
      return 0;
    }

    $canonical = self::get_case_type_canonical_slug($slug);
    if ($canonical === '') {
      return 0;
    }

    $term = get_term_by('slug', $canonical, $taxonomy);
    return ($term && !is_wp_error($term)) ? (int) $term->term_id : 0;
  }

  private static function get_terms_for_taxonomy(string $taxonomy) : array {
    if ($taxonomy === '' || !taxonomy_exists($taxonomy)) {
      return [];
    }

    $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
    return is_wp_error($terms) ? [] : $terms;
  }

  private static function get_case_display_title(int $post_id, string $fallback = 'Caso sin titulo') : string {
    $title = trim((string) get_the_title($post_id));
    return $title !== '' ? $title : $fallback;
  }

  private static function get_case_term_label(int $post_id, string $taxonomy, string $fallback = 'Sin asignar') : string {
    if ($taxonomy === '') {
      return $fallback;
    }

    $terms = get_the_terms($post_id, $taxonomy);
    if (is_wp_error($terms) || empty($terms) || !isset($terms[0]->name)) {
      return $fallback;
    }

    $name = trim((string) $terms[0]->name);
    return $name !== '' ? $name : $fallback;
  }

  private static function get_case_meta_config() : array {
    return [
      'lat' => ['key' => '_pm_lat', 'default' => '', 'type' => 'float'],
      'lng' => ['key' => '_pm_lng', 'default' => '', 'type' => 'float'],
      'date' => ['key' => '_pm_date', 'default' => '', 'type' => 'date'],
      'status' => ['key' => '_pm_status', 'default' => 'open', 'type' => 'status'],
      'whatsapp' => ['key' => '_pm_whatsapp', 'default' => '', 'type' => 'whatsapp'],
      'pet_name' => ['key' => '_pm_pet_name', 'default' => '', 'type' => 'text'],
      'sex' => ['key' => '_pm_sex', 'default' => '', 'type' => 'text'],
      'age' => ['key' => '_pm_age', 'default' => '', 'type' => 'text'],
      'size' => ['key' => '_pm_size', 'default' => '', 'type' => 'text'],
      'color' => ['key' => '_pm_color', 'default' => '', 'type' => 'text'],
      'collar' => ['key' => '_pm_collar', 'default' => '', 'type' => 'text'],
      'neutered' => ['key' => '_pm_neutered', 'default' => '', 'type' => 'text'],
      'images' => ['key' => '_pm_images', 'default' => [], 'type' => 'array_int'],
    ];
  }

  private static function get_case_meta_definition(string $field) : ?array {
    $config = self::get_case_meta_config();
    return $config[$field] ?? null;
  }

  private static function normalize_case_meta_value(string $field, $value) {
    $definition = self::get_case_meta_definition($field);
    if (!$definition) {
      return $value;
    }

    switch ($definition['type']) {
      case 'float':
        $normalized = self::get_valid_coordinate($value);
        return $normalized === null ? $definition['default'] : $normalized;
      case 'date':
        $normalized = self::get_valid_case_date($value);
        return $normalized === '' ? $definition['default'] : $normalized;
      case 'status':
        return self::normalize_case_status((string) $value);
      case 'whatsapp':
        $normalized = self::get_valid_whatsapp($value);
        return $normalized === '' ? $definition['default'] : $normalized;
      case 'array_int':
        if (!is_array($value)) {
          return $definition['default'];
        }
        return array_values(array_unique(array_filter(array_map('intval', $value))));
      case 'text':
      default:
        return sanitize_text_field((string) $value);
    }
  }

  private static function get_case_meta(int $post_id, string $field) {
    $definition = self::get_case_meta_definition($field);
    if (!$definition) {
      return null;
    }

    $value = get_post_meta($post_id, $definition['key'], true);
    if ($value === '' || $value === null) {
      return $definition['default'];
    }

    return self::normalize_case_meta_value($field, $value);
  }

  private static function update_case_meta(int $post_id, string $field, $value) : void {
    $definition = self::get_case_meta_definition($field);
    if (!$definition) {
      return;
    }

    $normalized = self::normalize_case_meta_value($field, $value);
    $default = $definition['default'];

    if ($normalized === $default || $normalized === '' || $normalized === []) {
      delete_post_meta($post_id, $definition['key']);
      return;
    }

    update_post_meta($post_id, $definition['key'], $normalized);
  }

  private static function delete_case_meta(int $post_id, string $field) : void {
    $definition = self::get_case_meta_definition($field);
    if ($definition) {
      delete_post_meta($post_id, $definition['key']);
    }
  }

  private static function get_alert_type_options() : array {
    return [
      'lost' => 'Perdidos',
      'adoption' => 'Adopcion',
    ];
  }

  private static function normalize_alert_type(string $type) : string {
    $type = sanitize_key($type);
    return array_key_exists($type, self::get_alert_type_options()) ? $type : '';
  }

  private static function get_alert_meta(int $alert_id) : array {
    return [
      'email' => sanitize_email((string) get_post_meta($alert_id, 'email', true)),
      'type' => self::normalize_alert_type((string) get_post_meta($alert_id, 'type', true)),
      'species_id' => self::get_valid_term_id((int) get_post_meta($alert_id, 'species_id', true), self::get_case_taxonomy('species')),
      'zone_id' => self::get_valid_term_id((int) get_post_meta($alert_id, 'zone_id', true), self::get_case_taxonomy('zone')),
    ];
  }

  private static function get_case_alert_context(int $post_id) : array {
    $type_slug = wp_get_post_terms($post_id, self::get_case_taxonomy('type'), ['fields' => 'slugs'])[0] ?? '';
    $species_id = (int) (wp_get_post_terms($post_id, self::get_case_taxonomy('species'), ['fields' => 'ids'])[0] ?? 0);
    $zone_id = (int) (wp_get_post_terms($post_id, self::get_case_taxonomy('zone'), ['fields' => 'ids'])[0] ?? 0);

    return [
      'post_id' => $post_id,
      'type' => self::get_case_type_canonical_slug((string) $type_slug),
      'species_id' => self::get_valid_term_id($species_id, self::get_case_taxonomy('species')),
      'zone_id' => self::get_valid_term_id($zone_id, self::get_case_taxonomy('zone')),
      'title' => get_the_title($post_id),
      'permalink' => get_permalink($post_id),
      'date' => (string) self::get_case_meta($post_id, 'date'),
    ];
  }

  private static function match_alert_to_case(array $alert, array $case_context) : array {
    $reasons = [];

    if (empty($alert['email'])) {
      return [false, ['missing_email']];
    }

    if (empty($alert['type'])) {
      return [false, ['missing_type']];
    }

    if ($alert['type'] !== ($case_context['type'] ?? '')) {
      return [false, ['type_mismatch']];
    }

    $reasons[] = 'type';

    if (!empty($alert['species_id'])) {
      if ((int) $alert['species_id'] !== (int) ($case_context['species_id'] ?? 0)) {
        return [false, ['species_mismatch']];
      }
      $reasons[] = 'species';
    }

    if (!empty($alert['zone_id'])) {
      if ((int) $alert['zone_id'] !== (int) ($case_context['zone_id'] ?? 0)) {
        return [false, ['zone_mismatch']];
      }
      $reasons[] = 'zone';
    }

    $matched = true;
    $matched = (bool) apply_filters('pm_alert_matches_case', $matched, $alert, $case_context, $reasons);
    return [$matched, $matched ? $reasons : ['filtered_out']];
  }

  private static function can_manage_plugin() : bool {
    return current_user_can('manage_options');
  }

  private static function can_current_user_create_case() : bool {
    if (!self::require_login_create_enabled()) {
      return true;
    }

    return is_user_logged_in();
  }

  private static function can_manage_case(int $post_id) : bool {
    if ($post_id <= 0 || get_post_type($post_id) !== self::CPT) {
      return false;
    }

    if (self::can_manage_plugin()) {
      return true;
    }

    $current_user_id = get_current_user_id();
    if ($current_user_id <= 0) {
      return false;
    }

    return $current_user_id === (int) get_post_field('post_author', $post_id);
  }

  private static function can_resolve_case(int $post_id) : bool {
    return self::can_manage_case($post_id);
  }

  private static function get_plugin_version() : string {
    static $version = null;

    if ($version !== null) {
      return $version;
    }

    $header_version = '';
    $plugin_data = get_file_data(PM_PLUGIN_FILE, ['Version' => 'Version'], 'plugin');
    if (is_array($plugin_data) && !empty($plugin_data['Version'])) {
      $header_version = sanitize_text_field((string) $plugin_data['Version']);
    }

    $version = $header_version !== '' ? $header_version : self::VERSION;

    if ($header_version !== '' && $header_version !== self::VERSION && class_exists('PM_Logger')) {
      PM_Logger::log('WARN', 'Plugin version mismatch detected', [
        'header_version' => $header_version,
        'class_version' => self::VERSION,
        'effective_version' => $version,
      ]);
    }

    return $version;
  }

  public static function init() : void {
    if (class_exists('PM_Logger')) { PM_Logger::log('INFO', 'PM init'); }

    // Prevent 404 on /casos/{slug} after installs/updates by flushing rewrite rules once.
    add_action('init', [__CLASS__, 'load_textdomain'], 5);
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

  public static function load_textdomain() : void {
    load_plugin_textdomain('pet-match', false, dirname(plugin_basename(PM_PLUGIN_FILE)) . '/languages');
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
    update_option('pm_pet_match_version', self::get_plugin_version());
  }

  public static function deactivate() : void {
    flush_rewrite_rules(false);
  }

  public static function maybe_flush_rewrite() : void {
    $stored = get_option('pm_pet_match_version');
    $current_version = self::get_plugin_version();
    if ($stored !== $current_version) {
      flush_rewrite_rules(false);
      update_option('pm_pet_match_version', $current_version);
    }
  }

  private static function get_settings() : array {
    $defaults = [
      'admin_email' => get_option('admin_email'),
      'admin_whatsapp' => '',
      'require_login_create' => 0,
      'asset_delivery_mode' => 'auto',
      'enable_external_google_fonts' => 1,
    ];
    $settings = get_option('pm_settings', []);
    if (!is_array($settings)) {
      $settings = [];
    }
    return array_merge($defaults, $settings);
  }

  private static function get_setting(string $key, $default = null) {
    $settings = self::get_settings();
    return array_key_exists($key, $settings) ? $settings[$key] : $default;
  }

  private static function get_notification_admin_email() : string {
    $email = sanitize_email((string) self::get_setting('admin_email', get_option('admin_email')));
    return is_email($email) ? $email : '';
  }

  private static function get_admin_whatsapp() : string {
    $whatsapp = self::get_valid_whatsapp(self::get_setting('admin_whatsapp', ''));
    return $whatsapp !== '' ? $whatsapp : '';
  }

  private static function require_login_create_enabled() : bool {
    return !empty(self::get_setting('require_login_create', 0));
  }

  private static function get_asset_delivery_mode() : string {
    $mode = sanitize_key((string) self::get_setting('asset_delivery_mode', 'auto'));
    return in_array($mode, ['auto', 'cdn', 'local'], true) ? $mode : 'auto';
  }

  private static function allow_external_google_fonts() : bool {
    return !empty(self::get_setting('enable_external_google_fonts', 1));
  }

  private static function get_create_case_return_url() : string {
    $current = get_permalink();
    if (is_string($current) && $current !== '') {
      return $current;
    }
    if (!empty($_SERVER['REQUEST_URI'])) {
      $request_uri = wp_unslash((string) $_SERVER['REQUEST_URI']);
      return home_url($request_uri);
    }
    return home_url('/');
  }

  private static function log_blocked_create_attempt(string $source) : void {
    self::log_event('WARN', 'case.create.blocked_login', 'Blocked unauthenticated case creation attempt', [
      'source' => $source,
      'request_uri' => self::get_request_path(),
      'request_method' => self::get_request_method(),
      'ip_hash' => self::get_request_ip_hash(),
      'user_agent' => self::get_request_user_agent_summary(),
    ]);
  }

  private static function render_login_required_notice() : string {
    $return_url = self::get_create_case_return_url();
    $login_url = wp_login_url($return_url);
    $register_url = wp_registration_url();

    $actions = '<div class="pm-form-actions">'
      . '<a class="pm-btn pm-btn-primary" href="' . esc_url($login_url) . '">' . esc_html__('Iniciar sesion', 'pet-match') . '</a>';

    if (is_string($register_url) && $register_url !== '') {
      $actions .= '<a class="pm-btn" href="' . esc_url($register_url) . '">' . esc_html__('Registrarme', 'pet-match') . '</a>';
    }

    $actions .= '</div>';

    return '<div class="pm-wrap pm-app"><div class="pm-callout"><strong>' . esc_html__('Para publicar un caso necesitas iniciar sesion.', 'pet-match') . '</strong><p class="pm-muted">' . esc_html__('Activamos esta restriccion para poder cuidar mejor las publicaciones y el seguimiento de cada caso.', 'pet-match') . '</p>' . $actions . '</div></div>';
  }

  private static function render_feedback_notice(string $message, string $type = 'error') : string {
    $class = ($type === 'success') ? 'pm-ok' : 'pm-error';
    $role = ($type === 'success') ? 'status' : 'alert';
    return '<div class="' . esc_attr($class) . '" role="' . esc_attr($role) . '" aria-live="polite">' . esc_html($message) . '</div>';
  }

  private static function get_request_feedback_notice(string $key) : string {
    $message = isset($_GET[$key]) ? sanitize_text_field(wp_unslash((string) $_GET[$key])) : '';
    if ($message === '') {
      return '';
    }
    $type_key = $key . '_type';
    $type = isset($_GET[$type_key]) ? sanitize_key((string) $_GET[$type_key]) : 'error';
    if (!in_array($type, ['success', 'error'], true)) {
      $type = 'error';
    }
    return self::render_feedback_notice($message, $type);
  }

  private static function redirect_with_notice(string $url, string $message, string $type = 'error') : void {
    $url = add_query_arg([
      'pm_notice' => $message,
      'pm_notice_type' => sanitize_key($type),
    ], $url);
    wp_safe_redirect($url);
    exit;
  }

  private static function log_validation_failure(string $context, string $reason, array $meta = []) : void {
    self::log_event('WARN', $context . '.validation', 'Validation rejected', array_merge([
      'reason' => $reason,
    ], $meta));
  }

  private static function get_request_method() : string {
    return isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash((string) $_SERVER['REQUEST_METHOD'])) : '';
  }

  private static function get_request_path() : string {
    if (empty($_SERVER['REQUEST_URI'])) {
      return '';
    }
    $request_uri = wp_unslash((string) $_SERVER['REQUEST_URI']);
    $path = wp_parse_url($request_uri, PHP_URL_PATH);
    return is_string($path) ? $path : '';
  }

  private static function get_request_ip_hash() : string {
    if (empty($_SERVER['REMOTE_ADDR'])) {
      return '';
    }
    return substr(sha1((string) wp_unslash($_SERVER['REMOTE_ADDR'])), 0, 12);
  }

  private static function get_request_user_agent_summary() : string {
    if (empty($_SERVER['HTTP_USER_AGENT'])) {
      return '';
    }
    $value = sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_USER_AGENT']));
    if (function_exists('mb_substr')) {
      return mb_substr($value, 0, 120);
    }
    return substr($value, 0, 120);
  }

  private static function get_log_actor_context() : array {
    return [
      'user_id' => get_current_user_id() ?: 0,
      'request_method' => self::get_request_method(),
      'request_path' => self::get_request_path(),
    ];
  }

  private static function log_event(string $level, string $event, string $message, array $context = []) : void {
    if (!class_exists('PM_Logger')) {
      return;
    }

    $base_context = array_merge([
      'event' => $event,
    ], self::get_log_actor_context());

    PM_Logger::log($level, $message, array_merge($base_context, $context));
  }

  private static function get_valid_term_id(int $term_id, string $taxonomy) : int {
    if ($term_id <= 0 || !taxonomy_exists($taxonomy)) {
      return 0;
    }
    $term = get_term($term_id, $taxonomy);
    if (!$term || is_wp_error($term)) {
      return 0;
    }
    return (int) $term->term_id;
  }

  private static function get_valid_case_date($value) : string {
    $date = sanitize_text_field(wp_unslash((string) $value));
    if ($date === '') {
      return '';
    }
    $dt = \DateTime::createFromFormat('Y-m-d', $date);
    if (!$dt || $dt->format('Y-m-d') !== $date) {
      return '';
    }
    return $date;
  }

  private static function get_valid_whatsapp($value) : string {
    $raw = sanitize_text_field(wp_unslash((string) $value));
    $digits = preg_replace('/\D+/', '', $raw);
    if ($digits === '') {
      return '';
    }
    $len = strlen($digits);
    if ($len < 10 || $len > 15) {
      return '';
    }
    return $digits;
  }

  private static function get_allowed_upload_mimes() : array {
    return [
      'jpg|jpeg|jpe' => 'image/jpeg',
      'png' => 'image/png',
      'gif' => 'image/gif',
      'webp' => 'image/webp',
    ];
  }

  private static function get_upload_error_message(int $error, int $index) : string {
    $position = $index + 1;
    switch ($error) {
      case UPLOAD_ERR_INI_SIZE:
      case UPLOAD_ERR_FORM_SIZE:
        return 'La imagen ' . $position . ' excede el tamano permitido.';
      case UPLOAD_ERR_PARTIAL:
        return 'La imagen ' . $position . ' no se subio completa.';
      case UPLOAD_ERR_NO_FILE:
        return 'La imagen ' . $position . ' no se recibio correctamente.';
      default:
        return 'La imagen ' . $position . ' no se pudo subir correctamente.';
    }
  }

  private static function validate_uploaded_images(array $files) {
    if (empty($files['name']) || !is_array($files['name'])) {
      return new \WP_Error('pm_upload_missing', 'Tenes que subir al menos una imagen valida.');
    }

    $valid_indexes = [];
    foreach ($files['name'] as $i => $name) {
      $name = is_string($name) ? trim($name) : '';
      if ($name === '') {
        continue;
      }
      $error = isset($files['error'][$i]) ? (int) $files['error'][$i] : UPLOAD_ERR_NO_FILE;
      $size = isset($files['size'][$i]) ? (int) $files['size'][$i] : 0;
      $tmp = isset($files['tmp_name'][$i]) ? (string) $files['tmp_name'][$i] : '';

      if ($error !== UPLOAD_ERR_OK) {
        return new \WP_Error('pm_upload_error', self::get_upload_error_message($error, (int) $i));
      }
      if ($size <= 0 || $tmp === '' || !file_exists($tmp) || !is_uploaded_file($tmp)) {
        return new \WP_Error('pm_upload_empty', 'La imagen ' . ($i + 1) . ' esta vacia o es invalida.');
      }

      $check = wp_check_filetype_and_ext($tmp, $name, self::get_allowed_upload_mimes());
      if (empty($check['type']) || strpos((string) $check['type'], 'image/') !== 0) {
        return new \WP_Error('pm_upload_type', 'La imagen ' . ($i + 1) . ' no tiene un formato permitido. Solo se aceptan JPG, PNG, GIF o WEBP.');
      }

      $valid_indexes[] = $i;
    }

    if (empty($valid_indexes)) {
      return new \WP_Error('pm_upload_missing', 'Tenes que subir al menos una imagen valida.');
    }

    return $valid_indexes;
  }

  private static function get_case_statuses() : array {
    return [
      'open' => [
        'label' => __('Abierto', 'pet-match'),
        'filter_label' => __('Abiertos', 'pet-match'),
        'badge_class' => 'pm-badge-blue',
      ],
      'in_contact' => [
        'label' => __('En contacto', 'pet-match'),
        'filter_label' => __('En contacto', 'pet-match'),
        'badge_class' => 'pm-badge-amber',
      ],
      'resolved' => [
        'label' => __('Resuelto', 'pet-match'),
        'filter_label' => __('Resueltos', 'pet-match'),
        'badge_class' => 'pm-badge-green',
      ],
      'closed' => [
        'label' => __('Cerrado', 'pet-match'),
        'filter_label' => __('Cerrados', 'pet-match'),
        'badge_class' => 'pm-badge-amber',
      ],
    ];
  }

  private static function get_valid_case_statuses() : array {
    return array_keys(self::get_case_statuses());
  }

  private static function normalize_case_status(string $status) : string {
    $status = sanitize_key($status);
    $legacy_map = [
      '' => 'open',
      'contact' => 'in_contact',
      'in-contact' => 'in_contact',
    ];
    if (isset($legacy_map[$status])) {
      $status = $legacy_map[$status];
    }
    if (!in_array($status, self::get_valid_case_statuses(), true)) {
      return 'open';
    }
    return $status;
  }

  private static function get_case_status(int $post_id) : string {
    $raw = self::get_case_meta($post_id, 'status');
    return self::normalize_case_status((string) $raw);
  }

  private static function update_case_status(int $post_id, string $status) : void {
    self::update_case_meta($post_id, 'status', $status);
  }

  private static function get_case_status_label(string $status, string $context = 'label') : string {
    $status = self::normalize_case_status($status);
    $statuses = self::get_case_statuses();
    $field = ($context === 'filter_label') ? 'filter_label' : 'label';
    return $statuses[$status][$field] ?? $statuses['open']['label'];
  }

  private static function get_case_status_meta_values(string $status) : array {
    $status = self::normalize_case_status($status);
    if ($status === 'in_contact') {
      return ['in_contact', 'contact'];
    }
    return [$status];
  }

  private static function get_case_status_badge_html(string $status) : string {
    $status = self::normalize_case_status($status);
    $statuses = self::get_case_statuses();
    $label = self::get_case_status_label($status);
    $class = $statuses[$status]['badge_class'] ?? 'pm-badge-blue';
    return '<span class="pm-badge ' . esc_attr($class) . '">' . esc_html($label) . '</span>';
  }

  private static function get_valid_coordinate($value) : ?float {
    if ($value === '' || $value === null) {
      return null;
    }
    if (!is_numeric($value)) {
      return null;
    }
    $coord = (float) $value;
    return is_finite($coord) ? $coord : null;
  }

  private static function get_valid_case_coordinates(int $post_id) : ?array {
    if ($post_id <= 0) {
      return null;
    }
    $lat = self::get_valid_coordinate(self::get_case_meta($post_id, 'lat'));
    $lng = self::get_valid_coordinate(self::get_case_meta($post_id, 'lng'));
    if ($lat === null || $lng === null) {
      return null;
    }
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
      return null;
    }
    return ['lat' => $lat, 'lng' => $lng];
  }

  private static function get_valid_radius_km($value, int $default = 10, int $min = 1, int $max = 200) : int {
    $radius = (int) $value;
    if ($radius <= 0) {
      $radius = $default;
    }
    if ($radius < $min) {
      $radius = $min;
    }
    if ($radius > $max) {
      $radius = $max;
    }
    return $radius;
  }

  private static function get_bounding_box(float $lat, float $lng, int $radius_km) : array {
    $earth_radius = 6371;
    $lat_delta = rad2deg($radius_km / $earth_radius);
    $cos_lat = cos(deg2rad($lat));
    $lng_delta = $cos_lat === 0.0 ? 180.0 : rad2deg($radius_km / $earth_radius / max(abs($cos_lat), 0.00001));

    return [
      'min_lat' => max(-90, $lat - $lat_delta),
      'max_lat' => min(90, $lat + $lat_delta),
      'min_lng' => max(-180, $lng - $lng_delta),
      'max_lng' => min(180, $lng + $lng_delta),
    ];
  }

  private static function calculate_distance_km(float $from_lat, float $from_lng, float $to_lat, float $to_lng) : float {
    $earth_radius = 6371;

    $lat_delta = deg2rad($to_lat - $from_lat);
    $lng_delta = deg2rad($to_lng - $from_lng);
    $from_lat_rad = deg2rad($from_lat);
    $to_lat_rad = deg2rad($to_lat);

    $a = sin($lat_delta / 2) * sin($lat_delta / 2)
      + cos($from_lat_rad) * cos($to_lat_rad) * sin($lng_delta / 2) * sin($lng_delta / 2);
    $c = 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));

    return $earth_radius * $c;
  }

  private static function localize_create_map_config() : void {
    wp_localize_script('pm-map', 'PM_MAP_CREATE', [
      'selector' => 'pm-map',
      'defaultLat' => -34.6630,
      'defaultLng' => -58.3660,
      'defaultZoom' => 13,
      'i18n' => [
        'geoFail' => 'No pudimos obtener tu ubicacion. Move el pin en el mapa.',
        'invalidConfig' => 'No se pudo inicializar el mapa de publicacion.',
      ],
    ]);
  }

  private static function localize_single_map_config(int $post_id) : bool {
    $coords = self::get_valid_case_coordinates($post_id);
    if (!$coords) {
      self::log_event('WARN', 'map.single.skipped', 'Single map skipped due to missing or invalid coordinates', ['post_id' => $post_id]);
      return false;
    }
    wp_localize_script('pm-map', 'PM_MAP_SINGLE', [
      'selector' => 'pm-map-single',
      'lat' => $coords['lat'],
      'lng' => $coords['lng'],
      'defaultZoom' => 15,
      'i18n' => [
        'invalidConfig' => 'No se pudo inicializar el mapa del caso.',
      ],
    ]);
    return true;
  }
}


