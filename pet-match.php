<?php
/**
 * Plugin Name: Pet Match (MVP)
 * Description: MVP para casos de mascotas perdidas/encontradas con mapa Leaflet (OpenStreetMap) + shortcode de alta.
 * Version: 0.5.3.21
 * Author: Fluxo Studios (Internal)
 * Text Domain: pet-match
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

if (!defined('PM_PLUGIN_FILE')) {
  define('PM_PLUGIN_FILE', __FILE__);
}
if (!defined('PM_PLUGIN_DIR')) {
  define('PM_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('PM_PLUGIN_URL')) {
  define('PM_PLUGIN_URL', plugin_dir_url(__FILE__));
}

require_once PM_PLUGIN_DIR . 'includes/class-pm-logger.php';
require_once PM_PLUGIN_DIR . 'includes/trait-pm-pet-match-core.php';
require_once PM_PLUGIN_DIR . 'includes/trait-pm-pet-match-assets.php';
require_once PM_PLUGIN_DIR . 'includes/trait-pm-pet-match-taxonomies.php';
require_once PM_PLUGIN_DIR . 'includes/trait-pm-pet-match-frontend.php';
require_once PM_PLUGIN_DIR . 'includes/trait-pm-pet-match-forms.php';
require_once PM_PLUGIN_DIR . 'includes/trait-pm-pet-match-admin.php';
require_once PM_PLUGIN_DIR . 'includes/class-pm-pet-match.php';

register_activation_hook(PM_PLUGIN_FILE, ['PM_Pet_Match', 'activate']);
register_deactivation_hook(PM_PLUGIN_FILE, ['PM_Pet_Match', 'deactivate']);

try {
  PM_Pet_Match::init();
} catch (\Throwable $e) {
  if (class_exists('PM_Logger')) {
    PM_Logger::log('FATAL', 'Init exception: ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
  }
}
