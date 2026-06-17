<?php
class Baz_gestioneresiResoModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $is_logged = $this->context->customer->isLogged();
        $customer_data = array();
        $orders = array();

        // Se l'utente è loggato, recuperiamo i dati e i suoi ordini
        if ($is_logged) {
            $customer = $this->context->customer;
            $customer_data = array(
                'firstname' => $customer->firstname,
                'lastname' => $customer->lastname,
                'email' => $customer->email,
            );

            // Recuperiamo gli ultimi ordini del cliente
            $customer_orders = Order::getCustomerOrders($customer->id);
            if ($customer_orders) {
                foreach ($customer_orders as $order) {
                    $orders[] = array(
                        'id_order' => $order['id_order'],
                        'reference' => $order['reference'],
                        'date' => $order['date_add']
                    );
                }
            }
        }

        // Gestione del POST (Invio del modulo)
        if (Tools::isSubmit('submit_reso')) {
            $this->processResoForm();
        }

        // Passiamo le variabili a Smarty (il template)
        $this->context->smarty->assign(array(
            'is_logged' => $is_logged,
            'customer_data' => $customer_data,
            'orders' => $orders,
            'errors' => $this->errors,
            'success' => Tools::getValue('success'),
            'selected_order' => Tools::getValue('ordine'),
            'resi_days' => Configuration::get('BAZ_RESI_DAYS') !== false ? Configuration::get('BAZ_RESI_DAYS') : 14,
            'resi_privacy_link' => Configuration::get('BAZ_RESI_PRIVACY_LINK') !== false ? Configuration::get('BAZ_RESI_PRIVACY_LINK') : '/Privacy_Policy_sito_web.pdf',
            'resi_intro_text' => Configuration::get('BAZ_RESI_INTRO_TEXT') !== false ? Configuration::get('BAZ_RESI_INTRO_TEXT') : ''
        ));

        $this->setTemplate('module:baz_gestioneresi/views/templates/front/form_reso.tpl');
    }

    protected function processResoForm()
    {
        // Validazione Privacy
        if (!Tools::getValue('privacy')) {
            $this->errors[] = $this->module->l('Devi accettare l\'informativa sulla privacy.');
            return;
        }

        // Raccolta dati dal form
        $nome = Tools::getValue('nome');
        $cognome = Tools::getValue('cognome');
        $email = Tools::getValue('email');
        $ordine = Tools::getValue('ordine');
        $cellulare = Tools::getValue('cellulare');
        $messaggio = Tools::getValue('messaggio');

        if (empty($nome) || empty($cognome) || empty($email) || empty($ordine)) {
            $this->errors[] = $this->module->l('I campi Nome, Cognome, Email e Ordine sono obbligatori.');
            return;
        }

        // Gestione Allegato
        $attachment = null;
        if (isset($_FILES['allegato']) && !empty($_FILES['allegato']['name'])) {
            $file = $_FILES['allegato'];
            $allowed_extensions = array('jpg', 'jpeg', 'png', 'pdf');
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);

            if (!in_array(strtolower($ext), $allowed_extensions)) {
                $this->errors[] = $this->module->l('Formato file non valido. Ammessi solo JPG, PNG, PDF.');
                return;
            }

            // Prepariamo l'allegato per la mail
            $attachment = array(
                'content' => file_get_contents($file['tmp_name']),
                'name' => $file['name'],
                'mime' => $file['type']
            );
        }

        // Invio Email (Usa in automatico la config SMTP di PrestaShop)
        $template_vars = array(
            '{nome}' => $nome,
            '{cognome}' => $cognome,
            '{email}' => $email,
            '{ordine}' => $ordine,
            '{cellulare}' => $cellulare,
            '{messaggio}' => nl2br($messaggio),
            '{intro_text}' => Configuration::get('BAZ_RESI_INTRO_TEXT') !== false ? Configuration::get('BAZ_RESI_INTRO_TEXT') : ''
        );

        // Invio al gestore del negozio (o alle email configurate)
        $emails_config = Configuration::get('BAZ_RESI_EMAILS');
        if (empty($emails_config)) {
            $to = Configuration::get('PS_SHOP_EMAIL');
        } else {
            // Supporto a più indirizzi separati da virgola
            $to = array_map('trim', explode(',', $emails_config));
        }
        
        $mail_success = Mail::Send(
            (int)$this->context->language->id,
            'reso_admin', 
            $this->module->l('Nuova Richiesta di Reso Ordine #' . $ordine),
            $template_vars,
            $to,
            'Servizio Clienti Resi',
            null,
            null,
            $attachment,
            null,
            dirname(__FILE__).'/../../mails/',
            false,
            (int)$this->context->shop->id
        );

        // Invio mail di conferma al cliente (se attivato dalle impostazioni)
        if ($mail_success && Configuration::get('BAZ_RESI_SEND_CUSTOMER_MAIL')) {
            Mail::Send(
                (int)$this->context->language->id,
                'reso_cliente', 
                $this->module->l('Conferma ricezione richiesta reso ordine #' . $ordine),
                $template_vars, 
                $email,
                $nome . ' ' . $cognome,
                null,
                null,
                null, // Nessun allegato per il cliente
                null,
                dirname(__FILE__).'/../../mails/',
                false,
                (int)$this->context->shop->id
            );
        }

        if ($mail_success) {
            Tools::redirect($this->context->link->getModuleLink('baz_gestioneresi', 'reso', array('success' => 1)));
        } else {
            $this->errors[] = $this->module->l('Si è verificato un errore durante l\'invio della richiesta. Riprova più tardi.');
        }
    }
}