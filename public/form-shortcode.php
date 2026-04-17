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
            @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
            
            .gcs-form-container {
                width: 100%;
                max-width: 650px;
                margin: 40px auto;
                background: #ffffff !important;
                padding: 40px !important;
                box-shadow: 0 20px 40px -10px rgba(0,0,0,0.1) !important;
                border: 1px solid #f1f5f9 !important;
                border-radius: 20px !important;
                font-family: 'Inter', -apple-system, sans-serif;
                position: relative;
                z-index: 10;
            }

            .gcs-form-container h3 {
                margin-top: 0;
                margin-bottom: 25px;
                color: <?php echo $t_color; ?>;
                font-weight: 800;
                font-size: <?php echo $t_size; ?>;
                text-align: <?php echo $l_align; ?>;
                letter-spacing: -0.02em;
            }

            .gcs-booking-form label {
                display: block;
                margin-bottom: 6px;
                font-weight: 700;
                color: #475569;
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }

            .gcs-booking-form input[type="text"],
            .gcs-booking-form input[type="email"],
            .gcs-booking-form input[type="date"],
            .gcs-booking-form input[type="number"],
            .gcs-booking-form textarea {
                width: 100%;
                padding: 12px 16px;
                border: 2px solid #f1f5f9;
                background-color: #f8fafc !important;
                font-size: 15px;
                font-weight: 500;
                color: #1e293b;
                border-radius: 12px;
                transition: all 0.2s;
                box-sizing: border-box;
                box-shadow: none !important;
            }

            .gcs-booking-form input:focus,
            .gcs-booking-form textarea:focus {
                border-color: <?php echo $b_bg; ?>;
                background-color: #ffffff !important;
                outline: none;
                box-shadow: 0 0 0 4px <?php echo $b_hover; ?>22 !important;
                transform: translateY(-1px);
            }

            .gcs-form-row { margin-bottom: 20px; }
            .gcs-form-flex { display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 20px; }
            .gcs-form-flex > div { flex: 1 1 calc(50% - 10px); }

            .gcs-btn-submit {
                width: <?php echo ($l_btn_align == 'stretch') ? '100%' : 'auto'; ?>;
                padding: 14px 30px !important;
                background: <?php echo $b_bg; ?> !important;
                color: <?php echo $b_color; ?> !important;
                font-size: 15px !important;
                font-weight: 800 !important;
                text-transform: uppercase !important;
                letter-spacing: 0.1em !important;
                border: none !important;
                border-radius: 12px !important;
                cursor: pointer;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
                box-shadow: 0 10px 15px -3px <?php echo $b_bg; ?>44 !important;
            }

            .gcs-btn-submit:hover {
                background: <?php echo $b_hover; ?> !important;
                box-shadow: 0 20px 25px -5px <?php echo $b_bg; ?>66 !important;
                transform: translateY(-2px);
            }
            
            .gcs-success-message {
                background: #f0fdf4;
                color: #166534;
                padding: 20px;
                border-radius: 12px;
                margin-bottom: 30px;
                font-weight: 700;
                text-align: center;
                border: 1px solid #dcfce7;
            }
        </style>

        <div class="gcs-form-container">
            <?php if (!empty($title)) : ?>
                <h3><?php echo esc_html($title); ?></h3>
            <?php endif; ?>
            
            <?php if ( isset( $_GET['gcs_success'] ) && $_GET['gcs_success'] == 1 ) : ?>
                <div id="gcs-success-modal" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:999999; display:flex; align-items:center; justify-content:center; backdrop-filter:blur(3px);">
                    <div style="background:#ffffff !important; opacity:1 !important; padding:50px 40px; border-radius:16px; text-align:center; box-shadow:0 30px 60px rgba(0,0,0,0.5); max-width:90%; width:440px; border: 3px solid <?php echo $b_bg; ?>;">
                        <div style="font-size:70px; color:<?php echo $b_bg; ?>; margin-bottom:20px; opacity:1;">✓</div>
                        <h2 style="margin:0 0 15px 0; color:#000000; font-size:32px; font-weight:900; opacity:1;">Richiesta Inviata!</h2>
                        <p style="color:#000000; margin-bottom:35px; line-height:1.6; font-size:18px; font-weight:600; opacity:1;">La tua richiesta è stata registrata correttamente.<br/>Ti risponderemo via email il prima possibile.</p>
                        <button onclick="document.getElementById('gcs-success-modal').style.display='none'" style="background:<?php echo $b_bg; ?>; color:<?php echo $b_color; ?>; border:none; padding:15px 45px; border-radius:<?php echo $b_radius; ?>; font-weight:900; cursor:pointer; font-size:16px; text-transform:uppercase; letter-spacing:1px; box-shadow: 0 4px 10px rgba(0,0,0,0.2);">CHIUDI E TORNA AL SITO</button>
                    </div>
                </div>
                <script>
                    if (window.history.replaceState) {
                        var cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
                        window.history.replaceState({path:cleanUrl}, '', cleanUrl);
                    }
                </script>
            <?php endif; ?>
            
            <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="POST" class="gcs-booking-form">
                <input type="hidden" name="action" value="gcs_submit_form">
                <?php wp_nonce_field( 'gcs_verify_form', 'gcs_nonce' ); ?>
                
                <div class="gcs-form-row">
                    <label for="group_name">Nome Gruppo / Reparto *</label>
                    <input type="text" id="group_name" name="group_name" required placeholder="Es. Prato 6 - Reparto Eirene Brownsea">
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

        <!-- SCRIPT NUCLEARE: Disintegra righe, sfondi a tema e <br> spuri di WPBakery -->
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                var containers = document.querySelectorAll('.gcs-form-container');
                containers.forEach(function(container) {
                    /* 1. Elimina i famigerati br del wpautop che sfalsavano le altezze */
                    var brs = container.querySelectorAll('br');
                    brs.forEach(function(br) { br.remove(); });
                    
                    /* 2. Rimuove o ripulisce i paragrafi vuoti usati come distanziatori */
                    var ps = container.querySelectorAll('p');
                    ps.forEach(function(p) {
                        if(p.innerHTML.trim() === '' || p.innerHTML.trim() === '&nbsp;') {
                            p.remove();
                        } else {
                            p.style.setProperty('margin', '0', 'important');
                            p.style.setProperty('padding', '0', 'important');
                            p.style.setProperty('background', 'transparent', 'important');
                        }
                    });

                    /* 3. Stronca le righe orizzontali alternate del tema resettando gli sfondi interni */
                    var internals = container.querySelectorAll('form, div, span, label');
                    internals.forEach(function(el) {
                        el.style.setProperty('background-image', 'none', 'important');
                        el.style.setProperty('background-color', 'transparent', 'important');
                    });
                    
                    /* 4. FIX ULTIMATE: Se l'utente ha incollato lo shortcode dalla chat, WP lo avvolge in un pre o code a righe! */
                    var parent = container.parentElement;
                    while(parent && parent.tagName !== 'BODY') {
                        if(parent.tagName === 'PRE' || parent.tagName === 'CODE' || parent.classList.contains('wpb_wrapper')) {
                            parent.style.setProperty('background-image', 'none', 'important');
                            parent.style.setProperty('background-color', 'transparent', 'important');
                            parent.style.setProperty('border', 'none', 'important');
                            parent.style.setProperty('box-shadow', 'none', 'important');
                        }
                        parent = parent.parentElement;
                    }
                });
            });
        </script>

        <?php
        $form_html = ob_get_clean();
        
        // TRUCCO DEFINITIVO CONTRO WPAUTOP DI WPBAKERY
        // WPBakery prende gli a-capo del nostro codice PHP e ci inietta <br> o <p>, causando spazi esagerati.
        // Togliendo tutti gli a-capo (\n \r) dall'HTML renderizzato, scavalchiamo il problema per sempre.
        $form_html = str_replace(array("\r", "\n", "\t"), '', $form_html);

        return $form_html;
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
        $subject = 'Nuova richiesta per Casa Scout - Canneto: ' . $group_name;
        $body = "Hai ricevuto una nuova richiesta di prenotazione:\n\n" .
                "Gruppo/Reparto: $group_name\n" .
                "Email di contatto: $contact_email\n" .
                "Dal: $start_date\n" .
                "Al: $end_date\n" .
                "Numero persone: $guests_count\n\n" .
                "Messaggio:\n$message\n\n" .
                "Accedi alla Dashboard di WordPress per gestire o rispondere alla richiesta.";
        
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $group_name . ' <' . $contact_email . '>',
            'Reply-To: ' . $contact_email
        );
        
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
