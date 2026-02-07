<?php
require_once( PATH_HOME_CS .'/objets/gestion_site.php');

$objsite->requireAuth(Site::CS);

require_once PATH_HOME_CS . '/objets/compta_validations.php';


$objimport = new compta_validations($objsite->getDB(), true);


echo "<div class=\"validations\">" . PHP_EOL;

echo "\t<div class=\"validations-box\" id=\"validations-box\">" . PHP_EOL;



echo "<div class=\"filters-row\">" . PHP_EOL;

    echo "<div id=\"year_selection\">" . PHP_EOL;
    echo $objimport->getYearSelection();
    echo "</div>" . PHP_EOL; // Fermeture year_selection

    echo "<div>" . PHP_EOL;
    echo $objimport->getFiltrageValidation();
    echo "</div>" . PHP_EOL; // Fermeture year_selection

echo "</div>" . PHP_EOL;



echo "<div id=\"key_selection\">" . PHP_EOL;

echo $objimport->getKeySelection();

echo "</div>" . PHP_EOL; // Fermeture key_selection



echo "<div class=\"validations-key\" id=\"validations-key\">" . PHP_EOL;

echo "</div>" . PHP_EOL; // Fermeture year_selection



echo "\t</div>" . PHP_EOL; // Fermeture validations-box

echo "</div>" . PHP_EOL; // Fermeture validations

?>