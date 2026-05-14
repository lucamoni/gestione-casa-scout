<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Gestione Area Riservata
 * Versione 1.9.6 - RITORNO AL LOOK PREMIUM & SYNC FIX
 */
class GCS_Reserved_Area_Shortcode {
    public static function init() {
        add_shortcode( 'gcs_reserved_area', array( __CLASS__, 'render_reserved_area' ) );
        add_action( 'template_redirect', array( __CLASS__, 'handle_actions' ) );
        add_action( 'wp_ajax_gcs_fetch_calendar', array( __CLASS__, 'ajax_fetch_calendar' ) );
    }

    public static function ajax_fetch_calendar() {
        if (!self::is_authorized()) wp_die('Unauthorized');
        echo self::render_calendar_management();
        wp_die();
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
                    wp_safe_redirect(remove_query_arg(['msg', 'gcs_login_error', 'status_filter']));
                    exit;
                }
            }
        }
    }

    public static function render_reserved_area() {
        if (!self::is_authorized()) return self::render_login_form();

        global $wpdb;
        $table = $wpdb->prefix . 'gcs_requests';
        
        $primary_color = get_option('gcs_style_title_color', '#1a4581');
        $btn_bg = get_option('gcs_style_btn_bg', '#1a4581');
        $btn_radius = get_option('gcs_style_btn_radius', '12px');

        $pending_count = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status IN ('pending', 'in attesa', 'In attesa') AND contact_email != 'manuale@calendario.local'");
        $confirmed_count = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status IN ('confirmed', 'confermata', 'Confermata') AND contact_email != 'manuale@calendario.local'");

        ob_start(); ?>
        <div class="gcs-dashboard-wrapper">
            <style>
                :root {
                    --gcs-primary: <?php echo $primary_color; ?>;
                    --gcs-btn-bg: <?php echo $btn_bg; ?>;
                    --gcs-btn-radius: <?php echo $btn_radius; ?>;
                    --gcs-bg: #f8fafc;
                    --gcs-card-bg: #ffffff;
                    --gcs-text: #1e293b;
                    --gcs-text-light: #64748b;
                    --gcs-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
                    --gcs-radius: 16px;
                }

                .gcs-dashboard-wrapper { font-family: 'Inter', sans-serif; color: var(--gcs-text); background: var(--gcs-bg); padding: 25px; border-radius: var(--gcs-radius); }
                
                .gcs-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; background: #fff; padding: 20px 30px; border-radius: var(--gcs-radius); box-shadow: var(--gcs-shadow); }
                .gcs-header h2 { margin: 0; font-size: 26px; font-weight: 800; color: var(--gcs-primary); letter-spacing: -0.5px; }
                
                .gcs-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
                .gcs-stat-card { background: #fff; padding: 20px; border-radius: var(--gcs-radius); box-shadow: var(--gcs-shadow); display: flex; align-items: center; gap: 15px; border: 1px solid rgba(226, 232, 240, 0.5); transition: transform 0.2s; }
                .gcs-stat-card:hover { transform: translateY(-2px); }
                .stat-icon { width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
                .stat-info { display: flex; flex-direction: column; }
                .stat-label { font-size: 11px; font-weight: 700; color: var(--gcs-text-light); text-transform: uppercase; letter-spacing: 0.5px; }
                .stat-val { font-size: 22px; font-weight: 800; color: var(--gcs-primary); }

                .gcs-tabs { display: flex; gap: 10px; margin-bottom: 30px; background: #e2e8f0; padding: 6px; border-radius: 14px; width: fit-content; }
                .gcs-tab-btn { padding: 10px 25px; border: none; background: none; cursor: pointer; font-weight: 700; color: var(--gcs-text-light); border-radius: 10px; transition: all 0.2s; font-size: 14px; }
                .gcs-tab-btn.active { background: #fff; color: var(--gcs-primary); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }

                .gcs-card { background: var(--gcs-card-bg); border-radius: var(--gcs-radius); overflow: hidden; border: 1px solid #e2e8f0; box-shadow: var(--gcs-shadow); }
                .gcs-filter-bar { background: #fff; padding: 15px 20px; border-bottom: 1px solid #e2e8f0; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
                .gcs-filter-btn { padding: 8px 16px; border-radius: 10px; border: 1px solid #e2e8f0; background: #f8fafc; color: #64748b; font-size: 13px; font-weight: 700; cursor: pointer; text-decoration: none; transition: all 0.2s; }
                .gcs-filter-btn.active { background: var(--gcs-primary); color: #fff; border-color: var(--gcs-primary); }
                .gcs-filter-btn:hover:not(.active) { background: #f1f5f9; }

                .gcs-table { width: 100%; border-collapse: collapse; }
                .gcs-table th { background: #f8fafc; padding: 15px 20px; text-align: left; font-size: 12px; font-weight: 800; color: var(--gcs-text-light); text-transform: uppercase; letter-spacing: 0.5px; }
                .gcs-table td { padding: 20px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
                .gcs-table tr:hover { background: #f8fafc; }

                .badge { padding: 5px 12px; border-radius: 9999px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
                .badge-pending, .badge-in-attesa { background: #fef3c7; color: #92400e; }
                .badge-confirmed, .badge-confermata { background: #dcfce7; color: #166534; }
                .badge-rejected, .badge-rifiutata { background: #fee2e2; color: #991b1b; }

                .cal-nav { display: flex; justify-content: space-between; align-items: center; padding: 25px; background: #fff; border-bottom: 1px solid #eee; }
                .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; background: #e2e8f0; border: 1px solid #e2e8f0; }
                .cal-day-header { background: #f8fafc; padding: 12px; text-align: center; font-size: 12px; font-weight: 800; color: var(--gcs-text-light); }
                .cal-day { background: #fff; min-height: 120px; padding: 10px; position: relative; }
                .cal-day.today { background: #f0fdf4; }
                .cal-day-num { font-size: 12px; font-weight: 700; color: #94a3b8; margin-bottom: 8px; display: block; }
                
                .event-bar { 
                    padding: 6px 12px; border-radius: 8px; font-size: 11px; font-weight: 700; color: #fff; 
                    margin-bottom: 5px; cursor: pointer; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1); width: 100%; box-sizing: border-box;
                }
                .event-manual { background: #ef4444; }
                .event-request { background: var(--gcs-primary); }
                
                .event-bar.cont-prev { border-top-left-radius: 0; border-bottom-left-radius: 0; }
                .event-bar.cont-next { border-top-right-radius: 0; border-bottom-right-radius: 0; width: calc(100% + 15px); z-index: 10; }

                .gcs-modal { display:none; position:fixed; z-index:100000; top:0; left:0; width:100%; height:100%; background:rgba(15, 23, 42, 0.6); backdrop-filter: blur(5px); align-items:center; justify-content:center; }
                .gcs-modal-content { background:#fff; padding:35px; border-radius:20px; width:90%; max-width:450px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }

                input[type="text"], input[type="date"], input[type="email"], select, textarea {
                    border: 1px solid #e2e8f0; padding: 12px; border-radius: 10px; font-size: 14px; width: 100%; margin-bottom: 12px; background: #f8fafc;
                }
                button[type="submit"] {
                    background: var(--gcs-btn-bg); color: #fff; border: none; padding: 14px 28px; border-radius: var(--gcs-btn-radius); font-weight: 700; cursor: pointer; transition: all 0.3s; width: 100%;
                }
                button[type="submit"]:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }

                .gcs-logout { text-decoration: none; color: #ef4444; font-weight: 700; font-size: 14px; padding: 8px 16px; border-radius: 10px; background: #fee2e2; transition: all 0.2s; }
                .gcs-logout:hover { background: #fecaca; }
            </style>

            <div class="gcs-header">
                <h2>Amministrazione Casa Scout</h2>
                <a href="<?php echo esc_url(add_query_arg('gcs_logout', '1')); ?>" class="gcs-logout">Esci</a>
            </div>

            <div class="gcs-stats-grid">
                <div class="gcs-stat-card">
                    <div class="stat-icon" style="background: #fff8eb; color: #b45309;">✉️</div>
                    <div class="stat-info">
                        <span class="stat-label">In Attesa</span>
                        <span class="stat-val"><?php echo $pending_count; ?></span>
                    </div>
                </div>
                <div class="gcs-stat-card">
                    <div class="stat-icon" style="background: #ecfdf5; color: #059669;">📅</div>
                    <div class="stat-info">
                        <span class="stat-label">Confermate</span>
                        <span class="stat-val"><?php echo $confirmed_count; ?></span>
                    </div>
                </div>
                <div class="gcs-stat-card">
                    <div class="stat-icon" style="background: #eff6ff; color: var(--gcs-primary);">📊</div>
                    <div class="stat-info">
                        <span class="stat-label">Totale Gestito</span>
                        <span class="stat-val"><?php echo ($pending_count + $confirmed_count); ?></span>
                    </div>
                </div>
            </div>

            <div class="gcs-tabs">
                <button class="gcs-tab-btn active" id="btn_requests" onclick="gcsShowTab('requests')">Richieste</button>
                <button class="gcs-tab-btn" id="btn_calendar" onclick="gcsShowTab('calendar')">Calendario</button>
                <button class="gcs-tab-btn" id="btn_settings" onclick="gcsShowTab('settings')">Impostazioni</button>
            </div>

            <div id="tab_requests" class="gcs-tab-content"><?php echo self::render_requests_management(); ?></div>
            <div id="tab_calendar" class="gcs-tab-content" style="display:none;">
                <div id="gcs-calendar-ajax-container"><?php echo self::render_calendar_management(); ?></div>
            </div>
            <div id="tab_settings" class="gcs-tab-content" style="display:none;"><?php echo self::render_settings_management(); ?></div>

            <div id="gcsEditModal" class="gcs-modal">
                <div class="gcs-modal-content">
                    <h3 style="margin-top:0; color:var(--gcs-primary);">Dettaglio Evento</h3>
                    <form method="POST" id="gcs-calendar-edit-form" class="ajax-form">
                        <input type="hidden" name="gcs_edit_event_action" value="1">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <input type="hidden" name="gcs_event_op" id="event_op" value="save">
                        <input type="text" name="edit_title" id="edit_title" placeholder="Titolo">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                            <input type="date" name="edit_start" id="edit_start">
                            <input type="date" name="edit_end" id="edit_end">
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-top:20px; gap:15px;">
                            <button type="button" onclick="if(confirm('Eliminare definitivamente?')){ document.getElementById('event_op').value='delete'; document.getElementById('gcs-calendar-edit-form').requestSubmit(); }" style="background:#fee2e2; color:#ef4444; border:none; padding:12px; border-radius:10px; cursor:pointer; font-weight:700; width:100px;">Elimina</button>
                            <button type="submit">Salva</button>
                        </div>
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
                document.querySelectorAll('.ajax-form').forEach(form => {
                    form.onsubmit = function(e) {
                        e.preventDefault();
                        const formData = new FormData(this);
                        fetch(window.location.href, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(() => fetch(window.location.href))
                        .then(r => r.text())
                        .then(html => {
                            const doc = new DOMParser().parseFromString(html, 'text/html');
                            document.getElementById('tab_requests').innerHTML = doc.getElementById('tab_requests').innerHTML;
                            document.getElementById('gcs-calendar-ajax-container').innerHTML = doc.getElementById('gcs-calendar-ajax-container').innerHTML;
                            document.getElementById('gcsEditModal').style.display = 'none';
                            bindAjaxForms();
                        });
                    };
                    const sel = form.querySelector('select');
                    if (sel) sel.onchange = () => form.requestSubmit();
                });
            }
            function gcsNavigateCalendar(month, year) {
                const container = document.getElementById('gcs-calendar-ajax-container');
                const formData = new FormData();
                formData.append('action', 'gcs_fetch_calendar');
                formData.append('c_month', month);
                formData.append('c_year', year);
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: formData })
                .then(r => r.text()).then(html => { container.innerHTML = html; });
            }
            document.addEventListener('DOMContentLoaded', bindAjaxForms);
            </script>
        </div>
        <?php return ob_get_clean();
    }

    private static function render_requests_management() {
        global $wpdb; $table = $wpdb->prefix . 'gcs_requests';
        $filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : 'active';
        $where = ($filter === 'all') ? "WHERE 1=1" : "WHERE contact_email != 'manuale@calendario.local'";

        if ($filter === 'active') {
            $where .= " AND status IN ('pending', 'confirmed', 'in attesa', 'In attesa', 'confermata', 'Confermata')";
        } elseif ($filter === 'confirmed') {
            $where .= " AND status IN ('confirmed', 'confermata', 'Confermata')";
        } elseif ($filter === 'rejected') {
            $where .= " AND status IN ('rejected', 'rifiutata', 'Rifiutata')";
        }
        
        $requests = $wpdb->get_results("SELECT * FROM $table $where ORDER BY created_at DESC");
        ob_start(); ?>
        <div class="gcs-card">
            <div class="gcs-filter-bar">
                <a href="<?php echo add_query_arg('status_filter', 'active'); ?>" class="gcs-filter-btn <?php echo $filter == 'active' ? 'active' : ''; ?>">Attive</a>
                <a href="<?php echo add_query_arg('status_filter', 'confirmed'); ?>" class="gcs-filter-btn <?php echo $filter == 'confirmed' ? 'active' : ''; ?>">Confermate</a>
                <a href="<?php echo add_query_arg('status_filter', 'rejected'); ?>" class="gcs-filter-btn <?php echo $filter == 'rejected' ? 'active' : ''; ?>">Rifiutate</a>
                <a href="<?php echo add_query_arg('status_filter', 'all'); ?>" class="gcs-filter-btn <?php echo $filter == 'all' ? 'active' : ''; ?>">Tutte</a>
            </div>
            <div style="overflow-x:auto;">
                <table class="gcs-table">
                    <thead><tr><th>Gruppo / Email</th><th>Date</th><th>Stato</th><th style="text-align:right;">Azioni</th></tr></thead>
                    <tbody>
                        <?php if (empty($requests)): ?>
                            <tr><td colspan="4" style="padding:50px; text-align:center; color:#94a3b8;">Nessun dato trovato.</td></tr>
                        <?php else: foreach ($requests as $r): ?>
                            <tr>
                                <td><strong><?php echo esc_html($r->group_name); ?></strong><br><small><?php echo esc_html($r->contact_email); ?></small></td>
                                <td><?php echo date('d/m/y', strtotime($r->start_date)); ?> - <?php echo date('d/m/y', strtotime($r->end_date)); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo sanitize_title($r->status); ?>">
                                        <?php 
                                        $s = strtolower($r->status);
                                        if ($s == 'pending' || $s == 'in attesa') echo 'In attesa';
                                        elseif ($s == 'confirmed' || $s == 'confermata') echo 'Confermata';
                                        elseif ($s == 'rejected' || $s == 'rifiutata') echo 'Rifiutata';
                                        else echo esc_html($r->status);
                                        ?>
                                    </span>
                                </td>
                                <td style="text-align:right;">
                                    <form method="POST" class="ajax-form" style="display:inline-flex; gap:8px;">
                                        <input type="hidden" name="request_id" value="<?php echo $r->id; ?>"><input type="hidden" name="gcs_front_update_status" value="1">
                                        <select name="status" style="width:auto; margin-bottom:0; font-size:12px; padding:6px 10px;">
                                            <option value="pending" <?php selected($r->status, 'pending'); ?>>Attesa</option>
                                            <option value="confirmed" <?php selected($r->status, 'confirmed'); ?>>Conferma</option>
                                            <option value="rejected" <?php selected($r->status, 'rejected'); ?>>Rifiuta</option>
                                        </select>
                                        <button type="button" onclick="if(confirm('Eliminare?')){ this.form.querySelector('[name=gcs_front_delete_req]').value='1'; this.form.requestSubmit(); }" style="background:none; border:none; cursor:pointer; font-size:16px;">🗑️</button>
                                        <input type="hidden" name="gcs_front_delete_req" value="0">
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    private static function render_calendar_management() {
        global $wpdb; $table = $wpdb->prefix . 'gcs_requests';
        $m = isset($_REQUEST['c_month']) ? intval($_REQUEST['c_month']) : date('n');
        $y = isset($_REQUEST['c_year']) ? intval($_REQUEST['c_year']) : date('Y');
        $start_m = sprintf("%04d-%02d-01", $y, $m); $end_m = date("Y-m-t", strtotime($start_m));
        $events = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE status IN ('confirmed', 'confermata', 'Confermata') AND (start_date <= %s AND end_date >= %s)", $end_m, $start_m));
        $months = ['Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
        ob_start(); ?>
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap:25px;">
            <div class="gcs-card">
                <div class="cal-nav">
                    <button class="gcs-filter-btn" onclick="gcsNavigateCalendar(<?php echo $m==1?12:$m-1; ?>, <?php echo $m==1?$y-1:$y; ?>)">&larr;</button>
                    <h3 style="margin:0; font-size:18px; color:var(--gcs-primary);"><?php echo $months[$m-1] . ' ' . $y; ?></h3>
                    <button class="gcs-filter-btn" onclick="gcsNavigateCalendar(<?php echo $m==12?1:$m+1; ?>, <?php echo $m==12?$y+1:$y; ?>)">&rarr;</button>
                </div>
                <div class="cal-grid">
                    <?php foreach(['L','M','M','G','V','S','D'] as $d) echo '<div class="cal-day-header">'.$d.'</div>'; ?>
                    <?php
                    $fw = date('N', strtotime($start_m));
                    for ($i = 1; $i < $fw; $i++) echo '<div style="background:#f8fafc;"></div>';
                    for ($d = 1; $d <= date('t', strtotime($start_m)); $d++) {
                        $cur = sprintf("%04d-%02d-%02d", $y, $m, $d);
                        echo '<div class="cal-day '.($cur==date('Y-m-d')?'today':'').'"><span class="cal-day-num">'.$d.'</span>';
                        foreach($events as $e) {
                            if($cur >= $e->start_date && $cur <= $e->end_date) {
                                $isS = ($cur == $e->start_date); $isE = ($cur == $e->end_date);
                                $color = ($e->contact_email == 'manuale@calendario.local') ? 'event-manual' : 'event-request';
                                $classes = ['event-bar', $color];
                                if (!$isS) $classes[] = 'cont-prev';
                                if (!$isE) $classes[] = 'cont-next';
                                echo '<div onclick="gcsEditEvent('.$e->id.',\''.esc_js($e->group_name).'\',\''.$e->start_date.'\',\''.$e->end_date.'\')" class="'.implode(' ', $classes).'">'.($isS||$d==1?esc_html($e->group_name):'').'</div>';
                            }
                        }
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>
            <div class="gcs-card" style="padding:30px;">
                <h4 style="margin-top:0; color:var(--gcs-primary); margin-bottom:20px;">Nuovo Impegno Manuale</h4>
                <form method="POST" class="ajax-form">
                    <input type="hidden" name="gcs_front_add_manual" value="1">
                    <input type="text" name="event_title" placeholder="Nome dell'evento/gruppo" required>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                        <input type="date" name="event_start" required>
                        <input type="date" name="event_end" required>
                    </div>
                    <button type="submit" style="margin-top:10px;">Aggiungi ora</button>
                </form>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    private static function render_settings_management() {
        ob_start(); ?>
        <div class="gcs-card" style="padding:40px; max-width:600px;">
            <h4 style="margin-top:0; color:var(--gcs-primary); margin-bottom:20px;">Impostazioni Area Riservata</h4>
            <form method="POST">
                <input type="hidden" name="gcs_front_settings_save" value="1">
                <div style="margin-bottom:20px;">
                    <label style="display:block; font-weight:700; margin-bottom:8px; font-size:12px;">EMAIL PER NOTIFICHE</label>
                    <input type="email" name="gcs_notification_email" value="<?php echo esc_attr(get_option('gcs_notification_email')); ?>">
                </div>
                <div style="margin-bottom:25px;">
                    <label style="display:block; font-weight:700; margin-bottom:8px; font-size:12px;">UTENTI AUTORIZZATI (user:password)</label>
                    <textarea name="gcs_reserved_users" style="height:100px;"><?php echo esc_textarea(get_option('gcs_reserved_users')); ?></textarea>
                </div>
                <button type="submit">Salva Configurazione</button>
            </form>
        </div>
        <?php return ob_get_clean();
    }

    private static function render_login_form() {
        $primary = get_option('gcs_style_title_color', '#1a4581');
        ob_start(); ?>
        <div class="gcs-dashboard-wrapper" style="max-width:400px; margin:100px auto; padding:40px; background:#fff; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.15); border-radius:24px; text-align:center; font-family:'Inter', sans-serif;">
            <div style="width:60px; height:60px; background:<?php echo $primary; ?>22; border-radius:18px; display:flex; align-items:center; justify-content:center; margin:0 auto 20px; font-size:30px;">🔐</div>
            <h2 style="color:<?php echo $primary; ?>; margin:0 0 10px; font-weight:800; font-size:24px;">Area Riservata</h2>
            <p style="color:#64748b; font-size:14px; margin-bottom:30px;">Inserisci le credenziali per accedere.</p>
            <?php if(isset($_GET['gcs_login_error'])) echo '<p style="color:#ef4444; font-weight:700; font-size:13px; margin-bottom:15px; background:#fee2e2; padding:10px; border-radius:10px;">❌ Accesso negato.</p>'; ?>
            <form method="POST">
                <input type="text" name="gcs_username" placeholder="Username" required style="background:#f8fafc; border:1px solid #e2e8f0; padding:12px; border-radius:12px; margin-bottom:15px; width:100%;">
                <input type="password" name="gcs_password" placeholder="Password" required style="background:#f8fafc; border:1px solid #e2e8f0; padding:12px; border-radius:12px; margin-bottom:25px; width:100%;">
                <button type="submit" name="gcs_reserved_login_submit" style="background:<?php echo $primary; ?>; color:#fff; border:none; padding:14px; border-radius:12px; font-weight:700; width:100%; cursor:pointer; transition:all 0.2s;">Accedi all'area</button>
            </form>
            <p style="margin-top:25px; font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:1px;">Gestione Casa Scout v1.9.6</p>
        </div>
        <?php return ob_get_clean();
    }
}
