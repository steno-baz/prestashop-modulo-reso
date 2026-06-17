<?php
class Baz_gestioneresiResoModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $is_logged = $this->context->customer->isLogged();
        $customer_data = array();
        $orders = array();
        $disabled_orders_count = 0;

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
            $resi_days = Configuration::get('BAZ_RESI_DAYS') !== false ? (int)Configuration::get('BAZ_RESI_DAYS') : 14;
            $delivered_state = (int)Configuration::get('BAZ_RESI_ORDER_STATE_DELIVERED');
            $payment_state = (int)Configuration::get('BAZ_RESI_ORDER_STATE_PAYMENT_ACCEPTED');

            if ($customer_orders) {
                $order_ids = array_map('intval', array_column($customer_orders, 'id_order'));
                $history = array();

                if (!empty($order_ids) && ($delivered_state || $payment_state)) {
                    $state_ids = array_filter(array($delivered_state, $payment_state));
                    if (!empty($state_ids)) {
                        $history_data = Db::getInstance()->executeS(
                            'SELECT id_order, id_order_state, date_add FROM '._DB_PREFIX_.'order_history'
                            .' WHERE id_order IN ('.implode(',', $order_ids).')'
                            .' AND id_order_state IN ('.implode(',', $state_ids).')'
                            .' ORDER BY date_add ASC'
                        );

                        if ($history_data) {
                            foreach ($history_data as $row) {
                                $history[$row['id_order']][] = $row;
                            }
                        }
                    }
                }

                foreach ($customer_orders as $order) {
                    $reference_date = $this->getReferenceDateForOrder($order['id_order'], $order['date_add'], $history, $delivered_state, $payment_state);
                    $selectable = true;
                    if ($reference_date) {
                        $reference_timestamp = strtotime($reference_date);
                        if ($reference_timestamp !== false) {
                            $selectable = (time() - $reference_timestamp) <= ($resi_days * 86400);
                        }
                    }

                    if (!$selectable) {
                        $disabled_orders_count++;
                    }

                    $order_obj = new Order((int)$order['id_order']);
                    $products = $order_obj->getProducts();

                    $orders[] = array(
                        'id_order' => $order['id_order'],
                        'reference' => $order['reference'],
                        'date' => $order['date_add'],
                        'reference_date' => $reference_date,
                        'selectable' => $selectable,
                        'products' => $products,
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
            'disabled_orders_count' => $disabled_orders_count,
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

        if ($this->context->customer->isLogged() && !$this->isOrderEligible($ordine, $this->context->customer->id)) {
            $this->errors[] = $this->module->l('L\'ordine selezionato non è più eligibile per il reso/recesso oppure non appartiene al tuo account.');
            return;
        }

        // Se l'utente è loggato, recuperiamo e validiamo i prodotti selezionati da rendere
        $selected_products = Tools::getValue('products_to_return');
        $products_detail = array();

        if ($this->context->customer->isLogged()) {
            if (empty($selected_products) || !is_array($selected_products)) {
                $this->errors[] = $this->module->l('Devi selezionare almeno un prodotto da rendere.');
                return;
            }

            $order_id = (int)Db::getInstance()->getValue('SELECT id_order FROM '._DB_PREFIX_.'orders WHERE reference = \''.pSQL($ordine).'\'');
            $order_obj = new Order($order_id);
            $order_products = $order_obj->getProducts();

            $ordered_qtys = array();
            $product_names = array();
            foreach ($order_products as $op) {
                $ordered_qtys[(int)$op['id_order_detail']] = (int)$op['product_quantity'];
                $product_names[(int)$op['id_order_detail']] = $op['product_name'] . ($op['product_reference'] ? ' (Rif: ' . $op['product_reference'] . ')' : '');
            }

            foreach ($selected_products as $id_order_detail) {
                $id_order_detail = (int)$id_order_detail;
                $qty = (int)Tools::getValue('product_qty_' . $id_order_detail);

                if (!isset($ordered_qtys[$id_order_detail])) {
                    $this->errors[] = $this->module->l('Uno dei prodotti selezionati non appartiene all\'ordine indicato.');
                    return;
                }

                if ($qty <= 0 || $qty > $ordered_qtys[$id_order_detail]) {
                    $this->errors[] = sprintf($this->module->l('Quantità non valida per il prodotto %s. Massimo consentito: %d.'), $product_names[$id_order_detail], $ordered_qtys[$id_order_detail]);
                    return;
                }

                $products_detail[] = array(
                    'id_order_detail' => $id_order_detail,
                    'qty' => $qty,
                    'name' => $product_names[$id_order_detail]
                );
            }
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

        // Scrittura nel database (solo se utente loggato)
        $id_order_return = 0;
        if ($this->context->customer->isLogged()) {
            $order_id = (int)Db::getInstance()->getValue('SELECT id_order FROM '._DB_PREFIX_.'orders WHERE reference = \''.pSQL($ordine).'\'');
            $db = Db::getInstance();
            $db->insert('order_return', array(
                'id_customer' => (int)$this->context->customer->id,
                'id_order' => (int)$order_id,
                'state' => 1, // In attesa di conferma
                'question' => pSQL($messaggio),
                'date_add' => date('Y-m-d H:i:s'),
                'date_upd' => date('Y-m-d H:i:s')
            ));

            $id_order_return = (int)$db->Insert_ID();

            if ($id_order_return) {
                foreach ($products_detail as $p) {
                    $db->insert('order_return_detail', array(
                        'id_order_return' => (int)$id_order_return,
                        'id_order_detail' => (int)$p['id_order_detail'],
                        'id_customization' => 0,
                        'product_quantity' => (int)$p['qty']
                    ));
                }
            }
        }

        // Generazione del riepilogo prodotti per email
        $prodotti_reso_html = '';
        $prodotti_reso_txt = '';

        if ($this->context->customer->isLogged() && !empty($products_detail)) {
            $prodotti_reso_html = '<h3 style="margin-top: 20px;">Prodotti da rendere:</h3>';
            $prodotti_reso_html .= '<table style="width: 100%; border-collapse: collapse; margin-top: 10px;">';
            $prodotti_reso_html .= '<thead><tr style="background-color: #f2f2f2; text-align: left;"><th style="padding: 8px; border: 1px solid #ddd;">Prodotto</th><th style="padding: 8px; border: 1px solid #ddd; width: 80px; text-align: center;">Quantità</th></tr></thead>';
            $prodotti_reso_html .= '<tbody>';

            $prodotti_reso_txt = "\nProdotti da rendere:\n";

            foreach ($products_detail as $p) {
                $prodotti_reso_html .= '<tr>';
                $prodotti_reso_html .= '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($p['name']) . '</td>';
                $prodotti_reso_html .= '<td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . (int)$p['qty'] . '</td>';
                $prodotti_reso_html .= '</tr>';

                $prodotti_reso_txt .= '- ' . $p['name'] . ' (Qta: ' . (int)$p['qty'] . ")\n";
            }
            $prodotti_reso_html .= '</tbody></table>';
        }

        // Invio Email (Usa in automatico la config SMTP di PrestaShop)
        $template_vars = array(
            '{data_ora}' => date('d/m/Y H:i:s'),
            '{nome}' => $nome,
            '{cognome}' => $cognome,
            '{email}' => $email,
            '{ordine}' => $ordine,
            '{cellulare}' => $cellulare,
            '{messaggio}' => nl2br($messaggio),
            '{prodotti_reso_html}' => $prodotti_reso_html,
            '{prodotti_reso_txt}' => $prodotti_reso_txt,
            '{intro_text}' => Configuration::get('BAZ_RESI_INTRO_TEXT') !== false ? Configuration::get('BAZ_RESI_INTRO_TEXT') : '',
            '{customer_email_text}' => Configuration::get('BAZ_RESI_CUSTOMER_EMAIL_TEXT') !== false ? Configuration::get('BAZ_RESI_CUSTOMER_EMAIL_TEXT') : ''
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

    protected function getReferenceDateForOrder($id_order, $fallback_date, array $history, $delivered_state, $payment_state)
    {
        $reference_date = null;
        if (!empty($history[$id_order])) {
            foreach ($history[$id_order] as $row) {
                if ($delivered_state && $row['id_order_state'] == $delivered_state) {
                    $reference_date = $row['date_add'];
                }
            }
            if (!$reference_date) {
                foreach ($history[$id_order] as $row) {
                    if ($payment_state && $row['id_order_state'] == $payment_state) {
                        $reference_date = $row['date_add'];
                    }
                }
            }
        }

        return $reference_date ? $reference_date : $fallback_date;
    }

    protected function isOrderEligible($reference, $customer_id)
    {
        $order_id = (int)Db::getInstance()->getValue('SELECT id_order FROM '._DB_PREFIX_.'orders WHERE reference = \''.pSQL($reference).'\'');
        if (!$order_id) {
            return false;
        }

        $order = new Order($order_id);
        if (!$order->id || (int)$order->id_customer !== (int)$customer_id) {
            return false;
        }

        $resi_days = Configuration::get('BAZ_RESI_DAYS') !== false ? (int)Configuration::get('BAZ_RESI_DAYS') : 14;
        $delivered_state = (int)Configuration::get('BAZ_RESI_ORDER_STATE_DELIVERED');
        $payment_state = (int)Configuration::get('BAZ_RESI_ORDER_STATE_PAYMENT_ACCEPTED');
        $reference_date = $this->getOrderReferenceDate($order->id, $order->date_add, $delivered_state, $payment_state);

        if (!$reference_date) {
            return false;
        }

        $reference_timestamp = strtotime($reference_date);
        if ($reference_timestamp === false) {
            return false;
        }

        return (time() - $reference_timestamp) <= ($resi_days * 86400);
    }

    protected function getOrderReferenceDate($id_order, $fallback_date, $delivered_state, $payment_state)
    {
        $reference_date = null;
        $state_ids = array_filter(array($delivered_state, $payment_state));
        if ($state_ids) {
            $history_data = Db::getInstance()->executeS(
                'SELECT id_order_state, date_add FROM '._DB_PREFIX_.'order_history'
                .' WHERE id_order = '.(int)$id_order
                .' AND id_order_state IN ('.implode(',', $state_ids).')'
                .' ORDER BY date_add ASC'
            );

            if ($history_data) {
                foreach ($history_data as $row) {
                    if ($delivered_state && $row['id_order_state'] == $delivered_state) {
                        $reference_date = $row['date_add'];
                    }
                }
                if (!$reference_date) {
                    foreach ($history_data as $row) {
                        if ($payment_state && $row['id_order_state'] == $payment_state) {
                            $reference_date = $row['date_add'];
                        }
                    }
                }
            }
        }

        return $reference_date ? $reference_date : $fallback_date;
    }
}
