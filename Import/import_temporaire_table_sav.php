<!DOCTYPE html>

<?php

include("ImportCtl.php");

$objdb = new Database;

?>

<html>

<style></style>

<body>

<table border=double>
    <tr><th>N° Compte</th><th>Compte</th><th>Libelle</th><th>Pièce</th><th>Fournisseur</th><th>Date</th><th>TVA</th><th>Charges</th><th>Montant TTC</th></tr>
<?php
    $objdb->query("SELECT * FROM IMPORT_EXCEL_20250527184101;");
    if ($objdb->execute())
    {
        while ( $row = $objdb->fetch())
        {
            echo '<tr>';
            echo '<td>' . $row['CompteNum'] . '</td>';
            echo '<td>' . $row['CompteLib'] . '</td>';
            echo '<td>' . $row['Libelle'] . '</td>';
            echo '<td>' . $row['NumVoucher'] . '</td>';
            echo '<td>' . $row['Fournisseur'] . '</td>';
            echo '<td>' . $row['Date'] . '</td>';
            echo '<td>' . $row['TVA'] . '</td>';
            echo '<td>' . $row['Charges'] . '</td>';
            echo '<td>' . $row['TTC'] . '</td>';
            echo '</tr>';
        }
    }
    else {
     echo '<tr style="width: 100%"><td colspan="9" align="center">Erreur select</td></tr>';
    }
?>
</table>

</body>
</html>

<?php
unset($objdb);
?>