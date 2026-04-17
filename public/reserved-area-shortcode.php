<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Gestione Area Riservata
 * Versione 1.4.7 - FIX EVENTI MULTIGIORNO & AJAX STATUS
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

            // Aggiornamento Stato
            if (isset($_POST['gcs_front_update_status'])) {
                $id = intval($_POST['request_id']);
                $st = sanitize_text_field($_POST['status']);
                GCS_DB_Manager::update_status($id, $st);
                if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) { wp_safe_redirect(add_query_arg('msg', 'updated', remove_query_arg('msg'))); exit; }
            }

            // Eliminazione Richiesta
            if (isset($_POST['gcs_front_delete_req'])) {
                $wpdb->delete($table, array('id' => intval($_POST['request_id'])));
                if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) { wp_safe_redirect(add_query_arg('msg', 'deleted', remove_query_arg('msg'))); exit; }
            }

            // Modifica Evento Calendario
            if (isset($_POST['gcs_edit_event_action'])) {
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

            // Aggiunta Manuale
            if (isset($_POST['gcs_front_add_manual'])) {
                $wpdb->insert($table, array(
                    'group_name' => sanitize_text_field($_POST['event_title']),
                    'contact_email' => 'manuale@calendario.local',
                    'start_date' => sanitize_text_field($_POST['event_start']),
                    'end_date' => sanitize_text_field($_POST['event_end']),
                    'status' => 'confirmed'
                ));
                if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) { wp_safe_redirect(add_query_arg('msg', 'manual_added', remove_query_arg('msg'))); exit; }
            }

            // Salvataggio Impostazioni
            if (isset($_POST['gcs_front_settings_save'])) {
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
            if (isset($labels[$m])) $msg_html = '<div style="background:#d4edda; color:#155724; padding:15px; border-radius:8px; margin-bottom:20px; text-align:center; font-weight:bold; box-shadow:0 10px 30px rgba(0,0,0,0.05); border-left: 5px solid #27ae60;">'.esc_html($labels[$m]).'</div>';
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
                
                .gcs-event-bar { 
                    cursor:pointer; padding:6px 10px; margin:4px 0; font-size:11px; font-weight:800; border-radius:4px; 
                    white-space:nowrap; overflow:hidden; text-overflow:ellipsis; transition:0.2s; position:relative; z-index:1; color:#fff;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
                }
                .gcs-event-bar:hover { filter: brightness(1.1); transform: scale(1.02); z-index:2; }
                .gcs-event-bar.cont-prev { border-top-left-radius: 0; border-bottom-left-radius: 0; margin-left: -11px; width: calc(100% + 11px); }
                .gcs-event-bar.cont-next { border-top-right-radius: 0; border-bottom-right-radius: 0; margin-right: -11px; width: calc(100% + 11px); }
                .gcs-event-bar.cont-prev.cont-next { width: calc(100% + 22px); }
            </style>

            <?php echo $msg_html; ?>
            
            <header style="display: flex; justify-content: space-between; align-items: center; background: linear-gradient(135deg, #1a4581 0%, #2c3e50 100%); padding: 35px; border-radius: 16px; color: #fff; box-shadow: 0 10px 30px rgba(0,0,0,0.15); margin-bottom: 40px; position:relative; overflow:hidden;">
                <div style="z-index:1;">
                    <h2 style="margin: 0; font-size: 28px; font-family: 'Martel', serif; color: #fff;">Gestione Casa Scout</h2>
                    <p style="margin: 5px 0 0; opacity: 0.8; font-size: 14px;">Pannello di Controllo Riservato</p>
                </div>
                <a href="<?php echo esc_url(add_query_arg('gcs_logout', '1')); ?>" style="background: #e74c3c; color: #fff; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 14px; transition: 0.3s; box-shadow: 0 4px 12px rgba(231,76,60,0.2); z-index:1;">Esci Sessione</a>
            </header>

            <nav style="display:flex; gap:5px; margin-bottom:30px; border-bottom:2px solid #f0f0f0;">
                <button class="gcs-tab-btn active" id="btn_requests" onclick="window.gcsShowTab('requests')">Bacheca Richieste</button>
                <button class="gcs-tab-btn" id="btn_calendar" onclick="window.gcsShowTab('calendar')">Calendario Impegni</button>
                <button class="gcs-tab-btn" id="btn_settings" onclick="window.gcsShowTab('settings')">Impostazioni</button>
            </nav>

            <div id="tab_requests" class="gcs-tab-content" style="display:block;"><?php echo self::render_requests_management(); ?></div>
            <div id="tab_calendar" class="gcs-tab-content" style="display:none;"><?php echo self::render_calendar_management(); ?></div>
            <div id="tab_settings" class="gcs-tab-content" style="display:none;"><?php echo self::render_settings_management(); ?></div>

            <script>
                (function() {
                    window.gcsShowTab = function(id) {
                        document.querySelectorAll('.gcs-tab-content').forEach(el => el.style.display = 'none');
                        document.querySelectorAll('.gcs-tab-btn').forEach(btn => btn.classList.remove('active'));
                        document.getElementById('tab_' + id).style.display = 'block';
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
                        if (form.closest('.gcs-tab-content') || form.id === 'gcs-edit-form') {
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
                                if (document.getElementById('gcsEditModal')) document.getElementById('gcsEditModal').style.display='none';
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
        ?>
        <div style="max-width: 450px; margin: 100px auto; padding: 50px; background: #fff; border-radius: 24px; box-shadow: 0 20px 60px rgba(0,0,0,0.1); text-align: center;">
            <h2 style="color: #1a4581; font-family: 'Martel', serif; margin-bottom: 30px;">Area Protetta</h2>
            <form method="POST">
                <input type="text" name="gcs_username" placeholder="Nome Utente" required style="width:100%; padding:14px; margin-bottom:15px; border:2px solid #eee; border-radius:12px;">
                <input type="password" name="gcs_password" placeholder="Password" required style="width:100%; padding:14px; margin-bottom:25px; border:2px solid #eee; border-radius:12px;">
                <button type="submit" name="gcs_reserved_login_submit" style="width:100%; background:#1a4581; color:#fff; padding:16px; border:none; border-radius:12px; font-weight:700; cursor:pointer;">Accedi</button>
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
                        <th style="padding:20px; text-align:left; color:#1a4581; font-size:11px; font-weight:800; text-transform:uppercase;">Info Gruppo</th>
                        <th style="padding:20px; text-align:left; color:#1a4581; font-size:11px; font-weight:800; text-transform:uppercase;">Periodo</th>
                        <th style="padding:20px; text-align:center; color:#1a4581; font-size:11px; font-weight:800; text-transform:uppercase;">Gestione Stato</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr><td colspan="3" style="padding:40px; text-align:center; color:#999;">Nessuna richiesta trovata.</td></tr>
                    <?php else: foreach ($requests as $r): ?>
                        <tr style="border-bottom:1px solid #f2f4f6;">
                            <td style="padding:20px;">
                                <strong style="font-size:17px; color:#1a4581; font-family:'Martel',serif;"><?php echo esc_html($r->group_name); ?></strong><br>
                                <span style="font-size:13px; color:#777;"><?php echo esc_html($r->contact_email); ?></span>
                            </td>
                            <td style="padding:20px; font-size:14px;">
                                📅 <strong><?php echo date('d/m/Y', strtotime($r->start_date)); ?></strong> &rarr; <strong><?php echo date('d/m/Y', strtotime($r->end_date)); ?></strong><br>
                                <span style="background:#f0f4f8; padding:3px 8px; border-radius:4px; font-size:11px; font-weight:700; color:#1a4581;">👥 <?php echo $r->guests_count; ?> Ospiti</span>
                            </td>
                            <td style="padding:20px; text-align:center;">
                                <form method="POST" style="display:flex; align-items:center; justify-content:center; gap:10px;">
                                    <?php wp_nonce_field('front_status', 'gcs_front_nonce'); ?>
                                    <input type="hidden" name="request_id" value="<?php echo $r->id; ?>">
                                    <input type="hidden" name="gcs_front_update_status" value="1">
                                    <select name="status" onchange="this.form.dispatchEvent(new Event('submit', {cancelable:true, bubbles:true}))" style="padding:8px; border-radius:8px; border:1px solid #ccc; font-weight:700; font-size:12px;">
                                        <option value="pending" <?php selected($r->status, 'pending'); ?>>IN ATTESA</option>
                                        <option value="confirmed" <?php selected($r->status, 'confirmed'); ?>>CONFERMATA</option>
                                        <option value="rejected" <?php selected($r->status, 'rejected'); ?>>RIFIUTATA</option>
                                    </select>
                                    <button type="submit" name="gcs_front_delete_req" value="1" style="background:none; border:none; color:#ddd; cursor:pointer; font-size:20px;" onclick="return confirm('Eliminare?')">🗑️</button>
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
        <div style="display:grid; grid-template-columns: 2fr 1fr; gap:30px;">
            <div class="gcs-premium-card" style="padding:30px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px;">
                    <a href="<?php echo esc_url(add_query_arg(array('c_month'=>($m==1?12:$m-1), 'c_year'=>($m==1?$y-1:$y)))); ?>" class="gcs-cal-nav" style="text-decoration:none; font-weight:800; color:#1a4581;">&laquo; <?php echo $mn[$m==1?12:$m-1]; ?></a>
                    <h3 style="font-family:'Martel',serif; color:#1a4581; margin:0;"><?php echo $mn[$m].' '.$y; ?></h3>
                    <a href="<?php echo esc_url(add_query_arg(array('c_month'=>($m==12?1:$m+1), 'c_year'=>($m==12?$y+1:$y)))); ?>" class="gcs-cal-nav" style="text-decoration:none; font-weight:800; color:#1a4581;"><?php echo $mn[$m==12?1:$m+1]; ?> &raquo;</a>
                </div>
                
                <div style="display:grid; grid-template-columns: repeat(7, 1fr); gap:1px; background:#eee; border-radius:12px; overflow:hidden; border:1px solid #eee;">
                    <?php foreach(array('L','M','M','G','V','S','D') as $d) echo '<div style="background:#f8f9fb; padding:10px; text-align:center; font-weight:900; font-size:11px; color:#999;">'.$d.'</div>'; ?>
                    <?php
                    $fw = date('w', strtotime($start)); $fw = ($fw == 0) ? 7 : $fw;
                    $tds = date('t', strtotime($start));
                    for ($i = 1; $i < $fw; $i++) echo '<div style="background:#fafafa; height:100px;"></div>';
                    for ($d = 1; $d <= $tds; $d++) {
                        $cur = sprintf("%04d-%02d-%02d", $y, $m, $d);
                        $cell_idx = ($fw + $d - 2);
                        echo '<div style="background:#fff; height:105px; padding:10px; display:flex; flex-direction:column; gap:4px; border-top:1px solid #f0f0f0; border-left:1px solid #f0f0f0;">';
                        echo '<span style="color:#ddd; font-weight:900; font-size:12px;">'.$d.'</span>';
                        foreach($events as $e) {
                            if($cur >= $e->start_date && $cur <= $e->end_date) {
                                $isS = ($cur == $e->start_date); $isE = ($cur == $e->end_date); $isM = ($cell_idx % 7 == 0);
                                $cls = ['gcs-event-bar'];
                                if (!$isS) $cls[] = 'cont-prev'; if (!$isE) $cls[] = 'cont-next';
                                $color = ($e->contact_email == 'manuale@calendario.local') ? '#e74c3c' : '#3498db';
                                $showTxt = ($isS || $isM || $d == 1);
                                echo '<div onclick="gcsEditEvent('.$e->id.', \''.esc_js($e->group_name).'\', \''.$e->start_date.'\', \''.$e->end_date.'\', \''.esc_js($e->message).'\')" class="'.implode(' ', $cls).'" style="background:'.$color.';">';
                                echo $showTxt ? esc_html($e->group_name) : '&nbsp;';
                                echo '</div>';
                            }
                        }
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>

            <div class="gcs-premium-card" style="padding:30px; height:fit-content;">
                <h4 style="margin-top:0; color:#1a4581; font-family:'Martel',serif; margin-bottom:20px; border-bottom:2px solid #eee; padding-bottom:10px;">Aggiungi Impegno</h4>
                <form method="POST">
                    <?php wp_nonce_field('add_manual_event', 'gcs_nonce'); ?>
                    <input type="hidden" name="gcs_front_add_manual" value="1">
                    <p><label style="font-size:12px; font-weight:800; color:#666;">TITOLO</label><br><input type="text" name="event_title" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;"></p>
                    <p><label style="font-size:12px; font-weight:800; color:#666;">INIZIO</label><br><input type="date" name="event_start" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;"></p>
                    <p><label style="font-size:12px; font-weight:800; color:#666;">FINE</label><br><input type="date" name="event_end" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;"></p>
                    <button type="submit" style="width:100%; background:#27ae60; color:#fff; padding:12px; border:none; border-radius:8px; font-weight:700; cursor:pointer;">+ Inserisci</button>
                </form>
            </div>
        </div>

        <div id="gcsEditModal" style="display:none; position:fixed; z-index:9999; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); backdrop-filter:blur(3px); align-items:center; justify-content:center;">
            <div class="gcs-premium-card" style="padding:40px; width:500px;">
                <h3 style="margin-top:0; color:#1a4581; font-family:'Martel',serif;">Dettagli Impegno</h3>
                <form method="POST" id="gcs-edit-form">
                    <?php wp_nonce_field('edit_event_action', 'gcs_edit_nonce'); ?>
                    <input type="hidden" name="gcs_edit_event_action" value="1">
                    <input type="hidden" name="edit_id" id="edit_id">
                    <input type="hidden" name="gcs_event_op" id="event_op" value="save">
                    <p><label>Titolo</label><br><input type="text" name="edit_title" id="edit_title" required style="width:100%; padding:10px;"></p>
                    <p><label>Dal</label><br><input type="date" name="edit_start" id="edit_start" required style="width:100%; padding:10px;"></p>
                    <p><label>Al</label><br><input type="date" name="edit_end" id="edit_end" required style="width:100%; padding:10px;"></p>
                    <p><label>Note</label><br><textarea name="edit_message" id="edit_message" rows="3" style="width:100%; padding:10px;"></textarea></p>
                    <div style="display:flex; justify-content:space-between; margin-top:30px;">
                        <button type="submit" onclick="document.getElementById('event_op').value='delete'" style="background:#e74c3c; color:#fff; padding:10px 20px; border:none; border-radius:6px; cursor:pointer;">ELIMINA</button>
                        <div>
                            <button type="button" onclick="document.getElementById('gcsEditModal').style.display='none'" style="background:#eee; padding:10px 20px; border:none; border-radius:6px; margin-right:10px;">Chiudi</button>
                            <button type="submit" style="background:#1a4581; color:#fff; padding:10px 20px; border:none; border-radius:6px;">SALVA</button>
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
        <div class="gcs-premium-card" style="padding:40px;">
            <h3 style="font-family:'Martel',serif; color:#1a4581; margin-top:0;">Impostazioni</h3>
            <form method="POST">
                <?php wp_nonce_field('front_settings', 'gcs_front_nonce'); ?>
                <input type="hidden" name="gcs_front_settings_save" value="1">
                <p><label style="font-weight:700;">Email Notifiche</label><br><input type="email" name="gcs_notification_email" value="<?php echo esc_attr(get_option('gcs_notification_email')); ?>" style="width:100%; padding:12px; border-radius:8px; border:1px solid #ddd;"></p>
                <p><label style="font-weight:700;">Utenti (username:password)</label><br><textarea name="gcs_reserved_users" rows="5" style="width:100%; padding:12px; border-radius:8px; border:1px solid #ddd;"><?php echo esc_textarea(get_option('gcs_reserved_users')); ?></textarea></p>
                <div style="margin:20px 0;">
                    <label><input type="checkbox" name="gcs_show_guests_field" value="1" <?php checked(1, get_option('gcs_show_guests_field')); ?>> Mostra campo ospiti</label><br>
                    <label><input type="checkbox" name="gcs_show_message_field" value="1" <?php checked(1, get_option('gcs_show_message_field')); ?>> Mostra campo messaggio</label>
                </div>
                <button type="submit" style="background:#27ae60; color:#fff; padding:15px 40px; border:none; border-radius:8px; font-weight:700; cursor:pointer;">Salva Impostazioni</button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}
