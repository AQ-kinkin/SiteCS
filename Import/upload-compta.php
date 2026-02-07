<?php
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

include("database.class.php");
include("ImportCtl.php");

$objdb = new Database;
$objimport = new ImportCtl($objdb);

if(isset($_POST["submit"])) {
  $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
  $reader->setReadDataOnly(true);
  $spreadsheet = $reader->load($_FILES["fileToUpload"]["tmp_name"]);
  
  // Recupère la feulle de calcul
  $worksheet = $spreadsheet->getActiveSheet();
  // Verif entête du fichier
  $objimport->check_Excel_import($worksheet);
  // Cretation de la table d'import
  if (!$objimport->prepare_Import_Table()) {
    echo "<H1>Erreur creation table et preparation requête : $objimport->error</H1>";
    return;
  }
  
  //Lecture des lignes du tableau excel  
  $line_values = array();
  $highestRow = $worksheet->getHighestDataRow();
  for ($row = 2; $row <= $highestRow; ++$row) {
    reset($line_values);
    $line_values = array();
    for ($col = 1; $col <= 10; ++$col) {
      $value = $worksheet->getCell([$col, $row])->getValue();
      array_push($line_values, $value);
    }
    #print_r($line_values);
    if ( !$objimport->add_import_line($line_values) ) {
      echo "<H1>$objimport->error</H1>";
      break;
    } 
  }
}

unset($objimport);
unset($objdb);
?>