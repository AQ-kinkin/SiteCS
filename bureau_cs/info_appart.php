<?php
if (!defined('PATH_HOME')) {
    require_once __DIR__ . '/../../bootstrap.php';
}
require_once(PATH_HOME_CS . '/objets/gestion_site.php');
    
$objsite = new Site;
$objsite->open();
$objsite->requireAuth(Site::CS);

require_once(PATH_HOME_CS . '/objets/appartement.php');
$objappart = new Appartement($objsite->getDB());

$lot = isset($_POST['lot']) ? (int)$_POST['lot'] : 0;

if ($lot > 0) {
    echo $objappart->get_html_info_lot($lot);
} else {
    echo '<div class="hall-content"><p>Lot non spécifié</p></div>';
}
?>