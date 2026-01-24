<?php
require_once( PATH_HOME_CS . '/objets/gestion_site.php' );

$objsite->requireAuth(Site::LOCAT, Site::PROPRIO, Site::CS, Site::SYNDIC, Site::AGENCE);
?>

<div class="login-container">
    <div class="login-box">
        <h2 class="login-title">Changer le mot de passe d'un utilisateur</h2>
        <form method="post">
            <div class="form-group">
                <label class="form-label" for="username">Identifiant de connexion</label>
                <input class="form-input" type="text" name="username" id="username" placeholder="Saisissez l'identifiant" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="new_password">Nouveau mot de passe</label>
                <input class="form-input" type="password" name="new_password" id="new_password" placeholder="Saisissez le nouveau mot de passe" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="confirm_password">Confirmer le mot de passe</label>
                <input class="form-input" type="password" name="confirm_password" id="confirm_password" placeholder="Confirmez le mot de passe" required>
            </div>
            <button class="login-button" type="submit">Changer le mot de passe</button>
        </form>
    </div>
</div>