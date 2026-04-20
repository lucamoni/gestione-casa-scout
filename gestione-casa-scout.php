<?php
/**
 * Plugin Name: Gestione Casa Scout
 * Description: Sistema cucito su misura per la casa scout: form contatti con salvataggio nel database, Dashboard Admin per la gestione e calendario richieste. Utilizzare [gcs_booking_form] per il modulo e [gcs_calendar] per il calendario.
 * Version: 1.8.6
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

// Plugin Update Checker
require_once plugin_dir_path( __FILE__ ) . 'includes/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/lucamoni/gestione-casa-scout/',
    __FILE__,
    'gestione-casa-scout'
);

// Set the branch that contains the updates.
$myUpdateChecker->setBranch('main');

// Forza la pulizia della cache se necessario (opzionale, meglio rimuovere in produzione)
add_action('admin_init', function() {
    if (isset($_GET['force_puc_check'])) {
        delete_site_transient('update_plugins');
        delete_transient('puc_update_check_gestione-casa-scout');
        wp_die('<b>Successo:</b> Cache degli aggiornamenti pulita. <br><br>Puoi tornare alla <a href="' . admin_url('plugins.php') . '">lista dei plugin</a> per controllare l\'aggiornamento.');
    }
});

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