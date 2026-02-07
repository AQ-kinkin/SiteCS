<?php
require_once( PATH_HOME_CS . '/objets/gestion_site.php' );

$objsite->requireAuth(Site::CS);
?>

<div class="page">
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
            <button class="login-button" type="submit">Changer le mot de passe</button>
        </form>
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $newPassword = $_POST['new_password'] ?? '';

            if (empty($username) || empty($newPassword)) {
                echo '<p style="color: red;">Veuillez saisir l\'identifiant et le nouveau mot de passe.</p>';
            } else {
                $result = $objsite->updatePassword($username, $newPassword);
                if ($result === 0) {
                    echo '<p style="color: green;">Mot de passe changé avec succès pour l\'utilisateur : ' . htmlspecialchars($username) . '</p>';
                } elseif ($result === 1) {
                    echo '<p style="color: orange;">Aucun utilisateur trouvé avec cet identifiant.</p>';
                } else {
                    echo '<p style="color: red;">Erreur lors de la mise à jour du mot de passe. Contactez l\'administrateur.</p>';
                }
            }
        }
        ?>
    </div>
</div>