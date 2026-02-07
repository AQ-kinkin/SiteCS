<?php
/**
 * Script de nettoyage automatique des sessions expirées
 * 
 * Ce script doit être exécuté quotidiennement via une tâche CRON sur OVH.
 * Il supprime les sessions expirées depuis plus de 48 heures.
 * 
 * Configuration CRON OVH :
 * - Commande : /usr/local/php8.2/bin/php /home/csresip/www/automatisation/cleanup_sessions.php
 * - Fréquence : Tous les jours à 3h00 du matin
 * - Email : Désactivé (ou votre email pour recevoir les rapports)
 * 
 * Exemple de configuration CRON :
 * 0 3 * * * /usr/local/php8.2/bin/php /home/csresip/www/automatisation/cleanup_sessions.php
 * 
 * @author GitHub Copilot
 * @date 2025-11-09
 */

// Chemins absolus
$pathHome = '/home/csresip/www';

// Inclure les dépendances
require_once($pathHome . '/objets/database.class.php');
require_once($pathHome . '/objets/mysql.sessions.php');

// Date/heure de début
$start_time = microtime(true);
$start_date = date('Y-m-d H:i:s');

// Démarrer la capture de l'output
ob_start();

echo "=" . str_repeat("=", 70) . "\n";
echo "NETTOYAGE DES SESSIONS EXPIRÉES\n";
echo "=" . str_repeat("=", 70) . "\n";
echo "Début : $start_date\n\n";

try {
    // Initialiser la base de données
    echo "[1/4] Connexion à la base de données...\n";
    $objdb = new Database();
    echo "      ✓ Connexion établie\n\n";
    
    // Créer l'objet Session
    echo "[2/4] Initialisation du gestionnaire de sessions...\n";
    $session = new Session($objdb);
    echo "      ✓ Gestionnaire initialisé\n\n";
    
    // Définir le délai : 48 heures = 172800 secondes
    $max_lifetime = 48 * 3600; // 48 heures
    echo "[3/4] Configuration du nettoyage :\n";
    echo "      - Délai d'expiration = $max_lifetime secondes\n";
    echo "      - Équivalent à 48 heures\n";
    echo "      - Sessions inactives depuis plus de 48h seront supprimées\n\n";
    
    // Compter les sessions avant nettoyage
    $sql_count_before = "SELECT COUNT(*) as total FROM `sessions`";
    $result_before = $objdb->execonerow($sql_count_before);
    $count_before = $result_before['total'] ?? 0;
    echo "      - Sessions avant nettoyage : $count_before\n\n";
    
    // Exécuter le garbage collector
    echo "[4/4] Exécution du nettoyage (gc)...\n";
    $gc_result = $session->gc($max_lifetime);
    
    if ($gc_result === false) {
        throw new Exception("Erreur lors de l'exécution du garbage collector");
    }
    
    // Compter les sessions après nettoyage
    $sql_count_after = "SELECT COUNT(*) as total FROM `sessions`";
    $result_after = $objdb->execonerow($sql_count_after);
    $count_after = $result_after['total'] ?? 0;
    $deleted = $count_before - $count_after;
    
    echo "      ✓ Nettoyage terminé avec succès\n";
    echo "      - Sessions après nettoyage : $count_after\n";
    echo "      - Sessions supprimées : $deleted\n\n";
    
    // Statistiques finales
    $end_time = microtime(true);
    $duration = round($end_time - $start_time, 3);
    $end_date = date('Y-m-d H:i:s');
    
    echo "=" . str_repeat("=", 70) . "\n";
    echo "RÉSUMÉ\n";
    echo "=" . str_repeat("=", 70) . "\n";
    echo "Fin : $end_date\n";
    echo "Durée d'exécution : {$duration}s\n";
    echo "Statut : ✓ SUCCÈS\n";
    echo "=" . str_repeat("=", 70) . "\n";
    
    // Capturer l'output et l'écrire dans un fichier de log
    $output = ob_get_clean();
    $log_file = '/home/csresip/www/logs/cleanup_execution.log';
    file_put_contents($log_file, $output . "\n\n", FILE_APPEND | LOCK_EX);
    
    // Afficher aussi dans la console (pour email CRON si configuré)
    echo $output;
    
    // Code de sortie succès
    exit(0);
    
} catch (Exception $e) {
    // Gestion des erreurs
    $end_date = date('Y-m-d H:i:s');
    $duration = round(microtime(true) - $start_time, 3);
    
    echo "\n";
    echo "=" . str_repeat("=", 70) . "\n";
    echo "ERREUR\n";
    echo "=" . str_repeat("=", 70) . "\n";
    echo "Date : $end_date\n";
    echo "Durée : {$duration}s\n";
    echo "Message : " . $e->getMessage() . "\n";
    echo "Fichier : " . $e->getFile() . "\n";
    echo "Ligne : " . $e->getLine() . "\n";
    echo "=" . str_repeat("=", 70) . "\n";
    
    // Capturer l'output et l'écrire dans un fichier de log
    $output = ob_get_clean();
    $log_file = '/home/csresip/www/logs/cleanup_execution.log';
    file_put_contents($log_file, $output . "\n\n", FILE_APPEND | LOCK_EX);
    
    // Afficher aussi dans la console (pour email CRON si configuré)
    echo $output;
    
    // Écrire dans les logs PHP
    error_log("Cleanup sessions error: " . $e->getMessage());
    
    // Code de sortie erreur
    exit(1);
}
