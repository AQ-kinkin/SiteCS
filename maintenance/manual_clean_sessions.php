<?php
/**
 * Script de nettoyage manuel des sessions expirées
 * 
 * À exécuter manuellement via navigateur ou ligne de commande
 * pour nettoyer les sessions obsolètes immédiatement.
 * 
 * Usage :
 * - Via navigateur : https://votresite.com/maintenance/manual_clean_sessions.php
 * - Via CLI : php /home/csresip/www/maintenance/manual_clean_sessions.php
 * 
 * @author GitHub Copilot
 * @date 2025-11-10
 */

// Chemins absolus
$pathHome = '/home/csresip/www';

// Inclure les dépendances
require_once($pathHome . '/objets/database.class.php');
require_once($pathHome . '/objets/gestion_site.php');

// Configuration
$output_html = php_sapi_name() !== 'cli'; // Détecter si exécuté via navigateur

// Démarrer la capture de l'output
ob_start();

// Date/heure de début
$start_time = microtime(true);
$start_date = date('Y-m-d H:i:s');

if ($output_html) {
    echo "<!DOCTYPE html>\n<html lang=\"fr\">\n<head>\n";
    echo "<meta charset=\"UTF-8\">\n";
    echo "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
    echo "<title>Nettoyage Manuel des Sessions</title>\n";
    echo "<style>\n";
    echo "body { font-family: monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px; }\n";
    echo "h1 { color: #4ec9b0; }\n";
    echo ".success { color: #4ec9b0; }\n";
    echo ".error { color: #f48771; }\n";
    echo ".info { color: #9cdcfe; }\n";
    echo ".separator { border-top: 1px solid #3e3e42; margin: 20px 0; }\n";
    echo "</style>\n</head>\n<body>\n";
}

echo str_repeat("=", 70) . "\n";
echo "NETTOYAGE MANUEL DES SESSIONS\n";
echo str_repeat("=", 70) . "\n";
echo "Début : $start_date\n\n";

