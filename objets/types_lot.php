<?php

require_once('/home/csresip/www/objets/database.class.php');
require_once('/home/csresip/www/objets/logs.trait.php');

class TypesLot
{
    use Logs;
    
    private array $labels = []; // [id_type_lot => libellé]
    private ?Database $db = null;
    private bool $log = false;

    public function __construct(Database $db, bool $trace = false)
    {
        $this->db = $db;
        $this->log = $trace;
        
        if ($trace) {
            $this->PrepareLog('TypesLot', 'd');
        }
        
        $this->loadLabels();
    }

    /**
     * Charge les libellés depuis la base de données
     */
    private function loadLabels(): void
    {
        $this->InfoLog("Chargement des types de lots depuis la base");
        
        $sql = "SELECT id_type_lot, libelle FROM types_lot ORDER BY id_type_lot";
        $this->InfoLog("Requête SQL : $sql");
        
        try {
            $rows = $this->db->ExecWithFetchAll($sql);
            
            $count = 0;
            foreach ($rows as $row) {
                $this->labels[(int)$row['id_type_lot']] = $row['libelle'];
                $count++;
            }
            
            $this->InfoLog("Types de lots chargés : $count entrées");
            $this->InfoLog("Labels chargés : " . print_r($this->labels, true));
        } catch (Exception $e) {
            $this->InfoLog("ERREUR : " . $e->getMessage());
        }
    }

    /**
     * Retourne tous les libellés des types de lots
     * @return array [id => libellé]
     */
    public function getLabels(): array
    {
        return $this->labels;
    }

    /**
     * Retourne le libellé d'un type de lot spécifique
     * @param int $typeId L'ID du type de lot
     * @return string Le libellé ou chaîne vide si non trouvé
     */
    public function getLabel(int $typeId): string
    {
        return $this->labels[$typeId] ?? '';
    }

    /**
     * Wrapper conditionnel pour les logs
     */
    private function InfoLog(string $message): void
    {
        if ($this->log === false) return;
        
        $this->write_info($message);
    }

    /**
     * Réinitialise les logs après désérialisation
     * Nécessaire car logsPath n'est pas sérialisé
     */
    public function reinitLogs(): void
    {
        if ($this->log === true) {
            $this->PrepareLog('TypesLot', 'd');
        }
    }

    /**
     * Méthode magique appelée lors de la sérialisation
     * Exclut Database (qui contient PDO) et logsPath de la sérialisation
     */
    public function __sleep(): array
    {
        return ['labels', 'log']; // Sérialiser labels et log pour garder l'état
    }

    /**
     * Méthode magique appelée lors de la désérialisation
     * Restaure Database depuis Site global
     */
    public function __wakeup(): void
    {
        // db sera réinitialisé via gestion_site __get()
        // log garde son état sérialisé
    }
}
