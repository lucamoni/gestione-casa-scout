<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Gestione Area Riservata
 * Versione 1.9.5 - RIPRISTINO GRAFICA CLASSICA & STATUS ITA
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
        $btn_radius = get_option('gcs_style_btn_radius', '4px');

        $pending_count = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status IN ('pending', 'in attesa', 'In attesa') AND contact_email != 'manuale@calendario.local'");
        $confirmed_count = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status IN ('confirmed', 'confermata', 'Confermata') AND contact_email != 'manuale@calendario.local'");

        ob_start(); ?>
        <div class="gcs-dashboard-wrapper">
            <style>
                :root {
                    --gcs-primary: <?php echo $primary_color; ?>;
                    --gcs-secondary: #a1d1d0;
                    --gcs-bg: #f4f7f9;
                    --gcs-text: #333;
                }

                .gcs-dashboard-wrapper { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; color: var(--gcs-text); background: var(--gcs-bg); padding: 20px; }
                
                .gcs-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; }
                .gcs-header h2 { margin: 0; font-size: 20px; color: var(--gcs-primary); }
                
                .gcs-stats-grid { display: flex; gap: 15px; margin-bottom: 20px; }
                .gcs-stat-card { background: #fff; padding: 15px; border: 1px solid #ccd0d4; border-radius: 4px; flex: 1; text-align: center; }
                .stat-label { font-size: 11px; font-weight: 700; color: #666; text-transform: uppercase; display: block; margin-bottom: 5px; }
                .stat-val { font-size: 24px; font-weight: 800; color: var(--gcs-primary); }

                .gcs-tabs { display: flex; gap: 5px; margin-bottom: 20px; }
                .gcs-tab-btn { padding: 10px 20px; border: 1px solid #ccd0d4; border-bottom: none; background: #eee; cursor: pointer; font-weight: 600; color: #555; border-radius: 4px 4px 0 0; }
                .gcs-tab-btn.active { background: #fff; color: var(--gcs-primary); position: relative; top: 1px; padding-bottom: 11px; }

                .gcs-card { background: #fff; border: 1px solid #ccd0d4; border-radius: 0 4px 4px 4px; padding: 20px; }
                .gcs-filter-bar { margin-bottom: 15px; display: flex; gap: 8px; }
                .gcs-filter-btn { padding: 4px 10px; border: 1px solid #ccd0d4; background: #f6f7f7; color: #2271b1; text-decoration: none; font-size: 13px; border-radius: 3px; }
                .gcs-filter-btn.active { background: var(--gcs-primary); color: #fff; border-color: var(--gcs-primary); }

                .gcs-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                .gcs-table th { background: #f0f0f1; padding: 10px; text-align: left; font-size: 13px; border: 1px solid #ccd0d4; }
                .gcs-table td { padding: 12px 10px; border: 1px solid #ccd0d4; font-size: 13px; }
                .gcs-table tr:nth-child(even) { background: #f9f9f9; }

                .badge { padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
                .badge-pending, .badge-in-attesa { background: #fff8e5; color: #856404; border: 1px solid #ffeeba; }
                .badge-confirmed, .badge-confermata { background: #e3fcef; color: #155724; border: 1px solid #c3e6cb; }
                .badge-rejected, .badge-rifiutata { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

                .cal-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
                .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; background: #ccd0d4; border: 1px solid #ccd0d4; }
                .cal-day-header { background: #f0f0f1; padding: 8px; text-align: center; font-size: 12px; font-weight: 700; }
                .cal-day { background: #fff; min-height: 100px; padding: 5px; position: relative; overflow: visible; }
                .cal-day.today { background: #fff8e5; }
                .cal-day-num { font-size: 11px; font-weight: 700; color: #999; margin-bottom: 5px; }
                
                .event-bar { 
                    padding: 4px 8px; border-radius: 3px; font-size: 10px; font-weight: 700; color: #fff; 
                    margin-bottom: 2px; cursor: pointer; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
                    position: relative; z-index: 5; width: 100%; box-sizing: border-box;
                }
                .event-manual { background: #d63638; }
                .event-request { background: #2271b1; }
                
                .event-bar.cont-prev { border-top-left-radius: 0; border-bottom-left-radius: 0; margin-left: -6px; width: calc(100% + 6px); }
                .event-bar.cont-next { border-top-right-radius: 0; border-bottom-right-radius: 0; margin-right: -100px; width: calc(100% + 6px); z-index: 6; }

                .gcs-modal { display:none; position:fixed; z-index:100000; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; }
                .gcs-modal-content { background:#fff; padding:20px; border-radius:4px; width:90%; max-width:400px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }

                input[type="text"], input[type="date"], input[type="email"], select, textarea {
                    border: 1px solid #8c8f94; padding: 6px; border-radius: 4px; font-size: 14px; width: 100%; margin-bottom: 10px;
                }
                .gcs-btn-blue {
                    background: #2271b1; color: #fff; border: 1px solid #135e96; padding: 8px 16px; border-radius: 3px; font-weight: 600; cursor: pointer;
                }
                .gcs-btn-blue:hover { background: #135e96; }

                #gcsConfirmModal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 200000; align-items: center; justify-content: center; }
                .gcs-confirm-content { background: #fff; padding: 25px; border-radius: 4px; max-width: 350px; text-align: center; }
            </style>

            <div class="gcs-header">
                <h2>Gestione Casa Scout</h2>
                <a href="<?php echo esc_url(add_query_arg('gcs_logout', '1')); ?>" style="color:#d63638; font-weight:700; text-decoration:none;">Esci</a>
            </div>

            <div class="gcs-stats-grid">
                <div class="gcs-stat-card">
                    <span class="stat-label">In Attesa</span>
                    <span class="stat-val"><?php echo $pending_count; ?></span>
                </div>
                <div class="gcs-stat-card">
                    <span class="stat-label">Confermate</span>
                    <span class="stat-val"><?php echo $confirmed_count; ?></span>
                </div>
            </div>

            <div class="gcs-tabs">
                <button class="gcs-tab-btn active" id="btn_requests" onclick="gcsShowTab('requests')">Richieste Form</button>
                <button class="gcs-tab-btn" id="btn_calendar" onclick="gcsShowTab('calendar')">Calendario</button>
                <button class="gcs-tab-btn" id="btn_settings" onclick="gcsShowTab('settings')">Impostazioni</button>
            </div>

            <div id="tab_requests" class="gcs-tab-content"><?php echo self::render_requests_management(); ?></div>
            <div id="tab_calendar" class="gcs-tab-content" style="display:none;">
                <div id="gcs-calendar-ajax-container"><?php echo self::render_calendar_management(); ?></div>
            </div>
            <div id="tab_settings" class="gcs-tab-content" style="display:none;"><?php echo self::render_settings_management(); ?></div>

            <div id="gcsConfirmModal">
                <div class="gcs-confirm-content">
                    <p id="gcsConfirmText" style="font-weight:700;"></p>
                    <div style="margin-top:20px; display:flex; gap:10px; justify-content:center;">
                        <button onclick="closeGcsConfirm()" style="padding:6px 12px;">Annulla</button>
                        <button id="gcsConfirmExec" style="background:#d63638; color:#fff; border:none; padding:6px 12px; border-radius:3px; cursor:pointer;">Procedi</button>
                    </div>
                </div>
            </div>

            <div id="gcsEditModal" class="gcs-modal">
                <div class="gcs-modal-content">
                    <h3 style="margin-top:0;">Dettaglio Impegno</h3>
                    <form method="POST" id="gcs-calendar-edit-form" class="ajax-form">
                        <input type="hidden" name="gcs_edit_event_action" value="1">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <input type="hidden" name="gcs_event_op" id="event_op" value="save">
                        <input type="text" name="edit_title" id="edit_title" placeholder="Titolo">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                            <input type="date" name="edit_start" id="edit_start">
                            <input type="date" name="edit_end" id="edit_end">
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-top:10px;">
                            <button type="button" onclick="openGcsConfirm('Eliminare impegno?', () => { document.getElementById('event_op').value='delete'; document.getElementById('gcs-calendar-edit-form').requestSubmit(); })" style="color:#d63638; background:none; border:none; cursor:pointer;">Elimina</button>
                            <button type="submit" class="gcs-btn-blue">Salva</button>
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
            let gcsConfirmCallback = null;
            function openGcsConfirm(text, callback) {
                document.getElementById('gcsConfirmText').innerText = text;
                gcsConfirmCallback = callback;
                document.getElementById('gcsConfirmModal').style.display = 'flex';
            }
            function closeGcsConfirm() { document.getElementById('gcsConfirmModal').style.display = 'none'; }
            document.getElementById('gcsConfirmExec').onclick = function() { if(gcsConfirmCallback) gcsConfirmCallback(); closeGcsConfirm(); };

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
            <table class="gcs-table">
                <thead><tr><th>Gruppo / Contatto</th><th>Periodo</th><th>Stato</th><th style="text-align:right;">Azioni</th></tr></thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr><td colspan="4" style="text-align:center;">Nessuna richiesta.</td></tr>
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
                                <form method="POST" class="ajax-form" style="display:inline-flex; gap:5px;">
                                    <input type="hidden" name="request_id" value="<?php echo $r->id; ?>"><input type="hidden" name="gcs_front_update_status" value="1">
                                    <select name="status" style="width:auto; margin-bottom:0; font-size:12px;">
                                        <option value="pending" <?php selected($r->status, 'pending'); ?>>Attesa</option>
                                        <option value="confirmed" <?php selected($r->status, 'confirmed'); ?>>Conferma</option>
                                        <option value="rejected" <?php selected($r->status, 'rejected'); ?>>Rifiuta</option>
                                    </select>
                                    <input type="hidden" name="gcs_front_delete_req" value="0">
                                    <button type="button" onclick="openGcsConfirm('Eliminare definitivamente?', () => { this.form.querySelector('[name=gcs_front_delete_req]').value='1'; this.form.requestSubmit(); })" style="color:#d63638; background:none; border:none; cursor:pointer;">Elimina</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
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
        <div style="display:grid; grid-template-columns: 2fr 1fr; gap:20px;">
            <div class="gcs-card" style="padding:15px;">
                <div class="cal-nav">
                    <button onclick="gcsNavigateCalendar(<?php echo $m==1?12:$m-1; ?>, <?php echo $m==1?$y-1:$y; ?>)">&larr;</button>
                    <h3 style="margin:0; font-size:16px;"><?php echo $months[$m-1] . ' ' . $y; ?></h3>
                    <button onclick="gcsNavigateCalendar(<?php echo $m==12?1:$m+1; ?>, <?php echo $m==12?$y+1:$y; ?>)">&rarr;</button>
                </div>
                <div class="cal-grid">
                    <?php foreach(['L','M','M','G','V','S','D'] as $d) echo '<div class="cal-day-header">'.$d.'</div>'; ?>
                    <?php
                    $fw = date('N', strtotime($start_m));
                    for ($i = 1; $i < $fw; $i++) echo '<div style="background:#f0f0f1;"></div>';
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
            <div class="gcs-card" style="padding:15px;">
                <h4 style="margin-top:0;">Nuovo Impegno</h4>
                <form method="POST" class="ajax-form">
                    <input type="hidden" name="gcs_front_add_manual" value="1">
                    <input type="text" name="event_title" placeholder="Titolo" required>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <input type="date" name="event_start" required>
                        <input type="date" name="event_end" required>
                    </div>
                    <button type="submit" class="gcs-btn-blue" style="width:100%;">Aggiungi</button>
                </form>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    private static function render_settings_management() {
        ob_start(); ?>
        <div class="gcs-card" style="padding:20px; max-width:500px;">
            <h4 style="margin-top:0;">Impostazioni Area Riservata</h4>
            <form method="POST">
                <input type="hidden" name="gcs_front_settings_save" value="1">
                <label>Email Notifiche</label><input type="email" name="gcs_notification_email" value="<?php echo esc_attr(get_option('gcs_notification_email')); ?>">
                <label>Utenti (user:password)</label><textarea name="gcs_reserved_users"><?php echo esc_textarea(get_option('gcs_reserved_users')); ?></textarea>
                <button type="submit" class="gcs-btn-blue">Salva</button>
            </form>
        </div>
        <?php return ob_get_clean();
    }

    private static function render_login_form() {
        ob_start(); ?>
        <div style="max-width:350px; margin:80px auto; padding:30px; border:1px solid #ccd0d4; background:#fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif;">
            <h2 style="text-align:center; color:#1a4581; margin-top:0;">Area Riservata</h2>
            <?php if(isset($_GET['gcs_login_error'])) echo '<p style="color:#d63638; text-align:center; font-weight:700;">Dati errati.</p>'; ?>
            <form method="POST">
                <div style="margin-bottom:15px;"><label style="display:block; margin-bottom:5px; font-weight:600;">Username</label><input type="text" name="gcs_username" required style="width:100%; border:1px solid #8c8f94; padding:8px; border-radius:3px;"></div>
                <div style="margin-bottom:20px;"><label style="display:block; margin-bottom:5px; font-weight:600;">Password</label><input type="password" name="gcs_password" required style="width:100%; border:1px solid #8c8f94; padding:8px; border-radius:3px;"></div>
                <button type="submit" name="gcs_reserved_login_submit" style="width:100%; background:#2271b1; color:#fff; border:1px solid #135e96; padding:10px; border-radius:3px; font-weight:700; cursor:pointer;">Accedi</button>
            </form>
        </div>
        <?php return ob_get_clean();
    }
}
