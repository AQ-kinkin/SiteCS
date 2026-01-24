<?php

require_once(PATH_HOME_CS . '/objets/database.class.php');
require_once(PATH_HOME_CS . '/objets/logs.trait.php');
require_once(PATH_HOME_CS . '/objets/appartement.php');
require_once(PATH_HOME_CS . '/objets/cave.php');
require_once(PATH_HOME_CS . '/objets/parking.php');

/**
 * Collection de lots pour un utilisateur
 * Implémente ArrayAccess et Iterator pour utilisation comme tableau
 */
class Lots implements ArrayAccess, Iterator, Countable
{
    use Logs;
    
    private array $lots = [];           // Tableau de Lot (Appartement|Cave|Parking)
    private int $position = 0;
    private ?Database $db = null;       // Non sérialisable
    private bool $log = false;          // Activation des logs

    /**
     * Constructeur privé (utiliser loadForUser)
     */
    private function __construct(Database $db, bool $trace = false)
    {
        $this->db = $db;
        $this->log = $trace;
        
        if ($trace === true) {
            $this->PrepareLog('Lots', 'd');
        }
    }

    /**
     * Factory method : charge tous les lots d'un utilisateur
     * @param Database $db
     * @param int $userId
     * @param bool $trace Activer les logs
     * @return Lots
     */
    public static function loadForUser(Database $db, int $userId, bool $trace = false): Lots
    {
        $collection = new self($db, $trace);
        $collection->load($userId);
        return $collection;
    }

    /**
     * Charge les lots depuis la base de données
     */
    private function load(int $userId): void
    {
        $this->InfoLog("Début chargement lots pour user_id=$userId");
        
        // Requête JOIN pour récupérer lots + appartements
        $sql = "
            WITH halls_enrichis AS (
                SELECT `halls`.id_hall, concat(`batiment`.`nom`, ' ', `halls`.esc) as nom_bat
                FROM `halls`
                LEFT JOIN `batiment` ON `halls`.bat = `batiment`.id_batiment
            )
            SELECT lots.lot, lots.type_lot, lots.position_id, lots.proprio, lots.repere, lots.commentaire, lots.tantieme, appartements.etage, appartements.type_appartement, appartements.position, types_lot.libelle as Label_Type_Lot, types_lot.code As Code_Type_Lot, def_parking.libelle as park_position, def_parking.code as park_position_code, halls_enrichis.nom_bat
            FROM gerants
            INNER JOIN lots ON gerants.lot = lots.lot
            INNER JOIN types_lot ON lots.type_lot = types_lot.id_type_lot
            LEFT JOIN appartements ON lots.lot = appartements.lot AND lots.type_lot = 1
            LEFT JOIN def_parking ON lots.position_id = def_parking.niveau_id AND lots.type_lot = 3
            LEFT JOIN halls_enrichis ON `lots`.position_id = halls_enrichis.id_hall AND `lots`.type_lot in (1,2)
            WHERE gerants.user_id = ?
            ORDER BY lots.type_lot, lots.lot
        ";
        
        $this->InfoLog("Exécution requête SQL");
        $this->write_sql($sql, [$userId]);
        
        try {
            $rows = $this->db->ExecWithFetchAll($sql, [$userId]);
            
            $this->InfoLog("Requête réussie : " . count($rows) . " ligne(s) retournée(s)");
            
            foreach ($rows as $row) {
                $lot = $this->createLot($row);
                if ($lot !== null) {
                    $this->lots[] = $lot;
                    $this->InfoLog("Lot créé : type=" . $row['type_lot'] . ", lot=" . $row['lot']);
                }
            }
            
            $this->InfoLog("Chargement terminé : " . count($this->lots) . " lot(s) chargé(s)");
        } catch (Exception $e) {
            $this->ErrorLog("Erreur chargement lots pour user $userId: " . $e->getMessage());
        }
    }

    /**
     * Factory pattern : crée le bon type de Lot selon type_lot
     * @param array $row Ligne de résultat SQL
     * @return Lot|null
     */
    private function createLot(array $row): ?Lot
    {
        $typeLot = (int)$row['type_lot'];
        
        switch ($typeLot) {
            case 1: // Appartement
                $appartData = [
                    'etage' => $row['etage'],
                    'type_appartement' => $row['type_appartement'],
                    'position' => $row['position'],
                    'batiment_nom' => $row['nom_bat']
                ];
                return new Appartement($this->db, $row, $appartData, true);
                
            case 2: // Cave
                $appartData = [
                    'batiment_nom' => $row['nom_bat']
                ];
                return new Cave($this->db, $row, $appartData, true);
                
            case 3: // Parking
                return new Parking($this->db, $row);
                
            default:
                error_log("Type de lot inconnu: $typeLot");
                return null;
        }
    }

    /**
     * Retourne tous les lots
     * @return array
     */
    public function getAll(): array
    {
        return $this->lots;
    }

    /**
     * Retourne un lot par son numéro
     * @param int $lotNum
     * @return Lot|null
     */
    public function getByNum(int $lotNum): ?Lot
    {
        foreach ($this->lots as $lot) {
            if ($lot->getLot() === $lotNum) {
                return $lot;
            }
        }
        return null;
    }

    /**
     * Filtre les lots par type (1=Appart, 2=Cave, 3=Park)
     * @param int $typeLot
     * @return array
     */
    public function filterByType(int $typeLot): array
    {
        return array_filter($this->lots, function($lot) use ($typeLot) {
            return $lot->getTypeLot() === $typeLot;
        });
    }

    // ========== Implémentation ArrayAccess ==========
    
    public function offsetExists($offset): bool
    {
        return isset($this->lots[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return $this->lots[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        if ($offset === null) {
            $this->lots[] = $value;
        } else {
            $this->lots[$offset] = $value;
        }
    }

    public function offsetUnset($offset): void
    {
        unset($this->lots[$offset]);
    }

    // ========== Implémentation Iterator ==========
    
    public function current(): mixed
    {
        return $this->lots[$this->position];
    }

    public function key(): mixed
    {
        return $this->position;
    }

    public function next(): void
    {
        ++$this->position;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function valid(): bool
    {
        return isset($this->lots[$this->position]);
    }

    // ========== Implémentation Countable ==========
    
    public function count(): int
    {
        return count($this->lots);
    }

    // ========== Sérialisation ==========
    
    /**
     * Méthode magique pour la sérialisation
     * Exclut Database de la sérialisation
     */
    public function __sleep(): array
    {
        return ['lots', 'log'];
    }

    /**
     * Méthode magique pour la désérialisation
     */
    public function __wakeup(): void
    {
        $this->position = 0;
        // db sera réinjecté via setDatabase()
    }

    /**
     * Réinjecte la référence Database après désérialisation
     */
    public function setDatabase(Database $db): void
    {
        $this->db = $db;
        
        // Réinjecter aussi dans chaque Lot
        foreach ($this->lots as $lot) {
            $lot->setDatabase($db);
        }
    }
    
    /**
     * Wrapper conditionnel pour les logs d'information
     */
    private function InfoLog(string $message): void
    {
        if ($this->log === false) return;
        $this->write_info($message);
    }
    
    /**
     * Wrapper conditionnel pour les logs d'erreur
     */
    private function ErrorLog(string $message): void
    {
        if ($this->log === false) return;
        $this->write_error($message);
    }
    
    /**
     * Réinitialise les logs après désérialisation
     */
    public function reinitLogs(): void
    {
        if ($this->log === true) {
            $this->PrepareLog('Lots', 'd');
        }
    }
}
