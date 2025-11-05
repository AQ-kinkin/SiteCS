<?php
require_once __DIR__ . '/../objets/gestion_site.php';
if ( !isset($objsite) || !$objsite->IsAsPriv(Site::DROIT_CS) ) {
    Header('Location:/');
}
require_once __DIR__ . '/../objets/compta_rapport.php';

$objrapport = new Compta_Rapport($objsite->getDB(), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<pre>" . $objrapport->create_rapport($_POST['periode']) . "</pre>" . PHP_EOL;
} else {
    $objrapport->displayYearSelectionForm();
}