<div class="headinfos">
    <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
    <img src="icons/buste-48x48.png" alt="Utilisateur" id="user-menu-icon">
    <div class="user-dropdown" id="user-dropdown">
        <ul>
            <li><a href="index.php?page=User&item=Infos">Mes infos</a></li>
            <li><a href="index.php?page=User&item=Demande">Réaliser une demande</a></li>
            <li><a href="index.php?page=User&item=Anomalie">Signaler un problème</a></li>
            <li class="separator"></li>
            <li><a href="index.php?page=Disconnect">Déconnexion</a></li>
        </ul>
    </div>
</div>