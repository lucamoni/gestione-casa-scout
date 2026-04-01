<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GCS_Calendar_Shortcode {
    public static function init() {
        add_shortcode( 'gcs_calendar', array( __CLASS__, 'render_calendar' ) );
    }

    public static function render_calendar($atts) {
        $month = isset($_GET['gcs_month']) ? intval($_GET['gcs_month']) : date('n');
        $year = isset($_GET['gcs_year']) ? intval($_GET['gcs_year']) : date('Y');

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

        ob_start();
        ?>
        <style>
            .gcs-pub-calendar {
                width: 100%;
                max-width: 800px;
                margin: 0 auto 30px;
                font-family: inherit;
            }
            .gcs-pub-cal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
                background: #1a4581;
                color: #ffffff;
                padding: 15px 20px;
                border-radius: 4px;
            }
            .gcs-pub-cal-header a {
                color: #a1d1d0;
                text-decoration: none;
                font-weight: bold;
                font-size: 14px;
                text-transform: uppercase;
                transition: color 0.3s ease;
            }
            .gcs-pub-cal-header a:hover {
                color: #ffffff;
            }
            .gcs-pub-cal-header h3 {
                margin: 0;
                color: #ffffff;
                font-family: 'Martel', serif;
                font-size: 22px;
            }
            .gcs-pub-cal-table {
                width: 100%;
                border-collapse: collapse;
                background: #ffffff;
                box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            }
            .gcs-pub-cal-table th {
                padding: 10px;
                background: #f4f4f4;
                color: #333;
                border: 1px solid #eaeaea;
                text-align: center;
                font-size: 13px;
                text-transform: uppercase;
            }
            .gcs-pub-cal-table td {
                border: 1px solid #eaeaea;
                padding: 5px;
                height: 90px;
                vertical-align: top;
                width: 14.28%;
            }
            .gcs-pub-cal-day-num {
                display: block;
                font-weight: bold;
                margin-bottom: 5px;
                color: #555;
            }
            .gcs-pub-cal-today {
                background: #fdfdfd;
            }
            .gcs-pub-cal-today .gcs-pub-cal-day-num {
                display: inline-block;
                background: #1a4581;
                color: white;
                padding: 2px 6px;
                border-radius: 50%;
            }
            .gcs-pub-cal-event {
                background: #a1d1d0;
                color: #1a4581;
                padding: 5px;
                font-size: 11px;
                border-radius: 3px;
                margin-bottom: 3px;
                line-height: 1.2;
                font-weight: 600;
                overflow: hidden;
                text-overflow: ellipsis;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            }
        </style>
        
        <div class="gcs-pub-calendar">
            <div class="gcs-pub-cal-header">
                <?php 
                    $prev_m = $month == 1 ? 12 : $month - 1;
                    $prev_y = $month == 1 ? $year - 1 : $year;
                    $next_m = $month == 12 ? 1 : $month + 1;
                    $next_y = $month == 12 ? $year + 1 : $year;

                    $current_url = remove_query_arg(array('gcs_month', 'gcs_year'));
                ?>
                <a href="<?php echo esc_url(add_query_arg(array('gcs_month' => $prev_m, 'gcs_year' => $prev_y), $current_url)); ?>">&laquo; <?php echo $months_names[$prev_m]; ?></a>
                <h3><?php echo $months_names[$month] . ' ' . $year; ?></h3>
                <a href="<?php echo esc_url(add_query_arg(array('gcs_month' => $next_m, 'gcs_year' => $next_y), $current_url)); ?>"><?php echo $months_names[$next_m]; ?> &raquo;</a>
            </div>

            <table class="gcs-pub-cal-table">
                <thead>
                    <tr>
                        <?php foreach(array('Lun','Mar','Mer','Gio','Ven','Sab','Dom') as $d): ?>
                            <th><?php echo $d; ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <?php
                        $cell_count = 0;
                        for ($i = 1; $i < $first_day_of_month; $i++) {
                            echo '<td style="background: #fafafa;"></td>';
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

                            $is_today_class = ($current_date == date('Y-m-d')) ? 'gcs-pub-cal-today' : '';

                            echo '<td class="'.$is_today_class.'">';
                            echo '<span class="gcs-pub-cal-day-num">' . $day . '</span>';
                            
                            foreach ($day_events as $de) {
                                echo '<div class="gcs-pub-cal-event" title="Occupato">';
                                echo esc_html($de->group_name);
                                echo '</div>';
                            }

                            echo '</td>';
                            $cell_count++;
                        }

                        while ($cell_count % 7 != 0) {
                            echo '<td style="background: #fafafa;"></td>';
                            $cell_count++;
                        }
                        ?>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }
}
