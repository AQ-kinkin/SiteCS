<?php

require_once(PATH_HOME_CS . '/objets/database.class.php');
require_once(PATH_HOME_CS . '/objets/logs.trait.php');

class Halls
{
    use Logs;
    
    private array $data = []; // [id_hall => ['esc' => ..., 'bat' => ..., ...]]
    private ?Database $db = null;
    private bool $log = false;

    public function __construct(Database $db, bool $trace = false)
    {
        $this->db = $db;
        $this->log = $trace;
        
        if ($trace) {
            $this->PrepareLog('Halls', 'd');
        }
        
        $this->loadData();
    }

    /**
     * Charge les données des halls depuis la base de données
     */
    private function loadData(): void
    {
        $this->InfoLog("Chargement des halls depuis la base");
        
        $sql = "SELECT id_hall, esc, bat FROM halls ORDER BY id_hall";
        $this->InfoLog("Requête SQL : $sql");
        
        try {
            $rows = $this->db->ExecWithFetchAll($sql);
            
            $count = 0;
            foreach ($rows as $row) {
                $this->data[(int)$row['id_hall']] = [
                    'esc' => $row['esc'],
                    'bat' => (int)$row['bat']
                ];
                $count++;
            }
            
            $this->InfoLog("Halls chargés : $count entrées");
            $this->InfoLog("Data chargée : " . print_r($this->data, true));
        } catch (Exception $e) {
            $this->InfoLog("ERREUR : " . $e->getMessage());
        }
    }

    /**
     * Retourne toutes les données des halls
     * @return array [id => ['esc' => ..., 'bat' => ...]]
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Retourne les données d'un hall spécifique
     * @param int $hallId L'ID du hall
     * @return array|null Les données du hall ou null si non trouvé
     */
    public function getHall(int $hallId): ?array
    {
        return $this->data[$hallId] ?? null;
    }

    /**
     * Retourne le numéro d'escalier d'un hall
     * @param int $hallId L'ID du hall
     * @return string|null Le numéro d'escalier ou null
     */
    public function getEsc(int $hallId): ?string
    {
        return $this->data[$hallId]['esc'] ?? null;
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
            $this->PrepareLog('Halls', 'd');
        }
    }

    /**
     * Méthode magique appelée lors de la sérialisation
     * Exclut Database (qui contient PDO) et logsPath de la sérialisation
     */
    public function __sleep(): array
    {
        return ['data', 'log']; // Sérialiser data et log pour garder l'état
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

     /**
      * Retourne un tableau de tableaux associatifs avec les clés : mark, name, floor pour un hall donné
      * @param int $id_hall L'ID du hall
      * @return array Tableau des données des appartements du hall
      */
     public function get_page_hall(int $id_hall): array
     {
         $this->InfoLog("Récupération des données pour le hall $id_hall");

         $sql = "
             SELECT
                 `lots`.`repere` AS mark,
                 `appartements`.`Labelhall` AS name,
                 CONCAT(
                     IF(`appartements`.`etage` = 0, 'RC', `appartements`.`etage`),
                     ' ',
                     `appartements`.`position`
                 ) AS floor
             FROM `appartements`
             JOIN `lots` ON `appartements`.`lot` = `lots`.`lot`
             WHERE `lots`.`position_id` = ?
             ORDER BY `appartements`.`etage` DESC;
         ";

         $this->InfoLog("Requête SQL : $sql avec paramètre $id_hall");

         try {
             $rows = $this->db->ExecWithFetchAll($sql, [$id_hall]);
             $this->InfoLog("Données récupérées : " . count($rows) . " entrées");
             return $rows;
         } catch (Exception $e) {
             $this->InfoLog("ERREUR : " . $e->getMessage());
             return [];
         }
     }

     /**
      * Retourne un tableau simple contenant tous les id_hall disponibles
      * @return array Liste des ID des halls
      */
     public function get_name_halls(): array
     {
         return array_keys($this->data);
     }
 }
