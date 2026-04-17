<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GCS_Reserved_Area_Shortcode {
    public static function init() {
        add_shortcode( 'gcs_reserved_area', array( __CLASS__, 'render_reserved_area' ) );
        add_action( 'template_redirect', array( __CLASS__, 'handle_actions' ) );
    }

    public static function get_authorized_users() {
        $users_opt = get_option('gcs_reserved_users', '');
        $users = [];
        if (!empty($users_opt)) {
            $lines = explode("\n", str_replace("\r", "", $users_opt));
            foreach ($lines as $line) {
                $line = trim($line);
                if (strpos($line, ':') !== false) {
                    list($u, $p) = explode(':', $line, 2);
                    $users[trim($u)] = trim($p);
                }
            }
        }
        return $users;
    }

    public static function is_authenticated() {
        $users = self::get_authorized_users();
        if (empty($users)) return false;

        if (isset($_COOKIE['gcs_reserved_auth'])) {
            $cookie_vals = explode('|', $_COOKIE['gcs_reserved_auth'], 2);
            if (count($cookie_vals) == 2) {
                $username = $cookie_vals[0];
                $hash = $cookie_vals[1];
                if (isset($users[$username]) && md5($username . $users[$username]) === $hash) {
                    return true;
                }
            }
        }
        return false;
    }

    public static function handle_actions() {
        // Handle Logout
        if (isset($_GET['gcs_logout'])) {
            setcookie('gcs_reserved_auth', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
            wp_safe_redirect(remove_query_arg('gcs_logout'));
            exit;
        }

        // Handle Login
        if (isset($_POST['gcs_reserved_login_submit'])) {
            $username = sanitize_text_field($_POST['gcs_username']);
            $pass = sanitize_text_field($_POST['gcs_password']);
            $users = self::get_authorized_users();

            if (isset($users[$username]) && $users[$username] === $pass) {
                setcookie('gcs_reserved_auth', $username . '|' . md5($username . $pass), time() + 86400, COOKIEPATH, COOKIE_DOMAIN);
                wp_safe_redirect(remove_query_arg('gcs_login_error'));
                exit;
            } else {
                wp_safe_redirect(add_query_arg('gcs_login_error', '1'));
                exit;
            }
        }

        if (self::is_authenticated()) {
            // Frontend Actions
            if (isset($_POST['gcs_front_update_status']) && wp_verify_nonce($_POST['gcs_front_nonce'], 'front_status')) {
                $req_id = intval($_POST['request_id']);
                $new_status = sanitize_text_field($_POST['new_status']);
                if (in_array($new_status, ['pending','confirmed','rejected'])) {
                    GCS_DB_Manager::update_status($req_id, $new_status);
                }
                wp_safe_redirect(add_query_arg(array('gcs_tab'=>'requests', 'msg'=>'status_updated'), remove_query_arg(array('gcs_tab','msg')))); 
                exit;
            }

            if (isset($_POST['gcs_front_delete_req']) && wp_verify_nonce($_POST['gcs_front_nonce'], 'front_status')) {
                global $wpdb;
                $wpdb->delete($wpdb->prefix . 'gcs_requests', ['id' => intval($_POST['request_id'])], ['%d']);
                wp_safe_redirect(add_query_arg(array('gcs_tab'=>'requests', 'msg'=>'deleted'), remove_query_arg(array('gcs_tab','msg')))); 
                exit;
            }
            
            if (isset($_POST['gcs_front_settings_save']) && wp_verify_nonce($_POST['gcs_front_nonce'], 'front_settings')) {
                $opts = ['gcs_notification_email', 'gcs_webmail_url', 'gcs_form_title', 'gcs_show_guests_field', 'gcs_show_message_field', 'gcs_style_title_color', 'gcs_style_title_size', 'gcs_style_label_color', 'gcs_style_input_bg', 'gcs_style_input_border', 'gcs_style_input_radius', 'gcs_style_btn_bg', 'gcs_style_btn_color', 'gcs_style_btn_radius', 'gcs_style_btn_bg_hover', 'gcs_layout_title_align', 'gcs_layout_row_gap', 'gcs_layout_btn_align', 'gcs_custom_css', 'gcs_reserved_users'];
                
                update_option('gcs_show_guests_field', isset($_POST['gcs_show_guests_field']) ? 1 : 0);
                update_option('gcs_show_message_field', isset($_POST['gcs_show_message_field']) ? 1 : 0);

                foreach($opts as $o) {
                    if ($o == 'gcs_show_guests_field' || $o == 'gcs_show_message_field') continue;
                    if (isset($_POST[$o])) {
                        if ($o == 'gcs_custom_css' || $o == 'gcs_reserved_users') {
                            update_option($o, wp_unslash($_POST[$o])); 
                        } else {
                            update_option($o, sanitize_text_field($_POST[$o]));
                        }
                    }
                }
                wp_safe_redirect(add_query_arg(array('gcs_tab'=>'settings', 'msg'=>'settings_saved'), remove_query_arg(array('gcs_tab','msg')))); 
                exit;
            }
        }
    }

    public static function render_reserved_area() {
        if (!self::is_authenticated()) {
            ob_start();
            $error_msg = isset($_GET['gcs_login_error']) ? 'Credenziali non valide. Riprova.' : '';
            ?>
            <div class="gcs-reserved-login" style="max-width: 400px; margin: 50px auto; padding: 30px; background: #fff; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: 1px solid #eaeaea; font-family: inherit;">
                <h3 style="text-align: center; margin-top: 0; margin-bottom: 25px; color: #1a4581; font-size: 22px;">Area Riservata</h3>
                <?php if (!empty($error_msg)): ?>
                    <div style="background: #f8dbdb; color: #a33a3a; padding: 10px; border-radius: 4px; margin-bottom: 20px; font-size: 14px; text-align: center;">
                        <?php echo esc_html($error_msg); ?>
                    </div>
                <?php endif; ?>
                <form method="POST" action="">
                    <p style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: bold; color: #444;">Utente / Email</label>
                        <input type="text" name="gcs_username" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 14px;">
                    </p>
                    <p style="margin-bottom: 25px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: bold; color: #444;">Password</label>
                        <input type="password" name="gcs_password" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 14px;">
                    </p>
                    <button type="submit" name="gcs_reserved_login_submit" style="width: 100%; background: #1a4581; color: #fff; border: none; padding: 12px; border-radius: 5px; font-weight: bold; cursor: pointer; transition: background 0.3s; font-size: 16px;">Accedi</button>
                </form>
            </div>
            <?php
            return ob_get_clean();
        }

        global $wpdb;
        $tabs_style = 'padding: 10px 20px; text-decoration: none; font-weight: bold; margin-bottom: -2px; transition: color 0.3s; cursor: pointer;';
        
        $message_html = '';

        if (isset($_POST['gcs_edit_event_action']) && wp_verify_nonce($_POST['gcs_edit_nonce'], 'edit_event_action')) {
            $table_name = $wpdb->prefix . 'gcs_requests';
            $edit_id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;
            $op = isset($_POST['gcs_event_op']) ? sanitize_text_field($_POST['gcs_event_op']) : 'save';

            if ($edit_id > 0) {
                if ($op === 'delete') {
                    $wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE id = %d", $edit_id));
                    $message_html = '<div style="background:#d4edda; color:#155724; padding:15px; border-radius:4px; margin-bottom:20px; text-align:center; font-weight:bold;">Evento rimosso con successo.</div>';
                } else {
                    $wpdb->update($table_name, array(
                        'group_name' => sanitize_text_field($_POST['edit_title']),
                        'start_date' => sanitize_text_field($_POST['edit_start']),
                        'end_date' => sanitize_text_field($_POST['edit_end']),
                        'message' => sanitize_textarea_field($_POST['edit_message'])
                    ), array('id' => $edit_id));
                    $message_html = '<div style="background:#d4edda; color:#155724; padding:15px; border-radius:4px; margin-bottom:20px; text-align:center; font-weight:bold;">Modifiche salvate correttamente.</div>';
                }
            }
        }

        if (isset($_POST['gcs_front_update_status']) && wp_verify_nonce($_POST['gcs_front_nonce'], 'front_status')) {
            $request_id = intval($_POST['request_id']);
            $new_status = sanitize_text_field($_POST['status']);
            GCS_DB_Manager::update_status($request_id, $new_status);
            $message_html = '<div style="background:#d4edda; color:#155724; padding:15px; border-radius:4px; margin-bottom:20px; text-align:center; font-weight:bold;">Stato aggiornato con successo.</div>';
        }

        if (isset($_POST['gcs_front_delete_req']) && wp_verify_nonce($_POST['gcs_front_nonce'], 'front_status')) {
            $request_id = intval($_POST['request_id']);
            global $wpdb;
            $wpdb->delete($wpdb->prefix . 'gcs_requests', array('id' => $request_id));
            $message_html = '<div style="background:#d4edda; color:#155724; padding:15px; border-radius:4px; margin-bottom:20px; text-align:center; font-weight:bold;">Richiesta eliminata.</div>';
        }

        if (isset($_POST['gcs_front_add_manual']) && wp_verify_nonce($_POST['gcs_nonce'], 'add_manual_event')) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'gcs_requests';
            $wpdb->insert($table_name, array(
                'group_name' => sanitize_text_field($_POST['event_title']),
                'contact_email' => 'manuale@calendario.local',
                'start_date' => sanitize_text_field($_POST['event_start']),
                'end_date' => sanitize_text_field($_POST['event_end']),
                'guests_count' => 0,
                'message' => 'Inserimento manuale.',
                'status' => 'confirmed'
            ));
            $message_html = '<div style="background:#d4edda; color:#155724; padding:15px; border-radius:4px; margin-bottom:20px; text-align:center; font-weight:bold;">Nuovo impegno aggiunto.</div>';
        }

        ob_start();
        ?>
        <div class="gcs-reserved-wrapper" style="font-family: inherit; margin: 30px 0;">
            <?php echo $message_html; ?>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #eaeaea;">
                <h2 style="margin: 0; color: #1a4581; font-size: 24px; font-family: 'Martel', serif;">Area Riservata</h2>
                <a href="<?php echo esc_url(add_query_arg('gcs_logout', '1')); ?>" style="background: #e74c3c; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 14px; transition: background 0.3s;" onmouseover="this.style.background='#c0392b'" onmouseout="this.style.background='#e74c3c'">Esci Sessione</a>
            </div>

            <!-- TABS -->
            <div style="display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #ccc; padding-bottom: 0px; flex-wrap: wrap;">
                <a id="gcs_btn_requests" class="gcs-tab-btn" style="<?php echo $tabs_style; ?> color: #888; border-bottom: 3px solid transparent;" onclick="window.gcsShowTab('requests')">Bacheca Richieste</a>
                <a id="gcs_btn_calendar" class="gcs-tab-btn" style="<?php echo $tabs_style; ?> color: #888; border-bottom: 3px solid transparent;" onclick="window.gcsShowTab('calendar')">Calendario</a>
                <a id="gcs_btn_settings" class="gcs-tab-btn" style="<?php echo $tabs_style; ?> color: #888; border-bottom: 3px solid transparent;" onclick="window.gcsShowTab('settings')">Impostazioni</a>
            </div>

            <?php
            if (isset($_GET['msg'])) {
                $msg = sanitize_text_field($_GET['msg']);
                if ($msg == 'status_updated') echo '<div style="background:#d4edda; color:#155724; padding:15px; border-radius:4px; margin-bottom:20px; font-weight:bold;">Stato aggiornato con successo.</div>';
                if ($msg == 'deleted') echo '<div style="background:#fff3cd; color:#856404; padding:15px; border-radius:4px; margin-bottom:20px; font-weight:bold;">Richiesta eliminata.</div>';
                if ($msg == 'settings_saved') echo '<div style="background:#d4edda; color:#155724; padding:15px; border-radius:4px; margin-bottom:20px; font-weight:bold;">Impostazioni salvate con successo.</div>';
            }
            ?>

            <div id="gcs_tab_requests" class="gcs-tab-content" style="display:none;">
                <?php echo self::render_requests_management(); ?>
            </div>
            <div id="gcs_tab_calendar" class="gcs-tab-content" style="display:none;">
                <?php echo self::render_calendar_management(); ?>
            </div>
            <div id="gcs_tab_settings" class="gcs-tab-content" style="display:none;">
                <?php echo self::render_settings_management(); ?>
            </div>

            <script>
                if (typeof window.gcsShowTab === 'undefined') {
                    window.gcsShowTab = function(tabId) {
                        document.querySelectorAll('.gcs-tab-content').forEach(function(el) { el.style.display = 'none'; });
                        document.querySelectorAll('.gcs-tab-btn').forEach(function(btn) {
                            btn.style.color = '#888';
                            btn.style.borderBottomColor = 'transparent';
                        });
                        
                        var tgtContent = document.getElementById('gcs_tab_' + tabId);
                        if(tgtContent) tgtContent.style.display = 'block';
                        
                        var activeBtn = document.getElementById('gcs_btn_' + tabId);
                        if(activeBtn) {
                            activeBtn.style.color = '#1a4581';
                            activeBtn.style.borderBottomColor = '#1a4581';
                        }
                    };

                    document.addEventListener('DOMContentLoaded', function() {
                        window.gcsShowTab('requests');
                    });
                    
                    if (document.readyState === 'complete' || document.readyState === 'interactive') {
                        setTimeout(function() { window.gcsShowTab('requests'); }, 50);
                    }

                    document.addEventListener('click', function(e) {
                        var calNav = e.target.closest('.gcs-cal-nav');
                        if (calNav) {
                            e.preventDefault();
                            var href = calNav.href;
                            var container = document.getElementById('gcs_reserved_calendar_col');
                            if(container) container.style.opacity = '0.5';
                            
                            var activeTabBtn = document.querySelector('.gcs-tab-btn[style*="border-bottom-color: rgb(26, 69, 129)"]') 
                                            || document.querySelector('.gcs-tab-btn[style*="border-bottom-color: #1a4581"]');
                            var activeTab = activeTabBtn ? activeTabBtn.id.replace('gcs_btn_', '') : 'calendar';

                            fetch(href)
                                .then(function(res) { return res.text(); })
                                .then(function(html) {
                                    var parser = new DOMParser();
                                    var doc = parser.parseFromString(html, 'text/html');
                                    var newWrapper = doc.querySelector('.gcs-reserved-wrapper');
                                    var currentWrapper = document.querySelector('.gcs-reserved-wrapper');
                                    if (newWrapper && currentWrapper) {
                                        currentWrapper.innerHTML = newWrapper.innerHTML;
                                        window.gcsShowTab(activeTab);
                                    }
                                });
                        }
                    });

                    document.addEventListener('submit', function(e) {
                        var form = e.target;
                        if (form.closest('.gcs-reserved-wrapper') && !form.closest('.gcs-reserved-login')) {
                            e.preventDefault();
                            
                            var submitBtn = e.submitter || form.querySelector('button[type="submit"]');
                            var originalBtnText = submitBtn ? submitBtn.innerHTML : '';
                            if (submitBtn) {
                                submitBtn.innerHTML = 'Attendere...';
                                submitBtn.style.opacity = '0.7';
                            }

                            var formData = new FormData(form);
                            if (e.submitter && e.submitter.name) {
                                formData.append(e.submitter.name, e.submitter.value || '1');
                            } else if (submitBtn && submitBtn.name) {
                                // Fallback for some browsers if submitter is missing but we found the button via querySelector (usually only if 1 button)
                                formData.append(submitBtn.name, submitBtn.value || '1');
                            }

                            var activeTabBtn = document.querySelector('.gcs-tab-btn[style*="border-bottom-color: rgb(26, 69, 129)"]') 
                                            || document.querySelector('.gcs-tab-btn[style*="border-bottom-color: #1a4581"]');
                            var activeTab = activeTabBtn ? activeTabBtn.id.replace('gcs_btn_', '') : 'requests';

                            var fetchUrl = window.location.href + (window.location.href.indexOf('?') > -1 ? '&' : '?') + 'gcs_t=' + Date.now();
                            console.log('PJAX: Submitting form to ' + fetchUrl);
                            fetch(fetchUrl, {
                                method: 'POST',
                                body: formData
                            })
                            .then(function(res) { 
                                console.log('PJAX: Response received, status: ' + res.status);
                                return res.text(); 
                            })
                            .then(function(html) {
                                var parser = new DOMParser();
                                var doc = parser.parseFromString(html, 'text/html');
                                var newWrapper = doc.querySelector('.gcs-reserved-wrapper');
                                var currentWrapper = document.querySelector('.gcs-reserved-wrapper');
                                if (newWrapper && currentWrapper) {
                                    currentWrapper.innerHTML = newWrapper.innerHTML;
                                    console.log('PJAX: Content updated, showing tab: ' + activeTab);
                                    window.gcsShowTab(activeTab);
                                } else {
                                    console.error('PJAX: Error - New wrapper not found in response');
                                }
                            })
                            .catch(function(err) {
                                console.error(err);
                                if (submitBtn) {
                                    submitBtn.innerHTML = originalBtnText;
                                    submitBtn.style.opacity = '1';
                                }
                            });
                        }
                    });
                }
            </script>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function render_requests_management() {
        $requests = GCS_DB_Manager::get_requests();
        ob_start();
        ?>
        <div style="background:#fff; border:1px solid #eaeaea; border-radius:8px; box-shadow:0 4px 15px rgba(0,0,0,.05); padding:20px; overflow-x:auto;">
            <?php if ( empty( $requests ) ) : ?>
                <p>Nessuna richiesta di prenotazione trovata al momento.</p>
            <?php else : ?>
                <table style="width:100%; border-collapse:collapse; min-width:600px;">
                    <thead style="background:#f9f9f9;">
                        <tr>
                            <th style="padding:15px; text-align:left; border-bottom:2px solid #eee; text-transform:uppercase; font-size:12px; color:#666;">Dettagli Gruppo</th>
                            <th style="padding:15px; text-align:left; border-bottom:2px solid #eee; text-transform:uppercase; font-size:12px; color:#666;">Periodo & Ospiti</th>
                            <th style="padding:15px; text-align:left; border-bottom:2px solid #eee; text-transform:uppercase; font-size:12px; color:#666;">Stato</th>
                            <th style="padding:15px; text-align:left; border-bottom:2px solid #eee; text-transform:uppercase; font-size:12px; color:#666; width: 150px;">Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $requests as $req ) : ?>
                            <tr style="border-bottom:1px solid #f0f0f0;">
                                <td style="padding:15px; vertical-align:top;">
                                    <strong style="display:block; font-size:16px; color:#1a4581; margin-bottom:5px;"><?php echo esc_html( wp_unslash( $req->group_name ) ); ?></strong>
                                    <div style="color:#777; font-size:13px; margin-bottom:8px;">Ricevuta: <?php echo esc_html( date( 'd/m/Y H:i', strtotime( $req->created_at ) ) ); ?></div>
                                    <a href="mailto:<?php echo esc_attr( wp_unslash( $req->contact_email ) ); ?>" style="color:#d35400; text-decoration:none; display:inline-block; margin-bottom:5px;">✉️ <?php echo esc_html( wp_unslash( $req->contact_email ) ); ?></a>
                                    <?php if (!empty($req->message)): ?>
                                        <div style="margin-top:10px; font-size:13px; background:#fefefe; padding:10px; border-left:3px solid #ddd; font-style:italic; border-radius:0 4px 4px 0;">
                                            <?php echo nl2br( esc_html( wp_unslash( $req->message ) ) ); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                
                                <td style="padding:15px; vertical-align:top; font-size:14px;">
                                    <div><strong>Dal:</strong> <?php echo esc_html( date( 'd/m/Y', strtotime( $req->start_date ) ) ); ?></div>
                                    <div style="margin-bottom:8px;"><strong>Al:</strong> <?php echo esc_html( date( 'd/m/Y', strtotime( $req->end_date ) ) ); ?></div>
                                    <div style="display:inline-block; background:#eee; padding:3px 8px; border-radius:4px; font-size:12px;"><strong><?php echo esc_html( $req->guests_count ); ?></strong> ospiti</div>
                                </td>
                                
                                <td style="padding:15px; vertical-align:top;">
                                    <?php 
                                        $bg_color = '#f0f0f1';
                                        $text_color = '#3c434a';
                                        $label = ucfirst(esc_html($req->status));
                                        
                                        if ( $req->status === 'confirmed' ) {
                                            $bg_color = '#edfaeb'; $text_color = '#007017'; $label = 'Confermata';
                                        } elseif ( $req->status === 'rejected' ) {
                                            $bg_color = '#fcf0f1'; $text_color = '#d63638'; $label = 'Rifiutata';
                                        } elseif ( $req->status === 'pending' ) {
                                            $bg_color = '#fef8ee'; $text_color = '#b32d2e'; $label = 'In Attesa';
                                        }
                                    ?>
                                    <span style="display:inline-block; padding:5px 12px; border-radius:20px; font-weight:bold; font-size:12px; background:<?php echo $bg_color; ?>; color:<?php echo $text_color; ?>; border: 1px solid rgba(0,0,0,0.05);"><?php echo $label; ?></span>
                                </td>

                                <td style="padding:15px; vertical-align:top;">
                                    <div style="display: flex; gap: 10px; align-items: center;">
                                        <form method="POST" style="display:inline;" class="gcs-ajax-form">
                                            <?php wp_nonce_field('front_status', 'gcs_front_nonce'); ?>
                                            <input type="hidden" name="request_id" value="<?php echo $req->id; ?>">
                                            <input type="hidden" name="gcs_front_update_status" value="1">
                                            <select name="status" onchange="this.form.dispatchEvent(new Event('submit', {cancelable: true, bubbles: true}))" style="padding: 5px; border-radius: 4px; border: 1px solid #ccc; font-size: 13px;">
                                                <option value="pending" <?php selected($req->status, 'pending'); ?>>In attesa</option>
                                                <option value="confirmed" <?php selected($req->status, 'confirmed'); ?>>Confermata</option>
                                                <option value="rejected" <?php selected($req->status, 'rejected'); ?>>Rifiutata</option>
                                            </select>
                                        </form>

                                        <form method="POST" style="display:inline;" class="gcs-ajax-form">
                                            <?php wp_nonce_field('front_status', 'gcs_front_nonce'); ?>
                                            <input type="hidden" name="request_id" value="<?php echo $req->id; ?>">
                                            <input type="hidden" name="gcs_front_delete_req" value="1">
                                            <button type="submit" style="background:none; border:none; color:#e74c3c; cursor:pointer; font-size:13px; text-decoration:underline;" onclick="return confirm('Sei sicuro di voler eliminare questa richiesta?')">Elimina</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function render_settings_management() {
        ob_start();
        ?>
        <div style="background:#fff; border:1px solid #eaeaea; border-radius:8px; box-shadow:0 4px 15px rgba(0,0,0,.05); padding:30px;">
            <form method="POST">
                <input type="hidden" name="gcs_front_settings_save" value="1">
                <?php wp_nonce_field('front_settings', 'gcs_front_nonce'); ?>
                
                <h3 style="margin-top:0; border-bottom:2px solid #eee; padding-bottom:10px; color:#333;">Configurazione Form e Frontend</h3>
                
                <div style="display:flex; flex-wrap:wrap; gap:20px; margin-bottom: 20px;">
                    <div style="flex:1 1 300px;">
                        <label style="display:block; font-weight:bold; margin-bottom:5px;">Email notifiche</label>
                        <input type="email" name="gcs_notification_email" value="<?php echo esc_attr( get_option('gcs_notification_email', get_option('admin_email')) ); ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                    <div style="flex:1 1 300px;">
                        <label style="display:block; font-weight:bold; margin-bottom:5px;">Titolo del Form</label>
                        <input type="text" name="gcs_form_title" value="<?php echo esc_attr( get_option('gcs_form_title', 'Invia una Richiesta di Prenotazione') ); ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                </div>

                <div style="margin-bottom: 30px;">
                    <label style="display:block; margin-bottom:10px; cursor:pointer;">
                        <input type="checkbox" name="gcs_show_guests_field" value="1" <?php checked( 1, get_option('gcs_show_guests_field', 1), true ); ?> />
                        Mostra campo "Numero persone" nel form
                    </label>
                    <label style="display:block; margin-bottom:10px; cursor:pointer;">
                        <input type="checkbox" name="gcs_show_message_field" value="1" <?php checked( 1, get_option('gcs_show_message_field', 1), true ); ?> />
                        Mostra campo "Messaggio aggiuntivo" nel form
                    </label>
                </div>

                <h3 style="border-bottom:2px solid #eee; padding-bottom:10px; color:#333;">Design e Colori</h3>
                <div style="display:flex; flex-wrap:wrap; gap:20px; margin-bottom: 30px;">
                    <div style="flex:1 1 200px;">
                        <label style="display:block; font-weight:bold; margin-bottom:5px;">Colore Titolo</label>
                        <input type="color" name="gcs_style_title_color" value="<?php echo esc_attr( get_option('gcs_style_title_color', '#1a4581') ); ?>" style="height:35px; width:100%;">
                    </div>
                    <div style="flex:1 1 200px;">
                        <label style="display:block; font-weight:bold; margin-bottom:5px;">Grandezza Titolo</label>
                        <input type="text" name="gcs_style_title_size" value="<?php echo esc_attr( get_option('gcs_style_title_size', '24px') ); ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                    <div style="flex:1 1 200px;">
                        <label style="display:block; font-weight:bold; margin-bottom:5px;">Testo Bottone</label>
                        <input type="color" name="gcs_style_btn_color" value="<?php echo esc_attr( get_option('gcs_style_btn_color', '#ffffff') ); ?>" style="height:35px; width:100%;">
                    </div>
                    <div style="flex:1 1 200px;">
                        <label style="display:block; font-weight:bold; margin-bottom:5px;">Sfondo Bottone</label>
                        <input type="color" name="gcs_style_btn_bg" value="<?php echo esc_attr( get_option('gcs_style_btn_bg', '#1a4581') ); ?>" style="height:35px; width:100%;">
                    </div>
                    <div style="flex:1 1 200px;">
                        <label style="display:block; font-weight:bold; margin-bottom:5px;">Hover Bottone</label>
                        <input type="color" name="gcs_style_btn_bg_hover" value="<?php echo esc_attr( get_option('gcs_style_btn_bg_hover', '#a1d1d0') ); ?>" style="height:35px; width:100%;">
                    </div>
                    <div style="flex:1 1 200px;">
                        <label style="display:block; font-weight:bold; margin-bottom:5px;">Rotondità Bottone</label>
                        <input type="text" name="gcs_style_btn_radius" value="<?php echo esc_attr( get_option('gcs_style_btn_radius', '20px') ); ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                </div>

                <h3 style="border-bottom:2px solid #eee; padding-bottom:10px; color:#333;">Impostazioni Area Riservata Multiple</h3>
                <div style="margin-bottom: 30px;">
                    <label style="display:block; font-weight:bold; margin-bottom:5px;">Utenti Autorizzati (uno per riga: username:password)</label>
                    <textarea name="gcs_reserved_users" rows="4" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px; font-family:monospace;"><?php echo esc_textarea( get_option('gcs_reserved_users', '') ); ?></textarea>
                    <p style="font-size:12px; color:#777; margin-top:5px;">Se li modifichi e scolleghi il tuo stesso utente, verrai disconnesso.</p>
                </div>

                <div style="border-top:1px solid #eee; padding-top:20px;">
                    <button type="submit" style="background: #27ae60; color: white; border: none; padding: 12px 25px; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 16px; transition: background 0.3s;" onmouseover="this.style.background='#2ecc71'" onmouseout="this.style.background='#27ae60'">Salva Tutte le Impostazioni</button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function render_calendar_management() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gcs_requests';
        
        $month = isset($_GET['c_month']) ? intval($_GET['c_month']) : date('n');
        $year = isset($_GET['c_year']) ? intval($_GET['c_year']) : date('Y');

        ob_start();
        $message_html = '';

        // Handlers moved to render_reserved_area for centralized execution
        
        $start_date_month = sprintf("%04d-%02d-01", $year, $month);
        $end_date_month = date("Y-m-t", strtotime($start_date_month));
        $events = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE status = 'confirmed' AND (start_date <= %s AND end_date >= %s) ORDER BY start_date ASC", $end_date_month, $start_date_month));

        $days_in_month = date('t', mktime(0, 0, 0, $month, 1, $year));
        $first_weekday = date('w', mktime(0, 0, 0, $month, 1, $year));
        $first_weekday = ($first_weekday == 0) ? 7 : $first_weekday;
        $months_names = array('', 'Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno','Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre');

        ?>
        <div class="gcs-reserved-management" style="font-family: inherit;">
            <?php echo $message_html; ?>

            <div style="display:flex; flex-wrap:wrap; gap:20px; align-items:flex-start;">
                <!-- Colonna Calendario -->
                <div id="gcs_reserved_calendar_col" style="flex:1 1 60%; background:#fff; border:1px solid #eaeaea; padding:25px; border-radius:8px; box-shadow:0 4px 15px rgba(0,0,0,.05); min-width: 300px; transition: opacity 0.3s;">
                    <?php
                    $pm = ($month == 1) ? 12 : $month - 1; $py = ($month == 1) ? $year - 1 : $year;
                    $nm = ($month == 12) ? 1 : $month + 1; $ny = ($month == 12) ? $year + 1 : $year;
                    
                    $prev_url = add_query_arg(array('c_month' => $pm, 'c_year' => $py));
                    $next_url = add_query_arg(array('c_month' => $nm, 'c_year' => $ny));
                    ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                        <a href="<?php echo esc_url($prev_url); ?>" class="gcs-cal-nav" style="padding: 8px 15px; background: #f0f0f1; border: 1px solid #ccc; border-radius: 4px; color: #3c434a; text-decoration: none; font-weight: bold;">&laquo; <?php echo $months_names[$pm]; ?></a>
                        <h3 style="margin:0; font-weight:700; font-size: 22px; color: #333; text-transform: uppercase;"><?php echo $months_names[$month].' '.$year; ?></h3>
                        <a href="<?php echo esc_url($next_url); ?>" class="gcs-cal-nav" style="padding: 8px 15px; background: #f0f0f1; border: 1px solid #ccc; border-radius: 4px; color: #3c434a; text-decoration: none; font-weight: bold;"><?php echo $months_names[$nm]; ?> &raquo;</a>
                    </div>
                    
                    <div style="overflow-x: auto;">
                        <table style="width:100%; border-collapse:collapse; table-layout:fixed; min-width: 600px; border: 1px solid #eee;">
                            <thead>
                                <tr>
                                    <?php foreach(array('Lun','Mar','Mer','Gio','Ven','Sab','Dom') as $d) echo '<th style="padding:15px 10px; border:1px solid #eee; background:#f9f9f9; font-size:13px; text-transform:uppercase; color:#666; text-align:center;">'.$d.'</th>'; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <?php
                                    $cell = 0;
                                    for ($i = 1; $i < $first_weekday; $i++) { echo '<td style="border:1px solid #eee; background:#fafafa; height:100px;"></td>'; $cell++; }
                                    for ($day = 1; $day <= $days_in_month; $day++) {
                                        if ($cell % 7 == 0 && $cell != 0) echo '</tr><tr>';
                                        $cur = sprintf("%04d-%02d-%02d", $year, $month, $day);
                                        $bgToday = ($cur == date('Y-m-d')) ? 'background-color:#f0f6fc;' : '';
                                        echo '<td style="border:1px solid #eee; padding:8px; height:100px; vertical-align:top; width: 14.28%; '.$bgToday.'">';
                                        
                                        $todayStyle = ($cur == date('Y-m-d')) ? 'background: #1a4581; color: white; display: inline-flex; justify-content:center; align-items:center; border-radius: 50%; width: 24px; height: 24px;' : 'color: #555; display: inline-flex; justify-content:center; align-items:center; width: 24px; height: 24px;';
                                        echo '<div style="display:flex; justify-content:center; align-items:center; height:28px; margin-bottom:5px;"><span style="font-weight:bold; font-size:13px; line-height:1; '.$todayStyle.'">'.$day.'</span></div>';
                                        
                                        foreach ($events as $ev) {
                                            if ($cur >= $ev->start_date && $cur <= $ev->end_date) {
                                                $color = ($ev->contact_email == 'manuale@calendario.local') ? '#e74c3c' : '#3498db';
                                                $cleanMsg = str_replace(array("\r","\n","'"), array(" "," ","\'"), $ev->message);
                                                
                                                $is_start = ($cur == $ev->start_date);
                                                $is_end = ($cur == $ev->end_date);
                                                $is_mon = ($cell % 7 == 0);
                                                
                                                $cls = array('gcs-front-cal-event');
                                                if (!$is_start) $cls[] = 'cont-prev';
                                                if (!$is_end) $cls[] = 'cont-next';
                                                
                                                $showText = ($is_start || $is_mon || $day == 1);
                                                $txtColor = $showText ? '#fff' : 'transparent';

                                                echo '<div onclick="gcsFrontOpenEventModal('.$ev->id.', \''.esc_js($ev->group_name).'\', \''.$ev->start_date.'\', \''.$ev->end_date.'\', \''.esc_js($cleanMsg).'\')" class="'.implode(' ', $cls).'" style="background:'.$color.'; color:'.$txtColor.';" title="'.esc_attr($ev->group_name).'">'.esc_html($ev->group_name).'</div>';
                                            }
                                        }
                                        echo '</td>'; $cell++;
                                    }
                                    while ($cell % 7 != 0) { echo '<td style="border:1px solid #eee; background:#fafafa; height:100px;"></td>'; $cell++; }
                                    ?>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Colonna Azioni -->
                <div style="flex:1 1 30%; background:#fff; border:1px solid #eaeaea; padding:25px; border-radius:8px; box-shadow:0 4px 15px rgba(0,0,0,.05); min-width: 250px;">
                    <h3 style="margin-top:0; color: #333; font-size: 18px; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px;">Aggiungi Impegno Rapido</h3>
                    <form method="POST">
                        <?php wp_nonce_field('add_manual_event', 'gcs_nonce'); ?>
                        <input type="hidden" name="gcs_front_add_manual" value="1">
                        <p style="margin-bottom: 15px;">
                            <label style="display:block; margin-bottom:5px; font-weight:bold; color:#555;">Titolo</label>
                            <input type="text" name="event_title" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 14px;">
                        </p>
                        <p style="margin-bottom: 15px;">
                            <label style="display:block; margin-bottom:5px; font-weight:bold; color:#555;">Data di Inizio</label>
                            <input type="date" name="event_start" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 14px;">
                        </p>
                        <p style="margin-bottom: 25px;">
                            <label style="display:block; margin-bottom:5px; font-weight:bold; color:#555;">Data di Fine</label>
                            <input type="date" name="event_end" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 14px;">
                        </p>
                        <button type="submit" style="width: 100%; background: #27ae60; color: white; border: none; padding: 12px; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 16px; transition: background 0.3s;" onmouseover="this.style.background='#2ecc71'" onmouseout="this.style.background='#27ae60'">+ Salva Evento</button>
                    </form>
                </div>
            </div>

            <!-- Modal e Script -->
            <style>
                .gcs-front-cal-event {
                    cursor:pointer; padding:5px 8px; margin:4px 0; font-size:11px; border-radius:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
                    transition: filter 0.2s; width: 100%; box-sizing: border-box; position:relative; z-index:1; line-height: 1.3; font-weight:bold; box-shadow: 0 1px 2px rgba(0,0,0,0.1); font-family: sans-serif;
                }
                .gcs-front-cal-event:hover { filter: brightness(1.1); z-index:2; box-shadow: 0 2px 5px rgba(0,0,0,0.15); }
                .gcs-front-cal-event.cont-prev {
                    border-top-left-radius: 0; border-bottom-left-radius: 0;
                    margin-left: -9px; width: calc(100% + 9px);
                }
                .gcs-front-cal-event.cont-next {
                    border-top-right-radius: 0; border-bottom-right-radius: 0;
                    margin-right: -9px; width: calc(100% + 9px);
                }
                .gcs-front-cal-event.cont-prev.cont-next { width: calc(100% + 18px); }

                .gcs-front-modal { display:none; position:fixed; z-index:99999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6); backdrop-filter:blur(3px); align-items:center; justify-content:center; }
                .gcs-front-modal-content { background:#fff; padding:35px; border-radius:12px; width:500px; max-width:90%; position:relative; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
                .gcs-front-modal input[type="text"], .gcs-front-modal input[type="date"], .gcs-front-modal textarea {
                    width: 100%;
                    padding: 10px;
                    border: 1px solid #ddd;
                    border-radius: 6px;
                    box-sizing: border-box;
                    font-family: inherit;
                    font-size: 14px;
                }
                .gcs-front-modal input:focus, .gcs-front-modal textarea:focus {
                    outline: none;
                    border-color: #3498db;
                    box-shadow: 0 0 0 2px rgba(52,152,219,0.2);
                }
                .gcs-front-modal label {
                    display: block;
                    margin-bottom: 6px;
                    font-weight: bold;
                    color: #444;
                    font-size: 14px;
                }
            </style>
            
            <div id="gcsFrontEditModal" class="gcs-front-modal">
                <div class="gcs-front-modal-content">
                    <h2 style="margin-top:0; color:#333; font-size: 22px; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; margin-bottom: 20px;">Dettagli Impegno</h2>
                    <form method="POST">
                        <?php wp_nonce_field('edit_event_action', 'gcs_edit_nonce'); ?>
                        <input type="hidden" name="gcs_edit_event_action" value="1">
                        <input type="hidden" name="edit_id" id="front_edit_id">
                        <input type="hidden" name="gcs_event_op" id="gcs_front_event_op" value="save">
                        
                        <p style="margin-bottom: 15px;">
                            <label>Titolo / Gruppo</label>
                            <input type="text" name="edit_title" id="front_edit_title" required>
                        </p>
                        
                        <div style="display:flex; gap:15px; margin-bottom: 15px;">
                            <div style="flex:1;">
                                <label>Dal</label>
                                <input type="date" name="edit_start" id="front_edit_start" required>
                            </div>
                            <div style="flex:1;">
                                <label>Al</label>
                                <input type="date" name="edit_end" id="front_edit_end" required>
                            </div>
                        </div>
                        
                        <p style="margin-bottom: 25px;">
                            <label>Note Addizionali</label>
                            <textarea name="edit_message" id="front_edit_message" rows="4"></textarea>
                        </p>
                        
                        <div style="display:flex; justify-content:space-between; align-items: center; border-top: 1px solid #f0f0f0; padding-top: 20px;">
                            <button type="button" onclick="document.getElementById('gcsFrontEditModal').style.display='none'" style="background: transparent; border: 1px solid #bbb; padding: 10px 15px; border-radius: 4px; cursor: pointer; color: #555; font-weight: bold; transition: background 0.3s;" onmouseover="this.style.background='#f9f9f9'" onmouseout="this.style.background='transparent'">Annulla</button>
                             <div style="display: flex; gap: 10px; flex-direction: column; align-items: flex-end;">
                                <div id="gcs_front_delete_confirm" style="display:none; background:#fff1f0; border:1px solid #ffa39e; padding:8px; border-radius:4px; text-align:center; margin-bottom:5px;">
                                    <p style="margin:0 0 5px; color:#e74c3c; font-size:12px; font-weight:bold;">Eliminare?</p>
                                    <button type="submit" style="background:#e74c3c; color:#fff; border:none; padding:4px 10px; border-radius:3px; cursor:pointer;" onclick="document.getElementById('gcs_front_event_op').value='delete';">Si</button>
                                    <button type="button" style="background:#bbb; color:#fff; border:none; padding:4px 10px; border-radius:3px; cursor:pointer;" onclick="document.getElementById('gcs_front_delete_confirm').style.display='none'; document.getElementById('gcs_front_delete_trigger').style.display='block';">No</button>
                                </div>
                                <div style="display:flex; gap:10px;">
                                    <button type="button" id="gcs_front_delete_trigger" style="background: white; border: 1px solid #e74c3c; color: #e74c3c; padding: 10px 15px; border-radius: 4px; cursor: pointer; font-weight: bold; transition: all 0.3s;" onmouseover="this.style.background='#e74c3c'; this.style.color='white';" onmouseout="this.style.background='white'; this.style.color='#e74c3c';" onclick="this.style.display='none'; document.getElementById('gcs_front_delete_confirm').style.display='block';">Elimina</button>
                                    <button type="submit" style="background: #1a4581; color: white; border: none; padding: 10px 25px; border-radius: 4px; cursor: pointer; font-weight: bold; transition: background 0.3s;" onmouseover="this.style.background='#133463'" onmouseout="this.style.background='#1a4581'" onclick="console.log('Frontend: Saving...'); document.getElementById('gcs_front_event_op').value='save';">Salva Modifiche</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <script>
                function gcsFrontOpenEventModal(id, title, start, end, msg) {
                    document.getElementById('front_edit_id').value = id;
                    document.getElementById('front_edit_title').value = title;
                    document.getElementById('front_edit_start').value = start;
                    document.getElementById('front_edit_end').value = end;
                    document.getElementById('front_edit_message').value = msg;
                    document.getElementById('gcs_front_event_op').value = 'save';
                    document.getElementById('gcs_front_delete_confirm').style.display = 'none';
                    document.getElementById('gcs_front_delete_trigger').style.display = 'block';
                    document.getElementById('gcsFrontEditModal').style.display = 'flex';
                }
                
                window.addEventListener('click', function(event) { 
                    var modal = document.getElementById('gcsFrontEditModal');
                    if (event.target == modal) {
                        modal.style.display = 'none'; 
                    }
                });
            </script>
        </div>
        <?php
        return ob_get_clean();
    }
}