try {
    // Initialiser le site
    echo "[1/5] Initialisation du site...\n";
    $objsite = new Site;
    $objsite->open();
    echo "      ✓ Site initialisé\n\n";
    
    // Vérifier les droits (niveau CS requis)
    echo "[2/5] Vérification des droits d'accès...\n";
    if ($output_html) {
        // Si via navigateur, vérifier authentification
        $objsite->requireAuth(Site::DROIT_CS, false);
        echo "      ✓ Droits vérifiés (utilisateur authentifié)\n\n";
    } else {
        // Si via CLI, pas de vérification
        echo "      ⓘ Exécution en ligne de commande (pas de vérification)\n\n";
    }
    
    // Récupérer la base de données
    echo "[3/5] Connexion à la base de données...\n";
    $objdb = $objsite->getDB();
    echo "      ✓ Connexion établie\n\n";
    
    // Définir les seuils de nettoyage
    echo "[4/5] Configuration du nettoyage :\n";
    
    // Option 1 : Sessions très anciennes (48 heures)
    $max_lifetime_old = 48 * 3600; // 48 heures
    $sql_old = "DELETE FROM `sessions` WHERE `access` < DATE_SUB(NOW(), INTERVAL :max_lifetime SECOND)";
    $params_old = [':max_lifetime' => $max_lifetime_old];
    
    // Option 2 : Sessions expirées selon gc_maxlifetime (45 min par défaut)
    $gc_maxlifetime = ini_get('session.gc_maxlifetime');
    $sql_expired = "DELETE FROM `sessions` WHERE `access` < DATE_SUB(NOW(), INTERVAL :max_lifetime SECOND)";
    $params_expired = [':max_lifetime' => $gc_maxlifetime];
    
    echo "      - Option 1 : Sessions > 48 heures\n";
    echo "      - Option 2 : Sessions > " . ($gc_maxlifetime / 60) . " minutes (gc_maxlifetime)\n\n";
    
    // Compter les sessions avant nettoyage
    $sql_count_before = "SELECT COUNT(*) as total FROM `sessions`";
    $result_before = $objdb->execonerow($sql_count_before);
    $count_before = $result_before['total'] ?? 0;
    
    // Compter les sessions à supprimer (48h)
    $sql_count_old = "SELECT COUNT(*) as total FROM `sessions` WHERE `access` < DATE_SUB(NOW(), INTERVAL {$max_lifetime_old} SECOND)";
    $result_count_old = $objdb->execonerow($sql_count_old);
    $count_old = $result_count_old['total'] ?? 0;
    
    // Compter les sessions expirées (gc_maxlifetime)
    $sql_count_expired = "SELECT COUNT(*) as total FROM `sessions` WHERE `access` < DATE_SUB(NOW(), INTERVAL {$gc_maxlifetime} SECOND)";
    $result_count_expired = $objdb->execonerow($sql_count_expired);
    $count_expired = $result_count_expired['total'] ?? 0;
    
    echo "      - Sessions totales : $count_before\n";
    echo "      - Sessions > 48h : $count_old\n";
    echo "      - Sessions expirées : $count_expired\n\n";
    
    // Exécuter le nettoyage
    echo "[5/5] Exécution du nettoyage...\n";
    
    // Nettoyer les sessions très anciennes (48h)
    $objdb->exec($sql_old, $params_old);
    echo "      ✓ Sessions > 48h supprimées : $count_old\n";
    
    // Nettoyer les sessions expirées restantes
    $objdb->exec($sql_expired, $params_expired);
    $deleted_expired = $count_expired - $count_old;
    if ($deleted_expired > 0) {
        echo "      ✓ Sessions expirées supprimées : $deleted_expired\n";
    }
    
    // Compter les sessions après nettoyage
    $sql_count_after = "SELECT COUNT(*) as total FROM `sessions`";
    $result_after = $objdb->execonerow($sql_count_after);
    $count_after = $result_after['total'] ?? 0;
    $total_deleted = $count_before - $count_after;
    
    echo "      ✓ Nettoyage terminé\n";
    echo "      - Sessions restantes : $count_after\n";
    echo "      - Total supprimé : $total_deleted\n\n";
    
    // Statistiques finales
    $end_time = microtime(true);
    $duration = round($end_time - $start_time, 3);
    $end_date = date('Y-m-d H:i:s');
    
    echo str_repeat("=", 70) . "\n";
    echo "RÉSUMÉ\n";
    echo str_repeat("=", 70) . "\n";
    echo "Fin : $end_date\n";
    echo "Durée d'exécution : {$duration}s\n";
    echo "Statut : ✓ SUCCÈS\n";
    echo str_repeat("=", 70) . "\n";
    
    $status = 'success';
    
} catch (Exception $e) {
    // Gestion des erreurs
    $end_date = date('Y-m-d H:i:s');
    $duration = round(microtime(true) - $start_time, 3);
    
    echo "\n";
    echo str_repeat("=", 70) . "\n";
    echo "ERREUR\n";
    echo str_repeat("=", 70) . "\n";
    echo "Date : $end_date\n";
    echo "Durée : {$duration}s\n";
    echo "Message : " . $e->getMessage() . "\n";
    echo "Fichier : " . $e->getFile() . "\n";
    echo "Ligne : " . $e->getLine() . "\n";
    echo str_repeat("=", 70) . "\n";
    
    // Écrire dans les logs PHP
    error_log("Manual cleanup sessions error: " . $e->getMessage());
    
    $status = 'error';
}

// Récupérer l'output
$output = ob_get_clean();

// Afficher selon le contexte
if ($output_html) {
    // Convertir pour HTML
    $output_html_formatted = nl2br(htmlspecialchars($output));
    $output_html_formatted = str_replace('✓', '<span class="success">✓</span>', $output_html_formatted);
    $output_html_formatted = str_replace('ERREUR', '<span class="error">ERREUR</span>', $output_html_formatted);
    $output_html_formatted = str_replace('ⓘ', '<span class="info">ⓘ</span>', $output_html_formatted);
    
    echo "<pre>$output_html_formatted</pre>\n";
    echo "</body>\n</html>";
} else {
    // Afficher en texte brut (CLI)
    echo $output;
}

// Code de sortie
exit($status === 'success' ? 0 : 1);
