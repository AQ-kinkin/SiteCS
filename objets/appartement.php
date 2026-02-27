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
     * Constructeurs
     */
    public function __construct(Database $db, array $lotData = [], array $appartData = [], $trace = false)
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
     * Affiche le n° de lot
     */
    public function show_num_lot(): string
    {
        $message = '<div class="hall-content">';
        $message .= '<h3>Lot ' . htmlspecialchars($this->lot) . '</h3>';
        return $message . '</div>';
        $typeLabel = "Affichage Hall";
        $ligne = "Ligne Saisie";

        $message = '<div class="hall-content">';
        $message .= <<<HTML
            <div class="lot-card appartement">
                <div class="lot-header">
                    <div class="lot-icon-wrapper">
                        <div class="lot-icon appartement">
                            <img src="/icons/list-24x24.png" alt="L">
                        </div>
                        <div class="lot-title">
                            <h3>{$typeLabel}</h3>
                        </div>
                    </div>
                </div>
                <div class="lot-details">
                    <div class="lot-detail">
                        <span class="lot-detail-label">Affichage:</span>
                        <span class="lot-edit">{$ligne}</span>
                        <div class="hall-content-nav"><button class="btn-retour" onclick="retour_hall(this)">Retour</button></div>
                    </div>
                </div>

            </div>
        HTML;

        return $message . '</div>';
        
    }

    /**
     * Affiche le n° de lot
     */
    public function show_entry_lot(): string
    {
        $typeLabel = "Affichage Hall";
        $ligne = "Ligne Saisie";

        return <<<HTML
            <div class="lot-card def-appartement">
                <div class="lot-header">
                    <div class="lot-icon-wrapper">
                        <div class="lot-icon appartement">
                            <img src="/icons/list-24x24.png" alt="L">
                        </div>
                        <div class="lot-title">
                            <h3>{$typeLabel}</h3>
                        </div>
                    </div>
                </div>
                <div class="lot-details">
                    <div class="lot-detail">
                        <span class="lot-detail-label">Affichage:</span>
                        <span class="lot-edit">{$ligne}</span>
                    </div>
                    <div class="button-on-card">
                        <button class="btn-modif" onclick="retour_hall(this)">Modifier</button>
                    </div>
                </div>
            </div>
        HTML;
    }

    /**
     * Affiche le n° de lot
     */
    public function show_boite_lot(): string
    {
        $typeLabel = "Boîte aux lettres";
        $ligne1 = "Premère Ligne";
        $ligne2 = "Deuxième Ligne";

        return <<<HTML
            <div class="lot-card def-appartement">
                <div class="lot-header">
                    <div class="lot-icon-wrapper">
                        <div class="lot-icon appartement">
                            <img src="/icons/boites-aux-lettres-24x24.png" alt="B">
                        </div>
                        <div class="lot-title">
                            <h3>{$typeLabel}</h3>
                        </div>
                    </div>
                </div>
                <div class="lot-details">
                    <div class="lot-detail">
                        <span class="lot-detail-label">Ligne 1:</span>
                        <span class="lot-edit">{$ligne1}</span>
                    </div>
                    <div class="lot-detail">
                        <span class="lot-detail-label">Ligne 2:</span>
                        <span class="lot-edit">{$ligne2}</span>
                    </div>
                    <div class="button-on-card">
                        <button class="btn-modif" onclick="retour_hall(this)">Modifier</button>
                    </div>
                </div>
            </div>
        HTML;
    }
    
    /**
     * Affiche le n° de lot
     */
    public function show_proprio_lot(string $nom_gestionaire = ''): string
    {
        $nom = htmlspecialchars($nom_gestionaire);

        return <<<HTML
            <div class="lot-card def-appartement">
                <div class="lot-header">
                    <div class="lot-icon-wrapper">
                        <div class="lot-icon appartement">
                            <img src="/icons/propriétaire-24x24.png" alt="P">
                        </div>
                        <div class="lot-title">
                            <h3>Propriétaire</h3>
                        </div>
                    </div>
                </div>
                <div class="lot-details">
                    <div class="lot-detail">
                        <span class="lot-detail-label">Gestionnaire:</span>
                        <span class="lot-detail-value">{$nom}</span>
                    </div>
                </div>
            </div>
        HTML;
    }

    /**
     * Affiche la liste des locataires du lot
     */
    public function show_locataire_lot(array $list_locataire = []): string
    {
        $lignes = '';
        foreach ($list_locataire as $locataire) {
            $nomLoc = htmlspecialchars($locataire);
            $lignes .= '<div class="lot-detail"><span class="lot-detail-value">' . $nomLoc . '</span></div>';
        }

        if (empty($lignes)) {
            $lignes = '<div class="lot-detail"><span class="lot-detail-value">Aucun locataire</span></div>';
        }

        return <<<HTML
            <div class="lot-card def-appartement">
                <div class="lot-header">
                    <div class="lot-icon-wrapper">
                        <div class="lot-icon appartement">
                            <img src="/icons/locataire-24x24.png" alt="L">
                        </div>
                        <div class="lot-title">
                            <h3>Locataires</h3>
                        </div>
                    </div>
                </div>
                <div class="lot-details">
                    {$lignes}
                </div>
            </div>
        HTML;
    }
    
    /**
     * load data for form residant 
     */
    public function load_for_residant($lot)
    {
        $this->lot = $lot;
    }

    /**
     * Retourne le HTML d'info d'un lot dans une div hall-content
     */
    public function get_html_info_lot(int $lot, string $nom_gestionaire = '', array $list_locataire = []): string
    {
        $this->load_for_residant($lot);

        $message = '<div class="hall-content">';

        $message .= $this->show_num_lot();

        // Affichage Hall + Boîte aux lettres côte à côte
        $message .= '<div class="hall-content-row">';
        $message .= $this->show_entry_lot();
        $message .= $this->show_boite_lot();
        $message .= '</div>';

        // Propriétaire + Locataires côte à côte
        $message .= '<div class="hall-content-row">';
        $message .= $this->show_proprio_lot($nom_gestionaire);
        $message .= $this->show_locataire_lot($list_locataire);
        $message .= '</div>';

        $message .= '<div class="hall-content-nav"><button class="btn-retour" onclick="retour_hall(this)">Retour</button></div>';
        $message .= '</div>';

        return $message;
    }

    /**
     * Sérialisation : ajouter les champs spécifiques
     */
    public function __sleep(): array
    {
        return array_merge(parent::__sleep(), ['etage', 'typeAppartement', 'position']);
    }
}
