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
