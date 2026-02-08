<?php

require_once(PATH_HOME_CS . '/objets/logs.trait.php');

/**
 * Classe abstraite de base pour les formulaires de lots
 * Génère le HTML des formulaires de saisie selon le type de lot
 */
abstract class LotFormAbstract
{
    use Logs;
    
    protected int $lotId;
    protected int $lotType;
    protected int $repere;
    protected int $positionId;
    protected ?int $tantieme;
    protected bool $log = false;
    protected ?Database $db = null;

    /**
     * Constructeur
     * @param int $lotId Numéro du lot
     * @param int $lotType Type de lot (1=Appart, 2=Cave, 3=Parking)
     * @param int $repere Valeur initiale pour les champs
     * @param int $positionId Position du lot
     * @param Database $db Instance de base de données
     * @param bool $trace Active les logs de debug
     */
    public function __construct(int $lotId, int $lotType, int $repere, int $positionId, ?int $tantieme, Database $db, bool $trace = false)
    {
        $this->lotId = $lotId;
        $this->repere = $repere;
        $this->positionId = $positionId;
        $this->lotType = $lotType;
        $this->tantieme = $tantieme;
        $this->db = $db;
        $this->log = $trace;

        if ($trace) {
            $this->PrepareLog('LotForm', 'd');
            $this->InfoLog("Construction " . static::class . " - lotId: $lotId, lotType: $lotType");
        }
    }
    
    /**
     * Méthode abstraite pour les champs spécifiques au type de lot
     * Doit être implémentée par chaque classe fille
     * @return string HTML des champs spécifiques
     */
    abstract protected function renderSpecificFields(int $info): string;
    
    /**
     * Génère le HTML complet du formulaire
     * @return string HTML du formulaire
     */
    public function render(): string
    {
        $this->InfoLog("Début render() pour " . static::class);

        $html  = "<!-- DEBUG: " . static::class . " render() pour lot=$this->lotId -->\n";
        $html .= '<form class="lot-form" data-lot-type="' . $this->lotType . '" data-lot="' . $this->lotId . '">' . "\n";
        $html .= $this->renderSpecificFields($this->repere);
        $html .= $this->renderGenericFields();
        $html .= '<div class="form-actions">' . "\n";
        $html .= '    <button type="button" class="btn btn-save" onclick="submitLotForm(this)">Valider</button>' . "\n";
        $html .= '</div>' . "\n";
        $html .= '</form>' . "\n";
        
        $this->InfoLog("Fin render()");
        
        return $html;
    }
    
    /**
     * Champs cachés communs à tous les formulaires
     * @return string HTML des champs cachés
     */
    protected function renderGenericFields(): string
    {
        return '
            <input type="hidden" name="lot_type" value="' . $this->lotType . '">
            <input type="hidden" name="lot" value="' . $this->lotId . '">
        ';
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
