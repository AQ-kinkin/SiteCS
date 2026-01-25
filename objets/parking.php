<?php

require_once(PATH_HOME_CS . '/objets/lot.php');
require_once(PATH_HOME_CS . '/objets/logs.trait.php');

/**
 * Classe représentant un parking
 * position_id est une FK vers def_parking
 */
class Parking extends Lot
{
    private ?string $parkingLocation = null; // Label parking (ex: "Parking Sud")
    
    /**
     * Constructeur
     */
    public function __construct(Database $db, array $lotData, $trace = false)
    {
        parent::__construct($db, $lotData);
        
        if ($trace == true) {
            $this->PrepareLog('Parking', 'd');
        }
        
        $this->parkingLocation = $lotData['park_position'] ?? null;
    }
    
    // Getters
    public function getParkingLocation(): ?string { return $this->parkingLocation; }
    public function getPlace(): int { return $this->positionId; }

    /**
     * Retourne 'Place' pour un parking
     */
    public function getRepereLabel(): string
    {
        return 'Place';
    }

    /**
     * Description textuelle du parking
     */
    public function getDescription(): string
    {
        return "Parking {$this->lot}";
    }

    /**
     * Génère le HTML complet pour l'affichage du parking
     */
    public function get_html_panel(): string
    {
        $typeLabel = htmlspecialchars($this->labelTypeLot ?? 'Parking');
        $lotNumber = htmlspecialchars($this->lot);
        $repereValue = $this->repere ? htmlspecialchars($this->repere) : 'N/A';
        
        // Détails parking
        $parkingLocation = htmlspecialchars($this->parkingLocation ?? 'N/A');
        $tantieme = $this->tantieme ? htmlspecialchars($this->tantieme) : 'N/A';
        
        return <<<HTML
                    <div class="lot-card parking">
                        <div class="lot-header">
                            <div class="lot-icon-wrapper">
                                <div class="lot-icon parking">
                                <img src="/icons/voiture-24x24.png" alt="P">
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
                                <span class="lot-detail-label">Location:</span>
                                <span class="lot-detail-value">{$parkingLocation}</span>
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
     * Sérialisation : ajouter parkingLocation
     */
    public function __sleep(): array
    {
        return array_merge(parent::__sleep(), ['parkingLocation']);
    }
}
