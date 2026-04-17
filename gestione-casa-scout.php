<?php
/**
 * Plugin Name: Gestione Casa Scout
 * Description: Sistema cucito su misura per la casa scout: form contatti con salvataggio nel database, Dashboard Admin per la gestione e calendario richieste. Utilizzare [gcs_booking_form] per il modulo e [gcs_calendar] per il calendario.
 * Version: 1.5.2
 * Author: Luca Moni
 * Text Domain: gestione-casa-scout
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Includo i file necessari
require_once plugin_dir_path( __FILE__ ) . 'includes/db-manager.php';
require_once plugin_dir_path( __FILE__ ) . 'public/form-shortcode.php';
require_once plugin_dir_path( __FILE__ ) . 'public/calendar-shortcode.php';
require_once plugin_dir_path( __FILE__ ) . 'public/reserved-area-shortcode.php';
require_once plugin_dir_path( __FILE__ ) . 'public/ics-feed.php';
require_once plugin_dir_path( __FILE__ ) . 'admin/admin-page.php';
require_once plugin_dir_path( __FILE__ ) . 'admin/settings-page.php';
require_once plugin_dir_path( __FILE__ ) . 'admin/calendar-page.php';

// Plugin Update Checker - Reset Totale
require_once plugin_dir_path( __FILE__ ) . 'includes/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/*
// Forza i parametri GitHub direttamente sull'oggetto API
$api = $myUpdateChecker->getVcsApi();
if ($api instanceof \YahnisElsts\PluginUpdateChecker\v5\Vcs\GitHubApi) {
    $api->user = 'lucamoni';
    $api->repo = 'gestione-casa-scout';
}
$myUpdateChecker->setBranch('main');
*/

// Forza la pulizia della cache ad ogni caricamento admin per questa sessione di debug
add_action('admin_init', function() {
    delete_site_transient('update_plugins');
    $transients = array(
        'puc_update_check_gestione-casa-scout',
        'puc_update_check_gcs-plugin-updates',
        'puc_update_check_gcs-final-slug-152'
    );
    foreach($transients as $t) delete_transient($t);
});

/*
// Intercetta la richiesta HTTP e correggi l'URL se contiene ancora i segnaposto
add_filter('pre_http_request', function($pre, $args, $url) {
    if (strpos($url, 'api.github.com') !== false && strpos($url, ':user') !== false) {
        $url = str_replace(':user/:repo', 'lucamoni/gestione-casa-scout', $url);
        return wp_remote_request($url, $url_args);
    }
    return $pre;
}, 20, 3);
*/

// Hook di attivazione per creare la tabella nel database al momento dell'installazione
register_activation_hook( __FILE__, array( 'GCS_DB_Manager', 'create_table' ) );

// Inizializzazione di tutti i componenti alla corretta action di WordPress
add_action( 'plugins_loaded', 'gcs_init_plugin' );
function gcs_init_plugin() {
    GCS_Form_Shortcode::init();
    GCS_Calendar_Shortcode::init();
    GCS_Reserved_Area_Shortcode::init();
    GCS_ICS_Feed::init();
    GCS_Admin_Page::init();
    GCS_Settings_Page::init();
}