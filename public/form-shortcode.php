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
            
            // Variabili di Stile
            $t_color = esc_attr(get_option('gcs_style_title_color', '#1a4581'));
            $t_size = esc_attr(get_option('gcs_style_title_size', '24px'));
            $l_color = esc_attr(get_option('gcs_style_label_color', '#444444'));
            $i_bg = esc_attr(get_option('gcs_style_input_bg', '#ffffff'));
            $i_border = esc_attr(get_option('gcs_style_input_border', '#cccccc'));
            $i_radius = esc_attr(get_option('gcs_style_input_radius', '6px'));
            $b_bg = esc_attr(get_option('gcs_style_btn_bg', '#1a4581'));
            $b_hover = esc_attr(get_option('gcs_style_btn_bg_hover', '#a1d1d0'));
            $b_color = esc_attr(get_option('gcs_style_btn_color', '#ffffff'));
            $b_radius = esc_attr(get_option('gcs_style_btn_radius', '20px'));
            
            // Variabili di Impaginazione
            $l_align = esc_attr(get_option('gcs_layout_title_align', 'left'));
            $l_gap = esc_attr(get_option('gcs_layout_row_gap', '8px'));
            $l_btn_align = esc_attr(get_option('gcs_layout_btn_align', 'left'));
            $custom_css = get_option('gcs_custom_css', '');
        ?>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Martel:wght@400;700&display=swap');
            
            .gcs-form-container {
                width: 100%;
                margin: 0;
                background: transparent;
                padding: 0;
                box-shadow: none;
                border: none;
                font-family: inherit;
            }
            .gcs-form-container h3 {
                margin-top: 0;
                margin-bottom: <?php echo $l_gap; ?>;
                color: <?php echo $t_color; ?>;
                font-family: 'Martel', serif;
                font-weight: 700;
                font-size: <?php echo $t_size; ?>;
                text-align: <?php echo $l_align; ?>;
            }
            .gcs-booking-form label {
                display: block;
                margin-bottom: 2px;
                font-weight: 600;
                color: <?php echo $l_color; ?>;
                font-size: 13px;
                padding-left: 2px;
            }
            .gcs-booking-form input[type="text"],
            .gcs-booking-form input[type="email"],
            .gcs-booking-form input[type="date"],
            .gcs-booking-form input[type="number"],
            .gcs-booking-form textarea {
                width: 100%;
                padding: 8px 12px;
                border: 1px solid <?php echo $i_border; ?>;
                background-color: <?php echo $i_bg; ?> !important;
                background-image: none !important;
                font-size: 14px;
                color: #333333;
                border-radius: <?php echo $i_radius; ?>;
                transition: all 0.3s ease;
                box-sizing: border-box;
                box-shadow: none !important;
            }
            .gcs-booking-form textarea {
                border-radius: <?php echo $i_radius; ?>;
                resize: vertical;
            }
            .gcs-booking-form input:focus,
            .gcs-booking-form textarea:focus {
                border-color: <?php echo $b_bg; ?>;
                background-color: <?php echo $i_bg; ?>;
                outline: none;
                box-shadow: 0 0 0 3px <?php echo $b_hover; ?>;
            }
            .gcs-form-row {
                margin-bottom: <?php echo $l_gap; ?>;
                background: transparent !important;
                border: none !important;
            }
            .gcs-form-flex {
                display: flex;
                flex-wrap: wrap;
                gap: <?php echo $l_gap; ?>;
                margin-bottom: <?php echo $l_gap; ?>;
                background: transparent !important;
                border: none !important;
            }
            .gcs-form-flex > div {
                flex: 1 1 calc(50% - 15px);
            }
            .gcs-btn-submit {
                <?php if ($l_btn_align == 'stretch'): ?>
                display: block !important;
                width: 100% !important;
                margin: <?php echo $l_gap; ?> 0 0 0 !important;
                <?php else: ?>
                display: inline-block !important;
                width: auto !important;
                min-width: 150px !important;
                margin: <?php echo $l_gap; ?> 0 0 0 !important;
                <?php endif; ?>
                padding: 10px 20px !important;
                background: <?php echo $b_bg; ?> !important;
                color: <?php echo $b_color; ?> !important;
                font-size: 14px !important;
                font-weight: 700 !important;
                text-transform: uppercase !important;
                letter-spacing: 0.5px !important;
                border: none !important;
                border-radius: <?php echo $b_radius; ?> !important;
                cursor: pointer;
                transition: transform 0.2s ease, background 0.3s ease !important;
                box-shadow: none !important;
            }
            .gcs-btn-submit:hover {
                background: <?php echo $b_hover; ?> !important;
                color: <?php echo $b_color; ?> !important;
                transform: translateY(-2px);
            }
            
            /* CSS Personalizzato Avanzato */
            <?php echo $custom_css; ?>
            
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

                <div style="margin-top: <?php echo $l_gap; ?>; text-align: <?php echo ($l_btn_align == 'stretch') ? 'left' : $l_btn_align; ?>;">
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
