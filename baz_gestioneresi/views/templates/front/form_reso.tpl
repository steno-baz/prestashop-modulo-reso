{extends file='page.tpl'}

{block name='page_content'}
    <div class="reso-container page-cms">
        <h2>Istruzioni per il Reso</h2>
        <div class="reso-instructions" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa;">
            {$resi_intro_text nofilter}
            <hr>
            <p class="mb-0"><strong>Nota Bene:</strong> Ti ricordiamo che il reso/recesso è possibile entro <strong>{$resi_days} giorni</strong> dalla consegna della merce (o dall'acquisto).</p>
        </div>

        {if $success}
            <div class="alert alert-success">
                La tua richiesta di reso è stata inviata con successo! Ti risponderemo via email il prima possibile.
            </div>
        {else}
            {if $errors}
                <div class="alert alert-danger">
                    <ul>
                        {foreach from=$errors item=error}
                            <li>{$error}</li>
                        {/foreach}
                    </ul>
                </div>
            {/if}

            <form action="" method="post" enctype="multipart/form-data" class="form-reso-custom">
                
                <div class="form-group">
                    <label for="nome">Nome *</label>
                    <input type="text" name="nome" id="nome" class="form-control" value="{if $is_logged}{$customer_data.firstname}{/if}" required>
                </div>

                <div class="form-group">
                    <label for="cognome">Cognome *</label>
                    <input type="text" name="cognome" id="cognome" class="form-control" value="{if $is_logged}{$customer_data.lastname}{/if}" required>
                </div>

                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" name="email" id="email" class="form-control" value="{if $is_logged}{$customer_data.email}{/if}" required>
                </div>

                <div class="form-group">
                    <label for="cellulare">Cellulare</label>
                    <input type="text" name="cellulare" id="cellulare" class="form-control">
                </div>

                <div class="form-group">
                    <label for="ordine">Codice Ordine *</label>
                    {if $is_logged && !empty($orders)}
                        <select name="ordine" id="ordine" class="form-control" required>
                            <option value="">-- Seleziona il tuo ordine --</option>
                            {foreach from=$orders item=order}
                                <option value="{$order.reference}" {if $selected_order == $order.reference}selected{/if} {if !$order.selectable}disabled{/if}>
                                    Ordine {$order.reference} del {$order.date|date_format:"%d/%m/%Y"}{if !$order.selectable} - non selezionabile{/if}
                                </option>
                            {/foreach}
                        </select>
                        <small class="form-text text-muted">Gli ordini bloccati sono oltre {$resi_days} giorni dalla data di consegna o dalla data in cui lo stato è diventato "pagamento accettato".</small>
                    {else}
                        <input type="text" name="ordine" id="ordine" class="form-control" placeholder="Es. XXXXXX" value="{$selected_order|escape:'htmlall':'UTF-8'}" required>
                        {if $is_logged}<small class="form-text text-muted">Non abbiamo trovato ordini recenti nel tuo account, inseriscilo manualmente.</small>{/if}
                    {/if}
                </div>

                <div class="form-group">
                    <label for="messaggio">Motivazione del reso / Messaggio *</label>
                    <textarea name="messaggio" id="messaggio" rows="5" class="form-control" required placeholder="Descrivi il motivo del reso..."></textarea>
                </div>

                <div class="form-group">
                    <label for="allegato">Allegato (Foto/Documento - max 4MB)</label>
                    <input type="file" name="allegato" id="allegato" class="form-control-file">
                </div>

                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="privacy" value="1" required>
                        Accetto il trattamento dei dati personali secondo la <a href="{$resi_privacy_link}" target="_blank">Privacy Policy</a> *
                    </label>
                </div>

                <button type="submit" name="submit_reso" class="btn btn-primary" style="margin-top: 15px;">Invia Richiesta di Reso</button>
            </form>
        {/if}
    </div>
{/block}