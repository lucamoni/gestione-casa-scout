<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** REFRESH DIAGNOSTICA **/
class GCS_Calendar_Page {
    public static function render_calendar_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gcs_requests';
        
        $month = isset($_GET['c_month']) ? intval($_GET['c_month']) : date('n');
        $year = isset($_GET['c_year']) ? intval($_GET['c_year']) : date('Y');

        $message = '';
        // GESTIONE AZIONI (POST)
        if (isset($_POST['gcs_admin_add_manual']) && wp_verify_nonce($_POST['gcs_nonce'], 'add_manual_event')) {
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
                $message = '<div class="notice notice-success is-dismissible"><p>Impegno aggiunto con successo.</p></div>';
            }
        }

        if (isset($_POST['gcs_edit_event_action']) && wp_verify_nonce($_POST['gcs_edit_nonce'], 'edit_event_action')) {
            $edit_id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;
            $op = isset($_POST['gcs_event_op']) ? sanitize_text_field($_POST['gcs_event_op']) : 'save';

            if ($edit_id > 0) {
                if ($op === 'delete') {
                    $wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE id = %d", $edit_id));
                    $message = '<div class="notice notice-success is-dismissible"><p>Evento rimosso correttamente.</p></div>';
                } else {
                    $new_title = sanitize_text_field($_POST['edit_title']);
                    $new_start = sanitize_text_field($_POST['edit_start']);
                    $new_end = sanitize_text_field($_POST['edit_end']);
                    $new_msg = sanitize_textarea_field($_POST['edit_message']);
                    $new_status = isset($_POST['edit_status']) ? sanitize_text_field($_POST['edit_status']) : 'confirmed';
                    
                    $wpdb->update($table_name, array(
                        'group_name' => $new_title,
                        'start_date' => $new_start,
                        'end_date' => $new_end,
                        'message' => $new_msg,
                        'status' => $new_status
                    ), array('id' => $edit_id));
                    
                    $message = '<div class="notice notice-success is-dismissible"><p>Modifiche salvate correttamente.</p></div>';
                }
            }
        }

        $start_date_month = sprintf("%04d-%02d-01", $year, $month);
        $end_date_month = date("Y-m-t", strtotime($start_date_month));
        $events = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE status = 'confirmed' AND (start_date <= %s AND end_date >= %s) ORDER BY start_date ASC", $end_date_month, $start_date_month));

        $days_in_month = date('t', mktime(0, 0, 0, $month, 1, $year));
        $first_weekday = date('w', mktime(0, 0, 0, $month, 1, $year));
        $first_weekday = ($first_weekday == 0) ? 7 : $first_weekday;
        $months_names = array('', 'Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno','Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre');

        echo '<div class="wrap"><h1 class="wp-heading-inline">Calendario Prenotazioni Casa Scout</h1><hr class="wp-header-end">';
        echo $message;
        echo '<div style="display:flex; flex-wrap:wrap; gap:20px; align-items:flex-start; margin-top:20px;">';
        
        // Colonna Calendario
        echo '<div style="flex:1 1 60%; background:#fff; border:1px solid #ccd0d4; padding:20px; border-radius:8px; box-shadow:0 5px 15px rgba(0,0,0,.05);">';
        $pm = ($month == 1) ? 12 : $month - 1; $py = ($month == 1) ? $year - 1 : $year;
        $nm = ($month == 12) ? 1 : $month + 1; $ny = ($month == 12) ? $year + 1 : $year;
        echo '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px;">';
        echo '<a href="?page=gcs-admin-calendar&c_month='.$pm.'&c_year='.$py.'" class="button">&laquo; '.$months_names[$pm].'</a>';
        echo '<h2 style="margin:0; font-weight:700;">'.$months_names[$month].' '.$year.'</h2>';
        echo '<a href="?page=gcs-admin-calendar&c_month='.$nm.'&c_year='.$ny.'" class="button">'.$months_names[$nm].' &raquo;</a>';
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
            echo '<div style="display:flex; justify-content:center; align-items:center; height:24px; margin-bottom:5px;"><span style="font-weight:bold; color:#444; opacity:0.6;">'.$day.'</span></div>';
            foreach ($events as $ev) {
                if ($cur >= $ev->start_date && $cur <= $ev->end_date) {
                    $color = ($ev->contact_email == 'manuale@calendario.local') ? '#e74c3c' : '#2d5a27';
                    $cleanMsg = str_replace(array("\r","\n","'"), array(" "," ","\'"), $ev->message);
                    
                    $is_start = ($cur == $ev->start_date);
                    $is_end = ($cur == $ev->end_date);
                    $is_mon = ($cell % 7 == 0);
                    
                    $cls = array('gcs-admin-cal-event');
                    if (!$is_start) $cls[] = 'cont-prev';
                    if (!$is_end) $cls[] = 'cont-next';
                    
                    $showText = ($is_start || $is_mon || $day == 1);
                    $txtColor = $showText ? '#fff' : 'transparent';

                    echo '<div onclick="gcsOpenEventModal('.$ev->id.', \''.esc_js($ev->group_name).'\', \''.$ev->start_date.'\', \''.$ev->end_date.'\', \''.esc_js($cleanMsg).'\', \''.esc_js($ev->contact_email).'\', \''.$ev->status.'\')" class="'.implode(' ', $cls).'" style="background:'.$color.'; color:'.$txtColor.';" title="'.esc_attr($ev->group_name).'">'.esc_html($ev->group_name).'</div>';
                }
            }
            echo '</td>'; $cell++;
        }
        while ($cell % 7 != 0) { echo '<td style="border:1px solid #eee; background:#fdfdfd; height:110px;"></td>'; $cell++; }
        echo '</tr></tbody></table></div>';
        
        echo '<div style="flex:1 1 30%; background:#fff; border:1px solid #ccd0d4; padding:25px; border-radius:8px; box-shadow:0 5px 15px rgba(0,0,0,.05);">';
        echo '<h3 style="margin-top:0;">✨ Nuovo Impegno Rapido</h3>';
        echo '<p class="description">Inserisci qui impegni personali o blocchi manuali per escludere date dal sistema.</p>';
        echo '<form method="POST">';
        wp_nonce_field('add_manual_event', 'gcs_nonce');
        echo '<input type="hidden" name="gcs_admin_add_manual" value="1">';
        echo '<p><label><b>Titolo Impegno</b></label><input type="text" name="event_title" required class="large-text" placeholder="Es. Manutenzione Giardino"></p>';
        echo '<div style="display:flex; gap:10px;">';
        echo '<p style="flex:1;"><label><b>Inizio</b></label><input type="date" name="event_start" required class="large-text"></p>';
        echo '<p style="flex:1;"><label><b>Fine</b></label><input type="date" name="event_end" required class="large-text"></p>';
        echo '</div>';
        echo '<button type="submit" class="button button-primary" style="width:100%; height:40px; font-weight:700;">Salva Impegno</button></form></div>';
        echo '</div></div>';

        // Modal e Script
        ?>
        <style>
            .gcs-admin-cal-event {
                cursor:pointer; padding:4px 8px; margin:4px 0; font-size:11px; border-radius:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
                transition: filter 0.2s; width: 100%; box-sizing: border-box; position:relative; z-index:1; font-weight: 600;
            }
            .gcs-admin-cal-event:hover { filter: brightness(1.1); z-index:2; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
            
            .gcs-admin-cal-event.cont-prev {
                border-top-left-radius: 0; border-bottom-left-radius: 0;
                margin-left: -6px; width: calc(100% + 6px);
                padding-left: 14px;
            }
            .gcs-admin-cal-event.cont-next {
                border-top-right-radius: 0; border-bottom-right-radius: 0;
                margin-right: -6px; width: calc(100% + 6px);
            }
            .gcs-admin-cal-event.cont-prev.cont-next { width: calc(100% + 12px); }

            .gcs-modal { display:none; position:fixed; z-index:99999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6); backdrop-filter:blur(4px); align-items:center; justify-content:center; }
            .gcs-modal-content { background:#fff; padding:30px; border-radius:16px; width:480px; max-width:95%; box-shadow: 0 20px 40px rgba(0,0,0,0.2); }
            .modal-label { display: block; font-weight: 700; font-size: 11px; text-transform: uppercase; color: #64748b; margin-bottom: 5px; }
            .modal-info-row { background: #f8fafc; padding: 10px 15px; border-radius: 8px; margin-bottom: 15px; border: 1px solid #e2e8f0; }
        </style>
        
        <div id="gcsEditModal" class="gcs-modal"><div class="gcs-modal-content">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h2 style="margin:0; font-weight:800; color:#1e293b;">Dettaglio Impegno</h2>
                <span id="gcs_source_badge" style="font-size:10px; padding:3px 8px; border-radius:10px; font-weight:800; text-transform:uppercase;"></span>
            </div>

            <div id="gcs_modal_contact_info" class="modal-info-row">
                <span class="modal-label">Contatto</span>
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <strong id="modal_display_email" style="color:var(--gcs-primary);"></strong>
                    <span id="modal_display_id" style="font-size:10px; color:#94a3b8;"></span>
                </div>
            </div>

            <form method="POST">
                <?php wp_nonce_field('edit_event_action', 'gcs_edit_nonce'); ?>
                <input type="hidden" name="gcs_edit_event_action" value="1">
                <input type="hidden" name="edit_id" id="edit_id">
                <input type="hidden" name="gcs_event_op" id="gcs_admin_event_op" value="save">
                
                <p>
                    <label class="modal-label">Titolo / Gruppo</label>
                    <input type="text" name="edit_title" id="edit_title" required class="large-text" style="font-weight:700;">
                </p>
                
                <div style="display:flex; gap:10px; margin-bottom:15px;">
                    <div style="flex:1;">
                        <label class="modal-label">Inizio</label>
                        <input type="date" name="edit_start" id="edit_start" required class="large-text">
                    </div>
                    <div style="flex:1;">
                        <label class="modal-label">Fine</label>
                        <input type="date" name="edit_end" id="edit_end" required class="large-text">
                    </div>
                </div>

                <div id="gcs_status_sector" style="margin-bottom:20px; padding:15px; background:#fff8eb; border-radius:10px; border:1px solid #ffe8cc;">
                    <label class="modal-label" style="color:#b45309;">STATO PRENOTAZIONE</label>
                    <select name="edit_status" id="edit_status" class="large-text" style="background:#fff;">
                        <option value="pending">⏳ Metti in ATTESA (toglie dal calendario)</option>
                        <option value="confirmed">✅ CONFERMATA (visibile nel calendario)</option>
                        <option value="rejected">❌ RIFIUTATA (toglie dal calendario)</option>
                    </select>
                </div>

                <p>
                    <label class="modal-label">Note / Messaggio</label>
                    <textarea name="edit_message" id="edit_message" rows="3" class="large-text" style="font-size:13px;"></textarea>
                </p>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:25px;">
                    <button type="button" onclick="document.getElementById('gcsEditModal').style.display='none'" class="button">Annulla</button>
                    <div style="display:flex; gap:10px; align-items:center;">
                        <div id="gcs_admin_delete_confirm" style="display:none; background:#fff1f0; border:1px solid #ffa39e; padding:8px 15px; border-radius:8px; text-align:center; position:absolute; bottom:80px; right:30px; box-shadow: 0 10px 20px rgba(0,0,0,0.1); z-index:10;">
                            <p style="margin:0 0 10px; color:#d63638; font-weight:bold; font-size:12px;">Eliminare definitivamente?</p>
                            <button type="submit" class="button" style="background:#d63638; color:#fff; border:none; font-weight:700;" onclick="document.getElementById('gcs_admin_event_op').value='delete';">Si, elimina</button>
                            <button type="button" class="button" onclick="document.getElementById('gcs_admin_delete_confirm').style.display='none'; document.getElementById('gcs_admin_delete_trigger').style.display='inline-block';">No</button>
                        </div>
                        <button type="button" class="button button-link-delete" style="color:#d63638;" id="gcs_admin_delete_trigger" onclick="document.getElementById('gcs_admin_delete_confirm').style.display='block'; this.style.display='none';">🗑️</button>
                        <button type="submit" class="button button-primary" style="height:40px; padding:0 30px; font-weight:700;" onclick="document.getElementById('gcs_admin_event_op').value='save';">Salva Modifiche</button>
                    </div>
                </div>
            </form>
        </div></div>
        <script>
            function gcsOpenEventModal(id, title, start, end, msg, email, status) {
                document.getElementById('edit_id').value = id;
                document.getElementById('edit_title').value = title;
                document.getElementById('edit_start').value = start;
                document.getElementById('edit_end').value = end;
                document.getElementById('edit_message').value = msg;
                document.getElementById('modal_display_email').innerText = email;
                document.getElementById('modal_display_id').innerText = '#' + id;
                document.getElementById('edit_status').value = status || 'confirmed';
                
                var sourceBadge = document.getElementById('gcs_source_badge');
                var statusSector = document.getElementById('gcs_status_sector');
                
                if (email === 'manuale@calendario.local') {
                    sourceBadge.innerText = 'Impegno Manuale';
                    sourceBadge.style.background = '#fee2e2';
                    sourceBadge.style.color = '#b91c1c';
                    statusSector.style.display = 'none';
                    document.getElementById('gcs_modal_contact_info').style.display = 'none';
                } else {
                    sourceBadge.innerText = 'Richiesta Modulo';
                    sourceBadge.style.background = '#dcfce7';
                    sourceBadge.style.color = '#166534';
                    statusSector.style.display = 'block';
                    document.getElementById('gcs_modal_contact_info').style.display = 'block';
                }

                document.getElementById('gcs_admin_event_op').value = 'save';
                document.getElementById('gcs_admin_delete_confirm').style.display = 'none';
                document.getElementById('gcs_admin_delete_trigger').style.display = 'inline-block';
                document.getElementById('gcsEditModal').style.display = 'flex';
            }
            window.addEventListener('click', function(event) { 
                var modal = document.getElementById('gcsEditModal');
                if (event.target == modal) modal.style.display = 'none'; 
            });
        </script>
        <?php
    }
}
