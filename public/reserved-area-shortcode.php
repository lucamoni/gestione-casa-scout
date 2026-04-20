<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Gestione Area Riservata
 * Versione 1.5.4 - INTEGRATION & SYNC FIX
 */
class GCS_Reserved_Area_Shortcode {
    public static function init() {
        add_shortcode( 'gcs_reserved_area', array( __CLASS__, 'render_reserved_area' ) );
        add_action( 'template_redirect', array( __CLASS__, 'handle_actions' ) );
    }

    private static function is_authorized() {
        $users_opt = get_option('gcs_reserved_users', '');
        if (empty($users_opt)) return false;
        if (isset($_COOKIE['gcs_reserved_auth'])) {
            $parts = explode('|', $_COOKIE['gcs_reserved_auth'], 2);
            if (count($parts) === 2) {
                $u = $parts[0]; $h = $parts[1];
                $lines = explode("\n", str_replace("\r", "", $users_opt));
                foreach ($lines as $line) {
                    $l = trim($line);
                    if (strpos($l, ':') !== false) {
                        list($user, $pass) = explode(':', $l, 2);
                        if (trim($user) === $u && md5($u . trim($pass)) === $h) return true;
                    }
                }
            }
        }
        return false;
    }

    public static function handle_actions() {
        if (isset($_GET['gcs_logout'])) {
            setcookie('gcs_reserved_auth', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
            wp_safe_redirect(remove_query_arg('gcs_logout'));
            exit;
        }

        if (isset($_POST['gcs_reserved_login_submit'])) {
            $u = sanitize_text_field($_POST['gcs_username']);
            $p = sanitize_text_field($_POST['gcs_password']);
            $users_opt = get_option('gcs_reserved_users', '');
            $lines = explode("\n", str_replace("\r", "", $users_opt));
            $found = false;
            foreach ($lines as $line) {
                if (strpos($line, ':') !== false) {
                    list($du, $dp) = explode(':', trim($line), 2);
                    if ($u === trim($du) && $p === trim($dp)) {
                        setcookie('gcs_reserved_auth', $u . '|' . md5($u . $p), time() + 86400, COOKIEPATH, COOKIE_DOMAIN);
                        $found = true; break;
                    }
                }
            }
            wp_safe_redirect($found ? remove_query_arg('gcs_login_error') : add_query_arg('gcs_login_error', '1'));
            exit;
        }

        if (self::is_authorized()) {
            global $wpdb;
            $table = $wpdb->prefix . 'gcs_requests';

            if (isset($_POST['gcs_front_update_status']) || isset($_POST['gcs_front_delete_req']) || isset($_POST['gcs_edit_event_action']) || isset($_POST['gcs_front_add_manual']) || isset($_POST['gcs_front_settings_save'])) {
                
                if (isset($_POST['gcs_front_update_status'])) {
                    GCS_DB_Manager::update_status(intval($_POST['request_id']), sanitize_text_field($_POST['status']));
                } elseif (isset($_POST['gcs_front_delete_req'])) {
                    $wpdb->delete($table, array('id' => intval($_POST['request_id'])));
                } elseif (isset($_POST['gcs_edit_event_action'])) {
                    $id = intval($_POST['edit_id']);
                    if ($_POST['gcs_event_op'] === 'delete') {
                        $wpdb->delete($table, array('id' => $id));
                    } else {
                        $wpdb->update($table, array(
                            'group_name' => sanitize_text_field($_POST['edit_title']),
                            'start_date' => sanitize_text_field($_POST['edit_start']),
                            'end_date' => sanitize_text_field($_POST['edit_end'])
                        ), array('id' => $id));
                    }
                } elseif (isset($_POST['gcs_front_add_manual'])) {
                    $wpdb->insert($table, array(
                        'group_name' => sanitize_text_field($_POST['event_title']),
                        'contact_email' => 'manuale@calendario.local',
                        'start_date' => sanitize_text_field($_POST['event_start']),
                        'end_date' => sanitize_text_field($_POST['event_end']),
                        'guests_count' => 0,
                        'message' => 'Inserimento manuale',
                        'status' => 'confirmed'
                    ));
                } elseif (isset($_POST['gcs_front_settings_save'])) {
                    update_option('gcs_notification_email', sanitize_email($_POST['gcs_notification_email']));
                    update_option('gcs_reserved_users', wp_unslash($_POST['gcs_reserved_users']));
                }

                if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                    wp_safe_redirect(remove_query_arg(['msg', 'gcs_login_error']));
                    exit;
                }
            }
        }
    }

