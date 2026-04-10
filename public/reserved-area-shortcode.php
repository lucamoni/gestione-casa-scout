<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GCS_Reserved_Area_Shortcode {
    public static function init() {
        add_shortcode( 'gcs_reserved_area', array( __CLASS__, 'render_reserved_area' ) );
        add_action( 'template_redirect', array( __CLASS__, 'handle_actions' ) );
    }

    public static function handle_actions() {
        $email_opt = get_option('gcs_reserved_email', '');
        $pass_opt = get_option('gcs_reserved_password', '');
        
        // Handle Logout
        if (isset($_GET['gcs_logout'])) {
            setcookie('gcs_reserved_auth', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
            wp_safe_redirect(remove_query_arg('gcs_logout'));
            exit;
        }

        // Handle Login
        if (isset($_POST['gcs_reserved_login_submit'])) {
            $email = sanitize_email($_POST['gcs_email']);
            $pass = sanitize_text_field($_POST['gcs_password']);

            if ($email === $email_opt && $pass === $pass_opt && !empty($email_opt)) {
                setcookie('gcs_reserved_auth', md5($email_opt . $pass_opt), time() + 86400, COOKIEPATH, COOKIE_DOMAIN);
                wp_safe_redirect(remove_query_arg('gcs_login_error'));
                exit;
            } else {
                wp_safe_redirect(add_query_arg('gcs_login_error', '1'));
                exit;
            }
        }
    }

    public static function is_authenticated() {
        $email_opt = get_option('gcs_reserved_email', '');
        $pass_opt = get_option('gcs_reserved_password', '');
        if (empty($email_opt) || empty($pass_opt)) return false;

        $expected = md5($email_opt . $pass_opt);
        return isset($_COOKIE['gcs_reserved_auth']) && $_COOKIE['gcs_reserved_auth'] === $expected;
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
                        <label style="display: block; margin-bottom: 5px; font-weight: bold; color: #444;">Email</label>
                        <input type="email" name="gcs_email" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 14px;">
                    </p>
                    <p style="margin-bottom: 25px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: bold; color: #444;">Password</label>
                        <input type="password" name="gcs_password" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 14px;">
                    </p>
                    <button type="submit" name="gcs_reserved_login_submit" style="width: 100%; background: #1a4581; color: #fff; border: none; padding: 12px; border-radius: 5px; font-weight: bold; cursor: pointer; transition: background 0.3s; font-size: 16px;">Accedi</button>
                    <p style="text-align: center; font-size: 12px; color: #888; margin-top: 15px; margin-bottom: 0;">Accesso riservato alla gestione calendario.</p>
                </form>
            </div>
            <?php
            return ob_get_clean();
        }

        return self::render_calendar_management();
    }

    public static function render_calendar_management() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gcs_requests';
        
        $month = isset($_GET['c_month']) ? intval($_GET['c_month']) : date('n');
        $year = isset($_GET['c_year']) ? intval($_GET['c_year']) : date('Y');

        ob_start();
        $message_html = '';

        // GESTIONE AZIONI (POST)
        if (isset($_POST['gcs_add_manual_event']) && wp_verify_nonce($_POST['gcs_nonce'], 'add_manual_event')) {
            $title = sanitize_text_field($_POST['event_title']);
            $start = sanitize_text_field($_POST['event_start']);
            $end = sanitize_text_field($_POST['event_end']);
            if ($title && $start && $end) {
                GCS_DB_Manager::insert_request(array(
                    'group_name' => $title,
                    'contact_email' => 'manuale@calendario.local',
                    'start_date' => $start,
                    'end_date' => $end,
                    'guests_count' => 0,
                    'message' => 'Impegno inserito manualmente dal frontend.',
                    'status' => 'confirmed'
                ));
                $message_html = '<div style="background:#d4edda; color:#155724; padding:15px; border-radius:4px; margin-bottom:20px; text-align:center; font-weight:bold;">Impegno aggiunto con successo.</div>';
            }
        }

        if (isset($_POST['gcs_edit_event_action']) && wp_verify_nonce($_POST['gcs_edit_nonce'], 'edit_event_action')) {
            if (isset($_POST['gcs_delete_event_action'])) {
                $wpdb->delete($table_name, array('id' => intval($_POST['edit_id'])));
                $message_html = '<div style="background:#d4edda; color:#155724; padding:15px; border-radius:4px; margin-bottom:20px; text-align:center; font-weight:bold;">Evento eliminato con successo.</div>';
            } else {
                GCS_DB_Manager::update_request(intval($_POST['edit_id']), array(
                    'group_name' => sanitize_text_field($_POST['edit_title']),
                    'start_date' => sanitize_text_field($_POST['edit_start']),
                    'end_date' => sanitize_text_field($_POST['edit_end']),
                    'message' => sanitize_textarea_field($_POST['edit_message'])
                ));
                $message_html = '<div style="background:#d4edda; color:#155724; padding:15px; border-radius:4px; margin-bottom:20px; text-align:center; font-weight:bold;">Evento aggiornato con successo.</div>';
            }
        }

        $start_date_month = sprintf("%04d-%02d-01", $year, $month);
        $end_date_month = date("Y-m-t", strtotime($start_date_month));
        $events = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE status = 'confirmed' AND (start_date <= %s AND end_date >= %s) ORDER BY start_date ASC", $end_date_month, $start_date_month));

        $days_in_month = date('t', mktime(0, 0, 0, $month, 1, $year));
        $first_weekday = date('w', mktime(0, 0, 0, $month, 1, $year));
        $first_weekday = ($first_weekday == 0) ? 7 : $first_weekday;
        $months_names = array('', 'Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno','Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre');

        ?>
        <div class="gcs-reserved-management" style="font-family: inherit; margin: 30px 0;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #eaeaea;">
                <h2 style="margin: 0; color: #1a4581; font-size: 24px; font-family: 'Martel', serif;">Gestione Area Riservata</h2>
                <a href="<?php echo esc_url(add_query_arg('gcs_logout', '1')); ?>" style="background: #e74c3c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 14px; transition: background 0.3s;" onmouseover="this.style.background='#c0392b'" onmouseout="this.style.background='#e74c3c'">Esci Sessione</a>
            </div>
            
            <?php echo $message_html; ?>

            <div style="display:flex; flex-wrap:wrap; gap:20px; align-items:flex-start;">
                <!-- Colonna Calendario -->
                <div style="flex:1 1 60%; background:#fff; border:1px solid #eaeaea; padding:25px; border-radius:8px; box-shadow:0 4px 15px rgba(0,0,0,.05); min-width: 300px;">
                    <?php
                    $pm = ($month == 1) ? 12 : $month - 1; $py = ($month == 1) ? $year - 1 : $year;
                    $nm = ($month == 12) ? 1 : $month + 1; $ny = ($month == 12) ? $year + 1 : $year;
                    
                    $prev_url = add_query_arg(array('c_month' => $pm, 'c_year' => $py));
                    $next_url = add_query_arg(array('c_month' => $nm, 'c_year' => $ny));
                    ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                        <a href="<?php echo esc_url($prev_url); ?>" style="padding: 8px 15px; background: #f0f0f1; border: 1px solid #ccc; border-radius: 4px; color: #3c434a; text-decoration: none; font-weight: bold;">&laquo; <?php echo $months_names[$pm]; ?></a>
                        <h3 style="margin:0; font-weight:700; font-size: 22px; color: #333; text-transform: uppercase;"><?php echo $months_names[$month].' '.$year; ?></h3>
                        <a href="<?php echo esc_url($next_url); ?>" style="padding: 8px 15px; background: #f0f0f1; border: 1px solid #ccc; border-radius: 4px; color: #3c434a; text-decoration: none; font-weight: bold;"><?php echo $months_names[$nm]; ?> &raquo;</a>
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
                                        
                                        $todayStyle = ($cur == date('Y-m-d')) ? 'background: #1a4581; color: white; display: inline-block; padding: 2px 6px; border-radius: 50%; font-size: 13px;' : 'color: #555;';
                                        echo '<span style="display:block; margin-bottom:8px; font-weight:bold; '.$todayStyle.'">'.$day.'</span>';
                                        
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
                        <input type="hidden" name="gcs_add_manual_event" value="1">
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
                            <div style="display: flex; gap: 10px;">
                                <button type="submit" name="gcs_delete_event_action" style="background: white; border: 1px solid #e74c3c; color: #e74c3c; padding: 10px 15px; border-radius: 4px; cursor: pointer; font-weight: bold; transition: all 0.3s;" onmouseover="this.style.background='#e74c3c'; this.style.color='white';" onmouseout="this.style.background='white'; this.style.color='#e74c3c';" onclick="return confirm('Sei sicuro di voler rimuovere definitivamente questo evento?')">Elimina</button>
                                <button type="submit" style="background: #1a4581; color: white; border: none; padding: 10px 25px; border-radius: 4px; cursor: pointer; font-weight: bold; transition: background 0.3s;" onmouseover="this.style.background='#133463'" onmouseout="this.style.background='#1a4581'">Salva Modifiche</button>
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
