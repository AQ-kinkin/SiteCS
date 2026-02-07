<?php

require_once('LotFormAbstract.php');

/**
 * Formulaire de saisie pour un appartement
 * Champs : repère/sonnette (3 chiffres) + tantième (3 chiffres)
 */
class AppartementForm extends LotFormAbstract
{
    /**
     * Génère les champs spécifiques à l'appartement
     * @return string HTML des champs
     */
    protected function renderSpecificFields(int $sonnette): string
    {
        $this->InfoLog("renderSpecificFields(" . $sonnette . ") pour AppartementForm");
        
        return '
            <div class="form-group">
                <label class="form-label">
                    <span>Repère / Sonnette</span>
                </label>
                <input type="number" name="repere" class="form-control" min="0" max="999" required placeholder="Ex: 125" value="' . $sonnette . '">
                <span class="input-separation"></span>
                <label class="form-label">
                    <span>Tantième</span>
                </label>
                <input type="number" name="tantieme" class="form-control" min="0" max="999" required placeholder="Ex: 125">
            </div>
        ';
    }
}
