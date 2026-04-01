<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GCS_Calendar_Shortcode {
    public static function init() {
        add_shortcode( 'gcs_calendar', array( __CLASS__, 'render_calendar' ) );
        add_action( 'wp_ajax_gcs_load_calendar', array( __CLASS__, 'ajax_load_calendar' ) );
        add_action( 'wp_ajax_nopriv_gcs_load_calendar', array( __CLASS__, 'ajax_load_calendar' ) );
    }

    public static function ajax_load_calendar() {
        $month = isset($_POST['month']) ? intval($_POST['month']) : date('n');
        $year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');
        
        echo self::generate_calendar_html($month, $year);
        wp_die();
    }

    public static function render_calendar($atts) {
        $month = isset($_GET['gcs_month']) ? intval($_GET['gcs_month']) : date('n');
        $year = isset($_GET['gcs_year']) ? intval($_GET['gcs_year']) : date('Y');

        ob_start();
        ?>
        <div class="gcs-calendar-wrapper" id="gcs-calendar-ajax-wrapper" style="position:relative;">
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
                    cursor: pointer;
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
                    background: transparent !important;
                }
                .gcs-pub-cal-table th, .gcs-pub-cal-table td {
                    border: 1px solid #f0f0f0;
                    text-align: center;
                    vertical-align: top;
                    padding: 8px;
                    background-color: transparent !important;
                    background-image: none !important;
                }
                .gcs-pub-cal-table th {
                    padding: 10px;
                    background: #f4f4f4 !important;
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
                #gcs-calendar-inner {
                    transition: opacity 0.3s ease;
                }
            </style>
            
            <div class="gcs-calendar-container" style="max-width: 800px; margin: 30px auto; font-family: inherit; background: #ffffff; padding: 20px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); position: relative; z-index: 10;">
                <div id="gcs-calendar-inner">
                    <?php echo self::generate_calendar_html($month, $year); ?>
                </div>
            </div>
            
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    var wrapper = document.getElementById('gcs-calendar-ajax-wrapper');
                    if (!wrapper) return;
                    
                    wrapper.addEventListener('click', function(e) {
                        if (e.target && e.target.classList.contains('gcs-ajax-cal-nav')) {
                            e.preventDefault();
                            var m = e.target.getAttribute('data-m');
                            var y = e.target.getAttribute('data-y');
                            var inner = document.getElementById('gcs-calendar-inner');
                            inner.style.opacity = '0.4';
                            
                            var formData = new FormData();
                            formData.append('action', 'gcs_load_calendar');
                            formData.append('month', m);
                            formData.append('year', y);
                            
                            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.text())
                            .then(data => {
                                inner.innerHTML = data;
                                inner.style.opacity = '1';
                                gcsCleanUpThemeStripesAjax();
                            })
                            .catch(error => {
                                console.error('Errore:', error);
                                inner.style.opacity = '1';
                            });
                        }
                    });
                    
                    window.gcsCleanUpThemeStripesAjax = function() {
                        var cals = document.querySelectorAll('.gcs-calendar-container');
                        cals.forEach(function(container) {
                            var parent = container.parentElement;
                            while(parent && parent.tagName !== 'BODY') {
                                if(parent.tagName === 'PRE' || parent.tagName === 'CODE' || parent.classList.contains('wpb_wrapper')) {
                                    parent.style.setProperty('background-image', 'none', 'important');
                                    parent.style.setProperty('background-color', 'transparent', 'important');
                                    parent.style.setProperty('border', 'none', 'important');
                                }
                                parent = parent.parentElement;
                            }
                            var trs = container.querySelectorAll('tr, th, td, table, tbody, thead');
                            trs.forEach(function(el) {
                                el.style.setProperty('background-image', 'none', 'important');
                                el.style.setProperty('background-color', 'transparent', 'important');
                            });
                        });
                    }
                    gcsCleanUpThemeStripesAjax();
                });
            </script>
        </div>
        <?php
        $cal_wrapper_html = ob_get_clean();
        $cal_wrapper_html = str_replace(array("\r", "\n", "\t"), '', $cal_wrapper_html);
        return $cal_wrapper_html;
    }

    public static function generate_calendar_html($month, $year) {
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
        <div class="gcs-pub-calendar">
            <div class="gcs-pub-cal-header">
                <?php 
                    $prev_m = $month == 1 ? 12 : $month - 1;
                    $prev_y = $month == 1 ? $year - 1 : $year;
                    $next_m = $month == 12 ? 1 : $month + 1;
                    $next_y = $month == 12 ? $year + 1 : $year;
                ?>
                <a class="gcs-ajax-cal-nav" data-m="<?php echo $prev_m; ?>" data-y="<?php echo $prev_y; ?>">&laquo; <?php echo $months_names[$prev_m]; ?></a>
                <h3><?php echo $months_names[$month] . ' ' . $year; ?></h3>
                <a class="gcs-ajax-cal-nav" data-m="<?php echo $next_m; ?>" data-y="<?php echo $next_y; ?>"><?php echo $months_names[$next_m]; ?> &raquo;</a>
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
        $cal_html = ob_get_clean();
        return $cal_html;
    }
}
