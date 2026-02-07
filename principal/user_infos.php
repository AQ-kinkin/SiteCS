<?php
require_once __DIR__ . '/../objets/gestion_site.php';

$objsite->requireAuth(Site::LOCAT, Site::PROPRIO, Site::CS, Site::SYNDIC, Site::AGENCE);

// Vérifier que l'utilisateur existe
if ($objsite->user === null) {
    header('Location: /?page=Disconnect&reason=user_not_found');
    exit;
}

// Récupérer les libellés des types d'acteurs
$userTypeLabels = $objsite->typesActeur->getUserLabels($objsite->user->getType());

// Préparer les données pour JavaScript
$initiales = strtoupper(substr($objsite->user->getPrenom(), 0, 1) . substr($objsite->user->getNom(), 0, 1));
$isLocataire = $objsite->user->hasRole(Site::LOCAT);
$badgeClass = $isLocataire ? 'locataire' : 'proprietaire';
$badgeLabel = implode(', ', $userTypeLabels); // Direct, pas besoin d'array_column

// DEBUG: État des lots
$debug = [
    'user exists' => $objsite->user !== null,
    'Lots property' => $objsite->user->Lots,
    'Lots count' => $objsite->user->Lots ? count($objsite->user->Lots) : 'NULL'
];
echo "<!-- DEBUG User Lots: " . htmlspecialchars(print_r($debug, true)) . " -->";
?>

<div class="page">
        <!-- Header -->
        <div class="header-card">
            <div class="header-content">
                <div class="user-info">
                    <div class="badge-user-type">P</div>
                    <!--div class="avatar" id="userAvatar">
                        <-- Icon will be inserted here -->
                    </div-->
                    <div class="user-details">
                        <h1 id="userName"><?= htmlspecialchars($objsite->user->getFullName()) ?></h1>
                        <div id="userBadge">
                            <?php foreach ($userTypeLabels as $label): ?>
                                <span class="badge"><?= htmlspecialchars($label) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <!--div class="header-meta">
                    <div class="date">
                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                        <span>Inscrit le <span id="userDate"></span></span>
                    </div>
                    <div class="id">ID: <span id="userId"></span></div>
                </div-->
            </div>
        </div>

        <!-- Main Grid -->
        <div class="grid">
            <!-- Contact Info -->
            <div class="card">
                <div class="card-header">
                    <h2>Informations</h2>
                    <div class="btn-group" id="editButtons">
                        <button class="btn btn-edit" onclick="toggleEdit()">
                            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <!--path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"></path-->
                                <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                            Modifier
                        </button>
                    </div>
                    <!--div class="btn-group hidden" id="saveButtons">
                        <button class="btn btn-save" onclick="saveChanges()">
                            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"></path>
                                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                <polyline points="7 3 7 8 15 8"></polyline>
                            </svg>
                            Enregistrer
                        </button>
                        <button class="btn btn-cancel" onclick="cancelEdit()">
                            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                            Annuler
                        </button>
                    </div-->
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        Identifiant de connexion
                    </label>
                    <input type="text" class="form-control" id="userIdent" value="<?= htmlspecialchars($objsite->user->getIdent() ?? '') ?>" disabled>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                        Email
                    </label>
                    <input type="email" class="form-control" id="userEmail" value="<?= htmlspecialchars($objsite->user->getEmail() ?? '') ?>" disabled>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"></path>
                        </svg>
                        Téléphone
                    </label>
                    <input type="tel" class="form-control" id="userPhone" value="<?= htmlspecialchars($objsite->user->getTelephone() ?? '') ?>" disabled>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg>
                        Adresse
                    </label>
                    <textarea class="form-control" id="userAddress" rows="3" disabled><?= htmlspecialchars($objsite->user->getAdresse() ?? '') ?></textarea>
                </div>

                <div class="password-section hidden" id="passwordSection">
                    <button class="password-toggle-btn" onclick="togglePasswordChange()">
                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0110 0v4"></path>
                        </svg>
                        <span id="passwordToggleText">Changer le mot de passe</span>
                    </button>

                    <div class="password-form hidden" id="passwordForm">
                        <div class="form-group">
                            <label class="form-label">Mot de passe actuel</label>
                            <div class="password-field">
                                <input type="password" class="form-control" id="currentPassword">
                                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('currentPassword')">
                                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Nouveau mot de passe</label>
                            <div class="password-field">
                                <input type="password" class="form-control" id="newPassword">
                                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('newPassword')">
                                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                            </div>
                            <p class="password-hint">Minimum 12 caractères</p>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Confirmer le nouveau mot de passe</label>
                            <div class="password-field">
                                <input type="password" class="form-control" id="confirmPassword">
                                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('confirmPassword')">
                                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lots -->
            <div class="card">
                <h2 style="margin-bottom: 1.5rem;">Mes lots</h2>
                <div class="lots-grid" id="lotsContainer">
                    <?php if ($objsite->user->Lots && count($objsite->user->Lots) > 0):
                        foreach ($objsite->user->Lots as $lot):
                            echo $lot->get_html_panel();
                        endforeach;
                    else: ?>
                    <p style="text-align: center; color: #666;">Aucun lot associé</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
</div>
