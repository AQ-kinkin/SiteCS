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