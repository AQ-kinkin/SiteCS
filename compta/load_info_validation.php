<?php

    $pathHome = '/home/csresip/www';

    require_once( $pathHome . '/objets/gestion_site.php');

    $objsite = new Site;
    $objsite->open();
    
    // Vérification de l'authentification (mode AJAX)
    $objsite->requireAuth(Site::CS, true);
    
    require_once __DIR__ . '/../objets/compta_validations.php';



    $objimport = new Compta_Validations($objsite->getDB(), true);



    echo $objimport->run_action($_POST);

?>

