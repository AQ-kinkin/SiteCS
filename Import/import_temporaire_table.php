<?php
if ( !isset($objsite) || $objsite->IsAsPriv(gestion_site::DROIT_CS) ) {
    Header('Location:/');
}
require("ImportCtl.php");
$objdb = new Database;
?>

<div class="factures">
<?php
    $objdb->query("SELECT * FROM `Compte_Key_Validation`;");
    if ($objdb->execute())
    {
        $_SESSION['ArrayValidation'] = $objdb->fetchall();
    }
    $objdb->query("SELECT * FROM `Compte_Key_type`;");
    if ($objdb->execute())
    {
        $_SESSION['ArrayKeyComptable'] = $objdb->fetchall();
    }
?>
    <!-- <div><?php
        // foreach ($_SESSION['ArrayValidation'] as $data) {
        //     echo "\t<h1>" . $data['numkey'] . "\t-:-\t" . $data['namekey'] . "\t-:-\t" . $data['default'] . "</h1>\n";
        //     // echo "\t<h1>" . $data['numkey'] . " -:- " . $data['namekey'] . "</h1>\n";
        // }
    ?></div> -->
    <div class="menu-cles">
<?php
    foreach ($_SESSION['ArrayKeyComptable'] as $data) {
        echo "\t\t<button title=\"" . $data['namekey'] . "\" onclick=\"showKey('" . $data['typekey'] .  "', '" . $data['namekey'] .  "')\">" . $data['shortname'] . "</button>\n";
        //echo "\t\t<a onclick=\"showKey('" . $data['typekey'] .  "')\">" . $data['namekey'] . "</a>\n";
    }
?>
    </div> <!-- Fin menu-cles -->
    <div class="contenu-cle" id="contenu-cle">
    </div> <!-- Fin contenu -->
<!-- <?php
    // foreach ($ArrayKeyComptable as $data) {
    //     echo "<div class=\"contenu-cle\" id=\"" . $data['typekey'] . "\"><span>" . $data['typekey'] . " : " . $data['namekey'] . "</span>\n</div>\n";
    // }
?> -->
</div> <!-- Fin factures -->
