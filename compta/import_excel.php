<?php
    $pathHome = '/home/csresip/www';
	require_once( $pathHome . '/objets/database.class.php');
    require_once( $pathHome . '/objets/gestion_site.php');
    require __DIR__ . '/../vendor/autoload.php';

    $objsite = new Site;
    $objsite->open();
    if ( !isset($objsite) || !$objsite->IsAsPriv(Site::DROIT_CS) ) {
        http_response_code(400);
        echo "appelant non connecté ou non autorisé.";
        exit;
    }
    require_once __DIR__ . '/../objets/compta_imports.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ( isset($_POST) && isset($_POST["form_num"]) && !empty($_POST["form_num"]) ) {
       
            $num_form = intval( $_POST["form_num"] );
            $objimport = new Compta_Imports($objsite->getDB(), true);

            if ( $num_form == 0 ) {
                echo $objimport->start_Import();
            } elseif ( $num_form == 10 ) {
                echo $objimport->Validation_conflit();
            } else {
                echo $objimport->ShowForm( $num_form );
            }
        }
    }
?>