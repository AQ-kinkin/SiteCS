/**
 * JavaScript pour la page resident.php
 * Gestion des onglets des halls
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('Initialisation page resident');

    // Onglets halls
    const tabButtons = document.querySelectorAll('.tab-btn');
    tabButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const tabId = this.dataset.tab;

            // Désactiver tous les onglets
            tabButtons.forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));

            // Activer l'onglet cliqué
            this.classList.add('active');
            document.getElementById(tabId).classList.add('active');

            console.log('Hall sélectionné:', tabId);
        });
    });
});

function show_appartement(lot, objsrc) {
    // Cacher le tableau
    var hall_list = objsrc.closest('.hall-content');
    hall_list.style.display = 'none';
    var div_contenu_key = hall_list.parentElement;
    
    console.log('lot : ' + lot + ' -- nom : ' + hall_list.id);
    fetch('bureau_cs/info_appart.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'lot=' + encodeURIComponent(lot)
        })
        .then(response => {
            //if (!response.ok) throw new Error("Erreur HTTP");
            return response.text();
            }
        )
        .then(html => {
            var info_div = document.createElement('div');
            info_div.className = 'info-appart';
            info_div.innerHTML = html;
            div_contenu_key.appendChild(info_div);
            }
        )
        .catch(error => {
            hall_list.style.display = '';
            console.error("Erreur fetch : ", error);
            }
        );

}

function retour_hall(btn) {
    var tab_pane = btn.closest('.tab-pane');
    var info_div = tab_pane.querySelector('.info-appart');
    if (info_div) info_div.remove();
    tab_pane.querySelector('.hall-content').style.display = '';
}