<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Gestione Area Riservata
 * Versione 1.4.9 - FULL JS & AJAX SYNC
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
                            'end_date' => sanitize_text_field($_POST['edit_end']),
                            'message' => sanitize_textarea_field($_POST['edit_message'])
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
                    update_option('gcs_show_guests_field', isset($_POST['gcs_show_guests_field']) ? 1 : 0);
                    update_option('gcs_show_message_field', isset($_POST['gcs_show_message_field']) ? 1 : 0);
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
        <div class="gcs-reserved-wrapper" style="font-family: 'Inter', sans-serif; margin: 30px 0; color: #333;">
            <link href="https://fonts.googleapis.com/css2?family=Martel:wght@700;900&family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
            <style>
                :root { --gcs-primary: #2d5a27; --gcs-secondary: #e67e22; --gcs-dark: #2c3e50; }
                .gcs-premium-card { background: #fff; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.06); border: 1px solid #eef2f7; overflow: hidden; margin-bottom: 25px; }
                .gcs-tab-btn { padding: 18px 35px; border: none; background: none; cursor: pointer; font-weight: 700; font-size: 15px; color: #7f8c8d; border-bottom: 3px solid transparent; transition: 0.3s; }
                .gcs-tab-btn:hover { color: var(--gcs-primary); }
                .gcs-tab-btn.active { color: var(--gcs-primary); border-bottom-color: var(--gcs-primary); background: rgba(45, 90, 39, 0.05); }
                
                .gcs-day-cell { background: #fff; height: 110px; padding: 8px; display: flex; flex-direction: column; gap: 4px; overflow: visible; position: relative; border: 0.5px solid #e2e8f0; }
                .gcs-event-bar { 
                    cursor: pointer; padding: 4px 10px; font-size: 10px; font-weight: 800; border-radius: 5px; 
                    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #fff;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.1); position: relative; z-index: 5; margin: 2px 0;
                    height: 22px; line-height: 14px; transition: transform 0.2s;
                }
                .gcs-event-bar:hover { transform: scale(1.02); z-index: 10; }
                .gcs-event-bar.cont-prev { border-top-left-radius: 0; border-bottom-left-radius: 0; margin-left: -9px; width: calc(100% + 9px); }
                .gcs-event-bar.cont-next { border-top-right-radius: 0; border-bottom-right-radius: 0; margin-right: -9px; width: calc(100% + 9px); }
                .gcs-event-bar.cont-prev.cont-next { width: calc(100% + 18px); }
            </style>

            <header style="display: flex; justify-content: space-between; align-items: center; background: linear-gradient(135deg, var(--gcs-primary) 0%, var(--gcs-dark) 100%); padding: 40px; border-radius: 20px; color: #fff; box-shadow: 0 15px 35px rgba(0,0,0,0.1); margin-bottom: 40px;">
                <div>
                    <h2 style="margin: 0; font-size: 32px; font-family: 'Martel', serif; letter-spacing: -0.5px;">Area Amministrazione</h2>
                    <p style="margin: 8px 0 0; opacity: 0.8; font-size: 14px; font-weight: 500;">Gestione Casa Scout &bull; Portale Riservato</p>
                </div>
                <a href="<?php echo esc_url(add_query_arg('gcs_logout', '1')); ?>" style="background: var(--gcs-secondary); color: #fff; padding: 14px 28px; border-radius: 12px; text-decoration: none; font-weight: 700; font-size: 14px; transition: 0.3s;">Disconnetti</a>
            </header>

            <nav style="display: flex; gap: 10px; margin-bottom: 30px; background: #fff; padding: 5px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
                <button class="gcs-tab-btn active" id="btn_requests" onclick="gcsShowTab('requests')">Bacheca Richieste</button>
                <button class="gcs-tab-btn" id="btn_calendar" onclick="gcsShowTab('calendar')">Calendario Impegni</button>
                <button class="gcs-tab-btn" id="btn_settings" onclick="gcsShowTab('settings')">Impostazioni</button>
            </nav>

            <div id="tab_requests" class="gcs-tab-content"><?php echo self::render_requests_management(); ?></div>
            <div id="tab_calendar" class="gcs-tab-content" style="display:none;"><?php echo self::render_calendar_management(); ?></div>
            <div id="tab_settings" class="gcs-tab-content" style="display:none;"><?php echo self::render_settings_management(); ?></div>

            <div id="gcsEditModal" style="display:none; position:fixed; z-index:99999; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); backdrop-filter:blur(4px); align-items:center; justify-content:center;">
                <div class="gcs-premium-card" style="padding:40px; width:550px; position:relative;">
                    <h3 style="margin-top:0; color:var(--gcs-primary); font-family:'Martel',serif; font-size:24px; margin-bottom:25px;">Modifica Evento</h3>
                    <form method="POST" id="gcs-edit-form">
                        <input type="hidden" name="gcs_edit_event_action" value="1">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <input type="hidden" name="gcs_event_op" id="event_op" value="save">
                        <div style="margin-bottom:15px;">
                            <label style="display:block; font-weight:700; font-size:12px; margin-bottom:5px;">TITOLO</label>
                            <input type="text" name="edit_title" id="edit_title" required style="width:100%; padding:12px; border:2px solid #f1f5f9; border-radius:10px;">
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px;">
                            <input type="date" name="edit_start" id="edit_start" required style="width:100%; padding:12px; border:2px solid #f1f5f9; border-radius:10px;">
                            <input type="date" name="edit_end" id="edit_end" required style="width:100%; padding:12px; border:2px solid #f1f5f9; border-radius:10px;">
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-top:20px;">
                            <button type="submit" onclick="document.getElementById('event_op').value='delete'; return confirm('Eliminare?');" style="background:#fff1f0; color:#e74c3c; border:1px solid #ffa39e; padding:12px 20px; border-radius:10px; cursor:pointer; font-weight:700;">ELIMINA</button>
                            <div style="display:flex; gap:10px;">
                                <button type="button" onclick="document.getElementById('gcsEditModal').style.display='none'" style="background:#f8fafc; border:1px solid #e2e8f0; padding:12px 25px; border-radius:10px; cursor:pointer;">ANNULLA</button>
                                <button type="submit" style="background:var(--gcs-primary); color:#fff; border:none; padding:12px 30px; border-radius:10px; cursor:pointer; font-weight:700;">SALVA</button>
                            </div>
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
            function gcsEditEvent(id, title, start, end, msg) {
                document.getElementById('edit_id').value = id;
                document.getElementById('edit_title').value = title;
                document.getElementById('edit_start').value = start;
                document.getElementById('edit_end').value = end;
                document.getElementById('gcsEditModal').style.display = 'flex';
            }
            document.querySelectorAll('#tab_requests form').forEach(form => {
                form.onsubmit = function(e) {
                    e.preventDefault();
                    let fd = new FormData(this);
                    fetch(window.location.href, {
                        method: 'POST',
                        body: fd,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    }).then(() => {
                        fetch(window.location.href).then(r => r.text()).then(html => {
                            let parser = new DOMParser();
                            let doc = parser.parseFromString(html, 'text/html');
                            document.getElementById('tab_requests').innerHTML = doc.getElementById('tab_requests').innerHTML;
                            document.getElementById('tab_calendar').innerHTML = doc.getElementById('tab_calendar').innerHTML;
                            document.querySelectorAll('#tab_requests form').forEach(f => f.onsubmit = form.onsubmit);
                        });
                    });
                };
            });
            </script>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function render_requests_management() {
        $requests = GCS_DB_Manager::get_requests();
        ob_start();
        ?>
        <div class="gcs-premium-card">
            <table style="width:100%; border-collapse:collapse;">
                <thead style="background:#f9fafb;">
                    <tr>
                        <th style="padding:22px; text-align:left; color:var(--gcs-primary); font-size:11px; font-weight:800; text-transform:uppercase; border-bottom:1px solid #eee;">Gruppo</th>
                        <th style="padding:22px; text-align:left; color:var(--gcs-primary); font-size:11px; font-weight:800; text-transform:uppercase; border-bottom:1px solid #eee;">Dettagli</th>
                        <th style="padding:22px; text-align:center; color:var(--gcs-primary); font-size:11px; font-weight:800; text-transform:uppercase; border-bottom:1px solid #eee; width:220px;">Stato</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr><td colspan="3" style="padding:60px; text-align:center; color:#999;">Nessuna richiesta.</td></tr>
                    <?php else: foreach ($requests as $r): ?>
                        <tr style="border-bottom:1px solid #f2f4f6;">
                            <td style="padding:22px;">
                                <strong style="font-size:18px; color:var(--gcs-primary); font-family: 'Martel', serif;"><?php echo esc_html($r->group_name); ?></strong><br>
                                <span style="font-size:13px; color:#777;"><?php echo esc_html($r->contact_email); ?></span>
                            </td>
                            <td style="padding:22px; font-size:14px;">
                                📅 <strong><?php echo date('d/m/Y', strtotime($r->start_date)); ?></strong> &rarr; <strong><?php echo date('d/m/Y', strtotime($r->end_date)); ?></strong><br>
                                👥 <?php echo esc_html($r->guests_count); ?> Ospiti
                            </td>
                            <td style="padding:22px; text-align:center;">
                                <form method="POST" style="display:flex; align-items:center; justify-content:center; gap:10px;">
                                    <?php wp_nonce_field('front_status', 'gcs_front_nonce'); ?>
                                    <input type="hidden" name="request_id" value="<?php echo $r->id; ?>">
                                    <input type="hidden" name="gcs_front_update_status" value="1">
                                    <?php 
                                        $sc = ($r->status === 'confirmed') ? '#27ae60' : (($r->status === 'rejected') ? '#e74c3c' : '#f39c12');
                                        $sbg = ($r->status === 'confirmed') ? '#e8f5ed' : (($r->status === 'rejected') ? '#fdeadb' : '#fef5e7');
                                    ?>
                                    <select name="status" onchange="this.form.dispatchEvent(new Event('submit', {cancelable:true, bubbles:true}))" style="padding:8px 12px; border-radius:8px; background:<?php echo $sbg; ?>; border:1px solid <?php echo $sc; ?>; font-weight:800; color:<?php echo $sc; ?>; font-size:11px;">
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
        <?php
        return ob_get_clean();
    }

    private static function render_calendar_management() {
        global $wpdb;
        $table = $wpdb->prefix . 'gcs_requests';
        $m = isset($_GET['c_month']) ? intval($_GET['c_month']) : date('n');
        $y = isset($_GET['c_year']) ? intval($_GET['c_year']) : date('Y');
        $start = sprintf("%04d-%02d-01", $y, $m);
        $end = date("Y-m-t", strtotime($start));
        $events = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE status = 'confirmed' AND (start_date <= %s AND end_date >= %s)", $end, $start));
        ob_start();
        ?>
        <div style="display:grid; grid-template-columns: 2fr 1fr; gap:30px;">
            <div class="gcs-premium-card" style="padding:30px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                    <a href="?c_month=<?php echo $m==1?12:$m-1; ?>&c_year=<?php echo $m==1?$y-1:$y; ?>" style="text-decoration:none; color:var(--gcs-primary); font-weight:800;">&laquo; Precedente</a>
                    <h3 style="margin:0; font-family:'Martel',serif;"><?php echo date('F Y', strtotime($start)); ?></h3>
                    <a href="?c_month=<?php echo $m==12?1:$m+1; ?>&c_year=<?php echo $m==12?$y+1:$y; ?>" style="text-decoration:none; color:var(--gcs-primary); font-weight:800;">Successivo &raquo;</a>
                </div>
                <div style="display:grid; grid-template-columns: repeat(7, 1fr); gap:1px; background:#e2e8f0; border-radius:10px; overflow:hidden;">
                    <?php foreach(array('L','M','M','G','V','S','D') as $d) echo '<div style="background:#f8fafc; padding:10px; text-align:center; font-weight:800; font-size:10px; color:#94a3b8;">'.$d.'</div>'; ?>
                    <?php
                    $fw = date('w', strtotime($start)); $fw = ($fw == 0) ? 7 : $fw;
                    for ($i = 1; $i < $fw; $i++) echo '<div style="background:#fafafa; height:100px;"></div>';
                    for ($d = 1; $d <= date('t', strtotime($start)); $d++) {
                        $cur = sprintf("%04d-%02d-%02d", $y, $m, $d);
                        $isToday = ($cur == date('Y-m-d'));
                        echo '<div style="background:#fff; height:100px; padding:5px; position:relative;'.($isToday?'background:#f0fdf4;':'').' border:0.5px solid #eee;">';
                        echo '<span style="color:#cbd5e1; font-weight:900; font-size:11px;">'.$d.'</span>';
                        foreach($events as $e) {
                            if($cur >= $e->start_date && $cur <= $e->end_date) {
                                $isStart = ($cur == $e->start_date); $isEnd = ($cur == $e->end_date);
                                $color = ($e->contact_email == 'manuale@calendario.local') ? '#e74c3c' : '#3498db';
                                ?>
                                <div onclick="gcsEditEvent(<?php echo $e->id; ?>, '<?php echo esc_js($e->group_name); ?>', '<?php echo $e->start_date; ?>', '<?php echo $e->end_date; ?>')" 
                                     class="gcs-event-bar <?php if(!$isStart) echo 'cont-prev'; ?> <?php if(!$isEnd) echo 'cont-next'; ?>" 
                                     style="background:<?php echo $color; ?>;"><?php if($isStart || $d==1) echo esc_html($e->group_name); ?></div>
                                <?php
                            }
                        }
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>
            <div class="gcs-premium-card" style="padding:25px; height:fit-content;">
                <h4 style="margin-top:0; color:var(--gcs-primary); font-family:'Martel',serif; margin-bottom:15px;">Aggiungi Impegno</h4>
                <form method="POST">
                    <input type="hidden" name="gcs_front_add_manual" value="1">
                    <input type="text" name="event_title" placeholder="Titolo" required style="width:100%; padding:10px; margin-bottom:10px; border:1px solid #ddd; border-radius:8px;">
                    <input type="date" name="event_start" required style="width:100%; padding:10px; margin-bottom:10px; border:1px solid #ddd; border-radius:8px;">
                    <input type="date" name="event_end" required style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #ddd; border-radius:8px;">
                    <button type="submit" style="width:100%; background:var(--gcs-secondary); color:#fff; border:none; padding:12px; border-radius:8px; font-weight:700; cursor:pointer;">Aggiungi</button>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function render_settings_management() {
        ob_start(); ?>
        <div class="gcs-premium-card" style="padding:40px;">
            <form method="POST">
                <input type="hidden" name="gcs_front_settings_save" value="1">
                <div style="margin-bottom:20px;">
                    <label style="font-weight:700;">Email Notifiche</label>
                    <input type="email" name="gcs_notification_email" value="<?php echo esc_attr(get_option('gcs_notification_email')); ?>" style="width:100%; padding:12px; border:2px solid #f1f5f9; border-radius:10px;">
                </div>
                <div style="margin-bottom:20px;">
                    <label style="font-weight:700;">Utenti (user:pass)</label>
                    <textarea name="gcs_reserved_users" rows="5" style="width:100%; padding:12px; border:2px solid #f1f5f9; border-radius:10px;"><?php echo esc_textarea(get_option('gcs_reserved_users')); ?></textarea>
                </div>
                <button type="submit" style="background:var(--gcs-primary); color:#fff; padding:12px 30px; border:none; border-radius:8px; cursor:pointer; font-weight:700;">Salva</button>
            </form>
        </div>
        <?php return ob_get_clean();
    }

    private static function render_login_form() {
        ob_start(); ?>
        <div class="gcs-premium-card" style="max-width:400px; margin:50px auto; padding:40px; text-align:center;">
            <h2 style="font-family:'Martel',serif; color:var(--gcs-primary); margin-bottom:30px;">Login Admin</h2>
            <form method="POST">
                <input type="text" name="gcs_username" placeholder="Username" required style="width:100%; padding:12px; border-radius:8px; border:1px solid #ddd; margin-bottom:15px;">
                <input type="password" name="gcs_password" placeholder="Password" required style="width:100%; padding:12px; border-radius:8px; border:1px solid #ddd; margin-bottom:20px;">
                <button type="submit" name="gcs_reserved_login_submit" style="width:100%; background:var(--gcs-primary); color:#fff; border:none; padding:12px; border-radius:8px; font-weight:700; cursor:pointer;">Entra</button>
            </form>
        </div>
        <?php return ob_get_clean();
    }
}
