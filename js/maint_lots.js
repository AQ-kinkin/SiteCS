/**
 * JavaScript pour la page de maintenance des lots
 * Gestion des onglets, filtres et soumission des formulaires
 */

// Gestion des onglets bâtiments
document.addEventListener('DOMContentLoaded', function() {
    console.log('Initialisation page maintenance lots');
    
    // Onglets bâtiments
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
            
            console.log('Bâtiment sélectionné:', tabId);
        });
    });
    
    // Onglets halls
    const hallButtons = document.querySelectorAll('.hall-btn');
    hallButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const hallId = this.dataset.hall;
            const hallsContainer = this.closest('.halls-tabs');
            
            // Désactiver tous les halls de ce bâtiment
            hallsContainer.querySelectorAll('.hall-btn').forEach(b => b.classList.remove('active'));
            hallsContainer.querySelectorAll('.hall-pane').forEach(p => p.classList.remove('active'));
            
            // Activer le hall cliqué
            this.classList.add('active');
            hallsContainer.querySelector('#' + hallId).classList.add('active');
            
            console.log('Hall sélectionné:', hallId);
        });
    });
});

/**
 * Soumet un formulaire de lot
 * @param {HTMLElement} button Bouton qui a déclenché la soumission
 */
function submitLotForm(button) {
    console.log('Soumission formulaire lot');

    const form = button.closest('form');

    // Si le bouton est "Modifier", passer en mode édition
    if (button.textContent === 'Modifier') {
        console.log('Passage en mode édition');
        button.textContent = 'Valider';
        button.classList.remove('btn-modify');
        // Activer les champs
        const inputs = form.querySelectorAll('input, select');
        inputs.forEach(input => {
            input.disabled = false;
        });
        return;
    }

    const data = new FormData(form);

    // Désactiver le bouton pendant l'envoi
    button.disabled = true;
    const originalText = button.textContent;
    button.textContent = 'Envoi...';

    // Log des données envoyées
    console.log('Données:', Object.fromEntries(data));

    fetch('maintenance/maint_lot_validation.php', {
        method: 'POST',
        body: data
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', [...response.headers.entries()]);

        if (!response.ok) {
            throw new Error('Erreur réseau: ' + response.status);
        }

        return response.text();
    })
    .then(text => {
        console.log('Réponse brute:', text);
        try {
            const result = JSON.parse(text);
            console.log('Résultat parsé:', result);

            if (result.success) {
                showMessage('Succès: ' + result.message, 'success');
                // Changer le bouton en "Modifier"
                button.textContent = 'Modifier';
                button.classList.add('btn-modify');
                button.disabled = false;
                // Rendre les champs readonly
                const inputs = form.querySelectorAll('input, select');
                inputs.forEach(input => {
                    input.disabled = true;
                });
            } else {
                showMessage('Erreur: ' + result.message, 'error');
                // Réactiver le bouton
                button.disabled = false;
                button.textContent = originalText;
            }
        } catch (e) {
            console.error('Erreur de parsing JSON:', e);
            showMessage('Erreur de parsing JSON: ' + text.substring(0, 100), 'error');
            // Réactiver le bouton
            button.disabled = false;
            button.textContent = originalText;
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showMessage('Erreur réseau: ' + error.message, 'error');
        // Réactiver le bouton
        button.disabled = false;
        button.textContent = originalText;
    });
}

/**
 * Affiche un message à l'utilisateur
 * @param {string} message Message à afficher
 * @param {string} type Type de message (success, error, info)
 */
function showMessage(message, type = 'info') {
    // Créer l'élément de message
    const messageDiv = document.createElement('div');
    messageDiv.className = 'toast-message toast-' + type;
    messageDiv.textContent = message;
    
    // Ajouter au DOM
    document.body.appendChild(messageDiv);
    
    // Afficher avec animation
    setTimeout(() => messageDiv.classList.add('show'), 10);
    
    // Masquer et supprimer après 5 secondes pour les erreurs, 3 pour le reste
    const duration = type === 'error' ? 5000 : 3000;
    setTimeout(() => {
        messageDiv.classList.remove('show');
        setTimeout(() => messageDiv.remove(), 300);
    }, duration);
}

/**
 * Toggle le filtre des lots non traités
 */
function toggleFiltre() {
    const currentUrl = new URL(window.location.href);
    const hasFiltre = currentUrl.searchParams.get('filtre') === 'non_traites';
    
    if (hasFiltre) {
        // Retirer le filtre
        currentUrl.searchParams.delete('filtre');
    } else {
        // Ajouter le filtre
        currentUrl.searchParams.set('filtre', 'non_traites');
    }
    
    // Recharger la page avec le nouveau paramètre
    window.location.href = currentUrl.toString();
}
