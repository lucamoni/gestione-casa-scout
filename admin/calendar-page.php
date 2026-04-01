<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GCS_Calendar_Page {
    public static function render_calendar_page() {
        $month = isset($_GET['c_month']) ? intval($_GET['c_month']) : date('n');
        $year = isset($_GET['c_year']) ? intval($_GET['c_year']) : date('Y');

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
                echo '<div class="notice notice-success is-dismissible"><p>Impegno aggiunto sul calendario confermato.</p></div>';
            }
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'gcs_requests';
        
        $start_date_month = sprintf("%04d-%02d-01", $year, $month);
        $end_date_month = date("Y-m-t", strtotime($start_date_month));

        // Get confirmed requests overlapping this month
        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE status = 'confirmed' AND ( (start_date <= %s AND end_date >= %s) ) ORDER BY start_date ASC",
            $end_date_month, $start_date_month
        ));

        $days_in_month = date('t', mktime(0, 0, 0, $month, 1, $year));
        $first_day_of_month = date('w', mktime(0, 0, 0, $month, 1, $year));
        $first_day_of_month = $first_day_of_month == 0 ? 7 : $first_day_of_month;

        $months_names = array('', 'Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno','Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre');

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Calendario Occupazione Casa Scout</h1>
            <hr class="wp-header-end">
            
            <div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-start; margin-top:20px;">
                <!-- Calendario Grid -->
                <div style="flex: 1 1 60%; background: #fff; border: 1px solid #ccd0d4; padding: 15px; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <a href="?page=gcs-calendar&c_month=<?php echo $month == 1 ? 12 : $month - 1; ?>&c_year=<?php echo $month == 1 ? $year - 1 : $year; ?>" class="button">&laquo; <?php echo $month == 1 ? $months_names[12] : $months_names[$month-1]; ?></a>
                        <h2 style="margin: 0; font-size:22px;"><?php echo $months_names[$month] . ' ' . $year; ?></h2>
                        <a href="?page=gcs-calendar&c_month=<?php echo $month == 12 ? 1 : $month + 1; ?>&c_year=<?php echo $month == 12 ? $year + 1 : $year; ?>" class="button"><?php echo $month == 12 ? $months_names[1] : $months_names[$month+1]; ?> &raquo;</a>
                    </div>
                    
                    <table style="width: 100%; border-collapse: collapse; text-align: left; table-layout: fixed;">
                        <thead>
                            <tr>
                                <?php foreach(array('Lun','Mar','Mer','Gio','Ven','Sab','Dom') as $d): ?>
                                    <th style="padding: 10px; border: 1px solid #ddd; background: #f9f9f9; text-align: center; color: #555;"><?php echo $d; ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <?php
                                $cell_count = 0;
                                for ($i = 1; $i < $first_day_of_month; $i++) {
                                    echo '<td style="border: 1px solid #ddd; background: #fcfcfc; padding: 10px; height: 110px;"></td>';
                                    $cell_count++;
                                }

                                for ($day = 1; $day <= $days_in_month; $day++) {
                                    if ($cell_count % 7 == 0 && $cell_count != 0) {
                                        echo '</tr><tr>';
                                    }

                                    $current_date = sprintf("%04d-%02d-%02d", $year, $month, $day);
                                    
                                    $day_events = array();
                                    foreach ($events as $ev) {
                                        if ($current_date >= $ev->start_date && $current_date <= $ev->end_date) {
                                            $day_events[] = $ev;
                                        }
                                    }

                                    $is_today = ($current_date == date('Y-m-d')) ? 'background-color: #f0f6fc;' : '';

                                    echo '<td style="border: 1px solid #ddd; padding: 5px; height: 110px; vertical-align: top; position: relative; ' . $is_today . '">';
                                    
                                    if ($current_date == date('Y-m-d')) {
                                        echo '<strong style="display: inline-block; background: #2271b1; color: white; padding: 2px 6px; border-radius: 50%; margin-bottom: 5px;">' . $day . '</strong>';
                                    } else {
                                        echo '<strong style="display: block; margin-bottom: 5px; color: #444;">' . $day . '</strong>';
                                    }
                                    
                                    foreach ($day_events as $de) {
                                        $is_manual = ($de->contact_email == 'manuale@calendario.local');
                                        $bg = $is_manual ? '#d63638' : '#0073aa'; // Rosso per manuali, Blu per form
                                        echo '<div style="background: '.$bg.'; color: #fff; padding: 3px 5px; margin-bottom: 3px; font-size: 11px; border-radius: 3px; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; cursor: default;" title="Da: ' . date('d/m/Y', strtotime($de->start_date)) . ' A: ' . date('d/m/Y', strtotime($de->end_date)) . '">';
                                        echo esc_html($de->group_name);
                                        echo '</div>';
                                    }

                                    echo '</td>';
                                    $cell_count++;
                                }

                                while ($cell_count % 7 != 0) {
                                    echo '<td style="border: 1px solid #ddd; background: #fcfcfc; padding: 10px; height: 110px;"></td>';
                                    $cell_count++;
                                }
                                ?>
                            </tr>
                        </tbody>
                    </table>
                    <div style="margin-top:15px; font-size:12px; color:#666;">
                        <span style="display:inline-block; width:12px; height:12px; background:#0073aa; border-radius:2px; vertical-align:middle; margin-right:5px;"></span> Prenotazioni Ricevute
                        <span style="display:inline-block; width:12px; height:12px; background:#d63638; border-radius:2px; vertical-align:middle; margin-right:5px; margin-left:15px;"></span> Impegni Manuali
                    </div>
                </div>

                <!-- Aggiungi Impegno Manuale -->
                <div style="flex: 1 1 30%; background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <h3 style="margin-top:0;">Occupa la casa (Impegno Manuale)</h3>
                    <p style="font-size:13px; color:#666;">Segna dei giorni in cui la casa è occupata e inaccessibile (es. Eventi di zona, manutenzioni, un gruppo esterno, ecc).</p>
                    
                    <form method="POST" action="">
                        <?php wp_nonce_field('add_manual_event', 'gcs_nonce'); ?>
                        <input type="hidden" name="gcs_add_manual_event" value="1">
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display:block; margin-bottom:4px; font-weight:bold;">Motivo / Nome Gruppo</label>
                            <input type="text" name="event_title" required class="regular-text" style="width:100%;" placeholder="Es. Lavori tetto">
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display:block; margin-bottom:4px; font-weight:bold;">Data Inizio</label>
                            <input type="date" name="event_start" required style="width:100%; border: 1px solid #8c8f94; border-radius: 4px; padding: 0 8px; line-height: 2; min-height: 30px;">
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <label style="display:block; margin-bottom:4px; font-weight:bold;">Data Fine (Inclusa)</label>
                            <input type="date" name="event_end" required style="width:100%; border: 1px solid #8c8f94; border-radius: 4px; padding: 0 8px; line-height: 2; min-height: 30px;">
                        </div>
                        
                        <button type="submit" class="button button-primary" style="width:100%; text-align:center;">Blocca Date sul Calendario</button>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
}
