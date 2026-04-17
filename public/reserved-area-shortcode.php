<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Gestione Area Riservata
 * Versione 1.4.5 - Refactoring Premium & Sincronizzazione Totale
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
        // Logout
        if (isset($_GET['gcs_logout'])) {
            setcookie('gcs_reserved_auth', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
            wp_safe_redirect(remove_query_arg('gcs_logout'));
            exit;
        }

        // Login
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

        // Azioni Protette
        if (self::is_authorized()) {
            global $wpdb;
            $table = $wpdb->prefix . 'gcs_requests';

            // Caricamento asincrono per il calendario (Navigazione mesi)
            if (isset($_GET['c_month']) && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                // Se è una richiesta AJAX, lasciamo che il shortcode render_reserved_area gestisca l'output
                return;
            }

            // Aggiornamento Stato
            if (isset($_POST['gcs_front_update_status']) && wp_verify_nonce($_POST['gcs_front_nonce'], 'front_status')) {
                GCS_DB_Manager::update_status(intval($_POST['request_id']), sanitize_text_field($_POST['status']));
                if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                    wp_safe_redirect(add_query_arg('msg', 'updated', remove_query_arg('msg'))); exit;
                }
            }

            // Eliminazione Richiesta
            if (isset($_POST['gcs_front_delete_req']) && wp_verify_nonce($_POST['gcs_front_nonce'], 'front_status')) {
                $wpdb->delete($table, array('id' => intval($_POST['request_id'])));
                if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                    wp_safe_redirect(add_query_arg('msg', 'deleted', remove_query_arg('msg'))); exit;
                }
            }

            // Modifica Impegno Calendario
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
                if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                    wp_safe_redirect(add_query_arg('msg', 'event_updated', remove_query_arg('msg'))); exit;
                }
            }

            // Aggiunta Manuale
            if (isset($_POST['gcs_front_add_manual']) && wp_verify_nonce($_POST['gcs_nonce'], 'add_manual_event')) {
                $wpdb->insert($table, array(
                    'group_name' => sanitize_text_field($_POST['event_title']),
                    'contact_email' => 'manuale@calendario.local',
                    'start_date' => sanitize_text_field($_POST['event_start']),
                    'end_date' => sanitize_text_field($_POST['event_end']),
                    'status' => 'confirmed'
                ));
                if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                    wp_safe_redirect(add_query_arg('msg', 'manual_added', remove_query_arg('msg'))); exit;
                }
            }

            // Salvataggio Impostazioni
            if (isset($_POST['gcs_front_settings_save']) && wp_verify_nonce($_POST['gcs_front_nonce'], 'front_settings')) {
                update_option('gcs_notification_email', sanitize_email($_POST['gcs_notification_email']));
                update_option('gcs_reserved_users', wp_unslash($_POST['gcs_reserved_users']));
                update_option('gcs_show_guests_field', isset($_POST['gcs_show_guests_field']) ? 1 : 0);
                update_option('gcs_show_message_field', isset($_POST['gcs_show_message_field']) ? 1 : 0);
                if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                    wp_safe_redirect(add_query_arg('msg', 'settings_saved', remove_query_arg('msg'))); exit;
                }
            }
        }
    }

    public static function render_reserved_area() {
        if (!self::is_authorized()) {
            return self::render_login_form();
        }

        ob_start();
        $msg_html = '';
        if (isset($_GET['msg'])) {
            $m = sanitize_text_field($_GET['msg']);
            $labels = array('updated'=>'Stato aggiornato', 'deleted'=>'Richiesta eliminata', 'event_updated'=>'Evento modificato', 'manual_added'=>'Impegno aggiunto', 'settings_saved'=>'Impostazioni salvate');
            if (isset($labels[$m])) $msg_html = '<div style="background:#d4edda; color:#155724; padding:15px; border-radius:8px; margin-bottom:20px; text-align:center; font-weight:bold; box-shadow:0 2px 5px rgba(0,0,0,0.05);">'.esc_html($labels[$m]).' con successo!</div>';
        }
        ?>
        <div class="gcs-reserved-wrapper" style="font-family: inherit; margin: 20px 0; color: #333;">
            <?php echo $msg_html; ?>
            
            <header style="display: flex; justify-content: space-between; align-items: center; background: linear-gradient(135deg, #1a4581 0%, #2980b9 100%); padding: 25px; border-radius: 12px; color: #fff; box-shadow: 0 4px 20px rgba(0,0,0,0.1); margin-bottom: 30px;">
                <h2 style="margin: 0; font-size: 26px; font-weight: 700; letter-spacing: -0.5px;">Area Gestione Casa Scout</h2>
                <a href="<?php echo esc_url(add_query_arg('gcs_logout', '1')); ?>" style="background: rgba(255,255,255,0.2); color: #fff; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 700; font-size: 14px; transition: 0.3s; border: 1px solid rgba(255,255,255,0.3);" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">Esci</a>
            </header>

            <nav style="display: flex; gap: 10px; margin-bottom: 30px; border-bottom: 2px solid #eee; padding-bottom: 0;">
                <button class="gcs-tab-btn" id="btn_requests" onclick="window.gcsShowTab('requests')" style="padding:12px 25px; border:none; background:none; cursor:pointer; font-weight:700; font-size:15px; color:#999; border-bottom:3px solid transparent; transition:0.3s;">Bacheca Richieste</button>
                <button class="gcs-tab-btn" id="btn_calendar" onclick="window.gcsShowTab('calendar')" style="padding:12px 25px; border:none; background:none; cursor:pointer; font-weight:700; font-size:15px; color:#999; border-bottom:3px solid transparent; transition:0.3s;">Calendario</button>
                <button class="gcs-tab-btn" id="btn_settings" onclick="window.gcsShowTab('settings')" style="padding:12px 25px; border:none; background:none; cursor:pointer; font-weight:700; font-size:15px; color:#999; border-bottom:3px solid transparent; transition:0.3s;">Mappa & Colori</button>
            </nav>

            <div id="tab_requests" class="gcs-tab-content" style="display:none;"><?php echo self::render_requests_management(); ?></div>
            <div id="tab_calendar" class="gcs-tab-content" style="display:none;"><?php echo self::render_calendar_management(); ?></div>
            <div id="tab_settings" class="gcs-tab-content" style="display:none;"><?php echo self::render_settings_management(); ?></div>

            <script>
                (function() {
                    window.gcsShowTab = function(id) {
                        document.querySelectorAll('.gcs-tab-content').forEach(el => el.style.display = 'none');
                        document.querySelectorAll('.gcs-tab-btn').forEach(btn => {
                            btn.style.color = '#999';
                            btn.style.borderBottomColor = 'transparent';
                        });
                        document.getElementById('tab_' + id).style.display = 'block';
                        var activeBtn = document.getElementById('btn_' + id);
                        activeBtn.style.color = '#1a4581';
                        activeBtn.style.borderBottomColor = '#1a4581';
                    };
                    
                    window.gcsShowTab('requests');

                    // Gestione AJAX per caricamento tab e navigazione calendario
                    document.addEventListener('click', function(e) {
                        var nav = e.target.closest('.gcs-cal-nav');
                        if (nav) {
                            e.preventDefault();
                            var wrapper = document.querySelector('.gcs-reserved-wrapper');
                            wrapper.style.opacity = '0.5';
                            fetch(nav.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                                .then(r => r.text())
                                .then(html => {
                                    var parser = new DOMParser();
                                    var doc = parser.parseFromString(html, 'text/html');
                                    var newContent = doc.querySelector('#tab_calendar').innerHTML;
                                    document.getElementById('tab_calendar').innerHTML = newContent;
                                    wrapper.style.opacity = '1';
                                });
                        }
                    });

                    // Gestione AJAX per i form
                    document.addEventListener('submit', function(e) {
                        var form = e.target;
                        if (form.closest('.gcs-tab-content')) {
                            e.preventDefault();
                            var formData = new FormData(form);
                            // Aggiungiamo il valore del pulsante cliccato se presente
                            if (e.submitter && e.submitter.name) formData.append(e.submitter.name, e.submitter.value);
                            
                            var wrapper = document.querySelector('.gcs-reserved-wrapper');
                            wrapper.style.opacity = '0.5';
                            
                            fetch(window.location.href, {
                                method: 'POST',
                                body: formData,
                                headers: { 'X-Requested-With': 'XMLHttpRequest' }
                            }).then(r => r.text()).then(html => {
                                var parser = new DOMParser();
                                var doc = parser.parseFromString(html, 'text/html');
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
        $err = isset($_GET['gcs_login_error']) ? 'Accesso negato. Riprova.' : '';
        ?>
        <div style="max-width: 400px; margin: 80px auto; padding: 40px; background: #fff; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); border: 1px solid #eee; text-align: center;">
            <div style="background: #1a4581; color: #fff; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 24px;">🔒</div>
            <h2 style="margin-bottom: 30px; color: #1a4581; font-family: 'Martel', serif;">Area Protetta</h2>
            <?php if ($err): ?><div style="background:#fdeaea; color:#c0392b; padding:12px; border-radius:8px; margin-bottom:20px; font-weight:bold; font-size:14px;"><?php echo $err; ?></div><?php endif; ?>
            <form method="POST">
                <input type="text" name="gcs_username" placeholder="Nome Utente" required style="width:100%; padding:14px; margin-bottom:15px; border:1px solid #ddd; border-radius:8px; font-size:16px;">
                <input type="password" name="gcs_password" placeholder="Password" required style="width:100%; padding:14px; margin-bottom:25px; border:1px solid #ddd; border-radius:8px; font-size:16px;">
                <button type="submit" name="gcs_reserved_login_submit" style="width:100%; background:#1a4581; color:#fff; padding:15px; border:none; border-radius:8px; font-weight:700; font-size:16px; cursor:pointer; transition: 0.3s; box-shadow: 0 4px 10px rgba(26,69,129,0.3);" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">Accedi Ora</button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function render_requests_management() {
        $requests = GCS_DB_Manager::get_requests();
        ob_start();
        ?>
        <div style="background:#fff; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.05); overflow:hidden; border:1px solid #eee;">
            <table style="width:100%; border-collapse:collapse;">
                <thead style="background:#f8f9fa;">
                    <tr>
                        <th style="padding:20px; text-align:left; color:#1a4581; text-transform:uppercase; font-size:12px; border-bottom:2px solid #eee;">Dettagli Richiesta</th>
                        <th style="padding:20px; text-align:left; color:#1a4581; text-transform:uppercase; font-size:12px; border-bottom:2px solid #eee;">Periodo</th>
                        <th style="padding:20px; text-align:left; color:#1a4581; text-transform:uppercase; font-size:12px; border-bottom:2px solid #eee;">Stato</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)) : ?>
                        <tr><td colspan="3" style="padding:40px; text-align:center; color:#999;">Nessuna richiesta in attesa.</td></tr>
                    <?php else: foreach ($requests as $r): ?>
                        <tr style="border-bottom:1px solid #f0f0f0;">
                            <td style="padding:20px;">
                                <strong style="font-size:17px; color:#333; display:block; margin-bottom:5px;"><?php echo esc_html($r->group_name); ?></strong>
                                <span style="font-size:13px; color:#777;"><?php echo esc_html($r->contact_email); ?></span>
                            </td>
                            <td style="padding:20px; font-size:14px; color:#555;">
                                <div>Dal: <strong><?php echo date('d/m/Y', strtotime($r->start_date)); ?></strong></div>
                                <div>Al: <strong><?php echo date('d/m/Y', strtotime($r->end_date)); ?></strong></div>
                            </td>
                            <td style="padding:20px;">
                                <form method="POST" style="display:flex; align-items:center; gap:10px;">
                                    <?php wp_nonce_field('front_status', 'gcs_front_nonce'); ?>
                                    <input type="hidden" name="request_id" value="<?php echo $r->id; ?>">
                                    <input type="hidden" name="gcs_front_update_status" value="1">
                                    <select name="status" onchange="this.form.submit()" style="padding:8px; border-radius:6px; background:#f9f9f9; border:1px solid #ccc; font-weight:700;">
                                        <option value="pending" <?php selected($r->status, 'pending'); ?>>In attesa</option>
                                        <option value="confirmed" <?php selected($r->status, 'confirmed'); ?>>Confermata</option>
                                        <option value="rejected" <?php selected($r->status, 'rejected'); ?>>Rifiutata</option>
                                    </select>
                                    <button type="submit" name="gcs_front_delete_req" value="1" style="background:none; border:none; color:#e74c3c; cursor:pointer; font-size:18px;" onclick="return confirm('Eliminare definitivamente?')">🗑️</button>
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
        
        $months_names = array('', 'Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno','Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre');

        ob_start();
        ?>
        <div style="display:grid; grid-template-columns: 2fr 1fr; gap:30px;">
            <div style="background:#fff; border-radius:12px; padding:30px; box-shadow:0 10px 30px rgba(0,0,0,0.05); border:1px solid #eee;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px;">
                    <a href="<?php echo esc_url(add_query_arg(array('c_month'=>($m==1?12:$m-1), 'c_year'=>($m==1?$y-1:$y)))); ?>" class="gcs-cal-nav" style="text-decoration:none; font-weight:700; color:#1a4581;">&laquo; <?php echo $months_names[$m==1?12:$m-1]; ?></a>
                    <h3 style="margin:0; font-size:22px; text-transform:uppercase; color:#1a4581;"><?php echo $months_names[$m].' '.$y; ?></h3>
                    <a href="<?php echo esc_url(add_query_arg(array('c_month'=>($m==12?1:$m+1), 'c_year'=>($m==12?$y+1:$y)))); ?>" class="gcs-cal-nav" style="text-decoration:none; font-weight:700; color:#1a4581;"><?php echo $months_names[$m==12?1:$m+1]; ?> &raquo;</a>
                </div>
                
                <div style="display:grid; grid-template-columns: repeat(7, 1fr); gap:1px; background:#eee; border:1px solid #eee; border-radius:8px; overflow:hidden;">
                    <?php foreach(array('Lun','Mar','Mer','Gio','Ven','Sab','Dom') as $d) echo '<div style="background:#f8f9fa; padding:10px; text-align:center; font-weight:700; font-size:12px; color:#777;">'.$d.'</div>'; ?>
                    <?php
                    $first = date('w', strtotime($start)); 
                    $first = ($first == 0) ? 7 : $first;
                    $days = date('t', strtotime($start));
                    for ($i = 1; $i < $first; $i++) echo '<div style="background:#fafafa; height:90px;"></div>';
                    for ($d = 1; $d <= $days; $d++) {
                        $cur = sprintf("%04d-%02d-%02d", $y, $m, $d);
                        echo '<div style="background:#fff; height:90px; padding:8px; display:flex; flex-direction:column; gap:5px; font-size:13px; border-top:1px solid #eee; border-left:1px solid #eee;">';
                        echo '<span style="color:#aaa; font-weight:700;">'.$d.'</span>';
                        foreach($events as $e) {
                            if($cur >= $e->start_date && $cur <= $e->end_date) {
                                $c = ($e->contact_email == 'manuale@calendario.local') ? '#e74c3c' : '#3498db';
                                echo '<div onclick="gcsEditEvent('.$e->id.', \''.esc_js($e->group_name).'\', \''.$e->start_date.'\', \''.$e->end_date.'\', \''.esc_js($e->message).'\')" style="background:'.$c.'; color:#fff; padding:4px 8px; border-radius:4px; font-size:10px; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; cursor:pointer;" title="'.esc_attr($e->group_name).'">'.esc_html($e->group_name).'</div>';
                            }
                        }
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>

            <div style="background:#fff; border-radius:12px; padding:30px; box-shadow:0 10px 30px rgba(0,0,0,0.05); border:1px solid #eee; height: fit-content;">
                <h3 style="margin-top:0; color:#1a4581; border-bottom:2px solid #eee; padding-bottom:15px; margin-bottom:20px;">Aggiungi Impegno</h3>
                <form method="POST">
                    <?php wp_nonce_field('add_manual_event', 'gcs_nonce'); ?>
                    <input type="hidden" name="gcs_front_add_manual" value="1">
                    <p style="margin-bottom:15px;"><label style="display:block; font-weight:700; font-size:13px; margin-bottom:8px;">Titolo</label><input type="text" name="event_title" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px;"></p>
                    <p style="margin-bottom:15px;"><label style="display:block; font-weight:700; font-size:13px; margin-bottom:8px;">Inizio</label><input type="date" name="event_start" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px;"></p>
                    <p style="margin-bottom:20px;"><label style="display:block; font-weight:700; font-size:13px; margin-bottom:8px;">Fine</label><input type="date" name="event_end" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px;"></p>
                    <button type="submit" style="width:100%; background:#27ae60; color:#fff; padding:12px; border:none; border-radius:6px; font-weight:700; cursor:pointer;">+ Aggiungi</button>
                </form>
            </div>
        </div>

        <!-- Popup Modifica -->
        <div id="gcsEditModal" style="display:none; position:fixed; z-index:9999; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); backdrop-filter:blur(3px); align-items:center; justify-content:center;">
            <div style="background:#fff; padding:40px; border-radius:16px; width:500px; max-width:90%;">
                <h3 style="margin-top:0; color:#1a4581;">Modifica Impegno</h3>
                <form method="POST">
                    <?php wp_nonce_field('edit_event_action', 'gcs_edit_nonce'); ?>
                    <input type="hidden" name="gcs_edit_event_action" value="1">
                    <input type="hidden" name="edit_id" id="edit_id">
                    <input type="hidden" name="gcs_event_op" id="event_op" value="save">
                    <p><label>Gruppo</label><input type="text" name="edit_title" id="edit_title" style="width:100%; padding:10px; border:1px solid #ddd;"></p>
                    <div style="display:flex; gap:15px; margin:15px 0;">
                        <p style="flex:1;"><label>Dal</label><input type="date" name="edit_start" id="edit_start" style="width:100%; padding:10px;"></p>
                        <p style="flex:1;"><label>Al</label><input type="date" name="edit_end" id="edit_end" style="width:100%; padding:10px;"></p>
                    </div>
                    <p><label>Note</label><textarea name="edit_message" id="edit_message" rows="3" style="width:100%; padding:10px;"></textarea></p>
                    <div style="display:flex; justify-content:space-between; margin-top:30px;">
                        <button type="submit" onclick="document.getElementById('event_op').value='delete'" style="background:#e74c3c; color:#fff; border:none; padding:10px 20px; border-radius:6px; cursor:pointer;">Elimina</button>
                        <div>
                            <button type="button" onclick="document.getElementById('gcsEditModal').style.display='none'" style="background:#eee; padding:10px 20px; border-radius:6px; border:none; margin-right:10px;">Annulla</button>
                            <button type="submit" style="background:#1a4581; color:#fff; padding:10px 25px; border-radius:6px; border:none;">Salva</button>
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
                document.getElementById('gcsEditModal').style.display = 'flex';
            };
        </script>
        <?php
        return ob_get_clean();
    }

    private static function render_settings_management() {
        ob_start();
        ?>
        <div style="background:#fff; border-radius:12px; padding:40px; box-shadow:0 10px 30px rgba(0,0,0,0.05); border:1px solid #eee;">
            <form method="POST">
                <?php wp_nonce_field('front_settings', 'gcs_front_nonce'); ?>
                <input type="hidden" name="gcs_front_settings_save" value="1">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:30px; margin-bottom:30px;">
                    <div><label style="display:block; font-weight:700; margin-bottom:10px;">Email Notifiche</label><input type="email" name="gcs_notification_email" value="<?php echo esc_attr(get_option('gcs_notification_email')); ?>" style="width:100%; padding:12px; border-radius:8px; border:1px solid #ddd;"></div>
                    <div>
                        <label style="display:block; font-weight:700; margin-bottom:10px;">Campi Form Visibili</label>
                        <label style="display:block; margin-bottom:10px;"><input type="checkbox" name="gcs_show_guests_field" value="1" <?php checked(1, get_option('gcs_show_guests_field')); ?>> Mostra Numero Ospiti</label>
                        <label style="display:block;"><input type="checkbox" name="gcs_show_message_field" value="1" <?php checked(1, get_option('gcs_show_message_field')); ?>> Mostra Messaggio Addizionale</label>
                    </div>
                </div>
                <div style="margin-bottom:30px;">
                    <label style="display:block; font-weight:700; margin-bottom:10px;">Accessi Area Riservata (username:password - uno per riga)</label>
                    <textarea name="gcs_reserved_users" rows="5" style="width:100%; padding:15px; border-radius:8px; border:1px solid #ddd; font-family:monospace;"><?php echo esc_textarea(get_option('gcs_reserved_users')); ?></textarea>
                </div>
                <button type="submit" style="background:#27ae60; color:#fff; padding:15px 40px; border:none; border-radius:8px; font-weight:700; font-size:16px; cursor:pointer;">Salva Impostazioni</button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}
