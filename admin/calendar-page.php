<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** REFRESH DIAGNOSTICA **/
class GCS_Calendar_Page {
    public static function render_calendar_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gcs_requests';
        
        $month = isset($_GET['c_month']) ? intval($_GET['c_month']) : date('n');
        $year = isset($_GET['c_year']) ? intval($_GET['c_year']) : date('Y');

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
                    'message' => 'Impegno inserito manualmente dal calendario.',
                    'status' => 'confirmed'
                ));
            }
        }

        if (isset($_POST['gcs_edit_event_action']) && wp_verify_nonce($_POST['gcs_edit_nonce'], 'edit_event_action')) {
            GCS_DB_Manager::update_request(intval($_POST['edit_id']), array(
                'group_name' => sanitize_text_field($_POST['edit_title']),
                'start_date' => sanitize_text_field($_POST['edit_start']),
                'end_date' => sanitize_text_field($_POST['edit_end']),
                'message' => sanitize_textarea_field($_POST['edit_message'])
            ));
        }

        if (isset($_POST['gcs_delete_event_action']) && wp_verify_nonce($_POST['gcs_edit_nonce'], 'edit_event_action')) {
            $wpdb->delete($table_name, array('id' => intval($_POST['edit_id'])));
        }

        $start_date_month = sprintf("%04d-%02d-01", $year, $month);
        $end_date_month = date("Y-m-t", strtotime($start_date_month));
        $events = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE status = 'confirmed' AND (start_date <= %s AND end_date >= %s) ORDER BY start_date ASC", $end_date_month, $start_date_month));

        $days_in_month = date('t', mktime(0, 0, 0, $month, 1, $year));
        $first_weekday = date('w', mktime(0, 0, 0, $month, 1, $year));
        $first_weekday = ($first_weekday == 0) ? 7 : $first_weekday;
        $months_names = array('', 'Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno','Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre');

        echo '<div class="wrap"><h1 class="wp-heading-inline">Calendario Prenotazioni Casa Scout</h1><hr class="wp-header-end">';
        echo '<div style="display:flex; flex-wrap:wrap; gap:20px; align-items:flex-start; margin-top:20px;">';
        
        // Colonna Calendario
        echo '<div style="flex:1 1 60%; background:#fff; border:1px solid #ccd0d4; padding:20px; border-radius:8px; box-shadow:0 5px 15px rgba(0,0,0,.05);">';
        $pm = ($month == 1) ? 12 : $month - 1; $py = ($month == 1) ? $year - 1 : $year;
        $nm = ($month == 12) ? 1 : $month + 1; $ny = ($month == 12) ? $year + 1 : $year;
        echo '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px;">';
        echo '<a href="?page=gcs-calendar&c_month='.$pm.'&c_year='.$py.'" class="button">&laquo; '.$months_names[$pm].'</a>';
        echo '<h2 style="margin:0; font-weight:700;">'.$months_names[$month].' '.$year.'</h2>';
        echo '<a href="?page=gcs-calendar&c_month='.$nm.'&c_year='.$ny.'" class="button">'.$months_names[$nm].' &raquo;</a>';
        echo '</div>';
        
        echo '<table style="width:100%; border-collapse:collapse; table-layout:fixed;"><thead><tr>';
        foreach(array('Lun','Mar','Mer','Gio','Ven','Sab','Dom') as $d) echo '<th style="padding:12px; border:1px solid #eee; background:#fafafa; font-size:12px; text-transform:uppercase; color:#888;">'.$d.'</th>';
        echo '</tr></thead><tbody><tr>';
        
        $cell = 0;
        for ($i = 1; $i < $first_weekday; $i++) { echo '<td style="border:1px solid #eee; background:#fdfdfd; height:110px;"></td>'; $cell++; }
        for ($day = 1; $day <= $days_in_month; $day++) {
            if ($cell % 7 == 0 && $cell != 0) echo '</tr><tr>';
            $cur = sprintf("%04d-%02d-%02d", $year, $month, $day);
            $bgToday = ($cur == date('Y-m-d')) ? 'background-color:#f0f6fc;' : '';
            echo '<td style="border:1px solid #eee; padding:5px; height:110px; vertical-align:top; '.$bgToday.'">';
            echo '<span style="display:block; margin-bottom:5px; font-weight:bold; color:#444; opacity:0.6;">'.$day.'</span>';
            foreach ($events as $ev) {
                if ($cur >= $ev->start_date && $cur <= $ev->end_date) {
                    $color = ($ev->contact_email == 'manuale@calendario.local') ? '#e74c3c' : '#3498db';
                    $cleanMsg = str_replace(array("\r","\n","'"), array(" "," ","\'"), $ev->message);
                    
                    $is_start = ($cur == $ev->start_date);
                    $is_end = ($cur == $ev->end_date);
                    $is_mon = ($cell % 7 == 0);
                    
                    $cls = array('gcs-admin-cal-event');
                    if (!$is_start) $cls[] = 'cont-prev';
                    if (!$is_end) $cls[] = 'cont-next';
                    
                    $showText = ($is_start || $is_mon || $day == 1);
                    $txtColor = $showText ? '#fff' : 'transparent';

                    echo '<div onclick="gcsOpenEventModal('.$ev->id.', \''.esc_js($ev->group_name).'\', \''.$ev->start_date.'\', \''.$ev->end_date.'\', \''.esc_js($cleanMsg).'\')" class="'.implode(' ', $cls).'" style="background:'.$color.'; color:'.$txtColor.';" title="'.esc_attr($ev->group_name).'">'.esc_html($ev->group_name).'</div>';
                }
            }
            echo '</td>'; $cell++;
        }
        while ($cell % 7 != 0) { echo '<td style="border:1px solid #eee; background:#fdfdfd; height:110px;"></td>'; $cell++; }
        echo '</tr></tbody></table></div>';
        
        // Colonna Azioni
        echo '<div style="flex:1 1 30%; background:#fff; border:1px solid #ccd0d4; padding:25px; border-radius:8px; box-shadow:0 5px 15px rgba(0,0,0,.05);">';
        echo '<h3 style="margin-top:0;">Aggiungi Impegno Rapido</h3>';
        echo '<form method="POST">';
        wp_nonce_field('add_manual_event', 'gcs_nonce');
        echo '<input type="hidden" name="gcs_add_manual_event" value="1">';
        echo '<p><label>Titolo</label><input type="text" name="event_title" required class="large-text"></p>';
        echo '<p><label>Inizio</label><input type="date" name="event_start" required class="large-text"></p>';
        echo '<p><label>Fine</label><input type="date" name="event_end" required class="large-text"></p>';
        echo '<button type="submit" class="button button-primary" style="width:100%;">Salva</button></form></div>';
        echo '</div></div>';

        // Modal e Script
        ?>
        <style>
            .gcs-admin-cal-event {
                cursor:pointer; padding:4px 8px; margin:4px 0; font-size:11px; border-radius:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
                transition: filter 0.2s; width: 100%; box-sizing: border-box; position:relative; z-index:1;
            }
            .gcs-admin-cal-event:hover { filter: brightness(1.1); z-index:2; }
            .gcs-admin-cal-event.cont-prev {
                border-top-left-radius: 0; border-bottom-left-radius: 0;
                margin-left: -5px; width: calc(100% + 5px);
            }
            .gcs-admin-cal-event.cont-next {
                border-top-right-radius: 0; border-bottom-right-radius: 0;
                margin-right: -5px; width: calc(100% + 5px);
            }
            .gcs-admin-cal-event.cont-prev.cont-next { width: calc(100% + 10px); }

            .gcs-modal { display:none; position:fixed; z-index:99999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); backdrop-filter:blur(3px); align-items:center; justify-content:center; }
            .gcs-modal-content { background:#fff; padding:30px; border-radius:12px; width:450px; max-width:90%; }
        </style>
        <div id="gcsEditModal" class="gcs-modal"><div class="gcs-modal-content">
            <h2 style="margin-top:0;">Modifica Evento</h2>
            <form method="POST">
                <?php wp_nonce_field('edit_event_action', 'gcs_edit_nonce'); ?>
                <input type="hidden" name="gcs_edit_event_action" value="1"><input type="hidden" name="edit_id" id="edit_id">
                <p><label>Titolo</label><br/><input type="text" name="edit_title" id="edit_title" required class="large-text"></p>
                <div style="display:flex; gap:10px;">
                    <p style="flex:1;"><label>Inizio</label><br/><input type="date" name="edit_start" id="edit_start" required class="large-text"></p>
                    <p style="flex:1;"><label>Fine</label><br/><input type="date" name="edit_end" id="edit_end" required class="large-text"></p>
                </div>
                <p><label>Note</label><br/><textarea name="edit_message" id="edit_message" rows="3" class="large-text"></textarea></p>
                <div style="display:flex; justify-content:space-between; margin-top:20px;">
                    <button type="button" onclick="document.getElementById('gcsEditModal').style.display='none'" class="button">Annulla</button>
                    <div>
                        <button type="submit" name="gcs_delete_event_action" class="button button-link-delete" style="color:#d63638;" onclick="return confirm('Rimuovere?')">Elimina</button>
                        <button type="submit" class="button button-primary">Salva</button>
                    </div>
                </div>
            </form>
        </div></div>
        <script>
            function gcsOpenEventModal(id, title, start, end, msg) {
                document.getElementById('edit_id').value = id;
                document.getElementById('edit_title').value = title;
                document.getElementById('edit_start').value = start;
                document.getElementById('edit_end').value = end;
                document.getElementById('edit_message').value = msg;
                document.getElementById('gcsEditModal').style.display = 'flex';
            }
            window.onclick = function(event) { if (event.target == document.getElementById('gcsEditModal')) document.getElementById('gcsEditModal').style.display = 'none'; }
        </script>
        <?php
    }
}
