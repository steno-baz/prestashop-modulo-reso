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

        // Legge gli stati ordine come array (salvati come CSV: "2,5,9")
        $resi_days        = Configuration::get('BAZ_RESI_DAYS') !== false ? (int)Configuration::get('BAZ_RESI_DAYS') : 14;
        $delivered_states = $this->parseStateIds(Configuration::get('BAZ_RESI_ORDER_STATE_DELIVERED'));
        $payment_states   = $this->parseStateIds(Configuration::get('BAZ_RESI_ORDER_STATE_PAYMENT_ACCEPTED'));
        $shipped_states   = $this->parseStateIds(Configuration::get('BAZ_RESI_ORDER_STATE_SHIPPED'));

        // Se l'utente è loggato, recuperiamo i dati e i suoi ordini
        if ($is_logged) {
            $customer = $this->context->customer;
            $customer_data = array(
                'firstname' => $customer->firstname,
                'lastname'  => $customer->lastname,
                'email'     => $customer->email,
            );

            // Recuperiamo gli ultimi ordini del cliente
            $customer_orders = Order::getCustomerOrders($customer->id);

            if ($customer_orders) {
                $order_ids = array_map('intval', array_column($customer_orders, 'id_order'));
                $history   = array();

                // Tutti gli stati rilevanti per il filtro
                $all_state_ids = array_filter(array_merge($delivered_states, $payment_states, $shipped_states));

                if (!empty($order_ids) && !empty($all_state_ids)) {
                    $history_data = Db::getInstance()->executeS(
                        'SELECT id_order, id_order_state, date_add FROM ' . _DB_PREFIX_ . 'order_history'
                        . ' WHERE id_order IN (' . implode(',', $order_ids) . ')'
                        . ' AND id_order_state IN (' . implode(',', $all_state_ids) . ')'
                        . ' ORDER BY date_add ASC'
                    );

                    if ($history_data) {
                        foreach ($history_data as $row) {
                            $history[$row['id_order']][] = $row;
                        }
                    }
                }

                foreach ($customer_orders as $order) {
                    // Escludi gli ordini annullati
                    if (isset($order['current_state']) && (int)$order['current_state'] === (int)Configuration::get('PS_OS_CANCELED')) {
                        continue;
                    }

                    $reference_date = $this->getReferenceDateForOrder(
                        $order['id_order'],
                        $order['date_add'],
                        $history,
                        $delivered_states,
                        $payment_states
                    );
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

                    $is_shipped = true;
                    if (!empty($shipped_states)) {
                        $is_shipped = false;
                        if (!empty($history[$order['id_order']])) {
                            foreach ($history[$order['id_order']] as $row) {
                                if (in_array((int)$row['id_order_state'], $shipped_states)) {
                                    $is_shipped = true;
                                    break;
                                }
                            }
                        }
                    }

                    $order_obj = new Order((int)$order['id_order']);
                    $products  = $order_obj->getProducts();

                    $orders[] = array(
                        'id_order'       => $order['id_order'],
                        'reference'      => $order['reference'],
                        'date'           => $order['date_add'],
                        'reference_date' => $reference_date,
                        'selectable'     => $selectable,
                        'is_shipped'     => $is_shipped,
                        'products'       => $products,
                    );
                }
            }
        }



        // Passiamo le variabili a Smarty (il template)
        $this->context->smarty->assign(array(
            'is_logged'             => $is_logged,
            'customer_data'         => $customer_data,
            'orders'                => $orders,
            'disabled_orders_count' => $disabled_orders_count,
            'errors'                => $this->errors,
            'success'               => Tools::getValue('success'),
            'selected_order'        => Tools::getValue('ordine'),
            'resi_days'             => $resi_days,
            'resi_privacy_link'     => Configuration::get('BAZ_RESI_PRIVACY_LINK') !== false ? Configuration::get('BAZ_RESI_PRIVACY_LINK') : '/Privacy_Policy_sito_web.pdf',
            'resi_intro_text'       => Configuration::get('BAZ_RESI_INTRO_TEXT') !== false ? Configuration::get('BAZ_RESI_INTRO_TEXT') : '',
            // Visibilità campi configurabili dal back-office
            'show_allegato'         => Configuration::get('BAZ_RESI_SHOW_ALLEGATO') !== false ? (int)Configuration::get('BAZ_RESI_SHOW_ALLEGATO') : 1,
            'show_motivazione'      => Configuration::get('BAZ_RESI_SHOW_MOTIVAZIONE') !== false ? (int)Configuration::get('BAZ_RESI_SHOW_MOTIVAZIONE') : 1,
            'show_nota_bene'        => Configuration::get('BAZ_RESI_SHOW_NOTA_BENE') !== false ? (int)Configuration::get('BAZ_RESI_SHOW_NOTA_BENE') : 1,
        ));

        $this->setTemplate('module:baz_gestioneresi/views/templates/front/form_reso.tpl');
    }

    public function postProcess()
    {
        if (Tools::isSubmit('submit_reso')) {
            $this->processResoForm();
        }
        parent::postProcess();
    }

    protected function processResoForm()
    {
        // 1. Controllo Honeypot anti-bot
        if (!empty(Tools::getValue('reso_website_hp'))) {
            $this->errors[] = $this->module->l('Invio non valido.');
            return;
        }

        // 2. Validazione Privacy
        if (!Tools::getValue('privacy')) {
            $this->errors[] = $this->module->l('Devi accettare l\'informativa sulla privacy.');
            return;
        }

        // 3. Raccolta e sanificazione dati dal form
        $nome      = trim((string)Tools::getValue('nome'));
        $cognome   = trim((string)Tools::getValue('cognome'));
        $email     = trim((string)Tools::getValue('email'));
        $ordine    = trim((string)Tools::getValue('ordine'));
        $cellulare = trim((string)Tools::getValue('cellulare'));
        $messaggio = trim((string)Tools::getValue('messaggio')); // Facoltativo

        if (empty($nome) || empty($cognome) || empty($email) || empty($ordine)) {
            $this->errors[] = $this->module->l('I campi Nome, Cognome, Email e Ordine sono obbligatori.');
            return;
        }

        if (!Validate::isEmail($email)) {
            $this->errors[] = $this->module->l('L\'indirizzo email inserito non è valido.');
            return;
        }

        // 4. Validazione Ordine ed Email
        $order_obj = $this->findOrderByReferenceOrId($ordine);

        if ($this->context->customer->isLogged()) {
            if (!$order_obj || (int)$order_obj->id_customer !== (int)$this->context->customer->id || !$this->isOrderEligible($order_obj, $this->context->customer->id)) {
                $this->errors[] = $this->module->l('L\'ordine selezionato non è più eligibile per il reso/recesso oppure non appartiene al tuo account.');
                return;
            }
        } else {
            // Utente non loggato: verifica che l'ordine esista
            if (!$order_obj) {
                $this->errors[] = $this->module->l('Nessun ordine trovato corrispondente al codice indicato.');
                return;
            }

            // Verifica che l'email inserita corrisponda a quella dell'ordine
            $order_customer = new Customer((int)$order_obj->id_customer);
            if (!Validate::isLoadedObject($order_customer) || strtolower(trim($order_customer->email)) !== strtolower(trim($email))) {
                $this->errors[] = $this->module->l('L\'indirizzo email inserito non corrisponde a quello associato all\'ordine.');
                return;
            }

            // Verifica che l'ordine sia idoneo (non annullato e nei limiti di tempo)
            if (!$this->isOrderEligible($order_obj)) {
                $this->errors[] = $this->module->l('L\'ordine indicato non è più idoneo per la richiesta di reso (termine scaduto o ordine annullato).');
                return;
            }

            // Normalizziamo il riferimento ordine ufficiale
            $ordine = $order_obj->reference;
        }

        // Se l'utente è loggato, recuperiamo e validiamo i prodotti selezionati da rendere
        $selected_products = Tools::getValue('products_to_return');
        $products_detail   = array();
        
        $is_shipped = true;
        if ($this->context->customer->isLogged()) {
            $order_id = (int)$order_obj->id;
            $shipped_states = $this->parseStateIds(Configuration::get('BAZ_RESI_ORDER_STATE_SHIPPED'));
            if (!empty($shipped_states) && $order_id) {
                $history_data = Db::getInstance()->executeS(
                    'SELECT id_order_state FROM ' . _DB_PREFIX_ . 'order_history'
                    . ' WHERE id_order = ' . (int)$order_id
                    . ' AND id_order_state IN (' . implode(',', array_map('intval', $shipped_states)) . ')'
                );
                if (empty($history_data)) {
                    $is_shipped = false;
                }
            }
        }

        if ($this->context->customer->isLogged()) {
            $order_products = $order_obj->getProducts();

            if (!$is_shipped) {
                // Annullamento intero ordine: popola automaticamente tutti i prodotti
                foreach ($order_products as $op) {
                    $products_detail[] = array(
                        'id_order_detail' => (int)$op['id_order_detail'],
                        'qty'             => (int)$op['product_quantity'],
                        'name'            => $op['product_name'] . ($op['product_reference'] ? ' (Rif: ' . $op['product_reference'] . ')' : '')
                    );
                }
            } else {
                if (empty($selected_products) || !is_array($selected_products)) {
                    $this->errors[] = $this->module->l('Devi selezionare almeno un prodotto da rendere.');
                    return;
                }

                $ordered_qtys  = array();
                $product_names = array();
                foreach ($order_products as $op) {
                    $ordered_qtys[(int)$op['id_order_detail']]  = (int)$op['product_quantity'];
                    $product_names[(int)$op['id_order_detail']] = $op['product_name'] . ($op['product_reference'] ? ' (Rif: ' . $op['product_reference'] . ')' : '');
                }

                foreach ($selected_products as $id_order_detail) {
                    $id_order_detail = (int)$id_order_detail;
                    $qty             = (int)Tools::getValue('product_qty_' . $id_order_detail);

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
                        'qty'             => $qty,
                        'name'            => $product_names[$id_order_detail]
                    );
                }
            }
        }

        // Gestione Allegato (solo se il campo è presente nel form e il file è stato inviato)
        $attachment = null;
        if (isset($_FILES['allegato']) && !empty($_FILES['allegato']['name'])) {
            $file               = $_FILES['allegato'];
            $allowed_extensions = array('jpg', 'jpeg', 'png', 'pdf');
            $ext                = pathinfo($file['name'], PATHINFO_EXTENSION);

            if (!in_array(strtolower($ext), $allowed_extensions)) {
                $this->errors[] = $this->module->l('Formato file non valido. Ammessi solo JPG, PNG, PDF.');
                return;
            }

            // Prepariamo l'allegato per la mail
            $attachment = array(
                'content' => file_get_contents($file['tmp_name']),
                'name'    => $file['name'],
                'mime'    => $file['type']
            );
        }

        // Scrittura nel database (solo se utente loggato)
        $id_order_return = 0;
        if ($this->context->customer->isLogged()) {
            $order_id = (int)$order_obj->id;
            $db       = Db::getInstance();
            $db->insert('order_return', array(
                'id_customer' => (int)$this->context->customer->id,
                'id_order'    => (int)$order_id,
                'state'       => 1, // In attesa di conferma
                'question'    => pSQL($messaggio),
                'date_add'    => date('Y-m-d H:i:s'),
                'date_upd'    => date('Y-m-d H:i:s')
            ));

            $id_order_return = (int)$db->Insert_ID();

            if ($id_order_return) {
                foreach ($products_detail as $p) {
                    $db->insert('order_return_detail', array(
                        'id_order_return'  => (int)$id_order_return,
                        'id_order_detail'  => (int)$p['id_order_detail'],
                        'id_customization' => 0,
                        'product_quantity' => (int)$p['qty']
                    ));
                }
            }
        }

        // Generazione del riepilogo prodotti per email
        $prodotti_reso_html = '';
        $prodotti_reso_txt  = '';

        if ($this->context->customer->isLogged() && !empty($products_detail)) {
            $prodotti_reso_html  = '<h3 style="margin-top: 20px;">Prodotti da rendere:</h3>';
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

        // Blocchi condizionali per email: se il campo è vuoto non viene inclusa la riga
        $cellulare_riga_html = !empty($cellulare)
            ? '<li><strong>Cellulare:</strong> ' . htmlspecialchars($cellulare) . '</li>'
            : '';
        $cellulare_riga_txt = !empty($cellulare)
            ? '- Cellulare: ' . $cellulare . "\n"
            : '';

        // Versione per email admin (sezione separata con h3)
        $messaggio_sezione_html = !empty($messaggio)
            ? '<h3 style="margin-top: 20px;">Messaggio:</h3><p>' . nl2br(htmlspecialchars($messaggio)) . '</p>'
            : '';
        // Versione per email cliente (riga di lista)
        $messaggio_riga_html = !empty($messaggio)
            ? '<li><strong>Messaggio:</strong><br />' . nl2br(htmlspecialchars($messaggio)) . '</li>'
            : '';
        $messaggio_sezione_txt = !empty($messaggio)
            ? "- Messaggio:\n" . $messaggio . "\n"
            : '';

        // Invio Email (Usa in automatico la config SMTP di PrestaShop)
        $template_vars = array(
            '{data_ora}'              => date('d/m/Y H:i:s'),
            '{nome}'                  => $nome,
            '{cognome}'               => $cognome,
            '{email}'                 => $email,
            '{ordine}'                => $ordine,
            // Blocchi condizionali (vuoti se il campo non è stato compilato)
            '{cellulare_riga_html}'   => $cellulare_riga_html,
            '{cellulare_riga_txt}'    => $cellulare_riga_txt,
            '{messaggio_sezione_html}'=> $messaggio_sezione_html,
            '{messaggio_riga_html}'   => $messaggio_riga_html,
            '{messaggio_sezione_txt}' => $messaggio_sezione_txt,
            '{prodotti_reso_html}'    => $prodotti_reso_html,
            '{prodotti_reso_txt}'     => $prodotti_reso_txt,
            '{intro_text}'            => Configuration::get('BAZ_RESI_INTRO_TEXT') !== false ? Configuration::get('BAZ_RESI_INTRO_TEXT') : '',
            '{customer_email_text}'   => Configuration::get('BAZ_RESI_CUSTOMER_EMAIL_TEXT') !== false ? Configuration::get('BAZ_RESI_CUSTOMER_EMAIL_TEXT') : ''
        );

        // Invio al gestore del negozio (o alle email configurate)
        $emails_config = Configuration::get('BAZ_RESI_EMAILS');
        if (empty($emails_config)) {
            $to_list = array(Configuration::get('PS_SHOP_EMAIL'));
        } else {
            // Supporto a più indirizzi separati da virgola
            $to_list = array_map('trim', explode(',', $emails_config));
        }

        $mail_success = false;
        foreach ($to_list as $to) {
            if (Validate::isEmail($to)) {
                $sent = Mail::Send(
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
                    dirname(__FILE__) . '/../../mails/',
                    false,
                    (int)$this->context->shop->id
                );
                if ($sent) {
                    $mail_success = true;
                }
            }
        }

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
                dirname(__FILE__) . '/../../mails/',
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

    /**
     * Converte una stringa CSV di ID stato (es. "2,5,9") in un array di interi.
     *
     * @param mixed $value Valore da Configuration::get()
     * @return array
     */
    protected function parseStateIds($value)
    {
        if ($value === false || $value === null || $value === '') {
            return array();
        }
        return array_values(array_filter(array_map('intval', explode(',', (string)$value))));
    }

    /**
     * Determina la data di riferimento per il calcolo dei giorni reso.
     * Priorità: stati "Consegnato" > stati "Pagamento accettato" > data ordine.
     *
     * @param int    $id_order
     * @param string $fallback_date     Data dell'ordine (fallback)
     * @param array  $history           Storico stati indicizzato per id_order
     * @param array  $delivered_states  ID stati "Consegnato"
     * @param array  $payment_states    ID stati "Pagamento accettato"
     * @return string
     */
    protected function getReferenceDateForOrder($id_order, $fallback_date, array $history, array $delivered_states, array $payment_states)
    {
        $reference_date = null;
        if (!empty($history[$id_order])) {
            // Prima cerchiamo uno stato "Consegnato"
            foreach ($history[$id_order] as $row) {
                if (!empty($delivered_states) && in_array((int)$row['id_order_state'], $delivered_states)) {
                    $reference_date = $row['date_add'];
                }
            }
            // Poi uno stato "Pagamento accettato" (solo se non trovato il consegnato)
            if (!$reference_date) {
                foreach ($history[$id_order] as $row) {
                    if (!empty($payment_states) && in_array((int)$row['id_order_state'], $payment_states)) {
                        $reference_date = $row['date_add'];
                    }
                }
            }
        }

        return $reference_date ? $reference_date : $fallback_date;
    }

    /**
     * Trova un oggetto Order a partire dal riferimento (anche con eventuale #) o dall'ID ordine.
     *
     * @param string|int $ordine
     * @return Order|null
     */
    protected function findOrderByReferenceOrId($ordine)
    {
        $clean_ordine = trim((string)$ordine);
        if (empty($clean_ordine)) {
            return null;
        }

        $stripped_ordine = ltrim($clean_ordine, '#');

        $sql = 'SELECT id_order FROM ' . _DB_PREFIX_ . 'orders 
                WHERE reference = \'' . pSQL($clean_ordine) . '\'
                   OR reference = \'' . pSQL($stripped_ordine) . '\'';

        if (is_numeric($stripped_ordine) && (int)$stripped_ordine > 0) {
            $sql .= ' OR id_order = ' . (int)$stripped_ordine;
        }

        $id_order = (int)Db::getInstance()->getValue($sql);
        if ($id_order > 0) {
            $order = new Order($id_order);
            if (Validate::isLoadedObject($order)) {
                return $order;
            }
        }

        return null;
    }

    /**
     * Verifica se un ordine (per reference o oggetto Order) è ancora eleggibile al reso.
     *
     * @param string|Order $order_param Riferimento ordine o istanza di Order
     * @param int|null     $customer_id ID cliente opzionale per verifica proprietà
     * @return bool
     */
    protected function isOrderEligible($order_param, $customer_id = null)
    {
        if ($order_param instanceof Order) {
            $order = $order_param;
        } else {
            $order = $this->findOrderByReferenceOrId($order_param);
        }

        if (!$order || !Validate::isLoadedObject($order)) {
            return false;
        }

        if ($customer_id !== null && (int)$order->id_customer !== (int)$customer_id) {
            return false;
        }

        // Escludi gli ordini annullati
        if (isset($order->current_state) && (int)$order->current_state === (int)Configuration::get('PS_OS_CANCELED')) {
            return false;
        }

        $resi_days        = Configuration::get('BAZ_RESI_DAYS') !== false ? (int)Configuration::get('BAZ_RESI_DAYS') : 14;
        $delivered_states = $this->parseStateIds(Configuration::get('BAZ_RESI_ORDER_STATE_DELIVERED'));
        $payment_states   = $this->parseStateIds(Configuration::get('BAZ_RESI_ORDER_STATE_PAYMENT_ACCEPTED'));
        $reference_date   = $this->getOrderReferenceDate($order->id, $order->date_add, $delivered_states, $payment_states);

        if (!$reference_date) {
            return false;
        }

        $reference_timestamp = strtotime($reference_date);
        if ($reference_timestamp === false) {
            return false;
        }

        return (time() - $reference_timestamp) <= ($resi_days * 86400);
    }

    /**
     * Recupera la data di riferimento per un singolo ordine interrogando direttamente la tabella storico.
     *
     * @param int    $id_order
     * @param string $fallback_date
     * @param array  $delivered_states
     * @param array  $payment_states
     * @return string
     */
    protected function getOrderReferenceDate($id_order, $fallback_date, array $delivered_states, array $payment_states)
    {
        $reference_date = null;
        $state_ids      = array_filter(array_merge($delivered_states, $payment_states));

        if (!empty($state_ids)) {
            $history_data = Db::getInstance()->executeS(
                'SELECT id_order_state, date_add FROM ' . _DB_PREFIX_ . 'order_history'
                . ' WHERE id_order = ' . (int)$id_order
                . ' AND id_order_state IN (' . implode(',', array_map('intval', $state_ids)) . ')'
                . ' ORDER BY date_add ASC'
            );

            if ($history_data) {
                // Prima: stati consegnato
                foreach ($history_data as $row) {
                    if (!empty($delivered_states) && in_array((int)$row['id_order_state'], $delivered_states)) {
                        $reference_date = $row['date_add'];
                    }
                }
                // Poi: stati pagamento (solo se consegnato non trovato)
                if (!$reference_date) {
                    foreach ($history_data as $row) {
                        if (!empty($payment_states) && in_array((int)$row['id_order_state'], $payment_states)) {
                            $reference_date = $row['date_add'];
                        }
                    }
                }
            }
        }

        return $reference_date ? $reference_date : $fallback_date;
    }
}
