<?php
    if (!defined('PATH_HOME')) {
        require_once __DIR__ . '/../../bootstrap.php';
    }
	require_once( PATH_HOME_CS . '/objets/database.class.php');
    require_once( PATH_HOME_CS . '/objets/gestion_site.php');
    require PATH_HOME_CS . '/vendor/autoload.php';

    $objsite = new Site;
    $objsite->open();

    // Vérification de l'authentification (mode AJAX)
    $objsite->requireAuth(Site::CS, true);
    
    require_once __DIR__ . '/../objets/compta_imports.php';

    echo "<!--<p><h1>import_excel : REQUEST_METHOD</h1></p>-->";
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        echo "<!--<p><h1>form_num : _POST</h1></p>-->";
        if ( isset($_POST) && isset($_POST["form_num"]) && $_POST["form_num"] !== '' ) {

            $num_form = intval( $_POST["form_num"] );
            echo "<!--<p><h1>num_form : $num_form</h1></p>-->";
            if ( isset($_POST["step"]) ) {
                $step = intval( $_POST["step"] );
                echo "<!--<p><h1>step : $step</h1></p>-->";
                $objimport = new Compta_Imports($objsite->getDB(), true);

                if ( $num_form == 1 ) {
                    echo $objimport->start_Import();
                } elseif ( $num_form == 10 ) {
                    echo $objimport->Validation_conflit();
                } elseif ( $num_form == 11 ) {
                    echo $objimport->forced_conflit();
                } elseif ( $num_form == 12 ) {
                    echo $objimport->Validation_lost();
                } else {
                    echo $objimport->ShowForm( $num_form , $step );
                }
            } else { echo "<!--<p><h1>import_excel : Pas de champ step dans le formulaire</h1></p>-->"; }
        } else { echo "<!--<p><h1>import_excel : Pas de champ form_num dans le formulaire</h1></p>-->"; }
    } else { echo "<!--<p><h1>import_excel : Pas de données dans le formulaire</h1></p>-->"; }
    echo "<!--<p><h1>import_excel : FIN</h1></p>-->";
?>