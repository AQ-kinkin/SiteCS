<?php

class ComtabilityException extends Exception {}

/* -----------------------------------------------------------------------------------
# Calss de gedtion des log
------------------------------------------------------------------------------------*/
trait Logs {
    private $logsPath;

    protected function PrepareLog(string $identifiant): void {
        $this->logsPath = '/home/csresip/www/logs/' . $identifiant . "_ " . date('YmdH');

        // Créer le dossier s’il n'existe pas
        $dossier = dirname($this->logsPath);
        if (!is_dir($dossier)) {
            mkdir($dossier, 0775, true);
        }
    }

    protected function write_info($message): void
    {
        // Format du message (date + contenu)
        $date = date('Y-m-d H:i:s');
        $ligne = "[$date] [Info] $message\n";

        // Écriture dans le fichier (append)
        file_put_contents($this->logsPath, $ligne, FILE_APPEND | LOCK_EX);
    }
}

class Compta 
{
    public const MODE_INSERT = 0;
    public const MODE_UPDATE = 1;

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

    protected function resetImport($periode): bool
    {
        if ( !empty( $periode ) ) {
            $this->objdb->exec( "update `Compta_years` set `state_compte` = 0 where `periode` = :periode;", [ ':periode' => $periode ] );
            return true;
        }

        return false;
    }

    public function getKeySelection():string
    {
        $html_button_Lst = "";

        $this->setKeyComptable();

        foreach ($_SESSION['ArrayKeyComptable'] as $data) {
            $html_button_Lst .= "<button title=\"" . $data['namekey'] . "\" onclick=\"showKey('" . $data['typekey'] .  "', '" . $data['namekey'] .  "')\">" . $data['shortname'] . "</button>\n";
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
    // élément de validation : OK, Rejeté, A vérifier, ...
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
}

?>