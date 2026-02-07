<?php

require_once('LotFormAbstract.php');

/**
 * Formulaire de saisie pour une cave
 * Champs : porte (2 chiffres) + tantième (2 chiffres)
 */
class CaveForm extends LotFormAbstract
{
    /**
     * Génère les champs spécifiques à la cave
     * @return string HTML des champs
     */
    protected function renderSpecificFields(int $porte): string
    {
        $this->InfoLog("renderSpecificFields(" . $porte . ") pour CaveForm");
        
        return '
            <div class="form-group">
                <label class="form-label">
                    <span>Porte</span>
                </label>
                <input type="number" name="porte" class="form-control" min="0" max="99" required placeholder="Ex: 08" value="'. $porte . '">
                <span class="input-separation"></span>
                <label class="form-label">
                    <span>Tantième</span>
                </label>
                <input type="number" name="tantieme" class="form-control" min="0" max="99" required placeholder="Ex: 05">
            </div>
        ';
    }
}
