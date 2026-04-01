<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GCS_Calendar_Page {
    public static function render_calendar_page() {
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
                echo '<div class="notice notice-success is-dismissible"><p>Impegno aggiunto con successo.</p></div>';
            }
        }

        if (isset($_POST['gcs_edit_event_action']) && wp_verify_nonce($_POST['gcs_edit_nonce'], 'edit_event_action')) {
            $id = intval($_POST['edit_id']);
            $title = sanitize_text_field($_POST['edit_title']);
            $start = sanitize_text_field($_POST['edit_start']);
            $end = sanitize_text_field($_POST['edit_end']);
            $msg = sanitize_textarea_field($_POST['edit_message']);
            
            GCS_DB_Manager::update_request($id, array(
                'group_name' => $title,
                'start_date' => $start,
                'end_date' => $end,
                'message' => $msg
            ));
            echo '<div class="notice notice-success is-dismissible"><p>Evento aggiornato correttamente.</p></div>';
        }

        if (isset($_POST['gcs_delete_event_action']) && wp_verify_nonce($_POST['gcs_edit_nonce'], 'edit_event_action')) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'gcs_requests';
            $wpdb->delete($table_name, array('id' => intval($_POST['edit_id'])));
            echo '<div class="notice notice-warning is-dismissible"><p>Evento rimosso dal calendario.</p></div>';
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'gcs_requests';
        $start_date_month = sprintf("%04d-%02d-01", $year, $month);
        $end_date_month = date("Y-m-t", strtotime($start_date_month));

        // Prendiamo gli eventi confermati
        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE status = 'confirmed' AND ( (start_date <= %s AND end_date >= %s) ) ORDER BY start_date ASC",
            $end_date_month, $start_date_month
        ));

        $days_in_month = date('t', mktime(0, 0, 0, $month, 1, $year));
        $first_day_of_month = date('w', mktime(0, 0, 0, $month, 1, $year));
        $first_day_of_month = $first_day_of_month == 0 ? 7 : $first_day_of_month;
        $months_names = array('', 'Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno','Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre');

        ?>
        <style>
            .gcs-admin-cal-event {
                cursor: pointer;
                transition: transform 0.2s, box-shadow 0.2s;
            }
            .gcs-admin-cal-event:hover {
                transform: scale(1.03);
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                z-index: 2;
                filter: brightness(1.1);
            }
            .gcs-modal {
                display: none;
                position: fixed;
                z-index: 99999;
                left: 0; top: 0; width: 100%; height: 100%;
                background: rgba(0,0,0,0.5);
                backdrop-filter: blur(3px);
                align-items: center;
                justify-content: center;
            }
            .gcs-modal-content {
                background: #fff;
                padding: 30px;
                border-radius: 12px;
                width: 450px;
                max-width: 90%;
            }
        </style>

        <div class="wrap">
            <h1 class="wp-heading-inline">Pannello Calendario Real-Time</h1>
            <hr class="wp-header-end">
            
            <div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-start; margin-top:20px;">
                <div style="flex: 1 1 60%; background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,.05);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                        <a href="?page=gcs-calendar&c_month=<?php echo $month == 1 ? 12 : $month - 1; ?>&c_year=<?php echo $month == 1 ? $year - 1 : $year; ?>" class="button">&laquo; <?php echo $months_names[$month-1 == 0 ? 12 : $month-1]; ?></a>
                        <h2 style="margin: 0; font-weight:700;"><?php echo $months_names[$month] . ' ' . $year; ?></h2>
                        <a href="?page=gcs-calendar&c_month=<?php echo $month == 12 ? 1 : $month + 1; ?>&c_year=<?php echo $month == 12 ? $year + 1 : $year; ?>" class="button"><?php echo $months_names[$month+1 == 13 ? 1 : $month+1]; ?> &raquo;</a>
                    </div>
                    
                    <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                        <thead>
                            <tr>
                                <?php foreach(array('Lun','Mar','Mer','Gio','Ven','Sab','Dom') as $d): ?>
                                    <th style="padding: 12px; border: 1px solid #eee; background: #fafafa; font-size:12px; text-transform:uppercase; color:#888;"><?php echo $d; ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <?php
                                $cell_count = 0;
                                for ($i = 1; $i < $first_day_of_month; $i++) {
                                    echo '<td style="border: 1px solid #eee; background: #fdfdfd; padding: 10px; height: 110px;"></td>';
                                    $cell_count++;
                                }

                                for ($day = 1; $day <= $days_in_month; $day++) {
                                    if ($cell_count % 7 == 0 && $cell_count != 0) echo '</tr><tr>';
                                    $current_date = sprintf("%04d-%02d-%02d", $year, $month, $day);
                                    
                                    $day_events = array();
                                    foreach ($events as $ev) if ($current_date >= $ev->start_date && $current_date <= $ev->end_date) $day_events[] = $ev;

                                    $is_today = ($current_date == date('Y-m-d')) ? 'background-color: #f0f6fc;' : '';
                                    echo '<td style="border: 1px solid #eee; padding: 5px; height: 110px; vertical-align: top; ' . $is_today . '">';
                                    echo '<span style="display:block; margin-bottom:5px; font-weight:bold; color:#444; opacity:0.6;">' . $day . '</span>';
                                    
                                    foreach ($day_events as $de) {
                                        $is_manual = ($de->contact_email == 'manuale@calendario.local');
                                        $bg = $is_manual ? '#e74c3c' : '#3498db';
                                        
                                        echo '<div class="gcs-admin-cal-event" 
                                                  onclick="gcsOpenEventModal('.$de->id.', \''.esc_js($de->group_name).'\', \''.$de->start_date.'\', \''.$de->end_date.'\', \''.esc_js(str_replace(array("\r","\n"), ' ', $de->message)).'\')"
                                                  style="background: '.$bg.'; color: #fff; padding: 4px 6px; margin-bottom: 4px; font-size: 11px; border-radius: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" 
                                                  title="Clicca per modificare">';
                                        echo esc_html($de->group_name);
                                        echo '</div>';
                                    }
                                    echo '</td>';
                                    $cell_count++;
                                }
                                while ($cell_count % 7 != 0) {
                                    echo '<td style="border: 1px solid #eee; background: #fdfdfd; padding: 10px; height: 110px;"></td>';
                                    $cell_count++;
                                }
                                ?>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div style="flex: 1 1 30%; background: #fff; border: 1px solid #ccd0d4; padding: 25px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,.05);">
                    <h3 style="margin-top:0; font-size:18px;">Aggiungi Impegno Rapido</h3>
                    <form method="POST">
                        <?php wp_nonce_field('add_manual_event', 'gcs_nonce'); ?>
                        <input type="hidden" name="gcs_add_manual_event" value="1">
                        <p><label>Titolo/Gruppo</label><input type="text" name="event_title" required class="large-text"></p>
                        <p><label>Data Inizio</label><input type="date" name="event_start" required class="large-text"></p>
                        <p><label>Data Fine</label><input type="date" name="event_end" required class="large-text"></p>
                        <button type="submit" class="button button-primary button-large" style="width:100%;">Salva su Calendario</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- MODAL MODIFICA EVENTO -->
        <div id="gcsEditModal" class="gcs-modal">
            <div class="gcs-modal-content">
                <h2 id="modal_label" style="margin-top:0;">Modifica Evento</h2>
                <form method="POST">
                    <?php wp_nonce_field('edit_event_action', 'gcs_edit_nonce'); ?>
                    <input type="hidden" name="gcs_edit_event_action" value="1">
                    <input type="hidden" name="edit_id" id="edit_id">
                    
                    <p><label>Nome Gruppo / Titolo</label><br/><input type="text" name="edit_title" id="edit_title" required class="large-text"></p>
                    <div style="display:flex; gap:10px;">
                        <p style="flex:1;"><label>Inizio</label><br/><input type="date" name="edit_start" id="edit_start" required class="large-text"></p>
                        <p style="flex:1;"><label>Fine</label><br/><input type="date" name="edit_end" id="edit_end" required class="large-text"></p>
                    </div>
                    <p><label>Note / Dettagli</label><br/><textarea name="edit_message" id="edit_message" rows="3" class="large-text"></textarea></p>
                    
                    <div style="display:flex; justify-content: space-between; margin-top:20px;">
                        <button type="button" onclick="document.getElementById('gcsEditModal').style.display='none'" class="button">Annulla</button>
                        <div>
                            <button type="submit" name="gcs_delete_event_action" class="button button-link-delete" style="color:#d63638;" onclick="return confirm('Sicuro di voler rimuovere questo evento?')">Elimina</button>
                            <button type="submit" class="button button-primary">Salva Modifiche</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <script>
            function gcsOpenEventModal(id, title, start, end, msg) {
                document.getElementById('edit_id').value = id;
                document.getElementById('edit_title').value = title;
                document.getElementById('edit_start').value = start;
                document.getElementById('edit_end').value = end;
                document.getElementById('edit_message').value = msg;
                document.getElementById('gcsEditModal').style.display = 'flex';
            }
            window.onclick = function(event) {
                var modal = document.getElementById('gcsEditModal');
                if (event.target == modal) modal.style.display = "none";
            }
        </script>
        <?php
    }
}
