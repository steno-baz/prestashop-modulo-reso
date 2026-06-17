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
            Configuration::updateValue('BAZ_RESI_ORDER_STATE_DELIVERED', 0) &&
            Configuration::updateValue('BAZ_RESI_ORDER_STATE_PAYMENT_ACCEPTED', 0) &&
            Configuration::updateValue('BAZ_RESI_EMAILS', Configuration::get('PS_SHOP_EMAIL')) &&
            Configuration::updateValue('BAZ_RESI_SEND_CUSTOMER_MAIL', 1);
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
            Configuration::deleteByName('BAZ_RESI_SEND_CUSTOMER_MAIL');
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitBaz_gestioneresiModule')) {
            $days = (int)Tools::getValue('BAZ_RESI_DAYS');
            $privacy = (string)Tools::getValue('BAZ_RESI_PRIVACY_LINK');
            $intro = Tools::getValue('BAZ_RESI_INTRO_TEXT'); // Raw value for HTML
            $customer_email_text = Tools::getValue('BAZ_RESI_CUSTOMER_EMAIL_TEXT'); // Raw value for HTML
            $emails = (string)Tools::getValue('BAZ_RESI_EMAILS');
            $send_customer_mail = (int)Tools::getValue('BAZ_RESI_SEND_CUSTOMER_MAIL');

            $delivered_state = (int)Tools::getValue('BAZ_RESI_ORDER_STATE_DELIVERED');
            $payment_state = (int)Tools::getValue('BAZ_RESI_ORDER_STATE_PAYMENT_ACCEPTED');

            if (!$days || $days <= 0 || empty($privacy) || empty($intro) || empty($emails) || !$delivered_state || !$payment_state) {
                $output .= $this->displayError($this->l('Compila tutti i campi obbligatori: Email interne, Giorni per il reso, Link alla Privacy Policy, Testo introduttivo e stati ordine.'));
            } else {
                Configuration::updateValue('BAZ_RESI_DAYS', $days);
                Configuration::updateValue('BAZ_RESI_PRIVACY_LINK', $privacy);
                Configuration::updateValue('BAZ_RESI_INTRO_TEXT', $intro, true);
                Configuration::updateValue('BAZ_RESI_CUSTOMER_EMAIL_TEXT', $customer_email_text, true);
                Configuration::updateValue('BAZ_RESI_ORDER_STATE_DELIVERED', $delivered_state);
                Configuration::updateValue('BAZ_RESI_ORDER_STATE_PAYMENT_ACCEPTED', $payment_state);
                Configuration::updateValue('BAZ_RESI_EMAILS', $emails);
                Configuration::updateValue('BAZ_RESI_SEND_CUSTOMER_MAIL', $send_customer_mail);
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
                'baz_reso_link' => $this->context->link->getModuleLink($this->name, 'reso'),
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
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Impostazioni Modulo Resi'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Email interne (Destinatari)'),
                        'name' => 'BAZ_RESI_EMAILS',
                        'desc' => $this->l('Indirizzi email a cui inviare la richiesta di reso (separati da virgola se multipli).'),
                        'class' => 'fixed-width-xxl',
                        'required' => true,
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Invia mail di conferma al cliente'),
                        'name' => 'BAZ_RESI_SEND_CUSTOMER_MAIL',
                        'is_bool' => true,
                        'desc' => $this->l('Attiva per inviare una email di riepilogo al cliente.'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Sì')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('No')
                            )
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Giorni per il reso'),
                        'name' => 'BAZ_RESI_DAYS',
                        'desc' => $this->l('Numero massimo di giorni entro cui è possibile richiedere il reso.'),
                        'class' => 'fixed-width-xs',
                        'required' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Link alla Privacy Policy'),
                        'name' => 'BAZ_RESI_PRIVACY_LINK',
                        'desc' => $this->l('URL del file PDF o pagina CMS per la privacy.'),
                        'class' => 'fixed-width-xxl',
                        'required' => true,
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => $this->l('Testo introduttivo'),
                        'name' => 'BAZ_RESI_INTRO_TEXT',
                        'autoload_rte' => true,
                        'desc' => $this->l('Testo mostrato in cima al form di reso.'),
                        'required' => true,
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => $this->l('Testo personalizzato email cliente'),
                        'name' => 'BAZ_RESI_CUSTOMER_EMAIL_TEXT',
                        'autoload_rte' => true,
                        'desc' => $this->l('Testo inviato solo all\'email di conferma cliente.'),
                        'required' => false,
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Stato ordine consegnato'),
                        'name' => 'BAZ_RESI_ORDER_STATE_DELIVERED',
                        'options' => array(
                            'query' => $this->getOrderStateOptions(),
                            'id' => 'id_order_state',
                            'name' => 'name',
                        ),
                        'desc' => $this->l('Stato ordine che indica la consegna della merce.'),
                        'required' => true,
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Stato ordine pagamento accettato'),
                        'name' => 'BAZ_RESI_ORDER_STATE_PAYMENT_ACCEPTED',
                        'options' => array(
                            'query' => $this->getOrderStateOptions(),
                            'id' => 'id_order_state',
                            'name' => 'name',
                        ),
                        'desc' => $this->l('Stato ordine da usare se non esiste ancora il relativo stato consegnato.'),
                        'required' => true,
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Salva'),
                ),
            ),
        );
    }

    protected function getConfigFormValues()
    {
        return array(
            'BAZ_RESI_DAYS' => Configuration::get('BAZ_RESI_DAYS') !== false ? Configuration::get('BAZ_RESI_DAYS') : 14,
            'BAZ_RESI_PRIVACY_LINK' => Configuration::get('BAZ_RESI_PRIVACY_LINK') !== false ? Configuration::get('BAZ_RESI_PRIVACY_LINK') : '/Privacy_Policy_sito_web.pdf',
            'BAZ_RESI_INTRO_TEXT' => Configuration::get('BAZ_RESI_INTRO_TEXT') !== false ? Configuration::get('BAZ_RESI_INTRO_TEXT') : '',
            'BAZ_RESI_CUSTOMER_EMAIL_TEXT' => Configuration::get('BAZ_RESI_CUSTOMER_EMAIL_TEXT') !== false ? Configuration::get('BAZ_RESI_CUSTOMER_EMAIL_TEXT') : '',
            'BAZ_RESI_ORDER_STATE_DELIVERED' => Configuration::get('BAZ_RESI_ORDER_STATE_DELIVERED') !== false ? Configuration::get('BAZ_RESI_ORDER_STATE_DELIVERED') : 0,
            'BAZ_RESI_ORDER_STATE_PAYMENT_ACCEPTED' => Configuration::get('BAZ_RESI_ORDER_STATE_PAYMENT_ACCEPTED') !== false ? Configuration::get('BAZ_RESI_ORDER_STATE_PAYMENT_ACCEPTED') : 0,
            'BAZ_RESI_EMAILS' => Configuration::get('BAZ_RESI_EMAILS') !== false ? Configuration::get('BAZ_RESI_EMAILS') : Configuration::get('PS_SHOP_EMAIL'),
            'BAZ_RESI_SEND_CUSTOMER_MAIL' => Configuration::get('BAZ_RESI_SEND_CUSTOMER_MAIL') !== false ? Configuration::get('BAZ_RESI_SEND_CUSTOMER_MAIL') : 1,
        );
    }

    protected function getOrderStateOptions()
    {
        return OrderState::getOrderStates((int)$this->context->language->id);
    }
}