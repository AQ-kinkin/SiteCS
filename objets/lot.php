<?php

require_once('/home/csresip/www/objets/database.class.php');

/**
 * Classe abstraite représentant un lot de copropriété
 * Un lot peut être un appartement, une cave ou un parking
 */
abstract class Lot
{
    use Logs;

    protected int $lot;                    // Numéro du lot (PK)
    protected int $typeLot;                // FK vers types_lot (1=Appart, 2=Cave, 3=Park)
    protected int $positionId;             // FK vers halls OU def_parking
    protected ?int $proprietaire = null;   // FK vers acteurs.id_user
    protected ?int $repere = null;         // Sonnette/Porte/Place selon type
    protected ?string $commentaire = null;
    protected ?int $tantieme = null;       // Tantième du lot
    protected bool $log = false;             // Active/désactive les logs
    
    // Données enrichies depuis la requête SQL
    protected ?string $labelTypeLot = null;  // Label du type (Appartement/Cave/Parking)
    
    protected ?Database $db = null;        // Non sérialisable

    /**
     * Constructeur protégé (utilisé par les classes filles)
     */
    protected function __construct(Database $db, array $data)
    {
        $this->db = $db;
        $this->lot = (int)$data['lot'];
        $this->typeLot = (int)$data['type_lot'];
        $this->positionId = (int)$data['position_id'];
        $this->proprietaire = isset($data['proprio']) ? (int)$data['proprio'] : null;
        $this->repere = isset($data['repere']) ? (int)$data['repere'] : null;
        $this->commentaire = $data['commentaire'] ?? null;
        $this->tantieme = isset($data['tantieme']) ? (int)$data['tantieme'] : null;
        
        // Stocker les données enrichies
        $this->labelTypeLot = $data['Label_Type_Lot'] ?? null;
    }

    // Getters communs
    public function getLot(): int { return $this->lot; }
    public function getTypeLot(): int { return $this->typeLot; }
    public function getPositionId(): int { return $this->positionId; }
    public function getProprietaire(): ?int { return $this->proprietaire; }
    public function getRepere(): ?int { return $this->repere; }
    public function getCommentaire(): ?string { return $this->commentaire; }
    public function getTantieme(): ?int { return $this->tantieme; }
    
    // Getters pour données enrichies
    public function getLabelTypeLot(): ?string { return $this->labelTypeLot; }

    /**
     * Retourne le label du repère selon le type de lot
     * @return string 'Sonnette', 'Porte' ou 'Place'
     */
    abstract public function getRepereLabel(): string;

    /**
     * Retourne une représentation textuelle du lot
     * @return string
     */
    abstract public function getDescription(): string;

    /**
     * Génère le HTML complet pour l'affichage du lot
     * @return string HTML complet avec <div class="lot-card">
     */
    abstract public function get_html_panel(): string;

    /**
     * Méthode magique pour la sérialisation
     * Exclut Database de la sérialisation
     */
    public function __sleep(): array
    {
        return [
            'lot', 'typeLot', 'positionId',
            'proprietaire', 'repere', 'commentaire', 'tantieme',
            'labelTypeLot'
        ];
    }

    /**
     * Méthode magique pour la désérialisation
     */
    public function __wakeup(): void
    {
        // db sera réinjecté par Lots
    }

    /**
     * Réinjecte la référence Database (appelé après désérialisation)
     */
    public function setDatabase(Database $db): void
    {
        $this->db = $db;
    }

    /**
     * Wrapper conditionnel pour les logs
     */
    protected function InfoLog(string $message): void
    {
        if ($this->log === false) return;
        
        $this->write_info($message);
    }
}
