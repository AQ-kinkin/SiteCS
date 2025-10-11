<?php
    $pathHome = '/home/csresip/www';
    require_once( $pathHome . '/objets/gestion_site.php');

    $objsite = new Site;
    $objsite->open();
    if ( !isset($objsite) || !$objsite->IsAsPriv(Site::DROIT_CS) ) {
        http_response_code(400);
        echo "appelant non connecté ou non autorisé.";
        exit;
    }
    
    require_once __DIR__ . '/../objets/compta_validations.php';

    $objimport = new Compta_Validations($objsite->getDB(), true);

    echo $objimport->run_action($_POST);
?>
