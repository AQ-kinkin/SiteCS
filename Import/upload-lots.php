<?php
$pathHome = '/home/csresip/www';

require __DIR__ . '/../vendor/autoload.php';
require_once( $pathHome . '/objets/database.class.php');

use PhpOffice\PhpSpreadsheet\IOFactory;

$objdb = new Database;

function remplit($lots, &$line_values, $arr_bat) {
    
    $num_bat = $arr_bat[0];
    $num_esc = substr($arr_bat[2],1);

    foreach ($lots as $lot) {
        $lot = trim($lot);
        if ( $lot >= 1 && $lot <= 18 ) {
            $line_values['app'][$lot] = [ 'bat' => 'U39', 'esc' => $num_esc, 'hall' => $num_bat ];
        }
        else if ( $lot >= 37 && $lot <= 46 ) {
            $line_values['app'][$lot] = [ 'bat' => 'U40', 'esc' => $num_esc, 'hall' => $num_bat ];
        }
        else if ( $lot >= 57 && $lot <= 101 ) {
            $line_values['app'][$lot] = [ 'bat' => 'U41', 'esc' => $num_esc, 'hall' => $num_bat ];
        }
        else if ( $lot >= 147 && $lot <= 166 ) {
            $line_values['app'][$lot] = [ 'bat' => 'U47', 'esc' => $num_esc, 'hall' => $num_bat ];
        }
        else if ( $lot >= 187 && $lot <= 206 ) {
            $line_values['app'][$lot] = [ 'bat' => 'U48', 'esc' => $num_esc, 'hall' => $num_bat ];
        }
        else if ( $lot >= 227 && $lot <= 266 ) {
            $line_values['app'][$lot] = [ 'bat' => 'U49', 'esc' => $num_esc, 'hall' => $num_bat ];
        }
        else if ( $lot >= 19 && $lot <= 36 ) {
            $line_values['cave'][$lot] = [ 'bat' => 'U39', 'esc' => $num_esc, 'hall' => $num_bat ];
        }
        else if ( $lot >= 47 && $lot <= 56 ) {
            $line_values['cave'][$lot] = [ 'bat' => 'U40', 'esc' => $num_esc, 'hall' => $num_bat ];
        }
        else if ( $lot >= 102 && $lot <= 146 ) {
            $line_values['cave'][$lot] = [ 'bat' => 'U41', 'esc' => $num_esc, 'hall' => $num_bat ];
        }
        else if ( $lot >= 167 && $lot <= 186 ) {
            $line_values['cave'][$lot] = [ 'bat' => 'U47', 'esc' => $num_esc, 'hall' => $num_bat ];
        }
        else if ( $lot >= 207 && $lot <= 226 ) {
            $line_values['cave'][$lot] = [ 'bat' => 'U48', 'esc' => $num_esc, 'hall' => $num_bat ];
        }
        else if ( $lot >= 267 && $lot <= 306 ) {
            $line_values['cave'][$lot] = [ 'bat' => 'U49', 'esc' => $num_esc, 'hall' => $num_bat ];
        }
        else if ( $lot >= 307 && $lot <= 465 ) {
            $line_values['park'][$lot] = "";
        }
        else {
            echo "<p><span>lot " . $lot . " en erreur</span></p>" . PHP_EOL;
        }    
    }
}

