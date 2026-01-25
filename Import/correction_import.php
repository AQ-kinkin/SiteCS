<?php
$pathHome = '/home/csresip/www';

require __DIR__ . '/../vendor/autoload.php';
require_once($pathHome . '/objets/database.class.php');

use PhpOffice\PhpSpreadsheet\IOFactory;

$objdb = new Database;

/**
 * Mapping des types de lots
 */
$type_lot_mapping = [
    'app' => 1,   // APPART
    'cave' => 2,  // CAVE
    'park' => 3   // PARK
];

/**
 * Détermine le type de lot et les informations d'emplacement selon le numéro de lot
 * @param int $lot Numéro du lot
 * @return array ['type' => 'app'|'cave'|'park', 'bat' => string, 'esc' => string] ou null si invalide
 */
function getLotInfo($lot) {
    $lot = intval($lot);
    
    // Définition des plages de lots
    $ranges = [
        // Appartements
        ['min' => 1, 'max' => 8, 'type' => 'app', 'bat' => 'U39', 'esc' => 'E1'],
        ['min' => 9, 'max' => 18, 'type' => 'app', 'bat' => 'U39', 'esc' => 'E2'],
        ['min' => 37, 'max' => 46, 'type' => 'app', 'bat' => 'U40', 'esc' => 'E1'],
        ['min' => 57, 'max' => 66, 'type' => 'app', 'bat' => 'U41', 'esc' => 'E1'],
        ['min' => 67, 'max' => 75, 'type' => 'app', 'bat' => 'U41', 'esc' => 'E2'],
        ['min' => 76, 'max' => 84, 'type' => 'app', 'bat' => 'U41', 'esc' => 'E3'],
        ['min' => 85, 'max' => 94, 'type' => 'app', 'bat' => 'U41', 'esc' => 'E4'],
        ['min' => 95, 'max' => 101, 'type' => 'app', 'bat' => 'U41', 'esc' => 'E5'],
        ['min' => 147, 'max' => 156, 'type' => 'app', 'bat' => 'U47', 'esc' => 'E1'],
        ['min' => 157, 'max' => 166, 'type' => 'app', 'bat' => 'U47', 'esc' => 'E2'],
        ['min' => 187, 'max' => 196, 'type' => 'app', 'bat' => 'U48', 'esc' => 'E1'],
        ['min' => 197, 'max' => 206, 'type' => 'app', 'bat' => 'U48', 'esc' => 'E2'],
        ['min' => 227, 'max' => 236, 'type' => 'app', 'bat' => 'U49', 'esc' => 'E1'],
        ['min' => 237, 'max' => 246, 'type' => 'app', 'bat' => 'U49', 'esc' => 'E2'],
        ['min' => 247, 'max' => 256, 'type' => 'app', 'bat' => 'U49', 'esc' => 'E3'],
        ['min' => 257, 'max' => 266, 'type' => 'app', 'bat' => 'U49', 'esc' => 'E4'],
        
        // Caves
        ['min' => 19, 'max' => 26, 'type' => 'cave', 'bat' => 'U39', 'esc' => 'E1'],
        ['min' => 27, 'max' => 36, 'type' => 'cave', 'bat' => 'U39', 'esc' => 'E2'],
        ['min' => 47, 'max' => 56, 'type' => 'cave', 'bat' => 'U40', 'esc' => 'E1'],
        ['min' => 102, 'max' => 111, 'type' => 'cave', 'bat' => 'U41', 'esc' => 'E1'],
        ['min' => 112, 'max' => 120, 'type' => 'cave', 'bat' => 'U41', 'esc' => 'E2'],
        ['min' => 121, 'max' => 129, 'type' => 'cave', 'bat' => 'U41', 'esc' => 'E3'],
        ['min' => 130, 'max' => 139, 'type' => 'cave', 'bat' => 'U41', 'esc' => 'E4'],
        ['min' => 140, 'max' => 146, 'type' => 'cave', 'bat' => 'U41', 'esc' => 'E5'],
        ['min' => 167, 'max' => 179, 'type' => 'cave', 'bat' => 'U47', 'esc' => 'E1'],
        ['min' => 180, 'max' => 186, 'type' => 'cave', 'bat' => 'U47', 'esc' => 'E2'],
        ['min' => 207, 'max' => 213, 'type' => 'cave', 'bat' => 'U48', 'esc' => 'E1'],
        ['min' => 214, 'max' => 226, 'type' => 'cave', 'bat' => 'U48', 'esc' => 'E2'],
        ['min' => 267, 'max' => 275, 'type' => 'cave', 'bat' => 'U49', 'esc' => 'E1'],
        ['min' => 276, 'max' => 287, 'type' => 'cave', 'bat' => 'U49', 'esc' => 'E2'],
        ['min' => 288, 'max' => 291, 'type' => 'cave', 'bat' => 'U49', 'esc' => 'E3'],
        ['min' => 292, 'max' => 306, 'type' => 'cave', 'bat' => 'U49', 'esc' => 'E4'],
    ];
    
    // Recherche dans les plages définies
    foreach ($ranges as $range) {
        if ($lot >= $range['min'] && $lot <= $range['max']) {
            return [
                'type' => $range['type'],
                'bat' => $range['bat'],
                'esc' => $range['esc']
            ];
        }
    }
    
    // Si pas trouvé dans les plages, c'est un parking
    if ($lot > 306) {
        return ['type' => 'park', 'bat' => null, 'esc' => null];
    }
    
    return null; // Numéro de lot invalide
}

