<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GCS_Admin_Page {
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
        add_action( 'admin_post_gcs_update_status', array( __CLASS__, 'handle_status_update' ) );
        add_action( 'admin_post_gcs_delete_request', array( __CLASS__, 'handle_request_deletion' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_scripts' ) );
    }

    public static function add_admin_menu() {
        add_menu_page(
            'Gestione Casa Scout',
            'Casa Scout',
            'manage_options',
            'gestione-casa-scout',
            array( __CLASS__, 'render_admin_dashboard' ),
            'dashicons-building',
            26
        );
        add_submenu_page(
            'gestione-casa-scout',
            'Richieste Form',
            'Richieste',
            'manage_options',
            'gestione-casa-scout',
            array( __CLASS__, 'render_admin_dashboard' )
        );
        // We keep aliases for legacy links but they will all show the dashboard
        add_submenu_page(
            'gestione-casa-scout',
            'Calendario',
            'Calendario',
            'manage_options',
            'gcs-admin-calendar',
            array( __CLASS__, 'render_admin_dashboard' )
        );
        add_submenu_page(
            'gestione-casa-scout',
            'Impostazioni',
            'Impostazioni',
            'manage_options',
            'gcs-admin-settings',
            array( __CLASS__, 'render_admin_dashboard' )
        );
    }

    public static function enqueue_admin_scripts( $hook ) {
        if ( $hook != 'toplevel_page_gestione-casa-scout' ) {
            return;
        }
        // Qui si possono aggiungere CSS/JS per l'admin se necessario.
    }

    public static function render_admin_dashboard() {
        $active_tab = 'requests';
        if (isset($_GET['page'])) {
            if ($_GET['page'] === 'gcs-admin-calendar') $active_tab = 'calendar';
            if ($_GET['page'] === 'gcs-admin-settings') $active_tab = 'settings';
        }
        
        // Handle POSTs for Requests (AJAX or regular)
        if (isset($_POST['action']) && $_POST['action'] === 'gcs_update_status') {
            self::handle_status_update();
        }
        if (isset($_POST['action']) && $_POST['action'] === 'gcs_delete_request') {
            self::handle_request_deletion();
        }

        ?>
        <div class="wrap gcs-admin-dashboard" id="gcs_admin_wrapper">
            <h1>Gestione Casa Scout <span style="font-size:12px; vertical-align:middle; background:#1a4581; color:#fff; padding:2px 8px; border-radius:10px; margin-left:10px;">v1.3.9</span></h1>
            
            <h2 class="nav-tab-wrapper" style="margin-bottom: 20px;">
                <a href="#" class="nav-tab <?php echo $active_tab == 'requests' ? 'nav-tab-active' : ''; ?>" onclick="gcsAdminTab('requests')">Richieste</a>
                <a href="#" class="nav-tab <?php echo $active_tab == 'calendar' ? 'nav-tab-active' : ''; ?>" onclick="gcsAdminTab('calendar')">Calendario</a>
                <a href="#" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>" onclick="gcsAdminTab('settings')">Impostazioni</a>
            </h2>

            <div id="tab_requests" class="gcs-tab-content" style="<?php echo $active_tab == 'requests' ? '' : 'display:none;'; ?>">
                <?php self::render_admin_page(); ?>
            </div>
            <div id="tab_calendar" class="gcs-tab-content" style="<?php echo $active_tab == 'calendar' ? '' : 'display:none;'; ?>">
                <?php GCS_Calendar_Page::render_calendar_page(); ?>
            </div>
            <div id="tab_settings" class="gcs-tab-content" style="<?php echo $active_tab == 'settings' ? '' : 'display:none;'; ?>">
                <?php GCS_Settings_Page::render_settings_page(); ?>
            </div>

            <script>
                function gcsAdminTab(tab) {
                    jQuery('.gcs-tab-content').hide();
                    jQuery('#tab_' + tab).show();
                    jQuery('.nav-tab').removeClass('nav-tab-active');
                    jQuery('.nav-tab').each(function(){
                        if(jQuery(this).text().toLowerCase().includes(tab === 'requests' ? 'richieste' : (tab === 'calendar' ? 'calendario' : 'impostazioni'))) {
                            jQuery(this).addClass('nav-tab-active');
                        }
                    });
                }

                // AJAX for Admin status updates
                jQuery(document).on('submit', '#gcs_admin_wrapper form', function(e) {
                    var $form = jQuery(this);
                    if ($form.attr('action') && $form.attr('action').includes('admin-post.php')) {
                        e.preventDefault();
                        var formData = $form.serialize();
                        $form.css('opacity', '0.5');
                        jQuery.post(ajaxurl.replace('admin-ajax.php', 'admin-post.php'), formData, function() {
                            // Re-load only the requests part
                            location.reload(); // Temporary simpler solution for admin
                        });
                    }
                });
            </script>
            <style>
                #gcs_admin_wrapper .wrap { margin: 0; padding: 0; }
                #gcs_admin_wrapper h1 { margin-bottom: 10px; }
            </style>
        </div>
        <?php
    }

    public static function render_admin_page() {
        $requests = GCS_DB_Manager::get_requests();
        ?>
        <div class="gcs-requests-list">
            <table class="wp-list-table widefat fixed striped table-view-list">
                <thead>
                    <tr>
                        <th class="manage-column column-primary">Gruppo / Reparto</th>
                        <th>Contatti</th>
                        <th>Periodo Previsto</th>
                        <th>Ospiti</th>
                        <th style="width:120px;">Stato</th>
                        <th style="width:200px;">Azioni Rapide</th>
                    </tr>
                </thead>
                <tbody id="the-list">
                    <?php if ( empty( $requests ) ) : ?>
                        <tr class="no-items">
                            <td class="colspanchange" colspan="6">Nessuna richiesta di prenotazione trovata.</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $requests as $req ) : ?>
                            <tr id="request-<?php echo esc_attr( $req->id ); ?>">
                                <td class="title column-title column-primary">
                                    <strong><?php echo esc_html( wp_unslash( $req->group_name ) ); ?></strong>
                                    <div class="row-actions visible">Ricevuta il: <?php echo esc_html( date( 'd/m/Y H:i', strtotime( $req->created_at ) ) ); ?></div>
                                </td>
                                <td><?php echo esc_html( $req->contact_email ); ?></td>
                                <td><?php echo esc_html( date( 'd/m/Y', strtotime( $req->start_date ) ) ); ?> - <?php echo esc_html( date( 'd/m/Y', strtotime( $req->end_date ) ) ); ?></td>
                                <td><?php echo esc_html( $req->guests_count ); ?> pax</td>
                                <td>
                                    <?php 
                                        $label = $req->status == 'confirmed' ? 'Confermata' : ($req->status == 'rejected' ? 'Rifiutata' : 'In attesa');
                                        $color = $req->status == 'confirmed' ? '#46b450' : ($req->status == 'rejected' ? '#dc3232' : '#ffb900');
                                    ?>
                                    <span style="color:<?php echo $color; ?>; font-weight:bold;"><?php echo $label; ?></span>
                                </td>
                                <td>
                                    <form method="POST" style="display:inline-block;">
                                        <input type="hidden" name="action" value="gcs_update_status">
                                        <?php wp_nonce_field( 'gcs_update_status_' . $req->id ); ?>
                                        <input type="hidden" name="request_id" value="<?php echo $req->id; ?>">
                                        <select name="new_status" onchange="this.form.submit()" style="font-size:12px;">
                                            <option value="pending" <?php selected($req->status, 'pending'); ?>>In attesa</option>
                                            <option value="confirmed" <?php selected($req->status, 'confirmed'); ?>>Confermata</option>
                                            <option value="rejected" <?php selected($req->status, 'rejected'); ?>>Rifiutata</option>
                                        </select>
                                    </form>
                                    <form method="POST" style="display:inline-block;" onsubmit="return confirm('Sicuro?');">
                                        <input type="hidden" name="action" value="gcs_delete_request">
                                        <?php wp_nonce_field( 'gcs_delete_request_' . $req->id ); ?>
                                        <input type="hidden" name="request_id" value="<?php echo $req->id; ?>">
                                        <button type="submit" class="button-link-delete" style="color:#a00; text-decoration:none; margin-left:10px;">Elimina</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function handle_status_update() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die();
        $request_id = intval( $_POST['request_id'] ?? 0 );
        if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'gcs_update_status_' . $request_id ) ) wp_die();
        $new_status = sanitize_text_field( $_POST['new_status'] ?? 'pending' );
        GCS_DB_Manager::update_status( $request_id, $new_status );
        
        if (!defined('DOING_AJAX')) {
            wp_safe_redirect(admin_url('admin.php?page=gestione-casa-scout&message=status_updated'));
            exit;
        }
    }

    public static function handle_request_deletion() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die();
        $request_id = intval( $_POST['request_id'] ?? 0 );
        if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'gcs_delete_request_' . $request_id ) ) wp_die();
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'gcs_requests', array( 'id' => $request_id ) );
        
        if (!defined('DOING_AJAX')) {
            wp_safe_redirect(admin_url('admin.php?page=gestione-casa-scout&message=request_deleted'));
            exit;
        }
    }
}
