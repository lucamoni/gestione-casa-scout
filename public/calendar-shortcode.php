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
                :root {
                    --gcs-primary: #2d5a27;
                    --gcs-primary-dark: #1b3a18;
                    --gcs-accent: #d4a373;
                    --gcs-bg: #f8fafc;
                    --gcs-card: #ffffff;
                    --gcs-text: #1e293b;
                    --gcs-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
                }

                .gcs-calendar-container {
                    font-family: 'Inter', -apple-system, sans-serif;
                    background: var(--gcs-card);
                    border-radius: 16px;
                    box-shadow: var(--gcs-shadow);
                    overflow: hidden;
                    border: 1px solid #e2e8f0;
                }

                .gcs-pub-cal-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    background: var(--gcs-primary);
                    color: #fff;
                    padding: 20px 25px;
                }

                .gcs-pub-cal-header h3 {
                    margin: 0;
                    color: #fff;
                    font-size: 22px;
                    font-weight: 800;
                    letter-spacing: -0.02em;
                }

                .gcs-ajax-cal-nav {
                    background: rgba(255,255,255,0.15);
                    color: #fff !important;
                    text-decoration: none !important;
                    padding: 8px 15px;
                    border-radius: 10px;
                    font-size: 13px;
                    font-weight: 700;
                    transition: all 0.2s;
                    cursor: pointer;
                    backdrop-filter: blur(5px);
                }

                .gcs-ajax-cal-nav:hover {
                    background: rgba(255,255,255,0.25);
                    transform: translateY(-1px);
                }

                .gcs-pub-cal-table {
                    width: 100%;
                    border-collapse: collapse;
                    table-layout: fixed;
                }

                .gcs-pub-cal-table th {
                    background: #f8fafc;
                    padding: 12px;
                    font-size: 11px;
                    font-weight: 800;
                    color: #64748b;
                    text-transform: uppercase;
                    border-bottom: 1px solid #e2e8f0;
                }

                .gcs-pub-cal-table td {
                    height: 110px;
                    vertical-align: top;
                    padding: 8px;
                    border: 1px solid #f1f5f9;
                    position: relative;
                }

                .gcs-pub-cal-day-num {
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    margin-bottom: 8px;
                    font-weight: 700;
                    color: #94a3b8;
                    font-size: 13px;
                }

                .gcs-pub-cal-today {
                    background: #f0fdf4;
                }

                .gcs-pub-cal-today .gcs-pub-cal-day-num-inner {
                    background: var(--gcs-primary);
                    color: #fff;
                    padding: 2px 6px;
                    border-radius: 6px;
                }

                .gcs-pub-cal-event {
                    background: #3498db;
                    color: #fff;
                    padding: 4px 8px;
                    font-size: 10px;
                    font-weight: 700;
                    border-radius: 6px;
                    margin-bottom: 3px;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                    position: relative;
                    z-index: 1;
                    width: calc(100% + 1px);
                }

                .gcs-pub-cal-event.cont-prev {
                    border-top-left-radius: 0;
                    border-bottom-left-radius: 0;
                    margin-left: -9px;
                    width: calc(100% + 10px);
                }

                .gcs-pub-cal-event.cont-next {
                    border-top-right-radius: 0;
                    border-bottom-right-radius: 0;
                    margin-right: -9px;
                    width: calc(100% + 10px);
                }

                .gcs-pub-cal-event.event-hidden-text {
                    color: transparent;
                }

                #gcs-calendar-ajax-wrapper { min-height: 400px; }
            </style>
            
            <div class="gcs-calendar-container" style="max-width: 800px; margin: 30px auto; font-family: inherit; background: #ffffff; padding: 20px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); position: relative; z-index: 10;">
                <div id="gcs-calendar-inner">
                    <?php echo self::generate_calendar_html($month, $year); ?>
                </div>
            </div>
            
            <script>
                (function() {
                    var wrapper = document.getElementById('gcs-calendar-ajax-wrapper');
                    if (!wrapper) return;
                    
                    wrapper.addEventListener('click', function(e) {
                        if (e.target && e.target.classList.contains('gcs-ajax-cal-nav')) {
                            e.preventDefault();
                            var m = e.target.getAttribute('data-m');
                            var y = e.target.getAttribute('data-y');
                            var inner = document.getElementById('gcs-calendar-inner');
                            inner.style.opacity = '0.4';
                            
                            var params = new URLSearchParams();
                            params.append('action', 'gcs_load_calendar');
                            params.append('month', m);
                            params.append('year', y);
                            
                            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                },
                                body: params
                            })
                            .then(response => {
                                if (!response.ok) throw new Error('Network fault');
                                return response.text();
                            })
                            .then(data => {
                                if (data == '0') throw new Error('WP Ajax rejected action');
                                inner.innerHTML = data;
                                inner.style.opacity = '1';
                                if (typeof window.gcsCleanUpThemeStripesAjax === 'function') {
                                    window.gcsCleanUpThemeStripesAjax();
                                }
                            })
                            .catch(error => {
                                console.error('Errore Calendario:', error);
                                inner.style.opacity = '1';
                                alert('Errore di connessione. Ricarica la pagina.');
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
                    };
                    window.gcsCleanUpThemeStripesAjax();
                })();
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
                            echo '<div class="gcs-pub-cal-day-num"><span class="gcs-pub-cal-day-num-inner">' . $day . '</span></div>';
                            
                            foreach ($day_events as $de) {
                                $is_start = ($current_date == $de->start_date);
                                $is_end = ($current_date == $de->end_date);
                                $is_monday = ($cell_count % 7 == 0);
                                
                                $classes = array('gcs-pub-cal-event');
                                if (!$is_start) $classes[] = 'cont-prev';
                                if (!$is_end) $classes[] = 'cont-next';
                                
                                // Mostriamo il testo solo al primo giorno dell'impegno
                                // OPPURE all'inizio di ogni settimana (Lunedì)
                                // OPPURE al primo giorno del mese visibile
                                $show_text = ($is_start || $is_monday || ($day == 1));
                                if (!$show_text) $classes[] = 'event-hidden-text';

                                echo '<div class="' . implode(' ', $classes) . '" title="Occupato dal ' . date('d/m', strtotime($de->start_date)) . ' al ' . date('d/m', strtotime($de->end_date)) . '">';
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