if (isset($_POST["submit"])) {
    try {
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($_FILES["fileToUpload"]["tmp_name"]);
        
        $worksheet = $spreadsheet->getSheetByName('BASE');
        
        echo "<h2>Correction des lots dans la base de données</h2>";
        echo "<p><strong>Feuille active:</strong> " . $worksheet->getTitle() . "</p>";
        
        $highestRow = $worksheet->getHighestDataRow();
        $highestColumn = $worksheet->getHighestDataColumn();
        echo "<p><strong>Nombre de lignes:</strong> $highestRow</p>";
        echo "<p><strong>Colonne max:</strong> $highestColumn</p>";
        
        $stats = [
            'total_lignes' => 0,
            'caves_corriges' => 0,
            'parkings_corriges' => 0,
            'erreurs' => 0
        ];
        
        $corrections_details = [];
        
        // Parcourir les lignes (ignorer la ligne 1 - entête)
        for ($row = 2; $row <= $highestRow; ++$row) {
            
            // Lecture des colonnes pertinentes
            $col6_parking = trim($worksheet->getCell('F' . $row)->getValue() ?? ''); // Colonne F
            $col7_cave = trim($worksheet->getCell('G' . $row)->getValue() ?? '');    // Colonne G
            $col10_lots = trim($worksheet->getCell('J' . $row)->getValue() ?? '');   // Colonne J - liste des lots
            
            // Ignorer les lignes sans lots
            if (empty($col10_lots)) {
                continue;
            }
            
            $stats['total_lignes']++;
            
            // Traiter chaque lot
            $lots_array = preg_split('/[,\s]+/', $col10_lots, -1, PREG_SPLIT_NO_EMPTY);
            
            foreach ($lots_array as $lot_num) {
                $lot_num = intval($lot_num);
                $lot_info = getLotInfo($lot_num);
                
                if (!$lot_info) {
                    echo "<p style='color:orange;'>⚠️ Lot $lot_num invalide (ligne $row) - ignoré</p>";
                    $stats['erreurs']++;
                    continue;
                }
                
                $type_lot_id = $type_lot_mapping[$lot_info['type']];
                $repere_value = null;
                $type_correction = null;
                
                // Déterminer la valeur de repère selon le type de lot
                if ($type_lot_id == 2) {
                    // CAVE - utiliser colonne G (col7_cave)
                    $repere_value = !empty($col7_cave) ? $col7_cave : null;
                    $type_correction = 'cave';
                } elseif ($type_lot_id == 3) {
                    // PARKING - utiliser colonne F (col6_parking)
                    $repere_value = !empty($col6_parking) ? $col6_parking : null;
                    $type_correction = 'parking';
                }
                
                // Mettre à jour uniquement les caves et parkings
                if ($type_correction) {
                    // Vérifier d'abord si le lot existe
                    $sql_check = "SELECT lot, repere FROM lots WHERE lot = ? AND type_lot = ?";
                    $result = $objdb->execonerow($sql_check, [$lot_num, $type_lot_id]);
                    
                    if ($result) {
                        $ancien_repere = $result['repere'];
                        
                        // Mettre à jour le repère
                        $sql_update = "UPDATE lots SET repere = ? WHERE lot = ? AND type_lot = ?";
                        $objdb->exec($sql_update, [$repere_value, $lot_num, $type_lot_id]);
                        
                        // Enregistrer les détails de la correction
                        $corrections_details[] = [
                            'lot' => $lot_num,
                            'type' => $type_correction,
                            'ancien' => $ancien_repere,
                            'nouveau' => $repere_value,
                            'ligne' => $row
                        ];
                        
                        if ($type_correction == 'cave') {
                            $stats['caves_corriges']++;
                        } else {
                            $stats['parkings_corriges']++;
                        }
                    } else {
                        echo "<p style='color:red;'>❌ Lot $lot_num (type $type_correction) non trouvé en base (ligne $row)</p>";
                        $stats['erreurs']++;
                    }
                }
            }
        }
        
        // Affichage du résumé
        echo "<hr><h2>📊 Résumé des corrections</h2>";
        echo "<div style='background-color:#d4edda;padding:15px;border-radius:5px;border:1px solid #c3e6cb;'>";
        echo "<p><strong>Total de lignes traitées:</strong> {$stats['total_lignes']}</p>";
        echo "<p><strong>Caves corrigées:</strong> {$stats['caves_corriges']}</p>";
        echo "<p><strong>Parkings corrigés:</strong> {$stats['parkings_corriges']}</p>";
        echo "<p><strong>Erreurs:</strong> {$stats['erreurs']}</p>";
        echo "</div>";
        
        // Affichage du détail des corrections
        if (count($corrections_details) > 0) {
            echo "<h3>📝 Détail des corrections effectuées</h3>";
            echo "<table border='1' cellpadding='10' style='border-collapse:collapse;width:100%;'>";
            echo "<thead style='background-color:#bee5eb;'>";
            echo "<tr><th>Lot</th><th>Type</th><th>Ancien repère</th><th>Nouveau repère</th><th>Ligne Excel</th></tr>";
            echo "</thead><tbody>";
            
            foreach ($corrections_details as $correction) {
                echo "<tr>";
                echo "<td><strong>{$correction['lot']}</strong></td>";
                echo "<td>" . ucfirst($correction['type']) . "</td>";
                echo "<td>" . ($correction['ancien'] ?: '<em>vide</em>') . "</td>";
                echo "<td><strong>" . ($correction['nouveau'] ?: '<em>vide</em>') . "</strong></td>";
                echo "<td>{$correction['ligne']}</td>";
                echo "</tr>";
            }
            
            echo "</tbody></table>";
        }
        
        echo "<hr><h2 style='color:green;'>✅ Correction terminée avec succès!</h2>";
        
    } catch (Exception $e) {
        echo "<h2 style='color:red;'>Erreur lors du traitement:</h2>";
        echo "<p>" . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>Aucun fichier soumis.</p>";
}
?>