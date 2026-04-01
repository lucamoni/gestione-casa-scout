<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GCS_Settings_Page {
    public static function init() {
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
    }

    public static function register_settings() {
        register_setting( 'gcs_settings_group', 'gcs_notification_email' );
        register_setting( 'gcs_settings_group', 'gcs_form_title' );
        register_setting( 'gcs_settings_group', 'gcs_show_guests_field' );
        register_setting( 'gcs_settings_group', 'gcs_show_message_field' );
        
        // Impostazioni Webmail PostfixAdmin / Roundcube
        register_setting( 'gcs_settings_group', 'gcs_webmail_url' );
        register_setting( 'gcs_settings_group', 'gcs_webmail_user' );
        register_setting( 'gcs_settings_group', 'gcs_webmail_pass' );
        
        // Stili Dinamici Form
        register_setting( 'gcs_settings_group', 'gcs_style_title_color' );
        register_setting( 'gcs_settings_group', 'gcs_style_title_size' );
        register_setting( 'gcs_settings_group', 'gcs_style_label_color' );
        register_setting( 'gcs_settings_group', 'gcs_style_input_bg' );
        register_setting( 'gcs_settings_group', 'gcs_style_input_border' );
        register_setting( 'gcs_settings_group', 'gcs_style_input_radius' );
        register_setting( 'gcs_settings_group', 'gcs_style_btn_bg' );
        register_setting( 'gcs_settings_group', 'gcs_style_btn_color' );
        register_setting( 'gcs_settings_group', 'gcs_style_btn_radius' );
        register_setting( 'gcs_settings_group', 'gcs_style_btn_bg_hover' );
        
        // Impaginazione e Layout
        register_setting( 'gcs_settings_group', 'gcs_layout_title_align' );
        register_setting( 'gcs_settings_group', 'gcs_layout_row_gap' );
        register_setting( 'gcs_settings_group', 'gcs_layout_btn_align' );
        register_setting( 'gcs_settings_group', 'gcs_custom_css' );
    }

    public static function render_settings_page() {
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Gestione Form e Impostazioni</h1>
            <hr class="wp-header-end">
            
            <form method="post" action="options.php" style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:4px; max-width:800px; margin-top:20px;">
                <?php settings_fields( 'gcs_settings_group' ); ?>
                <?php do_settings_sections( 'gcs_settings_group' ); ?>
                
                <h3 style="margin-top:0;">Configurazione Notifiche</h3>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Email per ricevere le richieste</th>
                        <td>
                            <input type="email" name="gcs_notification_email" value="<?php echo esc_attr( get_option('gcs_notification_email', get_option('admin_email')) ); ?>" class="regular-text" />
                            <p class="description">Inserisci l'indirizzo a cui vuoi che arrivino le notifiche. Default: email amministratore del sito.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">URL Accesso Webmail</th>
                        <td>
                            <input type="url" name="gcs_webmail_url" value="<?php echo esc_attr( get_option('gcs_webmail_url', 'http://mail.assdonrenato.it') ); ?>" class="large-text" />
                            <p class="description">Link alla pagina di login (es. http://mail.assdonrenato.it). Servirà al pulsante "Rispondi da Webmail".</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Utente Webmail</th>
                        <td>
                            <input type="text" name="gcs_webmail_user" value="<?php echo esc_attr( get_option('gcs_webmail_user', '') ); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Password Webmail</th>
                        <td>
                            <input type="password" name="gcs_webmail_pass" value="<?php echo esc_attr( get_option('gcs_webmail_pass', '') ); ?>" class="regular-text" />
                            <p class="description">Sconsigliamo l'uso di password sensibili se il sito è condiviso con altri amministratori. I bottoni useranno questi dati per auto-compliare il login.</p>
                        </td>
                    </tr>
                </table>
                <hr>
                
                <h3>Personalizzazione Form Pubblico</h3>
                <p class="description">I campi Nome Gruppo, Email, Data Inizio e Data Fine sono sempre obbligatori per il corretto funzionamento organizzativo.</p>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Titolo del Form</th>
                        <td>
                            <input type="text" name="gcs_form_title" value="<?php echo esc_attr( get_option('gcs_form_title', 'Invia una Richiesta di Prenotazione') ); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Mostra il campo "Numero persone"</th>
                        <td>
                            <label>
                                <input type="checkbox" name="gcs_show_guests_field" value="1" <?php checked( 1, get_option('gcs_show_guests_field', 1), true ); ?> />
                                Mostra questo campo nel form
                            </label>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Mostra il campo "Messaggio aggiuntivo"</th>
                        <td>
                            <label>
                                <input type="checkbox" name="gcs_show_message_field" value="1" <?php checked( 1, get_option('gcs_show_message_field', 1), true ); ?> />
                                Mostra questo campo nel form
                            </label>
                        </td>
                    </tr>
                </table>

                <hr>
                <h3>Stile Visivo del Modulo (Colori e Forme)</h3>
                <p class="description">Non ti piace lo stile di default? Personalizza liberamente i colori, la morbidezza degli angoli e le dimensioni dei testi.</p>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Colore Titolo Principale</th>
                        <td><input type="color" name="gcs_style_title_color" value="<?php echo esc_attr( get_option('gcs_style_title_color', '#1a4581') ); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Grandezza Titolo (es. 24px)</th>
                        <td><input type="text" name="gcs_style_title_size" value="<?php echo esc_attr( get_option('gcs_style_title_size', '24px') ); ?>" class="regular-text" style="width:100px;"/></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Colore Etichette/Sottotitoli</th>
                        <td><input type="color" name="gcs_style_label_color" value="<?php echo esc_attr( get_option('gcs_style_label_color', '#444444') ); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Sfondo Campi di testo</th>
                        <td><input type="color" name="gcs_style_input_bg" value="<?php echo esc_attr( get_option('gcs_style_input_bg', '#ffffff') ); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Colore Bordo Campi</th>
                        <td><input type="color" name="gcs_style_input_border" value="<?php echo esc_attr( get_option('gcs_style_input_border', '#cccccc') ); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Rotondità Campi (es. 6px, 20px)</th>
                        <td><input type="text" name="gcs_style_input_radius" value="<?php echo esc_attr( get_option('gcs_style_input_radius', '6px') ); ?>" class="regular-text" style="width:100px;"/></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Sfondo Pulsante Invia</th>
                        <td><input type="color" name="gcs_style_btn_bg" value="<?php echo esc_attr( get_option('gcs_style_btn_bg', '#1a4581') ); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Sfondo Pulsante al Passaggio Mouse (Hover)</th>
                        <td><input type="color" name="gcs_style_btn_bg_hover" value="<?php echo esc_attr( get_option('gcs_style_btn_bg_hover', '#a1d1d0') ); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Testo Pulsante Invia</th>
                        <td><input type="color" name="gcs_style_btn_color" value="<?php echo esc_attr( get_option('gcs_style_btn_color', '#ffffff') ); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Rotondità Pulsante (es. 20px)</th>
                        <td><input type="text" name="gcs_style_btn_radius" value="<?php echo esc_attr( get_option('gcs_style_btn_radius', '20px') ); ?>" class="regular-text" style="width:100px;"/></td>
                    </tr>
                </table>
                
                <hr>
                <h3>Impaginazione e Geometrie</h3>
                <p class="description">Definisci gli allineamenti spaziali del modulo e le sue distanze.</p>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Allineamento del Titolo</th>
                        <td>
                            <select name="gcs_layout_title_align">
                                <option value="left" <?php selected(get_option('gcs_layout_title_align', 'left'), 'left'); ?>>A Sinistra</option>
                                <option value="center" <?php selected(get_option('gcs_layout_title_align', 'left'), 'center'); ?>>Al Centro</option>
                                <option value="right" <?php selected(get_option('gcs_layout_title_align', 'left'), 'right'); ?>>A Destra</option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Spazio tra i campi / Interlinea (es. 8px, 20px)</th>
                        <td><input type="text" name="gcs_layout_row_gap" value="<?php echo esc_attr( get_option('gcs_layout_row_gap', '8px') ); ?>" class="regular-text" style="width:100px;"/></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Allineamento Bottone Invia</th>
                        <td>
                            <select name="gcs_layout_btn_align">
                                <option value="left" <?php selected(get_option('gcs_layout_btn_align', 'left'), 'left'); ?>>A Sinistra</option>
                                <option value="center" <?php selected(get_option('gcs_layout_btn_align', 'left'), 'center'); ?>>Al Centro</option>
                                <option value="right" <?php selected(get_option('gcs_layout_btn_align', 'left'), 'right'); ?>>A Destra</option>
                                <option value="stretch" <?php selected(get_option('gcs_layout_btn_align', 'left'), 'stretch'); ?>>A tutta larghezza</option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Codice CSS Personalizzato (Avanzato)</th>
                        <td>
                            <textarea name="gcs_custom_css" rows="4" style="width:100%; font-family:monospace; background:#2b2b2b; color:#a9b7c6; padding:10px; border-radius:4px;" placeholder=".gcs-booking-form { }"><?php echo esc_textarea( get_option('gcs_custom_css', '') ); ?></textarea>
                            <p class="description">Usa questo campo se vuoi applicare regole CSS grafiche assolute senza modificare i file del plugin.</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="Salva le Modifiche">
                </p>
            </form>

            <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:4px; max-width:800px; margin-top:20px;">
                <h3 style="margin-top:0;">Sincronizzazione Google Calendar</h3>
                <p>Usa questo link univoco per importare automaticamente il calendario delle prenotazioni all'interno del tuo Google Calendar (o Apple Calendar / Outlook).</p>
                <p>Nelle impostazioni di Google Calendar, cerca e scegli <strong>"Aggiungi tramite URL"</strong> (o "Aggiungi calendario da URL") e incolla questo indirizzo qui sotto:</p>
                
                <input type="text" readonly="readonly" class="large-text" value="<?php echo esc_url( home_url( '/?gcs_ics_feed=1' ) ); ?>" style="background:#f0f0f1; border-color:#8c8f94; color:#3c434a; font-family:monospace; padding:10px;" onfocus="this.select();">
                
                <p class="description" style="margin-top:10px;">I tempi di sincronizzazione tramite URL solitamente dipendono da Google (potrebbero volerci alcune ore per aggiornarsi autonomamente in caso di modifiche / aggiunte ai giorni prenotati).</p>
            </div>
        </div>
        <?php
    }
}
