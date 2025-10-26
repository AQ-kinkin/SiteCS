<?php

require_once __DIR__ . '/../objets/gestion_site.php';

// require __DIR__ . '/../vendor/autoload.php';

if ( !isset($objsite) || !$objsite->IsAsPriv(Site::DROIT_CS) ) {

    Header('Location:/');

}

require_once __DIR__ . '/../objets/compta_imports.php';



$objimport = new Compta_Imports($objsite->getDB(), true);



echo "<div class=\"imports\">" . PHP_EOL;

echo "\t<div class=\"imports-box\" id=\"imports-box\">" . PHP_EOL;



$step=0;

if (isset($_POST['step'])) {

    $step = $_POST['step'];

}



echo $objimport->ShowForm($step);

echo "\t<div id=\"imports-message\"></div>" . PHP_EOL;



echo "\t</div>" . PHP_EOL; // Fermeture imports-box

echo "</div>" . PHP_EOL; // Fermeture imports
echo "<div>" . PHP_EOL; // Fermeture imports
echo "import_selection.php" . PHP_EOL; // Fermeture imports
echo "</div>" . PHP_EOL; // Fermeture imports

?>