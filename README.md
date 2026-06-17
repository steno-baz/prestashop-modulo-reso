# Modulo PrestaShop: Gestione Resi Avanzata (`baz_gestioneresi`)

Questo modulo per PrestaShop 1.7 fornisce una pagina dedicata sul front-end per permettere ai clienti (sia loggati che guest) di inviare facilmente una richiesta di reso.

## Funzionalità Principali

1. **Pagina Dedicata (Front Controller)**: Il modulo crea una pagina pubblica accessibile ai clienti contenente un form di richiesta reso.
2. **Autocompilazione per Utenti Loggati**: Se il cliente è loggato, il form precompila automaticamente nome, cognome, email.
3. **Selezione degli Ordini**: Gli utenti loggati vedono un menu a tendina con i propri ordini recenti, mentre gli utenti guest possono inserire manualmente il codice del loro ordine.
4. **Caricamento Allegati**: Possibilità per l'utente di caricare una foto o un documento PDF (max 4MB) a supporto della richiesta.
5. **Informativa Privacy**: Checkbox obbligatoria per l'accettazione del trattamento dati.
6. **Notifica Email**: Una volta compilato il form, il sistema invia un'email automatica al Servizio Clienti (all'indirizzo email configurato in PrestaShop) contenente tutti i dati e gli eventuali allegati, utilizzando le impostazioni SMTP native di PrestaShop.

## Come Personalizzare l'URL della Pagina (Friendly URL)

Di base, PrestaShop assegna al modulo un URL tecnico del tipo:
`https://www.tuosito.it/module/baz_gestioneresi/reso`

Se hai attivato i "Friendly URL" (URL Comprensivi) sul tuo negozio, puoi personalizzare questo indirizzo (ad esempio trasformandolo in `https://www.tuosito.it/gestione-resi`) direttamente dal Back Office, **senza dover modificare alcun file di codice**.

### Procedura passo-passo dal Back Office:

1. Nel menu principale a sinistra, vai su **Parametri Negozio > Traffico & SEO** (nella sezione "Configura").
2. Assicurati di essere nella scheda **SEO & URLs**.
3. Scorri fino in fondo alla lista delle pagine esistenti e clicca sul pulsante **"Aggiungi una nuova pagina"**.
4. Compila il modulo nel seguente modo:
   - **Pagina**: Apri il menu a tendina e cerca l'opzione `module-baz_gestioneresi-reso` (si trova generalmente verso la fine della lista, tra le pagine dei moduli).
   - **Titolo della pagina**: Inserisci il titolo che preferisci (es. `Gestione Resi`). Questo apparirà come titolo (tag `<title>`) nella scheda del browser.
   - **URL Comprensivo**: Qui scrivi l'URL pulito che desideri utilizzare. Ad esempio, digitando `gestione-resi` la pagina sarà raggiungibile a `/gestione-resi`.
5. Clicca su **Salva**.

Da questo momento la pagina del modulo risponderà al nuovo indirizzo amichevole che hai scelto. Potrai quindi usare questo link per inserirlo nel menu principale del sito o nel footer.

---

## Appunti per Sviluppatori e Manutenzione

Se in futuro si effettuano modifiche ai file PHP del modulo, ricorda che:
- **Convenzione Nomi (Case Sensitivity)**: In PrestaShop 1.7, il nome della classe del modulo (`Baz_gestioneresi`) deve avere solo la prima lettera maiuscola, e deve corrispondere in minuscolo al nome della cartella (`baz_gestioneresi`) e del file PHP (`baz_gestioneresi.php`).
- **Svuotamento Cache**: PrestaShop utilizza una cache molto aggressiva. Qualsiasi modifica strutturale o aggiunta di nuovi moduli via FTP non sarà visibile finché la cache non verrà svuotata (dal Back Office in *Parametri Avanzati > Prestazioni*, oppure eliminando il contenuto di `var/cache/`).
- **Sintassi Smarty**: I file di template (come `form_reso.tpl`) utilizzano la sintassi Smarty. I cicli `foreach` si chiudono obbligatoriamente con il tag `{/foreach}` (non usare `{endforeach}`).
