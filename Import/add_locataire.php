<?php
$pathHome = '/home/csresip/www';

require __DIR__ . '/../vendor/autoload.php';
require_once($pathHome . '/objets/database.class.php');

use PhpOffice\PhpSpreadsheet\IOFactory;

$objdb = new Database;

// Lignes à exclure
$lignes_exclues = [5, 73, 77, 125];

/**
 * Détermine le type de lot selon le numéro
 */
function getLotType($lot) {
    $lot = intval($lot);
    
    $ranges = [
        // Appartements
        ['min' => 1, 'max' => 8, 'type' => 'appart'],
        ['min' => 9, 'max' => 18, 'type' => 'appart'],
        ['min' => 37, 'max' => 46, 'type' => 'appart'],
        ['min' => 57, 'max' => 66, 'type' => 'appart'],
        ['min' => 67, 'max' => 75, 'type' => 'appart'],
        ['min' => 76, 'max' => 84, 'type' => 'appart'],
        ['min' => 85, 'max' => 94, 'type' => 'appart'],
        ['min' => 95, 'max' => 101, 'type' => 'appart'],
        ['min' => 147, 'max' => 156, 'type' => 'appart'],
        ['min' => 157, 'max' => 166, 'type' => 'appart'],
        ['min' => 187, 'max' => 196, 'type' => 'appart'],
        ['min' => 197, 'max' => 206, 'type' => 'appart'],
        ['min' => 227, 'max' => 236, 'type' => 'appart'],
        ['min' => 237, 'max' => 246, 'type' => 'appart'],
        ['min' => 247, 'max' => 256, 'type' => 'appart'],
        ['min' => 257, 'max' => 266, 'type' => 'appart'],
        
        // Caves
        ['min' => 19, 'max' => 26, 'type' => 'cave'],
        ['min' => 27, 'max' => 36, 'type' => 'cave'],
        ['min' => 47, 'max' => 56, 'type' => 'cave'],
        ['min' => 102, 'max' => 111, 'type' => 'cave'],
        ['min' => 112, 'max' => 120, 'type' => 'cave'],
        ['min' => 121, 'max' => 129, 'type' => 'cave'],
        ['min' => 130, 'max' => 139, 'type' => 'cave'],
        ['min' => 140, 'max' => 146, 'type' => 'cave'],
        ['min' => 167, 'max' => 179, 'type' => 'cave'],
        ['min' => 180, 'max' => 186, 'type' => 'cave'],
        ['min' => 207, 'max' => 213, 'type' => 'cave'],
        ['min' => 214, 'max' => 226, 'type' => 'cave'],
        ['min' => 267, 'max' => 275, 'type' => 'cave'],
        ['min' => 276, 'max' => 287, 'type' => 'cave'],
        ['min' => 288, 'max' => 291, 'type' => 'cave'],
        ['min' => 292, 'max' => 306, 'type' => 'cave'],
    ];
    
    foreach ($ranges as $range) {
        if ($lot >= $range['min'] && $lot <= $range['max']) {
            return $range['type'];
        }
    }
    
    return 'park'; // Tous les autres sont des parkings
}

// Mode test
$mode_test = isset($_POST['test']) && $_POST['test'] == '1';

