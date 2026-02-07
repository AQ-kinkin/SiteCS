document.addEventListener('DOMContentLoaded', function() {
    const icon = document.getElementById('user-menu-icon');
    const dropdown = document.getElementById('user-dropdown');
    
    if (icon && dropdown) {
        icon.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.classList.toggle('show');
        });
        
        // Fermer le menu si on clique ailleurs
        document.addEventListener('click', function(e) {
            if (!icon.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.remove('show');
            }
        });
    }

    // Gestion du formulaire user_infos.php
    initUserInfosPage();
});

/**
 * Initialisation de la page d'informations utilisateur
 */
function initUserInfosPage() {
    // Vérifier si on est sur la page user_infos
    if (!document.getElementById('userName')) {
        return; // Pas sur la page user_infos
    }

    // Les données sont injectées via PHP dans des attributs data-*
    loadUserData();
}

/**
 * Charge les données utilisateur dans le formulaire
 */
function loadUserData() {
    // Récupérer les données depuis les attributs data-* du body
    const userData = document.body.dataset;
    
    if (userData.userName) {
        document.getElementById('userName').textContent = userData.userName;
    }
    if (userData.userBadge) {
        const badge = document.getElementById('userBadge');
        badge.textContent = userData.userBadge;
        badge.className = 'badge ' + userData.userBadgeClass;
    }
    if (userData.userId) {
        document.getElementById('userId').textContent = userData.userId;
    }
    if (userData.userDate) {
        document.getElementById('userDate').textContent = userData.userDate;
    }
    if (userData.userIdent) {
        document.getElementById('userIdent').value = userData.userIdent;
    }
    if (userData.userEmail) {
        document.getElementById('userEmail').value = userData.userEmail;
    }
    if (userData.userPhone) {
        document.getElementById('userPhone').value = userData.userPhone;
    }
    if (userData.userAddress) {
        document.getElementById('userAddress').value = userData.userAddress;
    }
    
    // Avatar
    const avatar = document.getElementById('userAvatar');
    if (avatar && userData.userInitials) {
        avatar.innerHTML = userData.userInitials;
        if (userData.userIsLocataire === 'true') {
            avatar.classList.add('locataire');
        }
    }
    
    // Lots
    if (userData.lotsJson) {
        const lots = JSON.parse(userData.lotsJson);
        displayLots(lots);
    }
}

/**
 * Affiche les lots de l'utilisateur
 */
function displayLots(lots) {
    const container = document.getElementById('lotsContainer');
    const countElement = document.getElementById('lotsCount');
    
    if (!container) return;
    
    countElement.textContent = lots.length;
    container.innerHTML = '';
    
    if (lots.length === 0) {
        container.innerHTML = '<p style="color: #78716c; text-align: center;">Aucun lot associé</p>';
        return;
    }
    
    lots.forEach(lot => {
        const card = createLotCard(lot);
        container.appendChild(card);
    });
}

/**
 * Crée une card HTML pour un lot
 */
function createLotCard(lot) {
    const div = document.createElement('div');
    const typeClass = lot.type_code.toLowerCase();
    
    div.className = `lot-card ${typeClass}`;
    div.innerHTML = `
        <div class="lot-header">
            <div class="lot-icon-wrapper">
                <div class="lot-icon ${typeClass}">
                    ${getLotIcon(lot.type_code)}
                </div>
                <div class="lot-title">
                    <h3>${lot.type_libelle}</h3>
                    <div class="lot-reference">Lot ${lot.lot}</div>
                </div>
            </div>
            <div class="lot-id">#${lot.lot}</div>
        </div>
        <div class="lot-details">
            ${lot.repere ? `
            <div class="lot-detail">
                <span class="lot-detail-label">Repère</span>
                <span class="lot-detail-value">${lot.repere}</span>
            </div>
            ` : ''}
            <div class="lot-detail">
                <span class="lot-detail-label">N° Client Syndic</span>
                <span class="lot-detail-value">${lot.num_client_syndic}</span>
            </div>
        </div>
    `;
    
    return div;
}

/**
 * Retourne l'icône SVG pour un type de lot
 */
function getLotIcon(typeCode) {
    const icons = {
        'APPART': '<svg class="icon-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>',
        'CAVE': '<svg class="icon-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"></path></svg>',
        'PARK': '<svg class="icon-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M7 17m-2 0a2 2 0 104 0 2 2 0 10-4 0"></path><path d="M17 17m-2 0a2 2 0 104 0 2 2 0 10-4 0"></path><path d="M5 17H3v-6l2-5h9l4 5h1a2 2 0 012 2v4h-2m-4 0H9"></path></svg>'
    };
    return icons[typeCode] || '';
}

/**
 * Active le mode édition du formulaire
 */
function toggleEdit() {
    const fields = ['userEmail', 'userPhone', 'userAddress'];
    fields.forEach(id => {
        const field = document.getElementById(id);
        if (field) field.disabled = false;
    });
    
    document.getElementById('editButtons').classList.add('hidden');
    document.getElementById('saveButtons').classList.remove('hidden');
    document.getElementById('passwordSection').classList.remove('hidden');
}

/**
 * Annule le mode édition
 */
function cancelEdit() {
    const fields = ['userEmail', 'userPhone', 'userAddress'];
    fields.forEach(id => {
        const field = document.getElementById(id);
        if (field) field.disabled = true;
    });
    
    document.getElementById('editButtons').classList.remove('hidden');
    document.getElementById('saveButtons').classList.add('hidden');
    document.getElementById('passwordSection').classList.add('hidden');
    document.getElementById('passwordForm').classList.add('hidden');
    
    // Recharger les données originales
    loadUserData();
}

/**
 * Sauvegarde les modifications
 */
async function saveChanges() {
    const email = document.getElementById('userEmail').value;
    const phone = document.getElementById('userPhone').value;
    const address = document.getElementById('userAddress').value;
    
    // TODO: Envoyer les données au serveur via AJAX
    console.log('Save:', { email, phone, address });
    
    showMessage('Modifications enregistrées avec succès', 'success');
    cancelEdit();
}

/**
 * Affiche un message temporaire
 */
function showMessage(text, type) {
    const message = document.getElementById('message');
    message.textContent = text;
    message.className = 'message ' + type;
    message.classList.remove('hidden');
    
    setTimeout(() => {
        message.classList.add('hidden');
    }, 3000);
}

/**
 * Toggle l'affichage du formulaire de changement de mot de passe
 */
function togglePasswordChange() {
    const form = document.getElementById('passwordForm');
    const toggleText = document.getElementById('passwordToggleText');
    
    if (form.classList.contains('hidden')) {
        form.classList.remove('hidden');
        toggleText.textContent = 'Annuler le changement de mot de passe';
    } else {
        form.classList.add('hidden');
        toggleText.textContent = 'Changer le mot de passe';
        // Reset des champs
        document.getElementById('currentPassword').value = '';
        document.getElementById('newPassword').value = '';
        document.getElementById('confirmPassword').value = '';
    }
}

/**
 * Toggle la visibilité d'un champ password
 */
function togglePasswordVisibility(fieldId) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    
    if (field.type === 'password') {
        field.type = 'text';
    } else {
        field.type = 'password';
    }
}