    public static function render_reserved_area() {
        if (!self::is_authorized()) return self::render_login_form();

        global $wpdb;
        $table = $wpdb->prefix . 'gcs_requests';
        $pending_count = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'pending' AND contact_email != 'manuale@calendario.local'");
        $confirmed_count = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'confirmed'");

        ob_start(); ?>
        <div class="gcs-dashboard-wrapper">
            <style>
                :root {
                    --gcs-primary: #1a4581;
                    --gcs-primary-light: #2c6abf;
                    --gcs-secondary: #a1d1d0;
                    --gcs-bg: #f8fafc;
                    --gcs-card-bg: #ffffff;
                    --gcs-text: #1e293b;
                    --gcs-text-light: #64748b;
                    --gcs-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
                    --gcs-radius: 12px;
                }

                .gcs-dashboard-wrapper { font-family: 'Inter', sans-serif; color: var(--gcs-text); background: var(--gcs-bg); padding: 20px; border-radius: var(--gcs-radius); }
                .gcs-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; background: #fff; padding: 15px 25px; border-radius: var(--gcs-radius); box-shadow: var(--gcs-shadow); }
                .gcs-header h2 { margin: 0; font-size: 24px; font-weight: 800; color: var(--gcs-primary); }
                
                .gcs-stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 25px; }
                .gcs-stat-card { background: #fff; padding: 15px; border-radius: var(--gcs-radius); box-shadow: var(--gcs-shadow); display: flex; align-items: center; gap: 12px; border: 1px solid #e2e8f0; }
                .stat-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
                .stat-info { display: flex; flex-direction: column; }
                .stat-label { font-size: 10px; font-weight: 700; color: var(--gcs-text-light); text-transform: uppercase; }
                .stat-val { font-size: 18px; font-weight: 800; color: var(--gcs-primary); }

                .gcs-filter-bar { background: #fff; padding: 10px 15px; border-bottom: 1px solid #e2e8f0; display: flex; gap: 10px; align-items: center; }
                .gcs-filter-btn { padding: 5px 12px; border-radius: 8px; border: 1px solid #e2e8f0; background: #f8fafc; color: #64748b; font-size: 12px; font-weight: 700; cursor: pointer; text-decoration: none; }
                .gcs-filter-btn.active { background: var(--gcs-primary); color: #fff; border-color: var(--gcs-primary); }
                .gcs-logout { 
                    text-decoration: none; 
                    background: #fee2e2; 
                    color: #b91c1c; 
                    padding: 8px 16px; 
                    border-radius: 8px; 
                    font-weight: 600; 
                    font-size: 14px;
                    transition: all 0.2s;
                }
                .gcs-logout:hover { background: #fecaca; transform: translateY(-1px); }

                .gcs-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
                .gcs-stat-card { 
                    background: var(--gcs-card-bg); 
                    padding: 20px; 
                    border-radius: var(--gcs-radius); 
                    border: 1px solid #e2e8f0;
                    display: flex;
                    flex-direction: column;
                }
                .gcs-stat-card span { font-size: 14px; font-weight: 600; color: var(--gcs-text-light); }
                .gcs-stat-card strong { font-size: 24px; font-weight: 800; color: var(--gcs-primary); }

                .gcs-tabs { display: flex; gap: 10px; margin-bottom: 25px; background: #e2e8f0; padding: 5px; border-radius: 10px; width: fit-content; }
                .gcs-tab-btn { 
                    padding: 10px 20px; 
                    border: none; 
                    background: none; 
                    cursor: pointer; 
                    font-weight: 700; 
                    color: var(--gcs-text-light);
                    border-radius: 8px;
                    transition: all 0.2s;
                }
                .gcs-tab-btn.active { background: #fff; color: var(--gcs-primary); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }

                /* Requests Styling */
                .gcs-card { background: var(--gcs-card-bg); border-radius: var(--gcs-radius); overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
                .gcs-table { width: 100%; border-collapse: collapse; }
                .gcs-table th { background: #f1f5f9; padding: 15px; text-align: left; font-size: 13px; font-weight: 700; color: var(--gcs-text-light); text-transform: uppercase; letter-spacing: 0.05em; }
                .gcs-table td { padding: 20px 15px; border-bottom: 1px solid #f1f5f9; }
                .gcs-table tr:hover { background: #f8fafc; }

                .badge { padding: 4px 10px; border-radius: 9999px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
                .badge-pending { background: #fef3c7; color: #92400e; }
                .badge-confirmed { background: #dcfce7; color: #166534; }
                .badge-rejected { background: #fee2e2; color: #991b1b; }

                .gcs-action-btn { 
                    background: #fff; border: 1px solid #e2e8f0; padding: 6px 12px; border-radius: 6px; 
                    font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.2s;
                    display: inline-flex; align-items: center; gap: 5px;
                }
                .btn-approve:hover { background: var(--gcs-primary); color: #fff; border-color: var(--gcs-primary); }
                .btn-reject:hover { background: #b91c1c; color: #fff; border-color: #b91c1c; }
                .btn-delete:hover { background: #000; color: #fff; border-color: #000; }

                /* Calendar Styling */
                .cal-nav { display: flex; justify-content: space-between; align-items: center; padding: 20px; background: #fff; border-bottom: 1px solid #eee; }
                .cal-nav h3 { margin: 0; font-size: 18px; font-weight: 700; color: var(--gcs-primary); }
                .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; background: #eee; }
                .cal-day-header { background: #f8fafc; padding: 10px; text-align: center; font-size: 11px; font-weight: 800; color: var(--gcs-text-light); }
                .cal-day { background: #fff; min-height: 120px; padding: 8px; position: relative; }
                .cal-day.today { background: #f0fdf4; }
                .cal-day-num { font-size: 12px; font-weight: 600; color: #94a3b8; margin-bottom: 5px; display: block; }
                
                .event-bar { 
                    padding: 6px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; color: #fff; 
                    margin-bottom: 4px; cursor: pointer; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
                    transition: transform 0.1s; position: relative; z-index: 10;
                }
                .event-bar:hover { transform: scale(1.02); filter: brightness(1.1); z-index: 20; }
                .event-manual { background: #ef4444; }
                .event-request { background: var(--gcs-primary); }

                .event-bar.cont-prev {
                    border-top-left-radius: 0;
                    border-bottom-left-radius: 0;
                    margin-left: -9px;
                    width: calc(100% + 9px);
                    padding-left: 17px;
                }
                .event-bar.cont-next {
                    border-top-right-radius: 0;
                    border-bottom-right-radius: 0;
                    margin-right: -9px;
                    width: calc(100% + 9px);
                }
                .event-bar.cont-prev.cont-next { width: calc(100% + 18px); }
                
                .cal-day { overflow: visible !important; }

                /* Modal glassmorphism */
                .gcs-modal { 
                    display:none; position:fixed; z-index:100000; top:0; left:0; width:100%; height:100%; 
                    background:rgba(0,0,0,0.3); backdrop-filter: blur(4px); align-items:center; justify-content:center; 
                }
                .gcs-modal-content { 
                    background:#fff; padding:30px; border-radius:16px; width:90%; max-width:450px; 
                    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
                }
            </style>

            <div class="gcs-header">
                <h2>Area Riservata</h2>
                <a href="<?php echo esc_url(add_query_arg('gcs_logout', '1')); ?>" class="gcs-logout">Disconnetti</a>
            </div>

            <div class="gcs-stats-grid">
                <div class="gcs-stat-card">
                    <div class="stat-icon" style="background: #fff8eb; color: #b45309;">⏳</div>
                    <div class="stat-info">
                        <span class="stat-label">In Attesa</span>
                        <span class="stat-val"><?php echo $pending_count; ?></span>
                    </div>
                </div>
                <div class="gcs-stat-card">
                    <div class="stat-icon" style="background: #ecfdf5; color: #059669;">✅</div>
                    <div class="stat-info">
                        <span class="stat-label">Confermate</span>
                        <span class="stat-val"><?php echo $confirmed_count; ?></span>
                    </div>
                </div>
                <div class="gcs-stat-card">
                    <div class="stat-icon" style="background: #e0f2fe; color: #1a4581;">⚡</div>
                    <div class="stat-info">
                        <span class="stat-label">Impegni Attivi</span>
                        <span class="stat-val"><?php echo ($pending_count + $confirmed_count); ?></span>
                    </div>
                </div>
            </div>

            <div class="gcs-tabs">
                <button class="gcs-tab-btn active" id="btn_requests" onclick="gcsShowTab('requests')">📦 Gestione Richieste</button>
                <button class="gcs-tab-btn" id="btn_calendar" onclick="gcsShowTab('calendar')">📅 Calendario</button>
                <button class="gcs-tab-btn" id="btn_settings" onclick="gcsShowTab('settings')">⚙️ Impostazioni</button>
            </div>

            <div id="tab_requests" class="gcs-tab-content"><?php echo self::render_requests_management(); ?></div>
            <div id="tab_calendar" class="gcs-tab-content" style="display:none;"><?php echo self::render_calendar_management(); ?></div>
            <div id="tab_settings" class="gcs-tab-content" style="display:none;"><?php echo self::render_settings_management(); ?></div>

            <div id="gcsEditModal" class="gcs-modal">
                <div class="gcs-modal-content">
                    <h3 style="margin-top:0; font-size:20px;">Modifica Evento</h3>
                    <form method="POST">
                        <input type="hidden" name="gcs_edit_event_action" value="1">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <input type="hidden" name="gcs_event_op" id="event_op" value="save">
                        <div style="margin-bottom:15px;">
                            <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">Titolo Gruppo</label>
                            <input type="text" name="edit_title" id="edit_title" style="width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:8px;">
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:20px;">
                            <div><label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">Inizio</label><input type="date" name="edit_start" id="edit_start" style="width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:8px;"></div>
                            <div><label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">Fine</label><input type="date" name="edit_end" id="edit_end" style="width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:8px;"></div>
                        </div>
                        <div style="display:flex; justify-content:flex-end; gap:10px;">
                            <button type="submit" style="background:var(--gcs-primary); color:#fff; border:none; padding:10px 25px; border-radius:8px; font-weight:700; cursor:pointer;">Salva Modifiche</button>
                        </div>
                        <button type="button" onclick="document.getElementById('gcsEditModal').style.display='none'" style="display:block; width:100%; margin-top:15px; background:none; border:none; color:var(--gcs-text-light); cursor:pointer; font-size:13px;">Annulla</button>
                    </form>
                </div>
            </div>

            <script>
            function gcsShowTab(tab) {
                document.querySelectorAll('.gcs-tab-content').forEach(el => el.style.display = 'none');
                document.querySelectorAll('.gcs-tab-btn').forEach(btn => btn.classList.remove('active'));
                document.getElementById('tab_' + tab).style.display = 'block';
                document.getElementById('btn_' + tab).classList.add('active');
            }
            function gcsEditEvent(id, title, start, end) {
                document.getElementById('edit_id').value = id;
                document.getElementById('edit_title').value = title;
                document.getElementById('edit_start').value = start;
                document.getElementById('edit_end').value = end;
                document.getElementById('gcsEditModal').style.display = 'flex';
            }
            function bindAjaxForms() {
                // Intercettiamo i bottoni di azione nelle richieste
                document.querySelectorAll('.gcs-action-btn').forEach(btn => {
                   btn.onclick = function(e) {
                       if (this.dataset.confirm && !confirm(this.dataset.confirm)) return;
                       
                       let form = this.closest('form');
                       let formData = new FormData(form);
                       if (this.name) formData.append(this.name, this.value);

                       // Effetto loading visuale
                       let row = this.closest('tr');
                       if (row) row.style.opacity = '0.5';

                       fetch(window.location.href, { 
                           method: 'POST', 
                           body: formData, 
                           headers: { 'X-Requested-With': 'XMLHttpRequest' } 
                       })
                       .then(r => fetch(window.location.href)) // Ricarica i dati
                       .then(r => r.text())
                       .then(html => {
                           let doc = new DOMParser().parseFromString(html, 'text/html');
                           document.getElementById('tab_requests').innerHTML = doc.getElementById('tab_requests').innerHTML;
                           document.getElementById('tab_calendar').innerHTML = doc.getElementById('tab_calendar').innerHTML;
                           // Aggiorna anche i contatori se possibile
                           let newWrapper = doc.querySelector('.gcs-dashboard-wrapper');
                           if (newWrapper) {
                               document.querySelector('.gcs-stats').innerHTML = newWrapper.querySelector('.gcs-stats').innerHTML;
                           }
                           bindAjaxForms();
                       });
                   };
                });
            }
            document.addEventListener('DOMContentLoaded', bindAjaxForms);
            </script>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function render_requests_management() {
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
        
        ob_start(); ?>
        <div class="gcs-card" style="padding:0; overflow:hidden;">
            <div class="gcs-filter-bar">
                <span style="font-size:12px; font-weight:800; text-transform:uppercase; color:#94a3b8; margin-right:10px;">Filtra:</span>
                <a href="?status_filter=active" class="gcs-filter-btn <?php echo $filter == 'active' ? 'active' : ''; ?>">⚡ Attive</a>
                <a href="?status_filter=confirmed" class="gcs-filter-btn <?php echo $filter == 'confirmed' ? 'active' : ''; ?>">✅ Confermate</a>
                <a href="?status_filter=rejected" class="gcs-filter-btn <?php echo $filter == 'rejected' ? 'active' : ''; ?>">❌ Rifiutate</a>
                <a href="?status_filter=all" class="gcs-filter-btn <?php echo $filter == 'all' ? 'active' : ''; ?>">📋 Tutte</a>
            </div>
            <table class="gcs-table">
                <thead>
                    <tr>
                        <th>Gruppo / Contatto</th>
                        <th>Periodo & Ospiti</th>
                        <th>Stato</th>
                        <th style="text-align:right;">Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr><td colspan="4" style="padding:40px; text-align:center; color:var(--gcs-text-light);">Nessuna richiesta trovata.</td></tr>
                    <?php else: foreach ($requests as $r): ?>
                        <tr>
                            <td>
                                <div style="font-weight:700; font-size:15px;"><?php echo esc_html($r->group_name); ?></div>
                                <div style="font-size:12px; color:var(--gcs-text-light);"><?php echo esc_html($r->contact_email); ?></div>
                            </td>
                            <td>
                                <div style="font-size:13px; font-weight:600;">
                                    📅 <?php echo date('d/m/Y', strtotime($r->start_date)); ?> - <?php echo date('d/m/Y', strtotime($r->end_date)); ?>
                                </div>
                                <div style="font-size:12px; color:var(--gcs-primary); font-weight:700;">
                                    👤 <?php echo esc_html($r->guests_count); ?> persone
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $r->status; ?>">
                                    <?php 
                                        if ($r->status === 'pending') echo 'In Attesa';
                                        elseif ($r->status === 'confirmed') echo 'Confermata';
                                        else echo 'Rifiutata';
                                    ?>
                                </span>
                                <?php if($r->status === 'confirmed'): ?>
                                    <div style="font-size:10px; color:var(--gcs-primary); font-weight:700; margin-top:5px;">🔗 Nel Calendario</div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;">
                                <div style="display:inline-flex; align-items:center; gap:8px;">
                                    <form method="POST" class="ajax-form" style="margin:0;">
                                        <input type="hidden" name="request_id" value="<?php echo $r->id; ?>">
                                        <input type="hidden" name="gcs_front_update_status" value="1">
                                        <select name="status" onchange="this.form.submit()" style="padding:5px; border-radius:6px; font-size:12px; border:1px solid #e2e8f0; font-weight:600;">
                                            <option value="pending" <?php selected($r->status, 'pending'); ?>>Cambia in: Attesa</option>
                                            <option value="confirmed" <?php selected($r->status, 'confirmed'); ?>>Cambia in: Conferma</option>
                                            <option value="rejected" <?php selected($r->status, 'rejected'); ?>>Cambia in: Rifiuta</option>
                                        </select>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php return ob_get_clean();
    }

    private static function render_calendar_management() {
        global $wpdb;
        $table = $wpdb->prefix . 'gcs_requests';
        $m = isset($_GET['c_month']) ? intval($_GET['c_month']) : date('n');
        $y = isset($_GET['c_year']) ? intval($_GET['c_year']) : date('Y');
        $start_of_month = sprintf("%04d-%02d-01", $y, $m);
        $end_of_month = date("Y-m-t", strtotime($start_of_month));
        
        // Fetch events
        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE status = 'confirmed' AND (start_date <= %s AND end_date >= %s) ORDER BY start_date ASC", 
            $end_of_month, $start_of_month
        ));

        // Month Names
        $months = ['Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
        
        ob_start(); ?>
        <div style="display:grid; grid-template-columns: 2.5fr 1fr; gap:25px; align-items: start;">
            <div class="gcs-card">
                <div class="cal-nav">
                    <a href="?c_month=<?php echo $m==1?12:$m-1; ?>&c_year=<?php echo $m==1?$y-1:$y; ?>#tab_calendar" style="text-decoration:none; font-size:20px;">⬅️</a>
                    <h3><?php echo $months[$m-1] . ' ' . $y; ?></h3>
                    <a href="?c_month=<?php echo $m==12?1:$m+1; ?>&c_year=<?php echo $m==12?$y+1:$y; ?>#tab_calendar" style="text-decoration:none; font-size:20px;">➡️</a>
                </div>
                <div class="cal-grid">
                    <?php foreach(['LUN','MAR','MER','GIO','VEN','SAB','DOM'] as $d) echo '<div class="cal-day-header">'.$d.'</div>'; ?>
                    <?php
                    $first_day_of_week = date('N', strtotime($start_of_month)); // 1 (Mon) to 7 (Sun)
                    for ($i = 1; $i < $first_day_of_week; $i++) echo '<div style="background:#f9fafb;"></div>';
                    
                    $days_in_month = date('t', strtotime($start_of_month));
                    for ($d = 1; $d <= $days_in_month; $d++) {
                        $cur_date = sprintf("%04d-%02d-%02d", $y, $m, $d);
                        $is_today = ($cur_date == date('Y-m-d'));
                        ?>
                        <div class="cal-day <?php echo $is_today ? 'today' : ''; ?>">
                            <span class="cal-day-num"><?php echo $d; ?></span>
                            <?php 
                            $week_day_index = date('N', strtotime($cur_date)); // 1-7
                            foreach($events as $e) {
                                if($cur_date >= $e->start_date && $cur_date <= $e->end_date) {
                                    $is_start = ($cur_date == $e->start_date);
                                    $is_end = ($cur_date == $e->end_date);
                                    $type_class = ($e->contact_email == 'manuale@calendario.local') ? 'event-manual' : 'event-request';
                                    
                                    $extra_classes = [];
                                    if (!$is_start) $extra_classes[] = 'cont-prev';
                                    if (!$is_end) $extra_classes[] = 'cont-next';
                                    
                                    $show_text = ($is_start || $d == 1 || $week_day_index == 1);
                                    $text_style = $show_text ? '' : 'color:transparent;';
                                    ?>
                                    <div onclick="gcsEditEvent(<?php echo $e->id; ?>, '<?php echo esc_js($e->group_name); ?>', '<?php echo $e->start_date; ?>', '<?php echo $e->end_date; ?>')" 
                                         class="event-bar <?php echo $type_class; ?> <?php echo implode(' ', $extra_classes); ?>" 
                                         style="<?php echo $text_style; ?>"
                                         title="<?php echo esc_attr($e->group_name); ?>">
                                        <?php echo esc_html($e->group_name); ?>
                                    </div>
                                    <?php
                                }
                            }
                            ?>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>

            <div class="gcs-card" style="padding:25px;">
                <h4 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px; margin-bottom:20px;">➕ Nuovo Impegno</h4>
                <form method="POST">
                    <input type="hidden" name="gcs_front_add_manual" value="1">
                    <div style="margin-bottom:15px;">
                        <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">Nome Gruppo / Nota</label>
                        <input type="text" name="event_title" placeholder="Es: Gruppo Roma 12" required style="width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:8px;">
                    </div>
                    <div style="margin-bottom:15px;">
                        <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">Data Inizio</label>
                        <input type="date" name="event_start" required style="width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:8px;">
                    </div>
                    <div style="margin-bottom:20px;">
                        <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">Data Fine</label>
                        <input type="date" name="event_end" required style="width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:8px;">
                    </div>
                    <button type="submit" style="width:100%; background:var(--gcs-primary); color:#fff; border:none; padding:12px; border-radius:8px; font-weight:700; cursor:pointer;">Aggiungi al Calendario</button>
                </form>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    private static function render_settings_management() {
        ob_start(); ?>
        <div style="max-width:600px;">
            <div class="gcs-card" style="padding:30px;">
                <h4 style="margin-top:0; margin-bottom:20px;">Configurazione Sistema</h4>
                <form method="POST">
                    <input type="hidden" name="gcs_front_settings_save" value="1">
                    <div style="margin-bottom:20px;">
                        <label style="display:block; font-size:13px; font-weight:700; margin-bottom:8px;">Email Notifiche</label>
                        <p style="font-size:12px; color:var(--gcs-text-light); margin-bottom:10px;">Le nuove richieste verranno inviate a questo indirizzo.</p>
                        <input type="email" name="gcs_notification_email" value="<?php echo esc_attr(get_option('gcs_notification_email')); ?>" style="width:100%; padding:12px; border:1px solid #e2e8f0; border-radius:8px;">
                    </div>
                    
                    <div style="margin-bottom:25px;">
                        <label style="display:block; font-size:13px; font-weight:700; margin-bottom:8px;">Utenti Area Riservata</label>
                        <p style="font-size:12px; color:var(--gcs-text-light); margin-bottom:10px;">Formato: <code>username:password</code> (una riga per utente)</p>
                        <textarea name="gcs_reserved_users" style="width:100%; height:100px; padding:12px; border:1px solid #e2e8f0; border-radius:8px; font-family:monospace;"><?php echo esc_textarea(get_option('gcs_reserved_users')); ?></textarea>
                    </div>

                    <button type="submit" style="background:var(--gcs-primary); color:#fff; border:none; padding:12px 30px; border-radius:8px; font-weight:700; cursor:pointer;">Salva Impostazioni</button>
                </form>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    private static function render_login_form() {
        ob_start(); ?>
        <div class="gcs-dashboard-wrapper" style="max-width:400px; margin:60px auto; padding:40px;">
            <style>
                .login-title { text-align: center; margin-bottom: 30px; }
                .login-title h2 { margin:0; color: #1a4581; font-size: 24px; font-weight: 800; }
                .login-title p { color: #64748b; font-size: 14px; margin-top:5px; font-weight: 600; }
                .login-input { width:100%; padding:12px; margin-bottom:15px; border:1px solid #e2e8f0; border-radius:8px; outline:none; transition: all 0.2s; box-sizing: border-box; }
                .login-input:focus { border-color: #1a4581; box-shadow: 0 0 0 3px rgba(26, 69, 129, 0.1); }
                .login-btn { width:100%; background:#1a4581; color:#fff; border:none; padding:14px; border-radius:8px; font-weight: 700; cursor:pointer; font-size:15px; transition: all 0.2s; }
                .login-btn:hover { background: #0d264a; transform: translateY(-1px); }
                .error-msg { background:#fee2e2; color:#b91c1c; padding:12px; border-radius:8px; margin-bottom:20px; font-size:13px; text-align:center; font-weight: 600; border: 1px solid #fecaca; }
            </style>
            
            <div class="login-title">
                <h2>Accesso Riservato</h2>
                <p>Gestione Casa Scout</p>
            </div>

            <?php if (isset($_GET['gcs_login_error'])): ?>
                <div class="error-msg">Credenziali non valide. Riprova.</div>
            <?php endif; ?>

            <form method="POST">
                <input type="text" name="gcs_username" placeholder="Nome utente" required class="login-input">
                <input type="password" name="gcs_password" placeholder="Password" required class="login-input">
                <button type="submit" name="gcs_reserved_login_submit" class="login-btn">Accedi al Sistema</button>
            </form>
        </div>
        <?php return ob_get_clean();
    }
}
