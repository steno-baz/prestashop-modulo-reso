{extends file='page.tpl'}

{block name='page_content'}
<style type="text/css">
.reso-container ol li,.reso-container ul li { padding: 2px; margin-left: 30px;}
select option:disabled {
    color: #b5b5b5;
}
</style>
    <div class="reso-container page-cms">
        <h2>Istruzioni per il Reso/Recesso</h2>
        <div class="reso-instructions" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa;">
            {$resi_intro_text nofilter}
            <hr>
            {if $show_nota_bene}
            <p class="mb-0"><strong>Nota Bene:</strong> Ti ricordiamo che il reso/recesso è possibile entro <strong>{$resi_days} giorni</strong> dalla consegna della merce (o dall'acquisto).</p>
            {/if}
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

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="nome">Nome *</label>
                            <input type="text" name="nome" id="nome" class="form-control" value="{if $is_logged}{$customer_data.firstname}{/if}" required>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="cognome">Cognome *</label>
                            <input type="text" name="cognome" id="cognome" class="form-control" value="{if $is_logged}{$customer_data.lastname}{/if}" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" name="email" id="email" class="form-control" value="{if $is_logged}{$customer_data.email}{/if}" required>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="cellulare">Cellulare</label>
                            <input type="text" name="cellulare" id="cellulare" class="form-control">
                        </div>
                    </div>
                </div>






                <div class="form-group">
                    <label for="ordine">Codice Ordine *</label>
                    {if $is_logged && !empty($orders)}
                        <select name="ordine" id="ordine" class="form-control" required>
                            <option value="">-- Seleziona il tuo ordine --</option>
                            {foreach from=$orders item=order}
                                <option value="{$order.reference}" {if $selected_order == $order.reference}selected{/if} {if !$order.selectable}disabled{/if}>
                                    {if !$order.selectable}[FUORI TEMPO] - {/if}Ordine {$order.reference} del {$order.date|date_format:"%d/%m/%Y"}
                                </option>
                            {/foreach}
                        </select>
                        {if $disabled_orders_count > 0}
                            <small class="form-text text-muted">Attenzione: alcuni ordini potrebbero non essere selezionabili perché oltre {$resi_days} giorni dalla data di consegna o dell'ordine.</small>
                        {else}
                            <small class="form-text text-muted">Gli ordini oltre {$resi_days} giorni dalla data di consegna o, se non consegnato, dalla data del pagamento non sono selezionabili.</small>
                        {/if}

                        <div id="products-selection-container" style="margin-top: 15px; display: none;">
                            <label><strong>Seleziona i prodotti da rendere: *</strong></label>
                            {foreach from=$orders item=order}
                                {if $order.selectable && !empty($order.products)}
                                    <div id="products-order-{$order.reference}" class="order-products-list" style="display: none; border: 1px solid #ddd; padding: 15px; border-radius: 4px; background: #fff; margin-bottom: 15px;">
                                        <div style="margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                                            <span style="font-size: 0.9rem; color: #666;">Seleziona i prodotti dell'ordine {$order.reference}</span>
                                            <button type="button" class="btn btn-outline-secondary btn-sm select-all-products-btn" data-order-ref="{$order.reference}">Seleziona tutto</button>
                                        </div>
                                        {foreach from=$order.products item=product}
                                            <div class="product-item" style="display: flex; align-items: center; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee;">
                                                <div style="display: flex; align-items: center; flex-grow: 1; margin-right: 15px;">
                                                    <input type="checkbox" name="products_to_return[]" value="{$product.id_order_detail}" class="product-select-chk" style="margin-right: 10px; width: 18px; height: 18px; cursor: pointer;">
                                                    <span>
                                                        <strong>{$product.product_name}</strong>
                                                        {if $product.product_reference}<br><small class="text-muted">Rif: {$product.product_reference}</small>{/if}
                                                    </span>
                                                </div>
                                                <div style="display: flex; align-items: center; width: 120px; justify-content: flex-end;">
                                                    <span style="margin-right: 8px; font-size: 0.85rem; color: #666;">Qta:</span>
                                                    <input type="number" name="product_qty_{$product.id_order_detail}" value="{$product.product_quantity}" min="1" max="{$product.product_quantity}" class="form-control product-qty-input" style="width: 70px; padding: 5px; height: auto;" disabled>
                                                </div>
                                            </div>
                                        {/foreach}
                                    </div>
                                {/if}
                            {/foreach}
                        </div>
                    {else}
                        <input type="text" name="ordine" id="ordine" class="form-control" placeholder="Es. XXXXXX" value="{$selected_order|escape:'htmlall':'UTF-8'}" required>
                        {if $is_logged}<small class="form-text text-muted">Non abbiamo trovato ordini recenti nel tuo account, inseriscilo manualmente.</small>{/if}
                    {/if}
                </div>

                {if $show_motivazione}
                <div class="form-group">
                    <label for="messaggio">Motivazione del reso</label>
                    <textarea name="messaggio" id="messaggio" rows="5" class="form-control" placeholder="Descrivi il motivo del reso..."></textarea>
                </div>
                {/if}

                {if $show_allegato}
                <div class="form-group">
                    <label for="allegato">Allegato (Foto/Documento - max 4MB)</label>
                    <input type="file" name="allegato" id="allegato" class="form-control-file">
                </div>
                {/if}

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

<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    var $ = window.jQuery;
    if (!$) return;

    function handleOrderChange() {
        var selectedRef = $('#ordine').val();
        
        // Nascondi il contenitore principale
        $('#products-selection-container').hide();
        // Nascondi tutte le liste, deseleziona i prodotti e disabilita gli input quantità
        $('.order-products-list').hide().find('.product-select-chk').prop('checked', false);
        $('.order-products-list').find('.product-qty-input').prop('disabled', true);

        if (selectedRef) {
            var $activeList = $('#products-order-' + selectedRef);
            if ($activeList.length) {
                $('#products-selection-container').show();
                $activeList.show();
            }
        }
    }

    // Inizializzazione al caricamento e al cambio ordine
    $('#ordine').on('change', handleOrderChange);
    if ($('#ordine').val()) {
        handleOrderChange();
    }

    // Abilita/Disabilita l'input quantità al click sulla checkbox del prodotto
    $(document).on('change', '.product-select-chk', function() {
        var $qtyInput = $(this).closest('.product-item').find('.product-qty-input');
        if ($(this).is(':checked')) {
            $qtyInput.prop('disabled', false);
        } else {
            $qtyInput.prop('disabled', true);
        }
    });

    // Gestione del pulsante Seleziona Tutto
    $(document).on('click', '.select-all-products-btn', function() {
        var ref = $(this).data('order-ref');
        var $list = $('#products-order-' + ref);
        
        $list.find('.product-select-chk').each(function() {
            $(this).prop('checked', true);
            var $qtyInput = $(this).closest('.product-item').find('.product-qty-input');
            $qtyInput.prop('disabled', false);
            // Imposta al valore massimo consentito (quantità ordinata)
            $qtyInput.val($qtyInput.attr('max'));
        });
    });
});
</script>
{/block}