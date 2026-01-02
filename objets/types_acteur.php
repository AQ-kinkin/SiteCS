<?php

require_once('/home/csresip/www/objets/database.class.php');
require_once('/home/csresip/www/objets/logs.trait.php');

class TypesActeur
{
    use Logs;
    
    private array $labels = []; // [id_type_acteur => libellé]
    private ?Database $db = null;
    private bool $log = false;

    public function __construct(Database $db, $trace = false){
        $this->db = $db;
        $this->log = $trace;
        
        if ($trace == true) {
            $this->PrepareLog('TypesActeur', 'd');
        }

        $this->loadLabels();
    }

    /**
     * Charge les libellés depuis la base de données
     */
    private function loadLabels(): void
    {
        $this->InfoLog("Chargement des types acteurs depuis la base");
        
        $sql = "SELECT id_type_acteur, libelle FROM types_acteur ORDER BY id_type_acteur";
        $this->InfoLog("Requête SQL : $sql");
        
        try {
            $rows = $this->db->ExecWithFetchAll($sql);
            
            $count = 0;
            foreach ($rows as $row) {
                $this->labels[(int)$row['id_type_acteur']] = $row['libelle'];
                $count++;
            }
            
            $this->InfoLog("Types acteurs chargés : $count entrées");
            $this->InfoLog("Labels chargés : " . print_r($this->labels, true));
        } catch (Exception $e) {
            $this->InfoLog("ERREUR : " . $e->getMessage());
        }
    }

    /**
     * Retourne tous les libellés des types d'acteurs
     * @return array [id => libellé]
     */
    public function getLabels(): array
    {
        return $this->labels;
    }

    /**
     * Retourne le libellé d'un type d'acteur spécifique
     * @param int $typeId L'ID du type d'acteur
     * @return string Le libellé ou chaîne vide si non trouvé
     */
    public function getLabel(int $typeId): string
    {
        return $this->labels[$typeId] ?? '';
    }

    /**
     * Retourne les libellés correspondant à un bitmask utilisateur
     * @param int $userType Le bitmask de l'utilisateur (ex: 6 = PROPRIO+CS)
     * @return array Tableau des libellés correspondants
     */
    public function getUserLabels(int $userType): array
    {
        $this->InfoLog("getUserLabels() appelé avec userType=$userType (binaire: " . decbin($userType) . ")");
        $this->InfoLog("Labels disponibles : " . count($this->labels));
        
        $userLabels = [];
        
        foreach ($this->labels as $typeId => $label) {
            $this->InfoLog("  Test typeId=$typeId (binaire: " . decbin($typeId) . ") contre userType=$userType");
            
            // Vérifier si ce type est présent dans le bitmask
            if (($userType & $typeId) !== 0) {
                $userLabels[] = $label;
                $this->InfoLog("    → MATCH trouvé ! Label ajouté : '$label'");
            } else {
                $this->InfoLog("    → Pas de match");
            }
        }
        
        $this->InfoLog("getUserLabels() retourne " . count($userLabels) . " label(s) : " . print_r($userLabels, true));
        
        return $userLabels;
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
            $this->PrepareLog('TypesActeur', 'd');
        }
    }

    /**
     * Méthode magique appelée lors de la sérialisation
     * Exclut Database (qui contient PDO) de la sérialisation
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
