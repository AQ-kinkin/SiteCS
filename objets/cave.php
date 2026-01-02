<?php

require_once('/home/csresip/www/objets/lot.php');
require_once('/home/csresip/www/objets/logs.trait.php');

/**
 * Classe représentant une cave
 * position_id est une FK vers halls
 */
class Cave extends Lot
{
    private ?string $batiment_nom = null;        // Nom du bâtiment

    /**
     * Constructeur
     */
    public function __construct(Database $db, array $lotData, array $caveData = [], $trace = false)
    {
        parent::__construct($db, $lotData);
        
        if ($trace == true) {
            $this->PrepareLog('Cave', 'd');
        }
        
        if (!empty($caveData)) {
            $this->batiment_nom = $caveData['batiment_nom'] ?? null;
            
            $this->InfoLog("Construction Cave - lot: {$lotData['lot']}");
            $this->InfoLog("batiment_nom assigné: " . ($this->batiment_nom ?? 'NULL'));
            $this->InfoLog("positionId: {$this->positionId}");
        }
    }
    
    // Getter spécifique
    public function getPorte(): int { return $this->positionId; }
    public function getBatiment(): ?string { return $this->batiment_nom; }

    /**
     * Retourne 'Porte' pour une cave
     */
    public function getRepereLabel(): string
    {
        return 'Porte';
    }

    /**
     * Description textuelle de la cave
     */
    public function getDescription(): string
    {
        return $this->lot;
    }

    /**
     * Génère le HTML complet pour l'affichage de la cave
     */
    public function get_html_panel(): string
    {
        $typeLabel = htmlspecialchars($this->labelTypeLot ?? 'Cave');
        $lotNumber = htmlspecialchars($this->lot);
        $repereValue = $this->repere ? htmlspecialchars($this->repere) : 'N/A';
        
        // Détails cave
        $batiment = $this->getBatiment() ? htmlspecialchars($this->getBatiment()) : 'N/A';
        $hallNum = $this->getPorte();
        $tantieme = $this->tantieme ? htmlspecialchars($this->tantieme) : 'N/A';
        
        $this->InfoLog("=== get_html_panel Cave ===");
        $this->InfoLog("batiment_nom (propriété): " . ($this->batiment_nom ?? 'NULL'));
        $this->InfoLog("getBatiment(): " . ($this->getBatiment() ?? 'NULL'));
        $this->InfoLog("Variable $batiment: $batiment");
        $this->InfoLog("hallNum (getPorte()): $hallNum");
        $this->InfoLog("positionId: {$this->positionId}");
        
        return <<<HTML
                    <div class="lot-card cave">
                        <div class="lot-header">
                            <div class="lot-icon-wrapper">
                                <div class="lot-icon cave">
                                    <img src="/icons/cave-24x24.png" alt="C">
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
                                <span class="lot-detail-value">{$hallNum}</span>
                            </div>
                            <div class="lot-detail">
                                <span class="lot-detail-label">Tantième:</span>
                                <span class="lot-detail-value">{$tantieme}</span>
                            </div>
                        </div>
                    </div>
HTML;
    }
}