if (isset($_POST["submit"])) {
    try {
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($_FILES["fileToUpload"]["tmp_name"]);
        
        $worksheet = $spreadsheet->getSheetByName('BASE');
        
        echo "<h2>Import des Locataires " . ($mode_test ? "(MODE TEST)" : "") . "</h2>";
        if ($mode_test) {
            echo "<p style='background-color:#fff3cd;padding:10px;border:2px solid #ffc107;'><strong>⚠️ MODE TEST ACTIVÉ</strong> - Aucune requête SQL ne sera exécutée</p>";
        }
        echo "<p><strong>Feuille active:</strong> " . $worksheet->getTitle() . "</p>";
        
        $highestRow = $worksheet->getHighestDataRow();
        echo "<p><strong>Nombre de lignes:</strong> $highestRow</p>";
        
        $stats = ['total' => 0, 'inseres' => 0, 'ignores' => 0, 'exclus' => 0];
        
        // Parcourir les lignes (ignorer la ligne 1 - entête)
        for ($row = 2; $row <= $highestRow; ++$row) {
            
            // Vérifier si la ligne doit être exclue
            if (in_array($row, $lignes_exclues)) {
                $stats['exclus']++;
                echo "<p style='color:orange;'>⊘ Ligne $row exclue</p>";
                continue;
            }
            
            // Lecture des colonnes
            $col3_nom_locataire = trim($worksheet->getCell('C' . $row)->getValue() ?? '');
            $col5_type_acteur = trim($worksheet->getCell('E' . $row)->getValue() ?? '');
            $col10_lots = trim($worksheet->getCell('J' . $row)->getValue() ?? '');
            
            // Ignorer les lignes qui ne sont pas des locataires
            if ($col5_type_acteur !== 'L') {
                continue;
            }
            
            // Ignorer les lignes vides
            if (empty($col3_nom_locataire)) {
                $stats['ignores']++;
                continue;
            }
            
            $stats['total']++;
            
            // Créer le locataire dans la table acteurs
            $nom_json = json_encode(['BRUT_DATA' => $col3_nom_locataire], JSON_UNESCAPED_UNICODE);
            
            if (!$mode_test) {
                $sql = "INSERT INTO acteurs (type_acteur, nom, email, Telephone, Adresse) VALUES (?, ?, NULL, NULL, NULL)";
                $objdb->exec($sql, [1, $nom_json], true); // type_acteur = 1 (LOCAT)
                $id_user = $objdb->lastInsertId();
                
                echo "<p>✓ Ligne $row : Locataire créé (ID: $id_user) - {$col3_nom_locataire}</p>";
            } else {
                echo "<p>🔍 Ligne $row : [TEST] Locataire - {$col3_nom_locataire}</p>";
                $id_user = "TEST_ID";
            }
            
            // Traiter les lots (colonne J)
            if (!empty($col10_lots)) {
                $lots_array = preg_split('/[,\s]+/', $col10_lots, -1, PREG_SPLIT_NO_EMPTY);
                
                foreach ($lots_array as $lot_num) {
                    $lot_num = intval($lot_num);
                    
                    if ($lot_num <= 0) {
                        continue;
                    }
                    
                    $lot_type = getLotType($lot_num);
                    
                    if (!$mode_test) {
                        // Ajouter la relation dans gerants
                        $sql = "INSERT IGNORE INTO gerants (user_id, lot) VALUES (?, ?)";
                        $objdb->exec($sql, [$id_user, $lot_num]);
                        
                        echo "<p style='margin-left:20px;'>→ Lot $lot_num ($lot_type) associé</p>";
                    } else {
                        echo "<p style='margin-left:20px;color:#666;'>→ [TEST] Lot $lot_num type [$lot_type]</p>";
                    }
                }
            }
            
            if (!$mode_test) {
                $stats['inseres']++;
            }
        }
        
        // Affichage du résumé
        echo "<hr><h2>📊 Résumé de l'import</h2>";
        echo "<div style='background-color:#e7f3ff;padding:15px;border-radius:5px;'>";
        echo "<p><strong>Lignes traitées:</strong> {$stats['total']}</p>";
        if (!$mode_test) {
            echo "<p><strong>Locataires insérés:</strong> {$stats['inseres']}</p>";
        }
        echo "<p><strong>Lignes ignorées:</strong> {$stats['ignores']}</p>";
        echo "<p><strong>Lignes exclues:</strong> {$stats['exclus']}</p>";
        echo "</div>";
        
        echo "<hr><h2 style='color:green;'>✓ Import terminé</h2>";
        
    } catch (Exception $e) {
        echo "<h2 style='color:red;'>Erreur lors du traitement:</h2>";
        echo "<p>" . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>Aucun fichier soumis.</p>";
}
?>
