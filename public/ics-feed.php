<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GCS_ICS_Feed {
    public static function init() {
        add_action( 'init', array( __CLASS__, 'serve_ics_feed' ) );
    }

    public static function serve_ics_feed() {
        if ( isset( $_GET['gcs_ics_feed'] ) && $_GET['gcs_ics_feed'] == '1' ) {
            
            header('Content-Type: text/calendar; charset=utf-8');
            header('Content-Disposition: attachment; filename="casascout.ics"');

            global $wpdb;
            $table_name = $wpdb->prefix . 'gcs_requests';
            $events = $wpdb->get_results("SELECT * FROM $table_name WHERE status = 'confirmed'");

            echo "BEGIN:VCALENDAR\r\n";
            echo "VERSION:2.0\r\n";
            echo "PRODID:-//Casa Scout//IT\r\n";
            echo "CALSCALE:GREGORIAN\r\n";
            echo "X-WR-CALNAME:Prenotazioni Casa Scout\r\n";

            foreach ($events as $ev) {
                $start = date('Ymd', strtotime($ev->start_date));
                // Add 1 day to end_date because iCal standard treats all-day events END as exclusive
                $end = date('Ymd', strtotime($ev->end_date . ' +1 day'));
                
                $uid = md5($ev->id . 'casascout');

                echo "BEGIN:VEVENT\r\n";
                echo "UID:{$uid}@casascout\r\n";
                echo "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
                echo "DTSTART;VALUE=DATE:{$start}\r\n";
                echo "DTEND;VALUE=DATE:{$end}\r\n";
                echo "SUMMARY:" . self::escapeString($ev->group_name) . "\r\n";
                $desc = "";
                if ($ev->guests_count > 0) {
                    $desc .= "Ospiti: " . $ev->guests_count . "\\n";
                }
                if (!empty($ev->message)) {
                    $desc .= "Nota: " . $ev->message . "\\n";
                }
                if (!empty($desc)) {
                    echo "DESCRIPTION:" . self::escapeString($desc) . "\r\n";
                }
                echo "TRANSP:OPAQUE\r\n";
                echo "END:VEVENT\r\n";
            }

            echo "END:VCALENDAR\r\n";
            exit;
        }
    }

    private static function escapeString($string) {
        return preg_replace('/([\,;])/', '\\\\$1', str_replace(array("\r\n", "\n", "\r"), '\n', $string));
    }
}
