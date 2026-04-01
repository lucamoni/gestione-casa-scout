<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GCS_Form_Shortcode {
    public static function init() {
        add_shortcode( 'gcs_booking_form', array( __CLASS__, 'render_form' ) );
        add_action( 'admin_post_nopriv_gcs_submit_form', array( __CLASS__, 'handle_form_submission' ) );
        add_action( 'admin_post_gcs_submit_form', array( __CLASS__, 'handle_form_submission' ) );
    }

    public static function render_form() {
        ob_start();
        ?>
        <?php 
            $title = get_option('gcs_form_title', 'Invia una Richiesta di Prenotazione'); 
            $show_guests = get_option('gcs_show_guests_field', 1);
            $show_message = get_option('gcs_show_message_field', 1);
        ?>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Martel:wght@400;700&display=swap');
            
            .gcs-form-container {
                max-width: 650px;
                margin: 20px auto;
                background: #ffffff;
                padding: 30px;
                border-radius: 20px;
                box-shadow: 0 15px 35px rgba(0,0,0,0.08);
                font-family: inherit;
            }
            .gcs-form-container h3 {
                margin-top: 0;
                margin-bottom: 20px;
                color: #1a4581;
                font-family: 'Martel', serif;
                font-weight: 700;
                font-size: 26px;
                text-align: center;
            }
            .gcs-booking-form label {
                display: block;
                margin-bottom: 5px;
                font-weight: 600;
                color: #444444;
                font-size: 13px;
                padding-left: 5px;
            }
            .gcs-booking-form input[type="text"],
            .gcs-booking-form input[type="email"],
            .gcs-booking-form input[type="date"],
            .gcs-booking-form input[type="number"],
            .gcs-booking-form textarea {
                width: 100%;
                padding: 12px 18px;
                border: 1px solid #e0e0e0;
                background-color: #fafafa;
                font-size: 14px;
                color: #333333;
                border-radius: 12px;
                transition: all 0.3s ease;
                box-sizing: border-box;
            }
            .gcs-booking-form textarea {
                border-radius: 16px;
                resize: vertical;
            }
            .gcs-booking-form input:focus,
            .gcs-booking-form textarea:focus {
                border-color: #a1d1d0;
                background-color: #ffffff;
                outline: none;
                box-shadow: 0 0 0 4px rgba(161, 209, 208, 0.2);
            }
            .gcs-form-row {
                margin-bottom: 15px;
            }
            .gcs-form-flex {
                display: flex;
                flex-wrap: wrap;
                gap: 15px;
                margin-bottom: 15px;
            }
            .gcs-form-flex > div {
                flex: 1 1 calc(50% - 15px);
            }
            .gcs-btn-submit {
                width: 100%;
                padding: 14px 20px;
                background: #1a4581;
                color: #ffffff;
                font-size: 15px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 1px;
                border: none;
                border-radius: 30px;
                cursor: pointer;
                transition: transform 0.2s ease, background 0.3s ease, box-shadow 0.3s ease;
                box-shadow: 0 4px 15px rgba(26, 69, 129, 0.3);
            }
            .gcs-btn-submit:hover {
                background: #a1d1d0;
                color: #1a4581;
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(161, 209, 208, 0.4);
            }
            .gcs-success-message {
                background: rgba(161, 209, 208, 0.2);
                color: #1a4581;
                padding: 15px 20px;
                border-radius: 12px;
                margin-bottom: 25px;
                font-weight: 600;
                text-align: center;
                border: 1px solid rgba(161, 209, 208, 0.4);
            }
        </style>

        <div class="gcs-form-container">
            <?php if (!empty($title)) : ?>
                <h3><?php echo esc_html($title); ?></h3>
            <?php endif; ?>
            
            <?php if ( isset( $_GET['gcs_success'] ) && $_GET['gcs_success'] == 1 ) : ?>
                <div class="gcs-success-message">
                    La tua richiesta è stata inviata con successo.<br/>Ti contatteremo al più presto!
                </div>
            <?php endif; ?>
            
            <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="POST" class="gcs-booking-form">
                <input type="hidden" name="action" value="gcs_submit_form">
                <?php wp_nonce_field( 'gcs_verify_form', 'gcs_nonce' ); ?>
                
                <div class="gcs-form-row">
                    <label for="group_name">Nome Gruppo / Reparto *</label>
                    <input type="text" id="group_name" name="group_name" required placeholder="Es. Piacenza 1 - Reparto Croce del Sud">
                </div>
                
                <div class="gcs-form-row">
                    <label for="contact_email">Email di contatto *</label>
                    <input type="email" id="contact_email" name="contact_email" required placeholder="iltuonome@email.it">
                </div>
                
                <div class="gcs-form-flex">
                    <div>
                        <label for="start_date">Data di arrivo *</label>
                        <input type="date" id="start_date" name="start_date" required>
                    </div>
                    <div>
                        <label for="end_date">Data di partenza *</label>
                        <input type="date" id="end_date" name="end_date" required>
                    </div>
                </div>

                <?php if ($show_guests) : ?>
                <div class="gcs-form-row">
                    <label for="guests_count">Numero approssimativo di persone *</label>
                    <input type="number" id="guests_count" name="guests_count" min="1" required placeholder="Es. 25">
                </div>
                <?php endif; ?>

                <?php if ($show_message) : ?>
                <div class="gcs-form-row">
                    <label for="message">Messaggio / Note aggiuntive</label>
                    <textarea id="message" name="message" rows="5" placeholder="Scrivi qui se hai esigenze particolari..."></textarea>
                </div>
                <?php endif; ?>

                <div style="margin-top: 30px;">
                    <button type="submit" class="gcs-btn-submit">
                        Invia la Richiesta
                    </button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function handle_form_submission() {
        if ( ! isset( $_POST['gcs_nonce'] ) || ! wp_verify_nonce( $_POST['gcs_nonce'], 'gcs_verify_form' ) ) {
            wp_die( 'Accesso non autorizzato o link scaduto.' );
        }

        $group_name    = sanitize_text_field( wp_unslash( $_POST['group_name'] ?? '' ) );
        $contact_email = sanitize_email( wp_unslash( $_POST['contact_email'] ?? '' ) );
        $start_date    = sanitize_text_field( wp_unslash( $_POST['start_date'] ?? '' ) );
        $end_date      = sanitize_text_field( wp_unslash( $_POST['end_date'] ?? '' ) );
        $guests_count  = intval( $_POST['guests_count'] ?? 0 );
        $message       = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );

        if ( empty( $group_name ) || empty( $contact_email ) || empty( $start_date ) || empty( $end_date ) ) {
            wp_die( 'I campi obbligatori come Nome, Mail e Date devono essere compilati.' );
        }

        $data = array(
            'group_name'    => $group_name,
            'contact_email' => $contact_email,
            'start_date'    => $start_date,
            'end_date'      => $end_date,
            'guests_count'  => $guests_count,
            'message'       => $message,
            'status'        => 'pending' // pending, confirmed, rejected
        );

        GCS_DB_Manager::insert_request( $data );

        // Invia email di notifica all'indirizzo configurato o amministratore
        $admin_email = get_option( 'gcs_notification_email', get_option( 'admin_email' ) );
        $subject = 'Nuova richiesta per Casa Scout: ' . $group_name;
        $body = "Hai ricevuto una nuova richiesta di prenotazione:\n\n" .
                "Gruppo/Reparto: $group_name\n" .
                "Email di contatto: $contact_email\n" .
                "Dal: $start_date\n" .
                "Al: $end_date\n" .
                "Numero persone: $guests_count\n\n" .
                "Messaggio:\n$message\n\n" .
                "Accedi alla Dashboard di WordPress per gestire o rispondere alla richiesta.";
        
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        
        wp_mail( $admin_email, $subject, $body, $headers );

        // Redirect alla pagina con messaggio di successo
        $referer = wp_get_referer();
        if ( ! $referer ) {
            $referer = home_url();
        }
        
        $redirect_url = add_query_arg( 'gcs_success', '1', sanitize_url($referer) );
        wp_safe_redirect( $redirect_url );
        exit;
    }
}