if(isset($_POST["submit"])) {
  $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
  $reader->setReadDataOnly(true);
  $spreadsheet = $reader->load($_FILES["fileToUpload"]["tmp_name"]);
  
  // Recupère la feulle de calcul
  //   $worksheet = $spreadsheet->getActiveSheet();
  $worksheet = $spreadsheet->getSheetByName('BASE');
  // Verif entête du fichier
  
  // Cretation de la table d'import
 
  
  //Lecture des lignes du tableau excel  
  $line_values = array();
  $highestRow = $worksheet->getHighestDataRow();
  for ($row = 2; $row <= $highestRow; ++$row)
  {
    //     reset($line_values);
    //     $line_values = array();
    //     vvvvvvvvvfor ($col = 1; $col <= 10; ++$col) {
    //       $value = $worksheet->getCell([$col, $row])->getValue();
    //       array_push($line_values, $value);
    //     }
    //     echo "<p><span>" . print_r($line_values,true) . "</span></p>" . PHP_EOL;
    //     // if ( !$objimport->add_import_line($line_values) ) {
    //     //   echo "<H1>$objimport->error</H1>";
    //     //   break;
    //     // } 
    $value = $worksheet->getCell([1, $row])->getValue();
    if ( !empty($value) )
    {
        $arr_bat = explode(' ', $value);
        
        $value = $worksheet->getCell([7, $row])->getValue();
        if ( !empty($value) && $value != 'parking' )
        {
            $lots = preg_split('/[ \n]+/', $value);
            remplit($lots, $line_values, $arr_bat); 
            // echo "<p><span>" . print_r($lots,true) . "</span></p>" . PHP_EOL;
        }
        else
        {
            // echo "<p><span>row : " . $row . " </span></p>" . PHP_EOL;
            if ( $row != 77 && $row != 156 ) {
                echo "<p><span>row " . $row . " en erreur</span></p>" . PHP_EOL;
            }
        }
    
    }
    // else { echo "<p><span>row " . $row . " en erreur</span></p>" . PHP_EOL; }        
  }

    ksort($line_values['app'], SORT_NUMERIC);
    $sql = "INSERT INTO `appartements` (`lot`, `hall`) VALUES ";
    foreach ($line_values['app'] as $lot => $data) {
        $sql .= "(" . $lot . "," . $data['hall'] . "),";
    }
    $sql = substr($sql, 0, -1);
    $sql .= ";";
    echo "<span>" . print_r($sql,true) . "</span>" . PHP_EOL;
    $objdb->exec($sql);
    //echo "<span>" . print_r($line_values,true) . "</span>" . PHP_EOL;
    
    // ksort($line_values['app'], SORT_NUMERIC);
    // echo "<table wight=\"300\" border=\"dashed\">" . PHP_EOL;
    // echo "<caption align=\"top\">Appartements</caption>" . PHP_EOL;
    // echo "<tr><th>Lot</th><th>Bat</th><th>Esc</th><th>Hall</th></tr>" . PHP_EOL;
    // foreach ($line_values['app'] as $lot => $data) {
    //     echo "<tr align=\"center\">" . PHP_EOL;
    //     echo "<td wight=\"25%\">" . $lot . "</td>". PHP_EOL;
    //     // echo "<td>" . print_r($data,true) . "<td>Bat</td><td>Esc</td></td>". PHP_EOL;
    //     echo "<td wight=\"25%\">" . $data['bat'] . "</td>". PHP_EOL;
    //     echo "<td wight=\"25%\">" . $data['esc'] . "</td>". PHP_EOL;
    //     echo "<td wight=\"25%\">" . $data['hall'] . "</td>". PHP_EOL;

    //     //         if ( !$objimport->add_import_line($type, $lot, $arr['bat'], $arr['esc'], $arr['hall']) ) {
    //     //             echo "<H1>$objimport->error</H1>";
    //     //             break;
    //     //         }
    //     echo "</tr>" . PHP_EOL;
    // }
    // echo "</table>" . PHP_EOL;
    // echo PHP_EOL;
    // ksort($line_values['cave'], SORT_NUMERIC);
    // echo "<table wight=\"300\" border=\"dashed\">" . PHP_EOL;
    // echo "<caption align=\"top\">Caves</caption>" . PHP_EOL;
    // echo "<tr><th>Lot</th><td>Bat</td><td>Esc</td><td>Hall</td></tr>" . PHP_EOL;
    // foreach ($line_values['cave'] as $lot => $data) {
    //     echo "<tr>" . PHP_EOL;
    //     echo "<td>" . $lot . "</td>". PHP_EOL;
    //     echo "<td>" . $data['bat'] . "</td>". PHP_EOL;
    //     echo "<td>" . $data['esc'] . "</td>". PHP_EOL;
    //     echo "<td>" . $data['hall'] . "</td>". PHP_EOL;
    //     echo "</tr>" . PHP_EOL;
    // }
    // echo "</table>" . PHP_EOL;
    // echo PHP_EOL;
    // ksort($line_values['park'], SORT_NUMERIC);
    // echo "<table wight=\"300\" border=\"dashed\">" . PHP_EOL;
    // echo "<caption align=\"top\">Places parking</caption>" . PHP_EOL;
    // echo "<tr><th>Lot</th></tr>" . PHP_EOL;
    // foreach ($line_values['park'] as $lot => $data) {
    //     echo "<tr>" . PHP_EOL;
    //     echo "<td>" . $lot . "</td>". PHP_EOL;
    //     echo "</tr>" . PHP_EOL;
    // }
    // echo "</table>" . PHP_EOL;

}
unset($objdb);
?>