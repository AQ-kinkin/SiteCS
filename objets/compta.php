<?php

require_once(__DIR__ . '/logs.trait.php');

class CompatibilityException extends Exception {}
class LeftColumnEmptyException extends CompatibilityException {}
class RightColumnEmptyException extends CompatibilityException {}

class Compta
{
    public const MODE_INSERT = 0;
    public const MODE_UPDATE = 1;
    
    public const STATE_VALIDATION_AV = '002';
    public const STATE_VALIDATION_AR = '003';

    use Logs;

    protected Database $objdb;
    private string $baseNameTables = "Compta_factures_";

    protected function getNameTableInfos($Periode): string
    { 
        return $this->baseNameTables . $Periode . "_infos";
    } 

    protected function getNameTableLines($Periode): string
    { 
        return $this->baseNameTables . $Periode . "_lines";
    } 

    protected function getNameTableStates(): string
    { 
        return "Compte_Key_Validation";
    }

    protected function getNameTableKeys(): string
    { 
        return "Compte_Key_type";
    }

    protected function getNameTableValidations($Periode): string
    { 
        return $this->baseNameTables . $Periode . "_validations";
    }

    protected function getNameTableVouchers($Periode): string
    { 
        return $this->baseNameTables . $Periode . "_vouchers";
    }

    protected function setKeyValidation(): void
    {
        if (!isset($_SESSION['ArrayValidation']) )
        {
            $this->objdb->query("SELECT * FROM `" . $this->getNameTableStates() . "`;");
            if ($this->objdb->execute())
            {
                $_SESSION['ArrayValidation'] = $this->objdb->fetchall();
            }
            else
            {
                $_SESSION['ArrayValidation'] = [];
            }
        }
    }

    

    public function getKeySelection():string
    {
        $html_button_Lst = "";

        $this->setKeyComptable();

        foreach ($_SESSION['ArrayKeyComptable'] as $data) {
            $html_button_Lst .= "<button title=\"" . $data['namekey'] . "\" onclick=\"showKey('" . $data['typekey'] .  "', '" . $data['namekey'] .  "', document.getElementById('filtre_etat').value)\">" . $data['shortname'] . "</button>\n";
        }

        return $html_button_Lst;
    }

    public function setKeyComptable(): void
    {
        if ( !isset($_SESSION['ArrayKeyComptable']) )
        {
            $this->objdb->query("SELECT * FROM `Compte_Key_type`;");
            if ($this->objdb->execute())
            {
                while( $row = $this->objdb->fetch() )
                {
                    $_SESSION['ArrayKeyComptable'][] = [ 'id_key' => $row['id_key'], 'typekey' => $row['typekey'], 'namekey' => $row['namekey'], 'shortname' => $row['shortname'] ];
                }
            }
            else
            {
                $_SESSION['ArrayKeyComptable'] = [];
            }
        }
    }

    // ****************************************************************************
    // élément de Comtabilité : 
    // ****************************************************************************
    protected function find_id_key($key): string
    {
        $this->setKeyComptable();

        $id_key = '-1';
        foreach ($_SESSION['ArrayKeyComptable'] as $data) {
            if ($data['typekey'] === $key) {
                $id_key = $data['id_key'];
                break;
            }
        }

        return $id_key;
    }

    // ****************************************************************************
    // élément de validation : OK, Rejeté, A vérifier, ...
    // ****************************************************************************
    protected function get_id_validation_with_key($key): string
    {
        $this->setKeyValidation();

        $id_key = '-1';
        foreach ($_SESSION['ArrayValidation'] as $data) {
            if ($data['numkey'] === $key) {
                $id_key = $data['id_state'];
                break;
            }
        }

        return $id_key;
    }

    protected function get_validation_label($id_state): string
    {
        $this->setKeyValidation();

        $label = '';
        foreach ($_SESSION['ArrayValidation'] as $data) {
            if ($data['id_state'] === $id_state) {
                $label = $data['namekey'];
                break;
            }
        }

        return $label;
    }
    // ****************************************************************************

    // ****************************************************************************
    //  getYearSelection  : génère le select pour la sélection de l'année
    // ****************************************************************************
    public function getYearSelection():string
    {
        $html_select = "<span><label>Select la période :</label><select name=\"periode\" id=\"periode\" onchange=\"updateYear()\">" . PHP_EOL;
        $annee = (int)0;

        if (!isset($_SESSION['selectedyear']) )
        {
            $annee_en_cours = (int)date('Y');
        }
        else
        {
            $annee_en_cours = (int)explode("_", $_SESSION['selectedyear'])[1];
        }

        if (!isset($_SESSION['selectionyearlst']) )
        {
            $this->objdb->query("SELECT periode FROM `Compta_years`");
            if ( $this->objdb->execute() )
            {
                while( $row = $this->objdb->fetch() )
                {
                    $_SESSION['selectionyearlst'][] = $row['periode'];
                }
            }
            else
            {
                $_SESSION['selectionyearlst'] = [];
            }
        }

        foreach( $_SESSION['selectionyearlst'] as $data )
        {
            $annee = (int)explode("_", $data)[1];

            if ( $annee == $annee_en_cours )
            {
                $text_selected = 'selected="selected">';
                $_SESSION['selectedyear'] = $data; // On stocke la période sélectionnée dans la session
            }
            else
            {
                $text_selected = '>';
            }

            $html_select .= "\t<option value=\"" . $data . '" ' . $text_selected . $data . "</option>" . PHP_EOL;
        }

        // $html_select .= "\t<option value=\"2025_2026\">2025_2026</option>" . PHP_EOL; // DEBUG Test
        $html_select .= "</select></span>" . PHP_EOL;
       
        return $html_select;
    }
    // ****************************************************************************

    // ****************************************************************************
    // Ajout du select de filtrage
    // ****************************************************************************
    public function getFiltrageValidation():string
    {
        $html_select = "<span><label>Filtrage :</label><select name=\"filtre_etat\" id=\"filtre_etat\">" . PHP_EOL;
        $html_select .= "<option value=\"0\" selected=\"selected\">Aucun</option>" . PHP_EOL;
        $html_select .= "<option value=\"1\">À saisir</option>" . PHP_EOL;
        $html_select .= "<option value=\"2\">À vérifier</option>" . PHP_EOL;
        $html_select .= "<option value=\"3\">Not OK</option>" . PHP_EOL;
        $html_select .= "</select></span>" . PHP_EOL;

        return $html_select;
    }
    // ****************************************************************************
}

?>