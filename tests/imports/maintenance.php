<?php
$pathHome = '/home/csresip/www';
require_once($pathHome . '/objets/database.class.php');
require_once($pathHome . '/objets/gestion_site.php');

$objsite = new Site;
$objsite->open();
$objsite->requireAuth(Site::DROIT_CS);

$objdb = $objsite->getDB();
$message = '';
$error = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    switch ($_POST['action']) {
        
        case 'create_test_year':
            // Créer l'année test 2000_2001 (structure uniquement)
            try {
                $periode = '2000_2001';
                
                // Table infos
                $sql = "CREATE TABLE IF NOT EXISTS `csresip1501`.`Compta_factures_2000_2001_infos` (
                    `id_info` int UNSIGNED NOT NULL AUTO_INCREMENT,
                    `LabelFact` TINYTEXT NOT NULL DEFAULT '',
                    `NumPiece` TINYTEXT NOT NULL DEFAULT '' COMMENT 'N° pièce comptable',
                    `NameFournisseur` TINYTEXT NOT NULL DEFAULT '',
                    `DateOpe` VARCHAR(10) NOT NULL DEFAULT '',
                    `Tva` VARCHAR(12) NOT NULL DEFAULT '',
                    `Charges` VARCHAR(12) NOT NULL DEFAULT '',
                    `MontantTTC` VARCHAR(12) NOT NULL DEFAULT '',
                    PRIMARY KEY (id_info)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;";
                $objdb->exec($sql);
                
                // Table lines
                $sql = "CREATE TABLE IF NOT EXISTS `csresip1501`.`Compta_factures_2000_2001_lines` (
                    `id_line` int UNSIGNED NOT NULL AUTO_INCREMENT,
                    `key_id` int UNSIGNED NOT NULL,
                    `num_account` varchar(10) NOT NULL,
                    `label_account` varchar(90) NOT NULL,
                    `info_id` int UNSIGNED NOT NULL,
                    `validation_id` int UNSIGNED DEFAULT NULL,
                    `voucher_id` int UNSIGNED DEFAULT NULL,
                    `import` VARCHAR(8) DEFAULT NULL,
                    PRIMARY KEY (id_line)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;";
                $objdb->exec($sql);
                
                // Table vouchers
                $sql = "CREATE TABLE IF NOT EXISTS `csresip1501`.`Compta_factures_2000_2001_vouchers` (
                    `id_voucher` int UNSIGNED NOT NULL AUTO_INCREMENT,
                    `nom` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
                    `url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
                    PRIMARY KEY (id_voucher)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;";
                $objdb->exec($sql);
                
                // Table validations
                $sql = "CREATE TABLE IF NOT EXISTS `csresip1501`.`Compta_factures_2000_2001_validations` (
                    `id_validation` int UNSIGNED NOT NULL AUTO_INCREMENT,
                    `state_id` tinyint UNSIGNED NOT NULL,
                    `infos` json NOT NULL,
                    `commentaire` text NOT NULL,
                    PRIMARY KEY (id_validation)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;";
                $objdb->exec($sql);
                
                // Ajouter la période dans Compta_years
                $sql = "INSERT IGNORE INTO `csresip1501`.`Compta_years` (`periode`) VALUES ('2000_2001');";
                $objdb->exec($sql);
                
                $message = "✅ Année test 2000_2001 créée avec succès (4 tables + entrée dans Compta_years)";
                
            } catch (Exception $e) {
                $error = "❌ Erreur création année test : " . $e->getMessage();
            }
            break;
            
        case 'copy_real_data':
            // Copier données d'une année réelle vers 2000_2001
            if (isset($_POST['source_year']) && !empty($_POST['source_year'])) {
                try {
                    $source = $_POST['source_year'];
                    
                    // Vider les tables cibles
                    $objdb->exec("TRUNCATE TABLE `csresip1501`.`Compta_factures_2000_2001_infos`;");
                    $objdb->exec("TRUNCATE TABLE `csresip1501`.`Compta_factures_2000_2001_lines`;");
                    $objdb->exec("TRUNCATE TABLE `csresip1501`.`Compta_factures_2000_2001_vouchers`;");
                    $objdb->exec("TRUNCATE TABLE `csresip1501`.`Compta_factures_2000_2001_validations`;");
                    
                    // Copier infos
                    $sql = "INSERT INTO `csresip1501`.`Compta_factures_2000_2001_infos` 
                            SELECT * FROM `csresip1501`.`Compta_factures_{$source}_infos`;";
                    $objdb->exec($sql);
                    
                    // Copier lines
                    $sql = "INSERT INTO `csresip1501`.`Compta_factures_2000_2001_lines` 
                            SELECT * FROM `csresip1501`.`Compta_factures_{$source}_lines`;";
                    $objdb->exec($sql);
                    
                    // Copier vouchers
                    $sql = "INSERT INTO `csresip1501`.`Compta_factures_2000_2001_vouchers` 
                            SELECT * FROM `csresip1501`.`Compta_factures_{$source}_vouchers`;";
                    $objdb->exec($sql);
                    
                    // Copier validations
                    $sql = "INSERT INTO `csresip1501`.`Compta_factures_2000_2001_validations` 
                            SELECT * FROM `csresip1501`.`Compta_factures_{$source}_validations`;";
                    $objdb->exec($sql);
                    
                    $message = "✅ Données copiées de {$source} vers 2000_2001 avec succès";
                    
                } catch (Exception $e) {
                    $error = "❌ Erreur copie données : " . $e->getMessage();
                }
            } else {
                $error = "❌ Veuillez sélectionner une année source";
            }
            break;
            
        case 'delete_test_year':
            // Supprimer l'année test 2000_2001
            try {
                $objdb->exec("DROP TABLE IF EXISTS `csresip1501`.`Compta_factures_2000_2001_infos`;");
                $objdb->exec("DROP TABLE IF EXISTS `csresip1501`.`Compta_factures_2000_2001_lines`;");
                $objdb->exec("DROP TABLE IF EXISTS `csresip1501`.`Compta_factures_2000_2001_vouchers`;");
                $objdb->exec("DROP TABLE IF EXISTS `csresip1501`.`Compta_factures_2000_2001_validations`;");
                $objdb->exec("DELETE FROM `csresip1501`.`Compta_years` WHERE `periode` = '2000_2001';");
                
                $message = "✅ Année test 2000_2001 supprimée avec succès";
                
            } catch (Exception $e) {
                $error = "❌ Erreur suppression année test : " . $e->getMessage();
            }
            break;
            
        case 'clear_import_log':
            // Effacer le log d'import du jour
            try {
                $logFile = '/home/csresip/www/logs/Import_' . date('Ymd');
                if (file_exists($logFile)) {
                    unlink($logFile);
                    $message = "✅ Log d'import effacé : Import_" . date('Ymd');
                } else {
                    $message = "ℹ️ Aucun log d'import trouvé pour aujourd'hui";
                }
            } catch (Exception $e) {
                $error = "❌ Erreur suppression log : " . $e->getMessage();
            }
            break;
    }
}

// Récupérer la liste des années disponibles
$years = [];
try {
    $objdb->query("SELECT periode FROM `Compta_years` WHERE periode != '2000_2001' ORDER BY periode DESC;");
    $objdb->execute();
    $years = $objdb->fetchall();
} catch (Exception $e) {
    $error = "❌ Erreur récupération années : " . $e->getMessage();
}

// Vérifier si l'année test existe
$test_year_exists = false;
try {
    $objdb->query("SELECT COUNT(*) as cnt FROM `Compta_years` WHERE periode = '2000_2001';");
    $objdb->execute();
    $result = $objdb->fetch();
    $test_year_exists = ($result['cnt'] > 0);
} catch (Exception $e) {
    // Table pas encore créée
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance - Tests Import</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 32px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 16px;
        }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
        }
        
        .section h2 {
            color: #495057;
            margin-bottom: 15px;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section p {
            color: #6c757d;
            margin-bottom: 15px;
            line-height: 1.6;
        }
        
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .status.exists {
            background: #d4edda;
            color: #155724;
        }
        
        .status.missing {
            background: #f8d7da;
            color: #721c24;
        }
        
        form {
            margin-top: 15px;
        }
        
        select {
            width: 100%;
            padding: 10px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 15px;
            cursor: pointer;
        }
        
        select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        button.danger {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        button.secondary {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .icon {
            font-size: 24px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🛠️ Maintenance - Tests Import</h1>
        <p class="subtitle">Gestion de l'année test 2000_2001 pour les tests d'import</p>
        
        <?php if ($message): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <!-- Section 1 : Créer année test -->
        <div class="section">
            <h2>
                <span class="icon">📦</span>
                Créer l'année test
                <?php if ($test_year_exists): ?>
                    <span class="status exists">EXISTE</span>
                <?php else: ?>
                    <span class="status missing">NON CRÉÉE</span>
                <?php endif; ?>
            </h2>
            <p>Crée les 4 tables vides pour l'année 2000_2001 : <code>_infos</code>, <code>_lines</code>, <code>_vouchers</code>, <code>_validations</code></p>
            <form method="post">
                <input type="hidden" name="action" value="create_test_year">
                <button type="submit" <?= $test_year_exists ? 'disabled' : '' ?>>
                    <?= $test_year_exists ? '✓ Année test déjà créée' : 'Créer l\'année test 2000_2001' ?>
                </button>
            </form>
        </div>
        
        <!-- Section 2 : Copier données -->
        <div class="section">
            <h2><span class="icon">📋</span> Copier des données réelles</h2>
            <p>Copie toutes les données d'une année existante vers 2000_2001 pour simuler un import réel</p>
            <form method="post">
                <input type="hidden" name="action" value="copy_real_data">
                <select name="source_year" required <?= !$test_year_exists ? 'disabled' : '' ?>>
                    <option value="">-- Sélectionnez une année source --</option>
                    <?php foreach ($years as $year): ?>
                        <option value="<?= htmlspecialchars($year['periode']) ?>">
                            <?= htmlspecialchars($year['periode']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="secondary" <?= !$test_year_exists ? 'disabled' : '' ?>>
                    Copier les données vers 2000_2001
                </button>
            </form>
            <?php if (!$test_year_exists): ?>
                <p style="color: #dc3545; margin-top: 10px; font-size: 13px;">⚠️ Créez d'abord l'année test</p>
            <?php endif; ?>
        </div>
        
        <!-- Section 3 : Supprimer année test -->
        <div class="section">
            <h2><span class="icon">🗑️</span> Supprimer l'année test</h2>
            <p>Supprime complètement l'année 2000_2001 (tables + entrée dans Compta_years) pour recommencer à zéro</p>
            <form method="post" onsubmit="return confirm('⚠️ Confirmer la suppression de l\'année test 2000_2001 ?');">
                <input type="hidden" name="action" value="delete_test_year">
                <button type="submit" class="danger" <?= !$test_year_exists ? 'disabled' : '' ?>>
                    Supprimer l'année test 2000_2001
                </button>
            </form>
        </div>
        
        <!-- Section 4 : Effacer log -->
        <div class="section">
            <h2><span class="icon">📝</span> Effacer le log d'import</h2>
            <p>Efface le fichier de log d'import du jour : <code>/logs/Import_<?= date('Ymd') ?></code></p>
            <form method="post">
                <input type="hidden" name="action" value="clear_import_log">
                <button type="submit" class="secondary">Effacer le log d'aujourd'hui</button>
            </form>
        </div>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #dee2e6; color: #6c757d; font-size: 14px;">
            <strong>📌 Workflow de test :</strong><br>
            1️⃣ Créer l'année test 2000_2001<br>
            2️⃣ (Optionnel) Copier des données réelles pour tests<br>
            3️⃣ Effacer le log avant chaque test<br>
            4️⃣ Lancer l'import sur la période 2000_2001<br>
            5️⃣ Analyser le log Import_<?= date('Ymd') ?><br>
            6️⃣ Supprimer l'année test pour recommencer
        </div>
    </div>
</body>
</html>
