<?php
    
    if (!defined('PATH_HOME')) {
        require_once __DIR__ . '/../../bootstrap.php';
    }
    require_once( PATH_HOME_CS . '/objets/gestion_site.php');

    $objsite = new Site;
    $objsite->open();
    
    // Vérification de l'authentification (mode AJAX)
    $objsite->requireAuth(Site::CS, true);
    
    require_once PATH_HOME_CS . '/objets/compta_validations.php';



    $objimport = new Compta_Validations($objsite->getDB(), true);



    echo $objimport->run_action($_POST);

?>

