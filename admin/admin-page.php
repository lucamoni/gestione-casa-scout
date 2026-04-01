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
            array( __CLASS__, 'render_admin_page' ),
            'dashicons-building',
            26
        );
        add_submenu_page(
            'gestione-casa-scout',
            'Richieste Form',
            'Richieste Form',
            'manage_options',
            'gestione-casa-scout',
            array( __CLASS__, 'render_admin_page' )
        );
        add_submenu_page(
            'gestione-casa-scout',
            'Calendario',
            'Calendario',
            'manage_options',
            'gcs-calendar',
            array( 'GCS_Calendar_Page', 'render_calendar_page' )
        );
        add_submenu_page(
            'gestione-casa-scout',
            'Impostazioni Form',
            'Impostazioni',
            'manage_options',
            'gcs-settings',
            array( 'GCS_Settings_Page', 'render_settings_page' )
        );
    }

    public static function enqueue_admin_scripts( $hook ) {
        if ( $hook != 'toplevel_page_gestione-casa-scout' ) {
            return;
        }
        // Qui si possono aggiungere CSS/JS per l'admin se necessario.
    }

    public static function render_admin_page() {
        $requests = GCS_DB_Manager::get_requests();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Gestione Prenotazioni Casa Scout</h1>
            <hr class="wp-header-end">
            
            <?php if ( isset( $_GET['message'] ) ) : ?>
                <?php if ( $_GET['message'] == 'status_updated' ) : ?>
                    <div class="notice notice-success is-dismissible">
                        <p>Stato della prenotazione aggiornato con successo.</p>
                    </div>
                <?php elseif ( $_GET['message'] == 'request_deleted' ) : ?>
                    <div class="notice notice-info is-dismissible">
                        <p>La richiesta è stata eliminata. Note: Elimina solo dopo aver confermato un annullamento.</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Filtro Rapido per stato -->
            <ul class="subsubsub">
                <li class="all"><a href="?page=gestione-casa-scout" class="current">Tutte le richieste</a></li>
            </ul>

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
                            <td class="colspanchange" colspan="6">Nessuna richiesta di prenotazione trovata al momento.</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $requests as $req ) : ?>
                            <tr id="request-<?php echo esc_attr( $req->id ); ?>">
                                <td class="title column-title has-row-actions column-primary">
                                    <strong><?php echo esc_html( wp_unslash( $req->group_name ) ); ?></strong>
                                    <div class="row-actions visible">
                                        Ricevuta il: <?php echo esc_html( date( 'd/m/Y H:i', strtotime( $req->created_at ) ) ); ?>
                                    </div>
                                    <button type="button" class="toggle-row"><span class="screen-reader-text">Mostra più dettagli</span></button>
                                </td>
                                
                                <td class="column-contacts" data-colname="Contatti">
                                    <a href="mailto:<?php echo esc_attr( wp_unslash( $req->contact_email ) ); ?>"><?php echo esc_html( wp_unslash( $req->contact_email ) ); ?></a>
                                    
                                    <?php
                                    $webmail_url = get_option('gcs_webmail_url');
                                    $webmail_user = get_option('gcs_webmail_user');
                                    $webmail_pass = get_option('gcs_webmail_pass');
                                    if ($webmail_url && $webmail_user && $webmail_pass) :
                                    ?>
                                    <form action="<?php echo esc_url($webmail_url); ?>" method="POST" target="_blank" style="margin-top:8px;">
                                        <input type="hidden" name="fUsername" value="<?php echo esc_attr($webmail_user); ?>">
                                        <input type="hidden" name="fPassword" value="<?php echo esc_attr($webmail_pass); ?>">
                                        <button type="submit" class="button button-small" style="font-size:11px; padding:0 8px; border-radius:3px;">Rispondi da Webmail 📧</button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="column-date" data-colname="Periodo">
                                    Da: <strong><?php echo esc_html( date( 'd/m/Y', strtotime( $req->start_date ) ) ); ?></strong><br>
                                    A: <strong><?php echo esc_html( date( 'd/m/Y', strtotime( $req->end_date ) ) ); ?></strong>
                                </td>
                                
                                <td class="column-guests" data-colname="Ospiti">
                                    <?php echo esc_html( $req->guests_count ); ?> pax
                                </td>
                                
                                <td class="column-status" data-colname="Stato">
                                    <?php 
                                        $bg_color = '#f0f0f1';
                                        $text_color = '#3c434a';
                                        $label = ucfirst(esc_html($req->status));
                                        
                                        if ( $req->status === 'confirmed' ) {
                                            $bg_color = '#edfaeb';
                                            $text_color = '#007017';
                                            $label = 'Confermata';
                                        } elseif ( $req->status === 'rejected' ) {
                                            $bg_color = '#fcf0f1';
                                            $text_color = '#d63638';
                                            $label = 'Rifiutata';
                                        } elseif ( $req->status === 'pending' ) {
                                            $bg_color = '#fef8ee';
                                            $text_color = '#b32d2e';
                                            $label = 'In Attesa';
                                        }
                                    ?>
                                    <span style="display:inline-block; padding: 4px 10px; border-radius: 4px; font-weight: 600; font-size: 11px; background-color: <?php echo esc_attr($bg_color); ?>; color: <?php echo esc_attr($text_color); ?>;">
                                        <?php echo esc_html( $label ); ?>
                                    </span>
                                </td>

                                <td class="column-actions" data-colname="Azioni Rapide">
                                    <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="POST" style="display: flex; gap: 5px;">
                                        <input type="hidden" name="action" value="gcs_update_status">
                                        <?php wp_nonce_field( 'gcs_update_status_' . $req->id ); ?>
                                        <input type="hidden" name="request_id" value="<?php echo esc_attr( $req->id ); ?>">
                                        
                                        <select name="new_status" style="max-width: 110px;">
                                            <option value="pending" <?php selected( $req->status, 'pending' ); ?>>In Attesa</option>
                                            <option value="confirmed" <?php selected( $req->status, 'confirmed' ); ?>>Confermata</option>
                                            <option value="rejected" <?php selected( $req->status, 'rejected' ); ?>>Rifiutata</option>
                                        </select>
                                        
                                        <button type="submit" class="button button-small action">Salva</button>
                                    </form>
                                    
                                    <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="POST" style="margin-top: 5px;" onsubmit="return confirm('Sei sicuro di voler eliminare questa richiesta? L\'azione è irreversibile.');">
                                        <input type="hidden" name="action" value="gcs_delete_request">
                                        <?php wp_nonce_field( 'gcs_delete_request_' . $req->id ); ?>
                                        <input type="hidden" name="request_id" value="<?php echo esc_attr( $req->id ); ?>">
                                        <button type="submit" class="button-link-delete" style="color: #d63638; text-decoration: none; font-size: 13px;">Elimina Richiesta</button>
                                    </form>
                                </td>
                            </tr>
                            <?php if ( ! empty( $req->message ) ) : ?>
                            <tr style="background: transparent;">
                                <td colspan="6" style="padding: 15px 20px; border-top: none; background-color: #fdfdfd; font-style: italic;">
                                    <strong>Messaggio lasciato dall'utente:</strong><br/>
                                    <?php echo nl2br( esc_html( wp_unslash( $req->message ) ) ); ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function handle_status_update() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Accesso non autorizzato.' );
        }

        $request_id = intval( $_POST['request_id'] ?? 0 );
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'gcs_update_status_' . $request_id ) ) {
            wp_die( 'Azione non valida.' );
        }

        $new_status = sanitize_text_field( $_POST['new_status'] ?? 'pending' );
        if ( in_array( $new_status, array( 'pending', 'confirmed', 'rejected' ) ) ) {
            GCS_DB_Manager::update_status( $request_id, $new_status );
        }

        $redirect_url = add_query_arg( array( 'page' => 'gestione-casa-scout', 'message' => 'status_updated' ), admin_url( 'admin.php' ) );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    public static function handle_request_deletion() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Accesso non autorizzato.' );
        }

        $request_id = intval( $_POST['request_id'] ?? 0 );
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'gcs_delete_request_' . $request_id ) ) {
            wp_die( 'Azione non valida.' );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'gcs_requests';
        $wpdb->delete( $table_name, array( 'id' => $request_id ), array( '%d' ) );

        $redirect_url = add_query_arg( array( 'page' => 'gestione-casa-scout', 'message' => 'request_deleted' ), admin_url( 'admin.php' ) );
        wp_safe_redirect( $redirect_url );
        exit;
    }
}
