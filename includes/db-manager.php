<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GCS_DB_Manager {
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gcs_requests';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            group_name varchar(255) NOT NULL,
            contact_email varchar(255) NOT NULL,
            start_date date NOT NULL,
            end_date date NOT NULL,
            guests_count int NOT NULL,
            message text NOT NULL,
            status varchar(50) DEFAULT 'pending' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    public static function insert_request( $data ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gcs_requests';
        return $wpdb->insert( $table_name, $data );
    }

    public static function get_requests() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gcs_requests';
        return $wpdb->get_results( "SELECT * FROM $table_name ORDER BY start_date ASC" );
    }

    public static function update_status( $id, $status ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gcs_requests';
        return $wpdb->update(
            $table_name,
            array( 'status' => $status ),
            array( 'id' => $id ),
            array( '%s' ),
            array( '%d' )
        );
    }
}
