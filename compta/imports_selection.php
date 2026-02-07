<?php
require_once( PATH_HOME_CS .'/objets/gestion_site.php');

$objsite->requireAuth(Site::CS);

require_once PATH_HOME_CS . '/objets/compta_imports.php';

$objimport = new Compta_Imports($objsite->getDB(), true);

echo "<!--<p><h1>import_selection : ShowForm</h1></p>-->";
echo "<div class=\"imports\">" . PHP_EOL;
echo "\t<div class=\"imports-box\" id=\"imports-box\">" . PHP_EOL;

$step=0;
if (isset($_POST['step'])) {
    echo "<!--<p><h1>import_selection : Affectation step</h1></p>-->";
    $step = $_POST['step'];
}
# Formulaire 0 => Show_Form
echo $objimport->ShowForm(0, $step);

echo "\t<div id=\"imports-message\"></div>" . PHP_EOL;
echo "\t</div>" . PHP_EOL; // Fermeture imports-box
echo "</div>" . PHP_EOL; // Fermeture imports
?>