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

        ob_start(); ?>
        <div class="gcs-reserved-wrapper" style="margin: 20px 0; color: inherit;">
            <style>
                .gcs-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 20px; overflow: hidden; }
                .gcs-tab-nav { display: flex; border-bottom: 2px solid #eee; margin-bottom: 25px; }
                .gcs-tab-btn { padding: 15px 25px; border: none; background: none; cursor: pointer; font-weight: 700; color: #666; }
                .gcs-tab-btn.active { color: #2d5a27; border-bottom: 3px solid #2d5a27; }
                
                .gcs-cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; background: #e2e8f0; border-radius: 8px; overflow: hidden; }
                .gcs-day { background: #fff; min-height: 100px; padding: 5px; position: relative; }
                .gcs-event-bar { 
                    cursor: pointer; padding: 4px 8px; font-size: 11px; font-weight: 700; border-radius: 4px; 
                    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #fff;
                    margin: 2px 0; height: 22px; line-height: 14px; position: relative; z-index: 5;
                }
                .gcs-event-bar.cont-prev { border-top-left-radius: 0; border-bottom-left-radius: 0; margin-left: -6px; width: calc(100% + 6px); }
                .gcs-event-bar.cont-next { border-top-right-radius: 0; border-bottom-right-radius: 0; margin-right: -6px; width: calc(100% + 6px); }
                .gcs-event-bar.cont-prev.cont-next { width: calc(100% + 12px); }
                
                .status-pending { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
                .status-confirmed { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
                .status-rejected { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
            </style>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #eee;">
                <h2 style="margin:0; font-size: 24px;">Amministrazione Prenotazioni</h2>
                <a href="<?php echo esc_url(add_query_arg('gcs_logout', '1')); ?>" style="font-size: 13px; color: #e74c3c; font-weight: 700;">&times; Esci</a>
            </div>

            <nav class="gcs-tab-nav">
                <button class="gcs-tab-btn active" id="btn_requests" onclick="gcsShowTab('requests')">Richieste Ricevute</button>
                <button class="gcs-tab-btn" id="btn_calendar" onclick="gcsShowTab('calendar')">Calendario</button>
                <button class="gcs-tab-btn" id="btn_settings" onclick="gcsShowTab('settings')">Impostazioni</button>
            </nav>

            <div id="tab_requests" class="gcs-tab-content"><?php echo self::render_requests_management(); ?></div>
            <div id="tab_calendar" class="gcs-tab-content" style="display:none;"><?php echo self::render_calendar_management(); ?></div>
            <div id="tab_settings" class="gcs-tab-content" style="display:none;"><?php echo self::render_settings_management(); ?></div>

            <!-- Modal -->
            <div id="gcsEditModal" style="display:none; position:fixed; z-index:99999; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.4); align-items:center; justify-content:center; backdrop-filter: blur(2px);">
                <div class="gcs-card" style="padding:30px; width:90%; max-width:450px; background:#fff;">
                    <h3 style="margin-top:0;">Modifica Impegno</h3>
                    <form method="POST">
                        <input type="hidden" name="gcs_edit_event_action" value="1">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <input type="hidden" name="gcs_event_op" id="event_op" value="save">
                        <div style="margin-bottom:10px;">
                            <label style="display:block; font-size:12px; font-weight:700;">Titolo</label>
                            <input type="text" name="edit_title" id="edit_title" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:15px;">
                            <div><label style="display:block; font-size:12px; font-weight:700;">Inizio</label><input type="date" name="edit_start" id="edit_start" style="width:100%;"></div>
                            <div><label style="display:block; font-size:12px; font-weight:700;">Fine</label><input type="date" name="edit_end" id="edit_end" style="width:100%;"></div>
                        </div>
                        <div style="display:flex; justify-content:space-between;">
                            <button type="submit" onclick="document.getElementById('event_op').value='delete'; return confirm('Eliminare?');" style="background:#fff; color:#d63031; border:1px solid #d63031; padding:8px 15px; border-radius:4px; font-weight:700;">Elimina</button>
                            <button type="submit" style="background:#2d5a27; color:#fff; border:none; padding:8px 20px; border-radius:4px; font-weight:700;">Salva</button>
                        </div>
                        <button type="button" onclick="document.getElementById('gcsEditModal').style.display='none'" style="display:block; width:100%; margin-top:10px; background:none; border:none; color:#777; font-size:12px;">Annulla</button>
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
                document.querySelectorAll('#tab_requests form').forEach(form => {
                    form.onsubmit = function(e) {
                        e.preventDefault();
                        let fd = new FormData(this);
                        fetch(window.location.href, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(() => fetch(window.location.href))
                        .then(r => r.text())
                        .then(html => {
                            let doc = new DOMParser().parseFromString(html, 'text/html');
                            document.getElementById('tab_requests').innerHTML = doc.getElementById('tab_requests').innerHTML;
                            document.getElementById('tab_calendar').innerHTML = doc.getElementById('tab_calendar').innerHTML;
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
        $requests = GCS_DB_Manager::get_requests();
        ob_start(); ?>
        <div class="gcs-card">
            <table style="width:100%; border-collapse:collapse;">
                <thead style="background:#f8fafc;">
                    <tr>
                        <th style="padding:15px; text-align:left; font-size:12px; border-bottom:1px solid #eee;">Gruppo</th>
                        <th style="padding:15px; text-align:left; font-size:12px; border-bottom:1px solid #eee;">Periodo</th>
                        <th style="padding:15px; text-align:center; font-size:12px; border-bottom:1px solid #eee;">Stato</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr><td colspan="3" style="padding:40px; text-align:center; color:#999;">Nessuna richiesta trovata.</td></tr>
                    <?php else: foreach ($requests as $r): ?>
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:15px;">
                                <strong><?php echo esc_html($r->group_name); ?></strong><br>
                                <small style="color:#666;"><?php echo esc_html($r->contact_email); ?></small>
                            </td>
                            <td style="padding:15px; font-size:13px;">
                                <?php echo date('d/m/Y', strtotime($r->start_date)); ?> - <?php echo date('d/m/Y', strtotime($r->end_date)); ?><br>
                                <span style="font-weight:700; color:#2d5a27;"><?php echo esc_html($r->guests_count); ?> persone</span>
                            </td>
                            <td style="padding:15px; text-align:center;">
                                <form method="POST" style="display:inline-flex; gap:8px;">
                                    <input type="hidden" name="request_id" value="<?php echo $r->id; ?>">
                                    <input type="hidden" name="gcs_front_update_status" value="1">
                                    <select name="status" onchange="this.form.dispatchEvent(new Event('submit'))" class="status-<?php echo $r->status; ?>" style="padding:5px 10px; border-radius:4px; font-size:11px; font-weight:700;">
                                        <option value="pending" <?php selected($r->status, 'pending'); ?>>IN ATTESA</option>
                                        <option value="confirmed" <?php selected($r->status, 'confirmed'); ?>>CONFERMATA</option>
                                        <option value="rejected" <?php selected($r->status, 'rejected'); ?>>RIFIUTATA</option>
                                    </select>
                                    <button type="submit" name="gcs_front_delete_req" value="1" style="background:none; border:none; cursor:pointer;" onclick="return confirm('Eliminare?')">🗑️</button>
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
        global $wpdb;
        $table = $wpdb->prefix . 'gcs_requests';
        $m = isset($_GET['c_month']) ? intval($_GET['c_month']) : date('n');
        $y = isset($_GET['c_year']) ? intval($_GET['c_year']) : date('Y');
        $start = sprintf("%04d-%02d-01", $y, $m);
        $end = date("Y-m-t", strtotime($start));
        $events = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE status = 'confirmed' AND (start_date <= %s AND end_date >= %s)", $end, $start));
        ob_start(); ?>
        <div style="display:grid; grid-template-columns: 2fr 1fr; gap:20px;">
            <div class="gcs-card" style="padding:20px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                    <a href="?c_month=<?php echo $m==1?12:$m-1; ?>&c_year=<?php echo $m==1?$y-1:$y; ?>" style="text-decoration:none; color:#2d5a27; font-weight:700;">&laquo;</a>
                    <h3 style="margin:0;"><?php echo date('F Y', strtotime($start)); ?></h3>
                    <a href="?c_month=<?php echo $m==12?1:$m+1; ?>&c_year=<?php echo $m==12?$y+1:$y; ?>" style="text-decoration:none; color:#2d5a27; font-weight:700;">&raquo;</a>
                </div>
                <div class="gcs-cal-grid">
                    <?php foreach(array('L','M','M','G','V','S','D') as $d) echo '<div style="background:#f8fafc; padding:8px; text-align:center; font-weight:700; font-size:10px;">'.$d.'</div>'; ?>
                    <?php
                    $fw = date('w', strtotime($start)); $fw = ($fw == 0) ? 7 : $fw;
                    for ($i = 1; $i < $fw; $i++) echo '<div style="background:#f9f9f9; height:100px;"></div>';
                    for ($d = 1; $d <= date('t', strtotime($start)); $d++) {
                        $cur = sprintf("%04d-%02d-%02d", $y, $m, $d);
                        $isToday = ($cur == date('Y-m-d'));
                        echo '<div class="gcs-day" '.($isToday?'style="background:#f0fdf4"':'').'><span style="color:#94a3b8; font-size:10px;">'.$d.'</span>';
                        foreach($events as $e) {
                            if($cur >= $e->start_date && $cur <= $e->end_date) {
                                $isStart = ($cur == $e->start_date); $isEnd = ($cur == $e->end_date);
                                $color = ($e->contact_email == 'manuale@calendario.local') ? '#e74c3c' : '#3498db';
                                echo '<div onclick="gcsEditEvent('.$e->id.',\''.esc_js($e->group_name).'\',\''.$e->start_date.'\',\''.$e->end_date.'\')" class="gcs-event-bar '.(!$isStart?'cont-prev ':'').(!$isEnd?'cont-next':'').'" style="background:'.$color.'">'.(($isStart||$d==1)?esc_html($e->group_name):'').'</div>';
                            }
                        }
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>
            <div class="gcs-card" style="padding:20px;">
                <h4 style="margin-top:0;">Nuovo Impegno</h4>
                <form method="POST">
                    <input type="hidden" name="gcs_front_add_manual" value="1">
                    <input type="text" name="event_title" placeholder="Titolo" required style="width:100%; margin-bottom:10px;">
                    <input type="date" name="event_start" required style="width:100%; margin-bottom:10px;">
                    <input type="date" name="event_end" required style="width:100%; margin-bottom:10px;">
                    <button type="submit" style="width:100%; background:#2d5a27; color:#fff; border:none; padding:10px; border-radius:4px; font-weight:700;">Aggiungi</button>
                </form>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    private static function render_settings_management() {
        ob_start(); ?>
        <div class="gcs-card" style="padding:30px;">
            <form method="POST">
                <input type="hidden" name="gcs_front_settings_save" value="1">
                <div style="margin-bottom:15px;"><label style="display:block; font-weight:700;">Email Notifiche</label><input type="email" name="gcs_notification_email" value="<?php echo esc_attr(get_option('gcs_notification_email')); ?>" style="width:100%;"></div>
                <button type="submit" style="background:#2d5a27; color:#fff; border:none; padding:10px 30px; border-radius:4px; font-weight:700;">Salva</button>
            </form>
        </div>
        <?php return ob_get_clean();
    }

    private static function render_login_form() {
        ob_start(); ?>
        <div class="gcs-card" style="max-width:350px; margin:50px auto; padding:30px; text-align:center;">
            <h3>Accesso Area Riservata</h3>
            <form method="POST">
                <input type="text" name="gcs_username" placeholder="User" required style="width:100%; margin-bottom:10px;">
                <input type="password" name="gcs_password" placeholder="Pass" required style="width:100%; margin-bottom:20px;">
                <button type="submit" name="gcs_reserved_login_submit" style="width:100%; background:#2d5a27; color:#fff; border:none; padding:10px; border-radius:4px; font-weight:700;">Entra</button>
            </form>
        </div>
        <?php return ob_get_clean();
    }
}
