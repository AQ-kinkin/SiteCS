<?php
/**
 * Script de migration des mots de passe en clair vers password_hash
 * Ce script :
 * 1. Sauvegarde tous les ident/passwd actuels dans logs/oldpasswd
 * 2. Remplace tous les passwd non-null par password_hash
 */

require_once(__DIR__ . '/../objets/database.class.php');
require_once(__DIR__ . '/../objets/logs.trait.php');

class UpdatePasswd
{
    use Logs;
    
    private Database $objdb;
    private string $logDir;
    private string $backupFile;
    
    public function __construct()
    {
        // Initialiser la connexion DB
        $this->objdb = new Database();
        
        // Préparer le répertoire de log
        $this->logDir = __DIR__ . '/../logs';
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
        
        // Fichier de sauvegarde avec timestamp
        $timestamp = date('Y-m-d_H-i-s');
        $this->backupFile = $this->logDir . '/oldpasswd_' . $timestamp . '.txt';
        
        // Initialiser le système de logs
        $this->PrepareLog('UpdatePasswd', 'd');
    }
    
    public function run()
    {
        echo "=== Début de la migration des mots de passe ===\n\n";
        $this->write_info("Début de la migration des mots de passe");
        
        // Étape 1 : Sauvegarder les anciens mots de passe
        echo "Étape 1/3 : Sauvegarde des anciens mots de passe...\n";
        if (!$this->backupOldPasswords()) {
            echo "ERREUR lors de la sauvegarde. Arrêt du script.\n";
            $this->write_info("ERREUR lors de la sauvegarde");
            return false;
        }
        echo "Sauvegarde réussie : {$this->backupFile}\n\n";
        
        // Étape 2 : Récupérer tous les utilisateurs avec mot de passe
        echo "Étape 2/3 : Récupération des utilisateurs...\n";
        $users = $this->getUsers();
        echo "Nombre d'utilisateurs trouvés : " . count($users) . "\n\n";
        
        // Étape 3 : Mettre à jour les mots de passe
        echo "Étape 3/3 : Mise à jour des mots de passe...\n";
        $success = $this->updatePasswords($users);
        
        echo "\n=== Migration terminée ===\n";
        echo "Utilisateurs mis à jour : $success/" . count($users) . "\n";
        echo "Fichier de sauvegarde : {$this->backupFile}\n";
        
        $this->write_info("Migration terminée : $success/" . count($users) . " utilisateurs mis à jour");
        
        return true;
    }
    
    private function backupOldPasswords(): bool
    {
        try {
            // Récupérer tous les ident/passwd non-null
            $sql = "SELECT `ident`, `passwd` 
                    FROM `acteurs` 
                    WHERE `ident` IS NOT NULL 
                    AND `passwd` IS NOT NULL 
                    AND `passwd` != ''
                    ORDER BY `ident`";
            
            $results = $this->objdb->ExecWithFetchAll($sql);
            
            if (empty($results)) {
                $this->write_info("Aucun utilisateur trouvé avec mot de passe");
                return true;
            }
            
            // Créer le contenu du fichier de sauvegarde
            $content = "# Sauvegarde des mots de passe - " . date('Y-m-d H:i:s') . "\n";
            $content .= "# Format : ident | passwd\n";
            $content .= str_repeat("=", 80) . "\n\n";
            
            foreach ($results as $row) {
                $content .= "{$row['ident']} | {$row['passwd']}\n";
            }
            
            // Sauvegarder dans le fichier
            if (file_put_contents($this->backupFile, $content) === false) {
                $this->write_info("ERREUR : Impossible d'écrire le fichier de sauvegarde");
                return false;
            }
            
            // Protéger le fichier (lecture seule pour le propriétaire)
            chmod($this->backupFile, 0400);
            
            $this->write_info("Sauvegarde réussie : " . count($results) . " utilisateurs");
            return true;
            
        } catch (Exception $e) {
            $this->write_info("ERREUR backup : " . $e->getMessage());
            error_log("Backup error: " . $e->getMessage());
            return false;
        }
    }
    
    private function getUsers(): array
    {
        try {
            $sql = "SELECT `id_user`, `ident`, `passwd` 
                    FROM `acteurs` 
                    WHERE `ident` IS NOT NULL 
                    AND `passwd` IS NOT NULL 
                    AND `passwd` != ''
                    ORDER BY `id_user`";
            
            $results = $this->objdb->ExecWithFetchAll($sql);
            
            $this->write_info("Utilisateurs récupérés : " . count($results));
            return $results ?? [];
            
        } catch (Exception $e) {
            $this->write_info("ERREUR getUsers : " . $e->getMessage());
            error_log("Get users error: " . $e->getMessage());
            return [];
        }
    }
    
    private function updatePasswords(array $users): int
    {
        $success = 0;
        
        foreach ($users as $user) {
            try {
                // Créer le hash du mot de passe
                $hashedPassword = password_hash($user['passwd'], PASSWORD_DEFAULT);
                
                if ($hashedPassword === false) {
                    echo "ERREUR hash pour {$user['ident']}\n";
                    $this->write_info("ERREUR hash pour {$user['ident']}");
                    continue;
                }
                
                // Mettre à jour la base de données
                $sql = "UPDATE `acteurs` 
                        SET `passwd` = :passwd 
                        WHERE `id_user` = :id_user";
                
                $params = [
                    ':passwd' => $hashedPassword,
                    ':id_user' => $user['id_user']
                ];
                
                $this->objdb->exec($sql, $params);
                
                echo "✓ {$user['ident']} mis à jour\n";
                $this->write_info("Utilisateur mis à jour : {$user['ident']}");
                $success++;
                
            } catch (Exception $e) {
                echo "✗ ERREUR pour {$user['ident']}: {$e->getMessage()}\n";
                $this->write_info("ERREUR update {$user['ident']}: {$e->getMessage()}");
            }
        }
        
        return $success;
    }
}

// Exécution du script
try {
    echo "\n";
    echo "╔══════════════════════════════════════════════════════════╗\n";
    echo "║  MIGRATION DES MOTS DE PASSE VERS PASSWORD_HASH         ║\n";
    echo "╚══════════════════════════════════════════════════════════╝\n\n";
    
    $updater = new UpdatePasswd();
    $updater->run();
    
} catch (Exception $e) {
    echo "\nERREUR FATALE : " . $e->getMessage() . "\n";
    error_log("Fatal error in update_passwd.php: " . $e->getMessage());
}

echo "\n";
?>
