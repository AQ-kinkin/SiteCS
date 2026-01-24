<?php

ob_start();
require_once '../../bootstrap.php';

require_once(PATH_HOME_CS . '/objets/database.class.php');
require_once(PATH_HOME_CS . '/objets/gestion_site.php');

// Création du fichier log
$logFile = PATH_HOME_CS . '/logs/maint_lot_' . date('Y-m-d-H-i') . '.log';

/**
 * Fonction de logging
 */
function Info_Log(string $message): void
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $line = "[$timestamp] $message\n";
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

Info_Log("Début de l'exécution de maint_lot_validation.php");

ob_clean();
header('Content-Type: application/json');

$db = new Database();
Info_Log("Base de données connectée. POST: " . print_r($_POST, true));

// Récupération des données
$lot = intval($_POST['lot'] ?? 0);
$lot_type = intval($_POST['lot_type'] ?? 0);

// Validation basique
if (!$lot || !$lot_type) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}

try {
    switch ($lot_type) {
        case 1: // Appartement
            $repere = intval($_POST['repere'] ?? 0);
            $tantieme = intval($_POST['tantieme'] ?? 0);
            if (!$repere || !$tantieme) {
                echo json_encode(['success' => false, 'message' => 'Données manquantes']);
                exit;
            }
            $db->ExecWithFetchAll(
                "UPDATE lots SET repere = ?, tantieme = ? WHERE lot = ?",
                [$repere, $tantieme, $lot]
            );
            break;

        case 2: // Cave
            $porte = intval($_POST['porte'] ?? 0);
            $tantieme = intval($_POST['tantieme'] ?? 0);
            if (!$porte || !$tantieme) {
                echo json_encode(['success' => false, 'message' => 'Données manquantes']);
                exit;
            }
            $db->ExecWithFetchAll(
                "UPDATE lots SET repere = ?, tantieme = ? WHERE lot = ?",
                [$porte, $tantieme, $lot]
            );
            break;

        case 3: // Parking
            $place = intval($_POST['place'] ?? 0);
            $tantieme = intval($_POST['tantieme'] ?? 0);
            $parking_type = $_POST['parking_type'] ?? '';
            if (!$place || !$tantieme || empty($parking_type)) {
                echo json_encode(['success' => false, 'message' => 'Données manquantes']);
                exit;
            }
            // Récupérer niveau_id
            $result = $db->ExecWithFetchAll(
                "SELECT niveau_id FROM def_parking WHERE libelle = ?",
                [$parking_type]
            );
            $niveau_id = $result[0]['niveau_id'] ?? null;

            $db->ExecWithFetchAll(
                "UPDATE lots SET repere = ?, tantieme = ?, position_id = ? WHERE lot = ?",
                [$place, $tantieme, $niveau_id, $lot]
            );
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Type invalide']);
            exit;
    }
    
    echo json_encode(['success' => true, 'message' => 'Enregistré']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
