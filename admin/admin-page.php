<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GCS_Admin_Page {
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'handle_admin_posts' ) );
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

    public static function handle_admin_posts() {
        if (!isset($_REQUEST['gcs_admin_action'])) return;
        if (!current_user_can('manage_options')) return;

        if (!isset($_REQUEST['gcs_admin_nonce']) || !wp_verify_nonce($_REQUEST['gcs_admin_nonce'], 'gcs_admin_dashboard_action')) {
            wp_die('Errore di sicurezza: Nonce non valido.');
        }

        $action = $_REQUEST['gcs_admin_action'];
        $request_id = intval($_REQUEST['request_id'] ?? 0);
        
        if ($action === 'gcs_update_status') {
            $new_status = sanitize_text_field($_POST['new_status'] ?? 'pending');
            GCS_DB_Manager::update_status($request_id, $new_status);
            wp_safe_redirect(admin_url('admin.php?page=gestione-casa-scout&message=status_updated'));
            exit;
        }
        
        if ($action === 'gcs_delete_request') {
            global $wpdb;
            $wpdb->delete($wpdb->prefix . 'gcs_requests', array('id' => $request_id));
            wp_safe_redirect(admin_url('admin.php?page=gestione-casa-scout&message=request_deleted'));
            exit;
        }
    }

    public static function render_admin_dashboard() {
        global $wpdb;
        $table = $wpdb->prefix . 'gcs_requests';
        $cur_month = date('n');
        $cur_year = date('Y');
        $pending = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'pending' AND contact_email != 'manuale@calendario.local'");
        $confirmed_month = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE status = 'confirmed' AND month(start_date) = %d AND year(start_date) = %d", $cur_month, $cur_year));
        $total_active = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status IN ('pending', 'confirmed') AND contact_email != 'manuale@calendario.local'");

        $active_tab = 'requests';
        if (isset($_GET['page'])) {
            if ($_GET['page'] === 'gcs-admin-calendar') $active_tab = 'calendar';
            if ($_GET['page'] === 'gcs-admin-settings') $active_tab = 'settings';
        }

        $message_html = '';
        if (isset($_GET['message'])) {
            $msg = sanitize_text_field($_GET['message']);
            if ($msg == 'status_updated') $message_html = '<div class="notice notice-success is-dismissible" style="margin: 20px 0; border-radius: 8px;"><p>✅ Operazione completata!</p></div>';
            if ($msg == 'request_deleted') $message_html = '<div class="notice notice-success is-dismissible" style="margin: 20px 0; border-radius: 8px;"><p>🗑️ Richiesta eliminata definitivamente.</p></div>';
        }

        ?>
        <div class="wrap gcs-admin-dashboard" id="gcs_admin_wrapper">
            <style>
                :root {
                    --gcs-primary: #1a4581;
                    --gcs-secondary: #a1d1d0;
                    --gcs-bg: #f0f2f5;
                    --gcs-white: #ffffff;
                    --gcs-text: #1e293b;
                    --gcs-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
                }
                .gcs-admin-dashboard { font-family: 'Inter', -apple-system, sans-serif; }
                .gcs-admin-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 25px; background: var(--gcs-white); padding: 20px 30px; border-radius: 12px; box-shadow: var(--gcs-shadow); }
                .gcs-admin-header h1 { margin: 0 !important; color: var(--gcs-primary); font-weight: 800; font-size: 28px !important; }
                .gcs-version { background: var(--gcs-primary); color: #fff; padding: 4px 10px; border-radius: 20px; font-size: 11px; vertical-align: middle; margin-left: 10px; }
                
                .gcs-admin-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
                .gcs-stat-item { background: var(--gcs-white); padding: 25px; border-radius: 12px; box-shadow: var(--gcs-shadow); border-left: 5px solid var(--gcs-primary); }
                .gcs-stat-item.pending { border-left-color: #f59e0b; }
                .gcs-stat-item.confirmed { border-left-color: #10b981; }
                .gcs-stat-label { display: block; color: #64748b; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.05em; }
                .gcs-stat-value { display: block; color: var(--gcs-text); font-size: 32px; font-weight: 800; margin-top: 5px; }

                .gcs-nav-tabs { display: flex; gap: 10px; background: #e2e8f0; padding: 5px; border-radius: 12px; margin-bottom: 25px; width: fit-content; }
                .gcs-nav-tab { padding: 10px 25px; text-decoration: none; color: #64748b; font-weight: 700; border-radius: 8px; transition: all 0.2s; border: none; cursor: pointer; background: transparent; }
                .gcs-nav-tab.active { background: var(--gcs-white); color: var(--gcs-primary); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
                .gcs-nav-tab:hover:not(.active) { color: var(--gcs-text); }

                .gcs-admin-card { background: var(--gcs-white); padding: 0; border-radius: 12px; box-shadow: var(--gcs-shadow); overflow: hidden; border: 1px solid #e2e8f0; }
                
                /* Custom table scrollbar */
                .gcs-table-container { overflow-x: auto; }
            </style>

            <div class="gcs-admin-header">
                <h1>Gestione Casa Scout <span class="gcs-version">v1.8.7</span></h1>
            </div>

            <?php echo $message_html; ?>

            <div class="gcs-admin-stats">
                <div class="gcs-stat-item pending">
                    <span class="gcs-stat-label">📩 Richieste Nuove</span>
                    <span class="gcs-stat-value"><?php echo $pending; ?></span>
                </div>
                <div class="gcs-stat-item confirmed">
                    <span class="gcs-stat-label">📅 Confermate (Mese)</span>
                    <span class="gcs-stat-value"><?php echo $confirmed_month; ?></span>
                </div>
                <div class="gcs-stat-item">
                    <span class="gcs-stat-label">🏛️ Totale Impegni Attivi</span>
                    <span class="gcs-stat-value"><?php echo $total_active; ?></span>
                </div>
            </div>

            <div class="gcs-nav-tabs">
                <button class="gcs-nav-tab <?php echo $active_tab == 'requests' ? 'active' : ''; ?>" onclick="gcsAdminTab('requests')">📦 Gestione Richieste</button>
                <button class="gcs-nav-tab <?php echo $active_tab == 'calendar' ? 'active' : ''; ?>" onclick="gcsAdminTab('calendar')">📅 Dashboard Calendario</button>
                <button class="gcs-nav-tab <?php echo $active_tab == 'settings' ? 'active' : ''; ?>" onclick="gcsAdminTab('settings')">⚙️ Configurazione</button>
            </div>

            <div id="tab_requests" class="gcs-tab-content" style="<?php echo $active_tab == 'requests' ? '' : 'display:none;'; ?>">
                <div class="gcs-admin-card"><?php self::render_admin_page(); ?></div>
            </div>
            <div id="tab_calendar" class="gcs-tab-content" style="<?php echo $active_tab == 'calendar' ? '' : 'display:none;'; ?>">
                <div class="gcs-admin-card"><?php GCS_Calendar_Page::render_calendar_page(); ?></div>
            </div>
            <div id="tab_settings" class="gcs-tab-content" style="<?php echo $active_tab == 'settings' ? '' : 'display:none;'; ?>">
                <div class="gcs-admin-card"><?php GCS_Settings_Page::render_settings_page(); ?></div>
            </div>

            <script>
                function gcsAdminTab(tab) {
                    jQuery('.gcs-tab-content').hide();
                    jQuery('#tab_' + tab).show();
                    jQuery('.gcs-nav-tab').removeClass('active');
                    // Find button by text
                    if (tab === 'requests') jQuery('.gcs-nav-tab:contains("Richieste")').addClass('active');
                    if (tab === 'calendar') jQuery('.gcs-nav-tab:contains("Calendario")').addClass('active');
                    if (tab === 'settings') jQuery('.gcs-nav-tab:contains("Configurazione")').addClass('active');
                }
            </script>
        </div>
        <?php
    }

    public static function render_admin_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'gcs_requests';
        
        $filter = $_GET['status_filter'] ?? 'active';
        $where = "WHERE contact_email != 'manuale@calendario.local'";
        
        if ($filter == 'active') {
            $where .= " AND status NOT IN ('rejected')";
        } elseif ($filter == 'rejected') {
            $where .= " AND status = 'rejected'";
        } elseif ($filter == 'confirmed') {
            $where .= " AND status = 'confirmed'";
        }
        
        $requests = $wpdb->get_results("SELECT * FROM $table $where ORDER BY created_at DESC");
        ?>
        <div class="gcs-requests-list">
            <style>
                .gcs-admin-table { width: 100%; border-collapse: collapse; margin-top: 0; }
                .gcs-admin-table th { background: #f8fafc; padding: 15px; text-align: left; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; }
                .gcs-admin-table td { padding: 15px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
                .gcs-admin-table tr:hover { background: #f8fafc; }
                
                .admin-badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; display: inline-block; }
                .admin-badge-pending { background: #fef3c7; color: #92400e; }
                .admin-badge-confirmed { background: #dcfce7; color: #166534; }
                .admin-badge-rejected { background: #fee2e2; color: #991b1b; }
                
                .admin-sync-tag { font-size: 10px; background: #e0f2fe; color: #1a4581; padding: 2px 6px; border-radius: 4px; font-weight: 700; margin-top: 5px; display: inline-flex; align-items: center; gap: 3px; }
                
                .admin-action-select { padding: 5px; border-radius: 6px; border: 1px solid #e2e8f0; font-size: 12px; font-weight: 600; cursor: pointer; }
                .admin-delete-btn { color: #ef4444; text-decoration: none; font-size: 18px; margin-left: 10px; cursor: pointer; transition: transform 0.2s; border:none; background:none; }
                .admin-delete-btn:hover { transform: scale(1.2); }

                .gcs-filter-bar { padding: 15px 20px; background: #fff; border-bottom: 1px solid #e2e8f0; display: flex; gap: 15px; align-items: center; }
                .gcs-filter-link { text-decoration: none; color: #64748b; font-weight: 600; font-size: 13px; padding: 5px 12px; border-radius: 6px; transition: all 0.2s; }
                .gcs-filter-link.active { background: var(--gcs-primary); color: #fff; }
            </style>

            <div class="gcs-filter-bar">
                <span style="font-size:12px; font-weight:800; text-transform:uppercase; color:#94a3b8; margin-right:10px;">Filtra per:</span>
                <a href="?page=gestione-casa-scout&status_filter=active" class="gcs-filter-link <?php echo $filter == 'active' ? 'active' : ''; ?>">⚡ Attive</a>
                <a href="?page=gestione-casa-scout&status_filter=confirmed" class="gcs-filter-link <?php echo $filter == 'confirmed' ? 'active' : ''; ?>">✅ Confermate</a>
                <a href="?page=gestione-casa-scout&status_filter=rejected" class="gcs-filter-link <?php echo $filter == 'rejected' ? 'active' : ''; ?>">❌ Rifiutate</a>
                <a href="?page=gestione-casa-scout&status_filter=all" class="gcs-filter-link <?php echo $filter == 'all' ? 'active' : ''; ?>">📋 Tutte</a>
            </div>

            <div class="gcs-table-container">
                <table class="gcs-admin-table">
                    <thead>
                        <tr>
                            <th>Gruppo / Contatto</th>
                            <th>Dettagli Soggiorno</th>
                            <th>Messaggio / Note</th>
                            <th>Stato</th>
                            <th style="width:180px; text-align:right;">Gestione</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $requests ) ) : ?>
                            <tr><td colspan="5" style="padding:50px; text-align:center; color:#64748b;">Nessuna richiesta trovata.</td></tr>
                        <?php else : ?>
                            <?php foreach ( $requests as $req ) : ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:700; color:var(--gcs-text);"><?php echo esc_html( wp_unslash( $req->group_name ) ); ?></div>
                                        <div style="font-size:12px; color:#64748b;"><?php echo esc_html( $req->contact_email ); ?></div>
                                        <div style="font-size:10px; color:#94a3b8; margin-top:3px;">ID: #<?php echo $req->id; ?> • Ricevuta: <?php echo date('d/m/y', strtotime($req->created_at)); ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight:600; font-size:13px;">📅 <?php echo date('d/m/Y', strtotime($req->start_date)); ?> - <?php echo date('d/m/Y', strtotime($req->end_date)); ?></div>
                                        <div style="font-size:12px; color:var(--gcs-primary); font-weight:700;">👤 <?php echo esc_html( $req->guests_count ); ?> persone</div>
                                    </td>
                                    <td style="font-size:12px; color:#475569; max-width:250px;">
                                        <?php echo !empty($req->message) ? esc_html(wp_trim_words($req->message, 15)) : '<em>Nessun messaggio</em>'; ?>
                                    </td>
                                    <td>
                                        <span class="admin-badge admin-badge-<?php echo $req->status; ?>">
                                            <?php 
                                            if ($req->status == 'confirmed') echo 'Confermata';
                                            elseif ($req->status == 'rejected') echo 'Rifiutata';
                                            else echo 'In attesa';
                                            ?>
                                        </span>
                                        <?php if($req->status == 'confirmed'): ?>
                                            <br/>
                                            <div class="admin-sync-tag">🔗 Sincronizzato con Calendario</div>
                                            <a href="#" onclick="gcsAdminTab('calendar'); return false;" style="font-size:10px; color:var(--gcs-primary); display:block; margin-top:5px; font-weight:700;">👁️ Visualizza nel Calendario</a>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:right; white-space:nowrap;">
                                        <div style="display:inline-flex; align-items:center; gap:5px;">
                                            <form method="POST" action="<?php echo admin_url('admin.php?page=gestione-casa-scout'); ?>" style="margin:0;">
                                                <input type="hidden" name="gcs_admin_action" value="gcs_update_status">
                                                <?php wp_nonce_field('gcs_admin_dashboard_action', 'gcs_admin_nonce'); ?>
                                                <input type="hidden" name="request_id" value="<?php echo $req->id; ?>">
                                                <select name="new_status" onchange="this.form.submit()" class="admin-action-select">
                                                    <option value="pending" <?php selected($req->status, 'pending'); ?>>Cambia in: Attesa</option>
                                                    <option value="confirmed" <?php selected($req->status, 'confirmed'); ?>>Cambia in: Conferma</option>
                                                    <option value="rejected" <?php selected($req->status, 'rejected'); ?>>Cambia in: Rifiuta</option>
                                                </select>
                                            </form>
                                            <?php if ($req->status === 'rejected'): ?>
                                            <form method="POST" action="<?php echo admin_url('admin.php?page=gestione-casa-scout'); ?>" style="margin:0;" onsubmit="return confirm('ELIMINARE DEFINITIVAMENTE QUESTA PRENOTAZIONE RIFIUTATA?');">
                                                <input type="hidden" name="gcs_admin_action" value="gcs_delete_request">
                                                <?php wp_nonce_field('gcs_admin_dashboard_action', 'gcs_admin_nonce'); ?>
                                                <input type="hidden" name="request_id" value="<?php echo $req->id; ?>">
                                                <button type="submit" class="admin-delete-btn" style="background:#fff; color:#d63638; border:1px solid #d63638; padding:5px 10px; border-radius:6px; font-size:11px; font-weight:700; cursor:pointer;" title="Elimina definitivamente">Elimina</button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public static function handle_status_update() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die('Accesso negato.');
        $request_id = intval( $_POST['request_id'] ?? 0 );
        $nonce = $_POST['_wpnonce'] ?? '';
        
        if ( ! wp_verify_nonce( $nonce, 'gcs_update_status_' . $request_id ) ) {
            wp_die('Errore di sicurezza: Nonce non valido per aggiornamento stato.');
        }

        $new_status = sanitize_text_field( $_POST['new_status'] ?? 'pending' );
        GCS_DB_Manager::update_status( $request_id, $new_status );
        
        wp_safe_redirect(admin_url('admin.php?page=gestione-casa-scout&message=status_updated'));
        exit;
    }

    public static function handle_request_deletion() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die('Accesso negato.');
        $request_id = intval( $_POST['request_id'] ?? 0 );
        $nonce = $_POST['_wpnonce'] ?? '';

        if ( ! wp_verify_nonce( $nonce, 'gcs_delete_request_' . $request_id ) ) {
            wp_die('Errore di sicurezza: Nonce non valido per eliminazione.');
        }

        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'gcs_requests', array( 'id' => $request_id ) );
        
        wp_safe_redirect(admin_url('admin.php?page=gestione-casa-scout&message=request_deleted'));
        exit;
    }
}
