<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Gestione Area Riservata
 * Versione 1.4.6 - RIPRISTINO ESTETICA PREMIUM "WOW"
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

            if (isset($_POST['gcs_front_update_status']) && wp_verify_nonce($_POST['gcs_front_nonce'], 'front_status')) {
                GCS_DB_Manager::update_status(intval($_POST['request_id']), sanitize_text_field($_POST['status']));
                if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) { wp_safe_redirect(add_query_arg('msg', 'updated', remove_query_arg('msg'))); exit; }
            }

            if (isset($_POST['gcs_front_delete_req']) && wp_verify_nonce($_POST['gcs_front_nonce'], 'front_status')) {
                $wpdb->delete($table, array('id' => intval($_POST['request_id'])));
                if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) { wp_safe_redirect(add_query_arg('msg', 'deleted', remove_query_arg('msg'))); exit; }
            }

            if (isset($_POST['gcs_edit_event_action']) && wp_verify_nonce($_POST['gcs_edit_nonce'], 'edit_event_action')) {
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
                if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) { wp_safe_redirect(add_query_arg('msg', 'event_updated', remove_query_arg('msg'))); exit; }
            }

            if (isset($_POST['gcs_front_add_manual']) && wp_verify_nonce($_POST['gcs_nonce'], 'add_manual_event')) {
                $wpdb->insert($table, array(
                    'group_name' => sanitize_text_field($_POST['event_title']),
                    'contact_email' => 'manuale@calendario.local',
                    'start_date' => sanitize_text_field($_POST['event_start']),
                    'end_date' => sanitize_text_field($_POST['event_end']),
                    'status' => 'confirmed'
                ));
                if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) { wp_safe_redirect(add_query_arg('msg', 'manual_added', remove_query_arg('msg'))); exit; }
            }

            if (isset($_POST['gcs_front_settings_save']) && wp_verify_nonce($_POST['gcs_front_nonce'], 'front_settings')) {
                update_option('gcs_notification_email', sanitize_email($_POST['gcs_notification_email']));
                update_option('gcs_reserved_users', wp_unslash($_POST['gcs_reserved_users']));
                update_option('gcs_show_guests_field', isset($_POST['gcs_show_guests_field']) ? 1 : 0);
                update_option('gcs_show_message_field', isset($_POST['gcs_show_message_field']) ? 1 : 0);
                if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) { wp_safe_redirect(add_query_arg('msg', 'settings_saved', remove_query_arg('msg'))); exit; }
            }
        }
    }

    public static function render_reserved_area() {
        if (!self::is_authorized()) return self::render_login_form();

        ob_start();
        $msg_html = '';
        if (isset($_GET['msg'])) {
            $m = sanitize_text_field($_GET['msg']);
            $labels = array('updated'=>'Stato aggiornato', 'deleted'=>'Richiesta eliminata', 'event_updated'=>'Evento modificato', 'manual_added'=>'Impegno aggiunto', 'settings_saved'=>'Impostazioni salvate');
            if (isset($labels[$m])) $msg_html = '<div style="background:#d4edda; color:#155724; padding:15px; border-radius:8px; margin-bottom:20px; text-align:center; font-weight:bold; box-shadow:0 4px 12px rgba(0,0,0,0.05); border-left: 5px solid #27ae60;">'.esc_html($labels[$m]).' correttamente!</div>';
        }
        ?>
        <div class="gcs-reserved-wrapper" style="font-family: inherit; margin: 30px 0; color: #333;">
            <link href="https://fonts.googleapis.com/css2?family=Martel:wght@700;900&family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
            <style>
                .gcs-reserved-wrapper { font-family: 'Inter', sans-serif; }
                .gcs-premium-card { background: #fff; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.04); border: 1px solid #eee; overflow: hidden; }
                .gcs-tab-btn { padding: 15px 30px; border: none; background: none; cursor: pointer; font-weight: 700; font-size: 15px; color: #999; border-bottom: 3px solid transparent; transition: 0.3s; }
                .gcs-tab-btn:hover { color: #1a4581; }
                .gcs-tab-btn.active { color: #1a4581; border-bottom-color: #1a4581; }
                .gcs-badge { display: inline-block; padding: 6px 14px; border-radius: 20px; font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }
            </style>

            <?php echo $msg_html; ?>
            
            <header style="display: flex; justify-content: space-between; align-items: center; background: linear-gradient(135deg, #1a4581 0%, #2c3e50 100%); padding: 35px; border-radius: 16px; color: #fff; box-shadow: 0 10px 30px rgba(0,0,0,0.15); margin-bottom: 40px; position:relative; overflow:hidden;">
                <div style="position:absolute; top:-20px; right:-20px; width:150px; height:150px; background:rgba(255,255,255,0.05); border-radius:50%;"></div>
                <div style="z-index:1;">
                    <h2 style="margin: 0; font-size: 28px; font-family: 'Martel', serif; color: #fff; text-shadow: 1px 1px 2px rgba(0,0,0,0.1);">Dashboard Gestione Casa Scout</h2>
                    <p style="margin: 10px 0 0; opacity: 0.8; font-size: 14px; font-weight: 500;">Benvenuto nell'area amministrativa riservata</p>
                </div>
                <a href="<?php echo esc_url(add_query_arg('gcs_logout', '1')); ?>" style="background: #e74c3c; color: #fff; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 14px; transition: 0.3s; box-shadow: 0 4px 12px rgba(231,76,60,0.2); z-index:1;" onmouseover="this.style.background='#c0392b'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='#e74c3c'; this.style.transform='translateY(0)'">Esci dalla Sessione</a>
            </header>

            <nav style="display: flex; gap: 5px; margin-bottom: 30px; border-bottom: 2px solid #f0f0f0; padding-bottom: 0;">
                <button class="gcs-tab-btn active" id="btn_requests" onclick="window.gcsShowTab('requests')">Bacheca Richieste</button>
                <button class="gcs-tab-btn" id="btn_calendar" onclick="window.gcsShowTab('calendar')">Calendario Impegni</button>
                <button class="gcs-tab-btn" id="btn_settings" onclick="window.gcsShowTab('settings')">Impostazioni & Login</button>
            </nav>

            <div id="tab_requests" class="gcs-tab-content" style="display:block;"><?php echo self::render_requests_management(); ?></div>
            <div id="tab_calendar" class="gcs-tab-content" style="display:none; transition: opacity 0.3s;"><?php echo self::render_calendar_management(); ?></div>
            <div id="tab_settings" class="gcs-tab-content" style="display:none; transition: opacity 0.3s;"><?php echo self::render_settings_management(); ?></div>

            <script>
                (function() {
                    window.gcsShowTab = function(id) {
                        document.querySelectorAll('.gcs-tab-content').forEach(el => el.style.display = 'none');
                        document.querySelectorAll('.gcs-tab-btn').forEach(btn => btn.classList.remove('active'));
                        
                        var content = document.getElementById('tab_' + id);
                        content.style.display = 'block';
                        content.style.opacity = '0';
                        setTimeout(() => { content.style.opacity = '1'; }, 10);
                        
                        document.getElementById('btn_' + id).classList.add('active');
                    };

                    document.addEventListener('click', function(e) {
                        var nav = e.target.closest('.gcs-cal-nav');
                        if (nav) {
                            e.preventDefault();
                            var calContainer = document.getElementById('tab_calendar');
                            calContainer.style.opacity = '0.4';
                            fetch(nav.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                                .then(r => r.text())
                                .then(html => {
                                    var doc = new DOMParser().parseFromString(html, 'text/html');
                                    calContainer.innerHTML = doc.querySelector('#tab_calendar').innerHTML;
                                    calContainer.style.opacity = '1';
                                });
                        }
                    });

                    document.addEventListener('submit', function(e) {
                        var form = e.target;
                        if (form.closest('.gcs-tab-content')) {
                            e.preventDefault();
                            var formData = new FormData(form);
                            if (e.submitter && e.submitter.name) formData.append(e.submitter.name, e.submitter.value);
                            
                            var wrapper = document.querySelector('.gcs-reserved-wrapper');
                            wrapper.style.opacity = '0.5';
                            
                            fetch(window.location.href, {
                                method: 'POST',
                                body: formData, 
                                headers: { 'X-Requested-With': 'XMLHttpRequest' }
                            }).then(r => r.text()).then(html => {
                                var doc = new DOMParser().parseFromString(html, 'text/html');
                                var currentTab = document.querySelector('.gcs-tab-content[style*="block"]').id;
                                wrapper.innerHTML = doc.querySelector('.gcs-reserved-wrapper').innerHTML;
                                wrapper.style.opacity = '1';
                                window.gcsShowTab(currentTab.replace('tab_', ''));
                            });
                        }
                    });
                })();
            </script>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function render_login_form() {
        ob_start();
        $err = isset($_GET['gcs_login_error']) ? 'Credenziali non valide.' : '';
        ?>
        <div style="max-width: 450px; margin: 100px auto; padding: 50px; background: #fff; border-radius: 24px; box-shadow: 0 20px 60px rgba(0,0,0,0.1); border: 1px solid #eee; text-align: center; font-family: 'Inter', sans-serif;">
            <div style="background: linear-gradient(135deg, #1a4581 0%, #3498db 100%); color: #fff; width: 80px; height: 80px; border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 30px; font-size: 32px; box-shadow: 0 10px 20px rgba(26,69,129,0.2);">🔒</div>
            <h2 style="margin-bottom: 35px; color: #1a4581; font-family: 'Martel', serif; font-size: 28px;">Area Amministrativa</h2>
            <?php if ($err): ?><div style="background:#fdeaea; color:#c0392b; padding:12px; border-radius:10px; margin-bottom:25px; font-weight:700; font-size:14px;"><?php echo $err; ?></div><?php endif; ?>
            <form method="POST">
                <div style="text-align:left; margin-bottom:20px;">
                    <label style="display:block; font-weight:700; color:#555; margin-bottom:8px; font-size:13px;">NOME UTENTE</label>
                    <input type="text" name="gcs_username" required style="width:100%; padding:14px; border:2px solid #eee; border-radius:12px; font-size:16px; transition:0.3s;" onfocus="this.style.borderColor='#1a4581'">
                </div>
                <div style="text-align:left; margin-bottom:30px;">
                    <label style="display:block; font-weight:700; color:#555; margin-bottom:8px; font-size:13px;">PASSWORD</label>
                    <input type="password" name="gcs_password" required style="width:100%; padding:14px; border:2px solid #eee; border-radius:12px; font-size:16px; transition:0.3s;" onfocus="this.style.borderColor='#1a4581'">
                </div>
                <button type="submit" name="gcs_reserved_login_submit" style="width:100%; background:#1a4581; color:#fff; padding:16px; border:none; border-radius:12px; font-weight:700; font-size:16px; cursor:pointer; transition: 0.3s; box-shadow: 0 10px 20px rgba(26,69,129,0.3);" onmouseover="this.style.transform='translateY(-2px)'; this.style.background='#133463'" onmouseout="this.style.transform='translateY(0)'; this.style.background='#1a4581'">Accedi Ora</button>
            </form>
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
                        <th style="padding:22px; text-align:left; color:#1a4581; text-transform:uppercase; font-size:11px; font-weight:800; border-bottom:1px solid #eee; letter-spacing:1px;">Info Gruppo</th>
                        <th style="padding:22px; text-align:left; color:#1a4581; text-transform:uppercase; font-size:11px; font-weight:800; border-bottom:1px solid #eee; letter-spacing:1px;">Date & Ospiti</th>
                        <th style="padding:22px; text-align:center; color:#1a4581; text-transform:uppercase; font-size:11px; font-weight:800; border-bottom:1px solid #eee; letter-spacing:1px; width:220px;">Stato Gestione</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr><td colspan="3" style="padding:60px; text-align:center; color:#999; font-style:italic;">Nessuna richiesta presente.</td></tr>
                    <?php else: foreach ($requests as $r): ?>
                        <tr style="border-bottom:1px solid #f2f4f6; transition: 0.2s;" onmouseover="this.style.background='#fbfcfe'" onmouseout="this.style.background='transparent'">
                            <td style="padding:22px;">
                                <strong style="font-size:18px; color:#1a4581; display:block; margin-bottom:6px; font-family: 'Martel', serif;"><?php echo esc_html($r->group_name); ?></strong>
                                <a href="mailto:<?php echo esc_attr($r->contact_email); ?>" style="color:#d35400; text-decoration:none; font-size:14px; font-weight:600;">✉️ <?php echo esc_html($r->contact_email); ?></a>
                            </td>
                            <td style="padding:22px; font-size:14px; color:#444;">
                                <div style="margin-bottom:6px;">📅 <strong><?php echo date('d/m/Y', strtotime($r->start_date)); ?></strong> &rarr; <strong><?php echo date('d/m/Y', strtotime($r->end_date)); ?></strong></div>
                                <div style="display:inline-block; background:#f0f4f8; padding:4px 10px; border-radius:6px; font-size:12px; font-weight:700; color:#1a4581;">👥 <?php echo esc_html($r->guests_count); ?> Ospiti</div>
                            </td>
                            <td style="padding:22px; text-align:center;">
                                <form method="POST" style="display:flex; align-items:center; justify-content:center; gap:12px;">
                                    <?php wp_nonce_field('front_status', 'gcs_front_nonce'); ?>
                                    <input type="hidden" name="request_id" value="<?php echo $r->id; ?>">
                                    <input type="hidden" name="gcs_front_update_status" value="1">
                                    <?php 
                                        $sc = ($r->status === 'confirmed') ? '#27ae60' : (($r->status === 'rejected') ? '#e74c3c' : '#f39c12');
                                        $sbg = ($r->status === 'confirmed') ? '#e8f5ed' : (($r->status === 'rejected') ? '#fdeadb' : '#fef5e7');
                                    ?>
                                    <select name="status" onchange="this.form.submit()" style="padding:10px 14px; border-radius:10px; background:<?php echo $sbg; ?>; border:1px solid <?php echo $sc; ?>; font-weight:800; color:<?php echo $sc; ?>; font-size:12px; cursor:pointer;">
                                        <option value="pending" <?php selected($r->status, 'pending'); ?>>IN ATTESA</option>
                                        <option value="confirmed" <?php selected($r->status, 'confirmed'); ?>>CONFERMATA</option>
                                        <option value="rejected" <?php selected($r->status, 'rejected'); ?>>RIFIUTATA</option>
                                    </select>
                                    <button type="submit" name="gcs_front_delete_req" value="1" style="background:none; border:none; color:#ddd; cursor:pointer; font-size:22px; transition:0.3s;" onmouseover="this.style.color='#e74c3c'" onmouseout="this.style.color='#ddd'" onclick="return confirm('Sei sicuro di voler eliminare definitivamente?')">🗑️</button>
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
        
        $mn = array('', 'Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno','Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre');
        ob_start();
        ?>
        <div style="display:grid; grid-template-columns: 2.2fr 1fr; gap:35px;">
            <div class="gcs-premium-card" style="padding:35px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:35px; border-bottom:1px solid #f0f0f0; padding-bottom:20px;">
                    <a href="<?php echo esc_url(add_query_arg(array('c_month'=>($m==1?12:$m-1), 'c_year'=>($m==1?$y-1:$y)))); ?>" class="gcs-cal-nav" style="text-decoration:none; font-weight:800; color:#1a4581; font-size:14px; background:#f4f7f9; padding:8px 15px; border-radius:8px;">&laquo; <?php echo $mn[$m==1?12:$m-1]; ?></a>
                    <h3 style="margin:0; font-size:24px; font-family:'Martel',serif; color:#1a4581; letter-spacing:-1px;"><?php echo $mn[$m].' '.$y; ?></h3>
                    <a href="<?php echo esc_url(add_query_arg(array('c_month'=>($m==12?1:$m+1), 'c_year'=>($m==12?$y+1:$y)))); ?>" class="gcs-cal-nav" style="text-decoration:none; font-weight:800; color:#1a4581; font-size:14px; background:#f4f7f9; padding:8px 15px; border-radius:8px;"><?php echo $mn[$m==12?1:$m+1]; ?> &raquo;</a>
                </div>
                
                <div style="display:grid; grid-template-columns: repeat(7, 1fr); gap:1px; background:#eee; border:1px solid #eee; border-radius:12px; overflow:hidden; box-shadow:0 10px 25px rgba(0,0,0,0.03);">
                    <?php foreach(array('LUN','MAR','MER','GIO','VEN','SAB','DOM') as $d) echo '<div style="background:#f8f9fb; padding:12px; text-align:center; font-weight:800; font-size:11px; color:#94a3b8; letter-spacing:1px;">'.$d.'</div>'; ?>
                    <?php
                    $fw = date('w', strtotime($start)); $fw = ($fw == 0) ? 7 : $fw;
                    $tds = date('t', strtotime($start));
                    for ($i = 1; $i < $fw; $i++) echo '<div style="background:#fafafa; height:100px;"></div>';
                    for ($d = 1; $d <= $tds; $d++) {
                        $cur = sprintf("%04d-%02d-%02d", $y, $m, $d);
                        $isToday = ($cur == date('Y-m-d'));
                        echo '<div style="background:#fff; height:100px; padding:10px; display:flex; flex-direction:column; gap:5px; font-size:13px; border-top:1px solid #f0f0f0; border-left:1px solid #f0f0f0; '.($isToday?'background:#f0fbff;':'').'">';
                        echo '<span style="color:#cbd5e1; font-weight:900; font-size:12px; '.($isToday?'color:#1a4581; font-size:14px;':'').'">'.$d.'</span>';
                        foreach($events as $e) {
                            if($cur >= $e->start_date && $cur <= $e->end_date) {
                                $c = ($e->contact_email == 'manuale@calendario.local') ? '#e74c3c' : '#3498db';
                                echo '<div onclick="gcsEditEvent('.$e->id.', \''.esc_js($e->group_name).'\', \''.$e->start_date.'\', \''.$e->end_date.'\', \''.esc_js($e->message).'\')" style="background:'.$c.'; color:#fff; padding:5px 9px; border-radius:6px; font-size:10px; font-weight:800; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; cursor:pointer; box-shadow:0 2px 4px rgba(0,0,0,0.1);" title="'.esc_attr($e->group_name).'">'.esc_html($e->group_name).'</div>';
                            }
                        }
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>

            <div>
                <div class="gcs-premium-card" style="padding:30px; margin-bottom: 25px;">
                    <h3 style="margin-top:0; color:#1a4581; font-family:'Martel',serif; font-size:20px; border-bottom:2px solid #f0f0f0; padding-bottom:15px; margin-bottom:25px;">Aggiunta Rapida</h3>
                    <form method="POST">
                        <?php wp_nonce_field('add_manual_event', 'gcs_nonce'); ?>
                        <input type="hidden" name="gcs_front_add_manual" value="1">
                        <div style="margin-bottom:18px;">
                            <label style="display:block; font-weight:800; font-size:11px; margin-bottom:10px; color:#64748b; text-transform:uppercase; letter-spacing:1px;">Titolo Impegno</label>
                            <input type="text" name="event_title" placeholder="Es: Manutenzione Caldaia" required style="width:100%; padding:14px; border:2px solid #f1f5f9; border-radius:12px; font-size:14px; background:#f8fafc;">
                        </div>
                        <div style="margin-bottom:18px;">
                            <label style="display:block; font-weight:800; font-size:11px; margin-bottom:10px; color:#64748b; text-transform:uppercase; letter-spacing:1px;">Data Inizio</label>
                            <input type="date" name="event_start" required style="width:100%; padding:12px; border:2px solid #f1f5f9; border-radius:12px; font-size:14px; background:#f8fafc;">
                        </div>
                        <div style="margin-bottom:25px;">
                            <label style="display:block; font-weight:800; font-size:11px; margin-bottom:10px; color:#64748b; text-transform:uppercase; letter-spacing:1px;">Data Fine</label>
                            <input type="date" name="event_end" required style="width:100%; padding:12px; border:2px solid #f1f5f9; border-radius:12px; font-size:14px; background:#f8fafc;">
                        </div>
                        <button type="submit" style="width:100%; background:#27ae60; color:#fff; padding:16px; border:none; border-radius:12px; font-weight:800; cursor:pointer; font-size:15px; box-shadow:0 8px 15px rgba(39,174,96,0.2); transition:0.3s;" onmouseover="this.style.background='#2ecc71'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='#27ae60'; this.style.transform='translateY(0)'">+ Inserisci Impegno</button>
                    </form>
                </div>
                
                <div style="background: #fff3e6; padding: 20px; border-radius: 12px; border: 1px dashed #f39c12; font-size: 13px; color: #856404; line-height:1.6;">
                    <strong>Suggerimento:</strong> Clicca su un evento nel calendario per modificarlo o eliminarlo rapidamente.
                </div>
            </div>
        </div>

        <!-- MODAL MODIFICA -->
        <div id="gcsEditModal" style="display:none; position:fixed; z-index:99999; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); backdrop-filter:blur(5px); align-items:center; justify-content:center;">
            <div class="gcs-premium-card" style="padding:45px; width:550px; max-width:92%; position:relative;">
                <h3 style="margin-top:0; color:#1a4581; font-family:'Martel',serif; font-size:24px; border-bottom:2px solid #eee; padding-bottom:15px; margin-bottom:25px;">Dettagli & Modifica</h3>
                <form method="POST">
                    <?php wp_nonce_field('edit_event_action', 'gcs_edit_nonce'); ?>
                    <input type="hidden" name="gcs_edit_event_action" value="1">
                    <input type="hidden" name="edit_id" id="edit_id">
                    <input type="hidden" name="gcs_event_op" id="event_op" value="save">
                    
                    <div style="margin-bottom:20px;">
                        <label style="display:block; font-weight:800; font-size:11px; color:#64748b; text-transform:uppercase; margin-bottom:8px;">Titolo / Gruppo</label>
                        <input type="text" name="edit_title" id="edit_title" required style="width:100%; padding:12px; border:2px solid #f1f5f9; border-radius:8px;">
                    </div>
                    
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px;">
                        <div>
                            <label style="display:block; font-weight:800; font-size:11px; color:#64748b; text-transform:uppercase; margin-bottom:8px;">Data Inizio</label>
                            <input type="date" name="edit_start" id="edit_start" required style="width:100%; padding:12px; border:2px solid #f1f5f9; border-radius:8px;">
                        </div>
                        <div>
                            <label style="display:block; font-weight:800; font-size:11px; color:#64748b; text-transform:uppercase; margin-bottom:8px;">Data Fine</label>
                            <input type="date" name="edit_end" id="edit_end" required style="width:100%; padding:12px; border:2px solid #f1f5f9; border-radius:8px;">
                        </div>
                    </div>
                    
                    <div style="margin-bottom:30px;">
                        <label style="display:block; font-weight:800; font-size:11px; color:#64748b; text-transform:uppercase; margin-bottom:8px;">Note / Messaggio</label>
                        <textarea name="edit_message" id="edit_message" rows="3" style="width:100%; padding:12px; border:2px solid #f1f5f9; border-radius:8px;"></textarea>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid #f1f5f9; padding-top:25px;">
                        <button type="submit" onclick="return confirm('Sicuro?') && (document.getElementById('event_op').value='delete')" style="background:#fff1f0; color:#e74c3c; border:1px solid #ffa39e; padding:12px 20px; border-radius:10px; cursor:pointer; font-weight:700;">ELIMINA</button>
                        <div style="display:flex; gap:12px;">
                            <button type="button" onclick="document.getElementById('gcsEditModal').style.display='none'" style="background:#f8fafc; border:1px solid #e2e8f0; padding:12px 25px; border-radius:10px; cursor:pointer; font-weight:700;">CHIUDI</button>
                            <button type="submit" style="background:#1a4581; color:#fff; border:none; padding:12px 30px; border-radius:10px; cursor:pointer; font-weight:700; box-shadow:0 6px 12px rgba(26,69,129,0.2);">SALVA</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <script>
            window.gcsEditEvent = function(id, title, start, end, msg) {
                document.getElementById('edit_id').value = id;
                document.getElementById('edit_title').value = title;
                document.getElementById('edit_start').value = start;
                document.getElementById('edit_end').value = end;
                document.getElementById('edit_message').value = msg;
                document.getElementById('event_op').value = 'save';
                document.getElementById('gcsEditModal').style.display = 'flex';
            };
        </script>
        <?php
        return ob_get_clean();
    }

    private static function render_settings_management() {
        ob_start();
        ?>
        <div class="gcs-premium-card" style="padding:45px; max-width:800px; margin:0 auto;">
            <h3 style="margin-top:0; color:#1a4581; font-family:'Martel',serif; font-size:24px; border-bottom:2px solid #f0f0f0; padding-bottom:15px; margin-bottom:30px;">Configurazione Sistema</h3>
            <form method="POST">
                <?php wp_nonce_field('front_settings', 'gcs_front_nonce'); ?>
                <input type="hidden" name="gcs_front_settings_save" value="1">
                
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:35px; margin-bottom:35px;">
                    <div>
                        <label style="display:block; font-weight:800; font-size:11px; color:#64748b; text-transform:uppercase; margin-bottom:12px; letter-spacing:1px;">Email per Notifiche</label>
                        <input type="email" name="gcs_notification_email" value="<?php echo esc_attr(get_option('gcs_notification_email')); ?>" style="width:100%; padding:14px; border:2px solid #f1f5f9; border-radius:12px; background:#f8fafc;">
                        <p style="font-size:12px; color:#94a3b8; margin-top:8px;">Tutte le nuove richieste verranno inviate qui.</p>
                    </div>
                    <div>
                        <label style="display:block; font-weight:800; font-size:11px; color:#64748b; text-transform:uppercase; margin-bottom:12px; letter-spacing:1px;">Visibilità Campi Form</label>
                        <div style="background:#f8fafc; padding:20px; border-radius:12px; border:2px solid #f1f5f9;">
                            <label style="display:flex; align-items:center; gap:12px; margin-bottom:15px; cursor:pointer; font-weight:600; font-size:14px;">
                                <input type="checkbox" name="gcs_show_guests_field" value="1" <?php checked(1, get_option('gcs_show_guests_field')); ?> style="width:18px; height:18px;"> Mostra Numero Ospiti
                            </label>
                            <label style="display:flex; align-items:center; gap:12px; cursor:pointer; font-weight:600; font-size:14px;">
                                <input type="checkbox" name="gcs_show_message_field" value="1" <?php checked(1, get_option('gcs_show_message_field')); ?> style="width:18px; height:18px;"> Mostra Note/Messaggio
                            </label>
                        </div>
                    </div>
                </div>

                <div style="margin-bottom:40px;">
                    <label style="display:block; font-weight:800; font-size:11px; color:#64748b; text-transform:uppercase; margin-bottom:12px; letter-spacing:1px;">Utenti Area Riservata (username:password)</label>
                    <textarea name="gcs_reserved_users" rows="6" style="width:100%; padding:20px; border:2px solid #f1f5f9; border-radius:12px; font-family:'Courier New', monospace; background:#f8fafc; font-size:14px;"><?php echo esc_textarea(get_option('gcs_reserved_users')); ?></textarea>
                    <p style="font-size:12px; color:#94a3b8; margin-top:8px;">Inserisci un utente per riga separandolo con i due punti (es. luca:segreta123).</p>
                </div>

                <div style="text-align:center;">
                    <button type="submit" style="background:#1a4581; color:#fff; padding:18px 60px; border:none; border-radius:14px; font-weight:800; font-size:16px; cursor:pointer; box-shadow:0 12px 25px rgba(26,69,129,0.25); transition:0.3s;" onmouseover="this.style.background='#133463'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='#1a4581'; this.style.transform='translateY(0)'">Salva Configurazioni Generali</button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}
