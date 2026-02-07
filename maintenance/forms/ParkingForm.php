<?php

require_once('LotFormAbstract.php');

/**
 * Formulaire de saisie pour un parking
 * Champs : place (3 chiffres) + tantième (2 chiffres)
 */
class ParkingForm extends LotFormAbstract
{
    /**
     * Génère les champs spécifiques au parking
     * @return string HTML des champs
     */
    protected function renderSpecificFields(int $place): string
    {
        $this->InfoLog("renderSpecificFields(" . $place . ") pour ParkingForm");

        // Récupération du libelle actuel si positionId défini
        $currentLibelle = '';
        if ($this->db && $this->positionId > 0) {
            $current = $this->db->ExecWithFetchAll("SELECT libelle FROM def_parking WHERE niveau_id = ?", [$this->positionId]);
            if (!empty($current)) {
                $currentLibelle = $current[0]['libelle'];
            }
        }

        // Récupération des options de parking depuis def_parking
        $parkingOptions = '';
        if ($this->db) {
            $rows = $this->db->ExecWithFetchAll("SELECT libelle FROM def_parking ORDER BY niveau_id");
            foreach ($rows as $row) {
                $libelle = htmlspecialchars($row['libelle']);
                $selected = ($libelle === $currentLibelle) ? ' selected' : '';
                $parkingOptions .= '<option value="' . $libelle . '"' . $selected . '>' . $libelle . '</option>';
            }
        }

        return '
            <div class="form-group">
                <label class="form-label">
                    <span>Type de parking</span>
                </label>
                <select name="parking_type" class="form-control" required>
                    <option value="">Sélectionnez un type</option>
                    ' . $parkingOptions . '
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">
                    <span>Place</span>
                </label>
                <input type="number" name="place" class="form-control" min="0" max="999" required placeholder="Ex: 042" value="'. $place . '">
                <span class="input-separation"></span>
                <label class="form-label">
                    <span>Tantième</span>
                </label>
                <input type="number" name="tantieme" class="form-control" min="0" max="99" required placeholder="Ex: 15">
            </div>
        ';
    }
}
