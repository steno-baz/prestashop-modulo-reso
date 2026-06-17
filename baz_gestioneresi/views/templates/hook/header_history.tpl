<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    // Cerchiamo tutti i link "Dettagli" nella tabella storico ordini (PrestaShop 1.7 standard)
    var orderLinks = document.querySelectorAll('a[data-link-action="view-order-details"]');
    
    orderLinks.forEach(function(link) {
        var td = link.parentElement;
        var tr = td.parentElement;
        
        // Cerchiamo il codice dell'ordine (solitamente in un <th> con scope="row")
        var orderRefElement = tr.querySelector('th[scope="row"]');
        var orderRef = '';
        if (orderRefElement) {
            orderRef = orderRefElement.innerText.trim();
        }
        
        // Creiamo il nuovo link per il reso
        var resoLink = document.createElement('a');
        // Passiamo l'id dell'ordine nell'url così potremmo autoselezionarlo in futuro
        resoLink.href = '{$baz_reso_link}' + (orderRef ? '?ordine=' + encodeURIComponent(orderRef) : '');
        resoLink.innerText = '{$baz_reso_label}';
        
        // Stile per farlo assomigliare ai bottoni standard ma distinguerlo
        resoLink.style.marginLeft = '10px';
        resoLink.style.color = '#d9534f'; // Un rosso tenue per indicare "Reso"
        resoLink.style.fontWeight = 'bold';
        resoLink.className = 'baz-reso-history-link';
        
        // Inseriamo il link dentro la cella delle azioni, alla fine
        td.appendChild(resoLink);
    });
});
</script>
