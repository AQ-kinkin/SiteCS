<?php
$pathHome = '/home/csresip/www';

require __DIR__ . '/../vendor/autoload.php';
require_once($pathHome . '/objets/database.class.php');

use PhpOffice\PhpSpreadsheet\IOFactory;

$objdb = new Database;

/**
 * Récupère ou crée un bâtiment par son nom
 * @param Database $objdb Instance de la base de données
 * @param string $nom_bat Nom du bâtiment (U39, U40, etc.)
 * @return int L'id_batiment
 */
function getOrCreateBatiment($objdb, $nom_bat) {
    // Vérifier si le bâtiment existe déjà
    $sql = "SELECT id_batiment FROM batiment WHERE nom = ?";
    $result = $objdb->execonerow($sql, [$nom_bat]);
    
    if (!empty($result)) {
        return $result['id_batiment'];
    }
    
    // Créer le bâtiment
    $sql = "INSERT INTO batiment (nom) VALUES (?)";
    $objdb->exec($sql, [$nom_bat], true);
    return $objdb->lastInsertId();
}

/**
 * Mapping des types d'acteurs
 */
$type_acteur_mapping = [
    'L' => 1,  // LOCAT
    'P' => 2   // PROPRIO
];

/**
 * Mapping des types de lots
 */
$type_lot_mapping = [
    'app' => 1,   // APPART
    'cave' => 2,  // CAVE
    'park' => 3   // PARK
];

/**
 * Parse l'étage et la position depuis la colonne 4
 * Ex: "RC D" => ['etage' => 0, 'position' => 'D']
 * Ex: "1er G" => ['etage' => 1, 'position' => 'G']
 */
function parseEtagePosition($str) {
    $str = trim($str);
    $position = null;
    $etage = null;
    
    // Extraire la position (dernière lettre G ou D)
    if (preg_match('/([GD])$/i', $str, $matches)) {
        $position = strtoupper($matches[1]);
        $str = trim(str_replace($matches[1], '', $str));
    }
    
    // Parser l'étage
    $str = strtolower($str);
    if ($str === 'rc' || $str === 'rdc') {
        $etage = 0;
    } elseif (preg_match('/(\d+)/', $str, $matches)) {
        $etage = intval($matches[1]);
    }
    
    return ['etage' => $etage, 'position' => $position];
}

/**
 * Retourne l'id_hall basé sur le batiment et l'escalier
 * Les IDs correspondent aux numéros postaux réels
 */
