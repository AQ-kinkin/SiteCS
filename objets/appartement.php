<?php

require_once(PATH_HOME_CS . '/objets/lot.php');
require_once(PATH_HOME_CS . '/objets/logs.trait.php');

/**
 * Classe représentant un appartement
 * position_id est une FK vers halls
 */
class Appartement extends Lot
{
    private ?int $etage = null;              // Étage (0=RC, 1, 2, ...)
    private ?int $typeAppartement = null;    // FK vers types_appartements (F1, F2, ...)
    private ?string $position = null;        // G ou D (Gauche/Droite)
    private ?string $batiment_nom = null;        // Nom du bâtiment

    /**
     * Constructeur
     */
    public function __construct(Database $db, array $lotData, array $appartData = [], $trace = false)
    {
        parent::__construct($db, $lotData);
        
        if ($trace == true) {
            $this->PrepareLog('Appartement', 'd');
        }
        
        if (!empty($appartData)) {
            $this->etage = isset($appartData['etage']) ? (int)$appartData['etage'] : null;
            $this->typeAppartement = isset($appartData['type_appartement']) ? (int)$appartData['type_appartement'] : null;
            $this->position = $appartData['position'] ?? null;
            $this->batiment_nom = $appartData['batiment_nom'] ?? null;
            
            $this->InfoLog("Construction Appartement - lot: {$lotData['lot']}");
            $this->InfoLog("batiment_nom assigné: " . ($this->batiment_nom ?? 'NULL'));
            $this->InfoLog("positionId: {$this->positionId}");
        }
    }

    // Getters spécifiques
    public function getEtage(): ?int { return $this->etage; }
    public function getTypeAppartement(): ?int { return $this->typeAppartement; }
    public function getPosition(): ?string { return $this->position; }
    public function getHall(): int { return $this->positionId; }
    public function getBatiment(): ?string { return $this->batiment_nom; }

    /**
     * Retourne 'Sonnette' pour un appartement
     */
    public function getRepereLabel(): string
    {
        return 'Sonnette';
    }

    /**
     * Description textuelle de l'appartement
     */
    public function getDescription(): string
    {
        $desc = "Appartement {$this->lot}";
        if ($this->typeAppartement) {
            $desc .= " (F{$this->typeAppartement})";
        }
        if ($this->etage !== null) {
            $desc .= " - Étage " . ($this->etage === 0 ? 'RDC' : $this->etage);
        }
        if ($this->position) {
            $desc .= " {$this->position}";
        }
        return $desc;
    }

    /**
     * Génère le HTML complet pour l'affichage de l'appartement
     */
    public function get_html_panel(): string
    {
        $typeLabel = htmlspecialchars($this->labelTypeLot ?? 'Appartement');
        $lotNumber = htmlspecialchars($this->lot);
        $repereValue = $this->repere ? htmlspecialchars($this->repere) : 'N/A';
        $batiment = $this->getBatiment() ? htmlspecialchars($this->getBatiment()) : 'N/A';
        
        // Détails appartement
        $hallNum = $this->getHall();
        $etageText = $this->etage !== null ? ($this->etage === 0 ? 'RC' : $this->etage) : 'N/A';
        
        $this->InfoLog("=== get_html_panel Appartement ===");
        $this->InfoLog("batiment_nom (propriété): " . ($this->batiment_nom ?? 'NULL'));
        $this->InfoLog("getBatiment(): " . ($this->getBatiment() ?? 'NULL'));
        $this->InfoLog("Variable $batiment: $batiment");
        $this->InfoLog("hallNum (getHall()): $hallNum");
        $this->InfoLog("positionId: {$this->positionId}");
        $typeText = $this->typeAppartement ? 'F' . $this->typeAppartement : 'N/A';
        $tantieme = $this->tantieme ? htmlspecialchars($this->tantieme) : 'N/A';
        
        return <<<HTML
                    <div class="lot-card appartement">
                        <div class="lot-header">
                            <div class="lot-icon-wrapper">
                                <div class="lot-icon appartement">
                                    <img src="/icons/appartement-24x24.png" alt="A">
                                </div>
                                <div class="lot-title">
                                    <h3>{$typeLabel}</h3>
                                    <div class="lot-reference">{$repereValue}</div>
                                </div>
                            </div>
                            <div class="lot-id">{$lotNumber}</div>
                        </div>
                        <div class="lot-details">
                            <div class="lot-detail">
                                <span class="lot-detail-label">Bâtiment:</span>
                                <span class="lot-detail-value">{$batiment}</span>
                            </div>
                            <div class="lot-detail">
                                <span class="lot-detail-label">Hall:</span>
                                <span class="lot-detail-value">{$this->getHall()}</span>
                            </div>
                            <div class="lot-detail">
                                <span class="lot-detail-label">Étage:</span>
                                <span class="lot-detail-value">{$etageText}</span>
                            </div>
                            <div class="lot-detail">
                                <span class="lot-detail-label">Type:</span>
                                <span class="lot-detail-value">{$typeText}</span>
                            </div>
                            <div class="lot-detail">
                                <span class="lot-detail-label">Tantième:</span>
                                <span class="lot-detail-value">{$tantieme}</span>
                            </div>
                        </div>
                    </div>
HTML;
    }

    /**
     * Sérialisation : ajouter les champs spécifiques
     */
    public function __sleep(): array
    {
        return array_merge(parent::__sleep(), ['etage', 'typeAppartement', 'position']);
    }
}
