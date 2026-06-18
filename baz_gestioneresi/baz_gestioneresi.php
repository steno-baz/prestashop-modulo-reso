<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class Baz_gestioneresi extends Module
{
    public function __construct()
    {
        $this->name = 'baz_gestioneresi';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Baz srl';
        $this->need_instance = 0;
        $this->is_configurable = 1;
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => '8.2');
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Gestione Resi Avanzata');
        $this->description = $this->l('Pagina dedicata alla richiesta di reso per utenti loggati e guest.');
    }

    public function install()
    {
        // Default text
        $defaultIntro = '<p>Se desideri effettuare un reso, segui queste semplici istruzioni:</p><ol><li>Compila il modulo sottostante in tutte le sue parti.</li><li>Seleziona o inserisci il codice del tuo ordine.</li><li>Se possibile, allega una foto del prodotto (specialmente se danneggiato).</li><li>Riceverai una mail di conferma con le istruzioni per il ritiro del pacco.</li></ol><br />';

        return parent::install() &&
            $this->registerHook('displayHeader') &&
            Configuration::updateValue('BAZ_RESI_DAYS', 14) &&
            Configuration::updateValue('BAZ_RESI_PRIVACY_LINK', '/Privacy_Policy_sito_web.pdf') &&
            Configuration::updateValue('BAZ_RESI_INTRO_TEXT', $defaultIntro, true) &&
            Configuration::updateValue('BAZ_RESI_CUSTOMER_EMAIL_TEXT', '', true) &&
            Configuration::updateValue('BAZ_RESI_ORDER_STATE_DELIVERED', '') &&
            Configuration::updateValue('BAZ_RESI_ORDER_STATE_PAYMENT_ACCEPTED', '') &&
            Configuration::updateValue('BAZ_RESI_EMAILS', Configuration::get('PS_SHOP_EMAIL')) &&
            Configuration::updateValue('BAZ_RESI_SEND_CUSTOMER_MAIL', 1) &&
            Configuration::updateValue('BAZ_RESI_SHOW_ALLEGATO', 1) &&
            Configuration::updateValue('BAZ_RESI_SHOW_MOTIVAZIONE', 1) &&
            Configuration::updateValue('BAZ_RESI_SHOW_NOTA_BENE', 1);
    }

    public function uninstall()
    {
        return parent::uninstall() &&
            Configuration::deleteByName('BAZ_RESI_DAYS') &&
            Configuration::deleteByName('BAZ_RESI_PRIVACY_LINK') &&
            Configuration::deleteByName('BAZ_RESI_INTRO_TEXT') &&
            Configuration::deleteByName('BAZ_RESI_CUSTOMER_EMAIL_TEXT') &&
            Configuration::deleteByName('BAZ_RESI_ORDER_STATE_DELIVERED') &&
            Configuration::deleteByName('BAZ_RESI_ORDER_STATE_PAYMENT_ACCEPTED') &&
            Configuration::deleteByName('BAZ_RESI_EMAILS') &&
            Configuration::deleteByName('BAZ_RESI_SEND_CUSTOMER_MAIL') &&
            Configuration::deleteByName('BAZ_RESI_SHOW_ALLEGATO') &&
            Configuration::deleteByName('BAZ_RESI_SHOW_MOTIVAZIONE') &&
            Configuration::deleteByName('BAZ_RESI_SHOW_NOTA_BENE');
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitBaz_gestioneresiModule')) {
            $days                = (int)Tools::getValue('BAZ_RESI_DAYS');
            $privacy             = (string)Tools::getValue('BAZ_RESI_PRIVACY_LINK');
            $intro               = Tools::getValue('BAZ_RESI_INTRO_TEXT'); // Raw value for HTML
            $customer_email_text = Tools::getValue('BAZ_RESI_CUSTOMER_EMAIL_TEXT'); // Raw value for HTML
            $emails              = (string)Tools::getValue('BAZ_RESI_EMAILS');
            $send_customer_mail  = (int)Tools::getValue('BAZ_RESI_SEND_CUSTOMER_MAIL');
            $show_allegato       = (int)Tools::getValue('BAZ_RESI_SHOW_ALLEGATO');
            $show_motivazione    = (int)Tools::getValue('BAZ_RESI_SHOW_MOTIVAZIONE');
            $show_nota_bene      = (int)Tools::getValue('BAZ_RESI_SHOW_NOTA_BENE');

            // Stati ordine: possono essere array (select multiple) o stringa singola
            $delivered_raw  = Tools::getValue('BAZ_RESI_ORDER_STATE_DELIVERED');
            $payment_raw    = Tools::getValue('BAZ_RESI_ORDER_STATE_PAYMENT_ACCEPTED');
            $delivered_states = array_filter(array_map('intval', is_array($delivered_raw) ? $delivered_raw : array($delivered_raw)));
            $payment_states   = array_filter(array_map('intval', is_array($payment_raw)  ? $payment_raw  : array($payment_raw)));

            $delivered_value = implode(',', $delivered_states);
            $payment_value   = implode(',', $payment_states);

            if (!$days || $days <= 0 || empty($privacy) || empty($intro) || empty($emails)) {
                $output .= $this->displayError($this->l('Compila tutti i campi obbligatori: Email interne, Giorni per il reso, Link alla Privacy Policy e Testo introduttivo.'));
            } else {
                Configuration::updateValue('BAZ_RESI_DAYS', $days);
                Configuration::updateValue('BAZ_RESI_PRIVACY_LINK', $privacy);
                Configuration::updateValue('BAZ_RESI_INTRO_TEXT', $intro, true);
                Configuration::updateValue('BAZ_RESI_CUSTOMER_EMAIL_TEXT', $customer_email_text, true);
                Configuration::updateValue('BAZ_RESI_ORDER_STATE_DELIVERED', $delivered_value);
                Configuration::updateValue('BAZ_RESI_ORDER_STATE_PAYMENT_ACCEPTED', $payment_value);
                Configuration::updateValue('BAZ_RESI_EMAILS', $emails);
                Configuration::updateValue('BAZ_RESI_SEND_CUSTOMER_MAIL', $send_customer_mail);
                Configuration::updateValue('BAZ_RESI_SHOW_ALLEGATO', $show_allegato);
                Configuration::updateValue('BAZ_RESI_SHOW_MOTIVAZIONE', $show_motivazione);
                Configuration::updateValue('BAZ_RESI_SHOW_NOTA_BENE', $show_nota_bene);
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }

        return $output . $this->renderForm();
    }

    public function hookDisplayHeader($params)
    {
        // Aggiunge il JS solo nella pagina dello storico ordini
        if ($this->context->controller->php_self == 'history') {
            $this->context->smarty->assign(array(
                'baz_reso_link'  => $this->context->link->getModuleLink($this->name, 'reso'),
                'baz_reso_label' => $this->l('Reso / Recesso')
            ));
            return $this->display(__FILE__, 'views/templates/hook/header_history.tpl');
        }
    }

    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitBaz_gestioneresiModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(),
            'languages'    => $this->context->controller->getLanguages(),
            'id_language'  => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    protected function getConfigForm()
    {
        $order_states = $this->getOrderStateOptions();

        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Impostazioni Modulo Resi'),
                    'icon'  => 'icon-cogs',
                ),
                'input' => array(
                    // --- Email e notifiche ---
                    array(
                        'type'     => 'text',
                        'label'    => $this->l('Email interne (Destinatari)'),
                        'name'     => 'BAZ_RESI_EMAILS',
                        'desc'     => $this->l('Indirizzi email a cui inviare la richiesta di reso (separati da virgola se multipli).'),
                        'class'    => 'fixed-width-xxl',
                        'required' => true,
                    ),
                    array(
                        'type'    => 'switch',
                        'label'   => $this->l('Invia mail di conferma al cliente'),
                        'name'    => 'BAZ_RESI_SEND_CUSTOMER_MAIL',
                        'is_bool' => true,
                        'desc'    => $this->l('Attiva per inviare una email di riepilogo al cliente.'),
                        'values'  => array(
                            array('id' => 'active_on',  'value' => 1, 'label' => $this->l('Sì')),
                            array('id' => 'active_off', 'value' => 0, 'label' => $this->l('No')),
                        ),
                    ),
                    // --- Parametri generali ---
                    array(
                        'type'     => 'text',
                        'label'    => $this->l('Giorni per il reso'),
                        'name'     => 'BAZ_RESI_DAYS',
                        'desc'     => $this->l('Numero massimo di giorni entro cui è possibile richiedere il reso.'),
                        'class'    => 'fixed-width-xs',
                        'required' => true,
                    ),
                    array(
                        'type'     => 'text',
                        'label'    => $this->l('Link alla Privacy Policy'),
                        'name'     => 'BAZ_RESI_PRIVACY_LINK',
                        'desc'     => $this->l('URL del file PDF o pagina CMS per la privacy.'),
                        'class'    => 'fixed-width-xxl',
                        'required' => true,
                    ),
                    array(
                        'type'        => 'textarea',
                        'label'       => $this->l('Testo introduttivo'),
                        'name'        => 'BAZ_RESI_INTRO_TEXT',
                        'autoload_rte'=> true,
                        'desc'        => $this->l('Testo mostrato in cima al form di reso.'),
                        'required'    => true,
                    ),
                    array(
                        'type'        => 'textarea',
                        'label'       => $this->l('Testo personalizzato email cliente'),
                        'name'        => 'BAZ_RESI_CUSTOMER_EMAIL_TEXT',
                        'autoload_rte'=> true,
                        'desc'        => $this->l('Testo inviato solo all\'email di conferma cliente.'),
                        'required'    => false,
                    ),
                    // --- Visibilità campi nel form front-end ---
                    array(
                        'type'    => 'switch',
                        'label'   => $this->l('Mostra "Nota Bene" (giorni reso)'),
                        'name'    => 'BAZ_RESI_SHOW_NOTA_BENE',
                        'is_bool' => true,
                        'desc'    => $this->l('Se attivo, mostra nel form la nota con i giorni entro cui è possibile fare il reso.'),
                        'values'  => array(
                            array('id' => 'nota_bene_on',  'value' => 1, 'label' => $this->l('Sì')),
                            array('id' => 'nota_bene_off', 'value' => 0, 'label' => $this->l('No')),
                        ),
                    ),
                    array(
                        'type'    => 'switch',
                        'label'   => $this->l('Mostra campo "Motivazione del reso"'),
                        'name'    => 'BAZ_RESI_SHOW_MOTIVAZIONE',
                        'is_bool' => true,
                        'desc'    => $this->l('Se attivo, mostra nel form il campo testuale per la motivazione (facoltativo).'),
                        'values'  => array(
                            array('id' => 'motivazione_on',  'value' => 1, 'label' => $this->l('Sì')),
                            array('id' => 'motivazione_off', 'value' => 0, 'label' => $this->l('No')),
                        ),
                    ),
                    array(
                        'type'    => 'switch',
                        'label'   => $this->l('Mostra campo "Allegato"'),
                        'name'    => 'BAZ_RESI_SHOW_ALLEGATO',
                        'is_bool' => true,
                        'desc'    => $this->l('Se attivo, mostra nel form il campo per allegare un file (foto/documento).'),
                        'values'  => array(
                            array('id' => 'allegato_on',  'value' => 1, 'label' => $this->l('Sì')),
                            array('id' => 'allegato_off', 'value' => 0, 'label' => $this->l('No')),
                        ),
                    ),
                    // --- Selezione multipla stati ordine ---
                    array(
                        'type'         => 'html',
                        'label'        => $this->l('Stati ordine "Consegnato"'),
                        'name'         => 'BAZ_RESI_ORDER_STATE_DELIVERED_HTML',
                        'html_content' => $this->renderMultiSelect(
                            'BAZ_RESI_ORDER_STATE_DELIVERED',
                            $order_states,
                            (string)Configuration::get('BAZ_RESI_ORDER_STATE_DELIVERED')
                        ),
                        'desc' => $this->l('Seleziona uno o più stati che indicano la consegna della merce. Tieni premuto Ctrl (o Cmd su Mac) per selezione multipla.'),
                    ),
                    array(
                        'type'         => 'html',
                        'label'        => $this->l('Stati ordine "Pagamento accettato"'),
                        'name'         => 'BAZ_RESI_ORDER_STATE_PAYMENT_HTML',
                        'html_content' => $this->renderMultiSelect(
                            'BAZ_RESI_ORDER_STATE_PAYMENT_ACCEPTED',
                            $order_states,
                            (string)Configuration::get('BAZ_RESI_ORDER_STATE_PAYMENT_ACCEPTED')
                        ),
                        'desc' => $this->l('Seleziona uno o più stati da usare quando non esiste il relativo stato consegnato. Tieni premuto Ctrl (o Cmd su Mac) per selezione multipla.'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Salva'),
                ),
            ),
        );
    }

    /**
     * Genera un elemento <select multiple> con i valori già salvati pre-selezionati.
     *
     * @param string $name            Nome dell'attributo HTML del select (verrà postfissato con [])
     * @param array  $states          Lista stati ordine da OrderState::getOrderStates()
     * @param string $saved_values_str Stringa CSV degli ID già salvati (es. "2,5,9")
     * @return string HTML del campo select
     */
    protected function renderMultiSelect($name, array $states, $saved_values_str)
    {
        $saved_ids = array_filter(array_map('intval', explode(',', $saved_values_str)));

        $html  = '<select name="' . htmlspecialchars($name) . '[]"';
        $html .= ' multiple="multiple"';
        $html .= ' style="min-height: 180px; max-height: 300px; width: 100%; max-width: 450px;">';

        foreach ($states as $state) {
            $id       = (int)$state['id_order_state'];
            $selected = in_array($id, $saved_ids) ? ' selected="selected"' : '';
            $html    .= '<option value="' . $id . '"' . $selected . '>'
                . htmlspecialchars($state['name'])
                . '</option>';
        }

        $html .= '</select>';
        return $html;
    }

    protected function getConfigFormValues()
    {
        return array(
            'BAZ_RESI_DAYS'                   => Configuration::get('BAZ_RESI_DAYS') !== false ? Configuration::get('BAZ_RESI_DAYS') : 14,
            'BAZ_RESI_PRIVACY_LINK'           => Configuration::get('BAZ_RESI_PRIVACY_LINK') !== false ? Configuration::get('BAZ_RESI_PRIVACY_LINK') : '/Privacy_Policy_sito_web.pdf',
            'BAZ_RESI_INTRO_TEXT'             => Configuration::get('BAZ_RESI_INTRO_TEXT') !== false ? Configuration::get('BAZ_RESI_INTRO_TEXT') : '',
            'BAZ_RESI_CUSTOMER_EMAIL_TEXT'    => Configuration::get('BAZ_RESI_CUSTOMER_EMAIL_TEXT') !== false ? Configuration::get('BAZ_RESI_CUSTOMER_EMAIL_TEXT') : '',
            'BAZ_RESI_EMAILS'                 => Configuration::get('BAZ_RESI_EMAILS') !== false ? Configuration::get('BAZ_RESI_EMAILS') : Configuration::get('PS_SHOP_EMAIL'),
            'BAZ_RESI_SEND_CUSTOMER_MAIL'     => Configuration::get('BAZ_RESI_SEND_CUSTOMER_MAIL') !== false ? Configuration::get('BAZ_RESI_SEND_CUSTOMER_MAIL') : 1,
            'BAZ_RESI_SHOW_ALLEGATO'          => Configuration::get('BAZ_RESI_SHOW_ALLEGATO') !== false ? Configuration::get('BAZ_RESI_SHOW_ALLEGATO') : 1,
            'BAZ_RESI_SHOW_MOTIVAZIONE'       => Configuration::get('BAZ_RESI_SHOW_MOTIVAZIONE') !== false ? Configuration::get('BAZ_RESI_SHOW_MOTIVAZIONE') : 1,
            'BAZ_RESI_SHOW_NOTA_BENE'         => Configuration::get('BAZ_RESI_SHOW_NOTA_BENE') !== false ? Configuration::get('BAZ_RESI_SHOW_NOTA_BENE') : 1,
        );
    }

    protected function getOrderStateOptions()
    {
        return OrderState::getOrderStates((int)$this->context->language->id);
    }
}