function getHallId($bat, $esc) {
    // Mapping bat+esc => id_hall (numéros postaux)
    $mapping = [
        'U49E3' => 1,
        'U49E4' => 3,
        'U39E2' => 42,
        'U39E1' => 44,
        'U40E1' => 46,
        'U41E5' => 48,
        'U41E4' => 50,
        'U41E3' => 52,
        'U41E2' => 54,
        'U41E1' => 56,
        'U49E2' => 60,
        'U49E1' => 62,
        'U48E2' => 64,
        'U48E1' => 66,
        'U47E2' => 68,
        'U47E1' => 70,
    ];
    
    $key = $bat . $esc;
    return $mapping[$key] ?? null;
}

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
        
        echo "<h2>Traitement du fichier Excel</h2>";
        echo "<p><strong>Feuille active:</strong> " . $worksheet->getTitle() . "</p>";
        
        $highestRow = $worksheet->getHighestDataRow();
        $highestColumn = $worksheet->getHighestDataColumn();
        echo "<p><strong>Nombre de lignes:</strong> $highestRow</p>";
        echo "<p><strong>Colonne max:</strong> $highestColumn</p>";
        
        $processedActeurs = []; // Pour gérer les doublons d'acteurs
        $doublons = []; // Liste des acteurs en doublon
        $stats = ['total' => 0, 'doublons' => 0, 'uniques' => 0];
        
        // Cache pour éviter les doublons d'insertion
        $halls_created = [];
        $lots_created = [];
        $batiments_cache = []; // Cache pour les IDs de bâtiments
        
        // Parcourir les lignes (ignorer la ligne 1 - entête)
        for ($row = 2; $row <= $highestRow; ++$row) {
            
            // Lecture des colonnes (avec gestion des valeurs null)
            $col1_id_batiment = trim($worksheet->getCell('A' . $row)->getValue() ?? '');
            $col2_id_appartement = trim($worksheet->getCell('B' . $row)->getValue() ?? '');
            $col3_affichage = trim($worksheet->getCell('C' . $row)->getValue() ?? '');
            $col4_etage_position = trim($worksheet->getCell('D' . $row)->getValue() ?? '');
            $col5_type_acteur = trim($worksheet->getCell('E' . $row)->getValue() ?? ''); // L ou P
            $col6_parking = trim($worksheet->getCell('F' . $row)->getValue() ?? '');
            $col7_cave = trim($worksheet->getCell('G' . $row)->getValue() ?? '');
            $col8_prenom = trim($worksheet->getCell('H' . $row)->getValue() ?? '');
            $col9_nom = trim($worksheet->getCell('I' . $row)->getValue() ?? '');
            $col10_lots = trim($worksheet->getCell('J' . $row)->getValue() ?? '');
            $col11_type_app = trim($worksheet->getCell('K' . $row)->getValue() ?? '');
            $col12_telephone = trim($worksheet->getCell('L' . $row)->getValue() ?? '');
            $col13_email = trim($worksheet->getCell('M' . $row)->getValue() ?? '');
            $col14_adresse = trim($worksheet->getCell('N' . $row)->getValue() ?? '');
            
            // Ignorer les lignes vides (si les colonnes principales sont vides)
            if (empty($col1_id_batiment) && empty($col2_id_appartement) && empty($col9_nom)) {
                continue;
            }
            
            // Traitement du nom complet (col8 + col9)
            $nom_complet = trim($col8_prenom . ' ' . $col9_nom);
            $nom_json = json_encode(['BRUT_DATA' => $nom_complet]);
            
            // Détection des doublons d'acteurs
            $acteur_key = strtolower($nom_complet) . '_' . $col5_type_acteur;
            $stats['total']++;
            
            if (isset($processedActeurs[$acteur_key])) {
                // Doublon détecté
                $stats['doublons']++;
                $processedActeurs[$acteur_key]['count']++;
                $processedActeurs[$acteur_key]['lignes'][] = $row;
                
                // Ajouter à la liste des doublons pour le résumé
                if (!isset($doublons[$acteur_key])) {
                    $doublons[$acteur_key] = [
                        'nom' => $nom_complet,
                        'type' => $col5_type_acteur == 'P' ? 'Propriétaire' : 'Locataire',
                        'lignes' => $processedActeurs[$acteur_key]['lignes']
                    ];
                }
            } else {
                // Premier enregistrement de cet acteur
                $stats['uniques']++;
                $processedActeurs[$acteur_key] = [
                    'nom' => $nom_complet,
                    'type' => $col5_type_acteur,
                    'count' => 1,
                    'lignes' => [$row],
                    'email' => $col13_email,
                    'telephone' => $col12_telephone,
                    'adresse' => $col14_adresse
                ];
            }
            
            // Traitement des lots (col10) - stockage sans affichage
            $lots_array = [];
            if (!empty($col10_lots)) {
                $lots_array = preg_split('/[,\s]+/', $col10_lots, -1, PREG_SPLIT_NO_EMPTY);
            }
            
            // Stocker les informations sans les afficher
            $processedActeurs[$acteur_key]['lots'] = $lots_array;
            
            // ========================================================================
            // INSERTION DANS LA BASE DE DONNÉES
            // ========================================================================
            
            // 1. Créer ou récupérer l'acteur
            $id_user = null;
            if (isset($processedActeurs[$acteur_key]['id_user'])) {
                // Acteur déjà créé (doublon)
                $id_user = $processedActeurs[$acteur_key]['id_user'];
            } else {
                // Créer le nouvel acteur
                $type_acteur_id = $type_acteur_mapping[$col5_type_acteur] ?? 1;
                $nom_json = json_encode(['BRUT_DATA' => $nom_complet], JSON_UNESCAPED_UNICODE);
                $tel_json = !empty($col12_telephone) ? json_encode(['tel' => $col12_telephone], JSON_UNESCAPED_UNICODE) : null;
                $adr_json = !empty($col14_adresse) ? json_encode(['adresse' => $col14_adresse], JSON_UNESCAPED_UNICODE) : null;
                
                $sql = "INSERT INTO acteurs (type_acteur, nom, email, Telephone, Adresse) VALUES (?, ?, ?, ?, ?)";
                $objdb->exec($sql, [
                    $type_acteur_id,
                    $nom_json,
                    $col13_email ?: null,
                    $tel_json,
                    $adr_json
                ], true);
                $id_user = $objdb->lastInsertId();
                $processedActeurs[$acteur_key]['id_user'] = $id_user;
            }
            
            // 2. Parser les informations d'appartement
            $etage_pos = parseEtagePosition($col4_etage_position);
            $type_app_code = strtoupper(trim($col11_type_app));
            
            // 3. Traiter chaque lot
            foreach ($lots_array as $lot_num) {
                $lot_num = intval($lot_num);
                $lot_info = getLotInfo($lot_num);
                
                if (!$lot_info) {
                    echo "<p style='color:red;'>⚠️ Lot $lot_num invalide (ligne $row)</p>";
                    continue;
                }
                
                $type_lot_id = $type_lot_mapping[$lot_info['type']];
                $bat = $lot_info['bat'];
                $esc = $lot_info['esc'];
                
                // 4. Créer le hall si nécessaire (seulement pour app et cave)
                if ($bat && $esc) {
                    // Récupérer ou créer le bâtiment
                    if (!isset($batiments_cache[$bat])) {
                        $batiments_cache[$bat] = getOrCreateBatiment($objdb, $bat);
                    }
                    $bat_id = $batiments_cache[$bat];
                    
                    $hall_id = getHallId($bat, $esc);
                    
                    if (!isset($halls_created[$hall_id])) {
                        // Vérifier si le hall existe déjà
                        $sql = "SELECT id_hall FROM halls WHERE id_hall = ?";
                        $result = $objdb->execonerow($sql, [$hall_id]);
                        
                        if (empty($result)) {
                            // Créer le hall
                            $sql = "INSERT INTO halls (id_hall, esc, bat) VALUES (?, ?, ?)";
                            $objdb->exec($sql, [$hall_id, $esc, $bat_id]);
                        }
                        $halls_created[$hall_id] = true;
                    }
                    
                    $position_id = $hall_id;
                } else {
                    // Pour les parkings, utiliser un niveau de parking par défaut
                    $position_id = 1; // TODO: à clarifier selon votre logique
                }
                
                // 5. Vérifier si le lot existe déjà
                if (isset($lots_created[$lot_num])) {
                    // Lot déjà créé, juste ajouter la relation dans gerants
                    $sql = "INSERT IGNORE INTO gerants (user_id, lot) VALUES (?, ?)";
                    $objdb->exec($sql, [$id_user, $lot_num]);
                    continue;
                }
                
                // 6. Créer le lot
                $sql = "INSERT INTO lots (lot, type_lot, position_id, num_client_syndic, gestionnaire, repere) 
                        VALUES (?, ?, ?, ?, ?, ?)";
                $objdb->exec($sql, [
                    $lot_num,
                    $type_lot_id,
                    $position_id,
                    (string)$lot_num,
                    $id_user,
                    $col2_id_appartement ?: null
                ]);
                $lots_created[$lot_num] = true;
                
                // 7. Créer l'entrée spécifique selon le type
                if ($lot_info['type'] === 'app') {
                    // Trouver l'id du type d'appartement
                    $sql = "SELECT id_type_appartement FROM types_appartements WHERE code = ?";
                    $result = $objdb->execonerow($sql, [$type_app_code]);
                    
                    if (!empty($result)) {
                        $type_app_id = $result['id_type_appartement'];
                        $sql = "INSERT INTO appartements (lot, etage, type_appartement, position) 
                                VALUES (?, ?, ?, ?)";
                        $objdb->exec($sql, [
                            $lot_num,
                            $etage_pos['etage'],
                            $type_app_id,
                            $etage_pos['position']
                        ]);
                    }
                }
                
                // 8. Ajouter la relation dans gerants
                $sql = "INSERT INTO gerants (user_id, lot) VALUES (?, ?)";
                $objdb->exec($sql, [$id_user, $lot_num]);
            }
        } 
        
        // Affichage du résumé des doublons
        echo "<hr><h2>📊 Résumé du traitement</h2>";
        echo "<div style='background-color:#e7f3ff;padding:15px;border-radius:5px;'>";
        echo "<p><strong>Total de lignes traitées:</strong> {$stats['total']}</p>";
        echo "<p><strong>Acteurs uniques:</strong> {$stats['uniques']}</p>";
        echo "<p><strong>Lignes en doublon:</strong> {$stats['doublons']}</p>";
        echo "</div>";
        
        if (count($doublons) > 0) {
            echo "<h3 style='color:#d9534f;'>⚠️ Liste des acteurs en doublon</h3>";
            echo "<table border='1' cellpadding='10' style='border-collapse:collapse;width:100%;'>";
            echo "<thead style='background-color:#f8d7da;'>";
            echo "<tr><th>Nom</th><th>Type</th><th>Nombre d'occurrences</th><th>Lignes</th></tr>";
            echo "</thead><tbody>";
            
            foreach ($doublons as $key => $info) {
                $nb_occurrences = count($info['lignes']);
                echo "<tr>";
                echo "<td><strong>{$info['nom']}</strong></td>";
                echo "<td>{$info['type']}</td>";
                echo "<td style='text-align:center;'>{$nb_occurrences}</td>";
                echo "<td>" . implode(', ', $info['lignes']) . "</td>";
                echo "</tr>";
            }
            
            echo "</tbody></table>";
        } else {
            echo "<p style='color:green;'>✓ Aucun doublon détecté</p>";
        }
        
        echo "<hr><h2 style='color:green;'>Traitement terminé avec succès!</h2>";
        
    } catch (Exception $e) {
        echo "<h2 style='color:red;'>Erreur lors du traitement:</h2>";
        echo "<p>" . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>Aucun fichier soumis.</p>";
}
?>
