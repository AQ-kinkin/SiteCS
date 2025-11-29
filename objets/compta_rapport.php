<?php
    $pathHome = '/home/csresip/www';
	require_once( $pathHome . '/objets/compta.php');

class Compta_Rapport extends Compta
{
    private $tables_name=[];
    private bool $log;
    private $query;
    private $the_rapport;
    public function __construct(Database $refdb, bool $trace = false)
    {
        $this->objdb = $refdb;
        $this->log = $trace;
        if ( $trace ) { $this->PrepareLog('Rapport'); }
    }
    
    private function InfoLog($message): void
    {
        // Format du message (date + contenu)
        $date = date('Y-m-d H:i:s');
        $ligne = "[$date] [Info] $message\n";

        // Écriture dans le fichier (append)
        $this->write_info($ligne);
    }
    
    // ****************************************************************************
    //  fonction qui affiche le formulaire de sélection de l'année
    // ****************************************************************************
    public function displayYearSelectionForm()
    {
        echo '<form method="POST" action="">';
        echo $this->getYearSelection();
        echo '<input type="submit" value="Envoyer">';
        echo '</form>';
    }	

    public function create_rapport($periode)
    {
        $TableLines = $this->getNameTableLines($periode);
        $TableValidations = $this->getNameTableValidations($periode);
        $TableInfos = $this->getNameTableInfos($periode);
        
        $this->the_rapport = new Rapport();

        // SELECT
        //     `Compta_factures_2024_2025_lines`.num_account,
        //     `Compta_factures_2024_2025_lines`.label_account,
        //     `Compta_factures_2025_2026_infos`.`LabelFact`,
        //     `Compta_factures_2025_2026_infos`.`NumPiece`,
        //     `Compta_factures_2025_2026_infos`.`NameFournisseur`,
        //     `Compta_factures_2025_2026_infos`.`DateOpe`,
        //     `Compta_factures_2025_2026_infos`.`Tva`,
        //     `Compta_factures_2025_2026_infos`.`Charges`,
        //     `Compta_factures_2025_2026_infos`.`MontantTTC`,
        //     `Compte_Key_Validation`.`numkey`,
        //     `Compta_factures_2024_2025_validations`.`infos`
        // FROM
        //     `Compta_factures_2024_2025_lines`
        //     INNER JOIN `Compta_factures_2024_2025_validations` ON id_validation = validation_id
        //     INNER JOIN `Compte_Key_Validation` ON id_state = state_id
        //     INNER JOIN `Compta_factures_2025_2026_infos` ON id_info = info_id
        // WHERE 
        //     validation_id IS NOT null
        //     AND key_id = 3
        //     AND `Compta_factures_2024_2025_validations`.`state_id` != 1
        // ;

        $this->query = "SELECT ";
        $this->query .= "`" . $TableLines . "`.`num_account`,";
        $this->query .= "`" . $TableLines . "`.`label_account`,";
        $this->query .= "`" . $TableInfos . "`.`LabelFact`,";
        $this->query .= "`" . $TableInfos . "`.`NumPiece`,";
        $this->query .= "`" . $TableInfos . "`.`NameFournisseur`,";
        $this->query .= "`" . $TableInfos . "`.`DateOpe`,";
        $this->query .= "`" . $TableInfos . "`.`Tva`,";
        $this->query .= "`" . $TableInfos . "`.`Charges`,";
        $this->query .= "`" . $TableInfos . "`.`MontantTTC`,";
        $this->query .= "`Compte_Key_Validation`.`numkey`,";
        $this->query .= "`" . $TableValidations . "`.`infos`";
        $this->query .= "FROM `" . $TableLines . "` ";
        $this->query .= "INNER JOIN `" . $TableValidations . "` ON id_validation = validation_id ";
        $this->query .= "INNER JOIN `Compte_Key_Validation` ON id_state = state_id ";
        $this->query .= "INNER JOIN `" . $TableInfos . "` ON id_info = info_id ";
        $this->query .= "WHERE validation_id IS NOT null AND key_id = :key AND `" . $TableValidations . "`.`state_id` not in (1,3,8)";

        $this->setKeyComptable();
        foreach ($_SESSION['ArrayKeyComptable'] as $key) {
            $this->the_rapport->add_key_of_repartition( new Rapport_key_of_repartition( $key, $this->get_key_comptable_elements_in_error($key['id_key']) ) );
        }

        // // Étape 5 : Préparer le mail
        $mailContent = "Rapport pour l'année $periode\n\n";
        $mailContent .= $this->the_rapport->to_string();
        // $this->InfoLog($mailContent);
        // foreach ($elements as $element) {
        //     $mailContent .= "Élément : " . $element['libele'] . "\n";
        // }

    // // Étape 6 : Envoyer le mail
    // $to = "destinataire@example.com";
    // $subject = "Rapport pour l'année $selectedYear";
    // $headers = "From: expéditeur@example.com";

    // if (mail($to, $subject, $mailContent, $headers)) {
    //     echo "Mail envoyé avec succès.";
    // } else {
    //     echo "Échec de l'envoi du mail.";
    // }
        return $mailContent;
    }

    public function get_key_comptable_elements_in_error($key): array
    {
        // $this->InfoLog('get_key_comptable_elements_in_error :: key = ' . print_r($key, true) . ' query :: ' . print_r($this->query) );
        $params = [ ':key' => $key ];
        
        $this->objdb->query($this->query);
        if ($this->objdb->execute($params)) {
            // $this->InfoLog('get_key_comptable_elements_in_error :: passge par execute avec succès ' . print_r($params, true));
            $elements = $this->objdb->fetchall();
        } else {
            $this->InfoLog('get_key_comptable_elements_in_error :: no elements found ');
        }

        // $this->InfoLog('get_key_comptable_elements_in_error :: key = ' . print_r($params, true) . ' --- elements : ' . print_r($elements, true) . 'query :: ' . $query );
        return $elements;
    }
}

class Rapport
{
    public array $keys_of_repartition;

    public function __construct()
    {
        $this->keys_of_repartition = [];
    }

    public function add_key_of_repartition(Rapport_key_of_repartition $key_of_repartition): void
    {
        $this->keys_of_repartition[] = $key_of_repartition;
    }

    public function to_string(): string
    {
        // return implode("", array_map(fn($obj) => $obj->to_string(), $this->keys_of_repartition));
        $output = '';
        foreach ($this->keys_of_repartition as $key) {
            $output .= $key->to_string();
        }
        return $output;
    }
}

class Rapport_key_of_repartition
{
    public array $infoskey;
    public array $affectations;

    public function __construct(array $infoskey, array $lines_in_error)
    {
        $this->infoskey = $infoskey;
        foreach ($lines_in_error as $line) {
            if (!isset($this->affectations[$line['num_account']])) {
                $this->affectations[$line['num_account']] = new Rapport_Affectation( $line['num_account'], $line['label_account'] );
            }
            switch ($line['numkey']) {
                case '001':
                    $this->affectations[ $line['num_account'] ]->add( new Rapport_line_rejeted($line) );
                    break;
                case '002':
                    $this->affectations[ $line['num_account'] ]->add( new Rapport_line_verified($line) );
                    break;
                case '003':
                    $this->affectations[ $line['num_account'] ]->add( new Rapport_line_reafected($line) );
                    break;
                case '004':
                    $this->affectations[ $line['num_account'] ]->add( new Rapport_line_moved($line) );
                    break;
                case '005':
                    $this->affectations[ $line['num_account'] ]->add( new Rapport_line_changed_repartition($line) );
                    break;
                case '006':
                    $this->affectations[ $line['num_account'] ]->add( new Rapport_line_changed_category($line) );
                    break;
            }
        }
    }

    public function to_string(): string
    {
        $output = '';
        if ( !empty( $this->affectations ) ) {
            // $output = "Clé de répartition : " . $this->infoskey['shortname'] . "\n";
            $output .= "Clé de répartition : " . $this->infoskey['namekey'] . "\n";
            foreach ($this->affectations as $affectation) {
                $output .= $affectation->to_string() . "\n";
                // $output .= "  Affectation : " . $affectation->libele . " (Compte : " . $affectation->account . ")\n";
                // foreach ($affectation->lines as $line) {
                //     $output .= "    Ligne : " . $line->LabelFact . ", Pièce : " . $line->NumPiece . ", Fournisseur : " . $line->NameFournisseur . ", Date : " . $line->DateOpe . ", TVA : " . $line->Tva . ", Charges : " . $line->Charges . ", Montant TTC : " . $line->MontantTTC . ", Infos : " . $line->infos . "\n";
                // }
            }
        }
        
        return $output;
    }
}

class Rapport_Affectation
{
    public string $libele;
    public float $account;
    public array $lines;

    public function __construct(string $num_account, string $label_account)
    {
        $this->libele = $label_account;
        $this->account = $num_account;
    }

    public function add(Rapport_line_in_error $line): void
    {
        $this->lines[] = $line;
    }
    
    public function to_string(): string
    {
        $output = '';
        $output .= "  Affectation : " . $this->account . " (Compte : " . $this->libele . ")\n";
        foreach ($this->lines as $line) {
            $output .= $line->to_string() . "\n";
        }
        return $output;
    }
}


abstract class Rapport_line_in_error
{
    public string $LabelFact;
    public string $NumPiece;
    public string $NameFournisseur;
    public string $DateOpe;
    public string $Tva;
    public string $Charges;
    public string $MontantTTC;
    public string $infos;

    abstract protected function extracted_json_infos(array $obj_json): string;

    public function __construct(array $lines_in_error)
    {
        $this->LabelFact = $lines_in_error['LabelFact'];
        $this->NumPiece = $lines_in_error['NumPiece'];
        $this->NameFournisseur = $lines_in_error['NameFournisseur'];
        $this->DateOpe = $lines_in_error['DateOpe'];
        $this->Tva = $lines_in_error['Tva'];
        $this->Charges = $lines_in_error['Charges'];
        $this->MontantTTC = $lines_in_error['MontantTTC'];
        $this->infos = $lines_in_error['infos'];
    }

    public function to_string(): string
    {
        $output = "\t\t - [" . $this->LabelFact . " | " . $this->NumPiece . " | " . $this->NameFournisseur . " | " . $this->DateOpe . " |  " . $this->Tva . " | " . $this->Charges . " | " . $this->MontantTTC . "]\n" . $this->extracted_infos();

        return $output;
    }

    private function extracted_infos(): string
    {
        $obj_json = json_decode($this->infos, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $output = "\t\t\tErreur JSON : " . json_last_error_msg() . " source : " . $this->infos;
        }
        else {
            $output = "\t\t\t" . $this->extracted_json_infos($obj_json);
        }

        return $output;
    }
}

class Rapport_line_rejeted extends Rapport_line_in_error
{
    public function __construct(array $lines_in_error)
    {
        parent::__construct($lines_in_error);
    }
    
    // public function to_string(): string
    // {
    //     $output = "\t\t - [" . $this->LabelFact . " | " . $this->NumPiece . " | " . $this->NameFournisseur . " | " . $this->DateOpe . " |  " . $this->Tva . " | " . $this->Charges . " | " . $this->MontantTTC . "]\n" . $this->extracted_infos();

    //     return $output;
    // }
 
    protected function extracted_json_infos(array $obj_json): string
    {
        if (isset($obj_json['cause'])) {
            $output = "Raison du rejet : " . $obj_json['cause'];
        } else {
            $output = "Aucune raison spécifiée.";
        }

        return $output;
    }
}

class Rapport_line_verified extends Rapport_line_in_error
{
    public function __construct(array $lines_in_error)
    {
        parent::__construct($lines_in_error);
    }
    
    // public function to_string(): string
    // {
    //     $output = "\t\t - [" . $this->LabelFact . " | " . $this->NumPiece . " | " . $this->NameFournisseur . " | " . $this->DateOpe . " |  " . $this->Tva . " | " . $this->Charges . " | " . $this->MontantTTC . "]\n" . $this->extracted_infos();

    //     return $output;
    // }
 
    protected function extracted_json_infos(array $obj_json): string
    {
        if (isset($obj_json['cause'])) {
            $output = "Demande de vérification : " . $obj_json['cause'];
        } else {
            $output = "Le conseil syndical vérifie cette ligne.";
        }

        return $output;
    }
}

class Rapport_line_reafected extends Rapport_line_in_error
{
    public function __construct(array $lines_in_error)
    {
        parent::__construct($lines_in_error);
    }
    
    // public function to_string(): string
    // {
    //     $output = "\t\t - [" . $this->LabelFact . " | " . $this->NumPiece . " | " . $this->NameFournisseur . " | " . $this->DateOpe . " |  " . $this->Tva . " | " . $this->Charges . " | " . $this->MontantTTC . "]\n" . $this->extracted_infos();

    //     return $output;
    // }
 
    protected function extracted_json_infos(array $obj_json): string
    {
        $output = "Demande de réaffectation :";

        if (empty($obj_json)) {
            $output .= "\tAucune réaffectation trouvée.\n";
        } else {
            $output .= "\n";
            foreach ($obj_json as $item) {
                $output .= "\t\t\t\tLot de destination :\t" . ($item['lot'] ?? '')
                    . "\t--\tSomme :\t" . ($item['Somme'] ?? '')
                //  . "\t--\tRaison/Label :\t" . ($item['cause'] ?? '')
                    . "\n";
            }
        }
 
        return $output;   
    }
}

class Rapport_line_moved extends Rapport_line_in_error
{
    public function __construct(array $lines_in_error)
    {
        parent::__construct($lines_in_error);
    }

    // public function to_string(): string
    // {
    //     $output = "\t\t - [" . $this->LabelFact . " | " . $this->NumPiece . " | " . $this->NameFournisseur . " | " . $this->DateOpe . " |  " . $this->Tva . " | " . $this->Charges . " | " . $this->MontantTTC . "]\n" . $this->extracted_infos();

    //     return $output;
    // }
 
    protected function extracted_json_infos(array $obj_json): string
    {
        $output = "Demande de déplacement :: ";
        if (isset($obj_json['destination'])) {
            $output .= "Destination : " . $obj_json['destination'];
        } else {
            $output .= "Aucune destination spécifiée.";
        }
        if (isset($obj_json['regle'])) {
            $output .= "\t| Règle : " . $obj_json['regle'];
        } else {
            $output .= "\t| Aucune règle spécifiée.";
        }
        
        return $output;
    }
}

class Rapport_line_changed_repartition extends Rapport_line_in_error
{
    public function __construct(array $lines_in_error)
    {
        parent::__construct($lines_in_error);
    }

    // public function to_string(): string
    // {
    //     $output = "\t\t - [" . $this->LabelFact . " | " . $this->NumPiece . " | " . $this->NameFournisseur . " | " . $this->DateOpe . " |  " . $this->Tva . " | " . $this->Charges . " | " . $this->MontantTTC . "]\n" . $this->extracted_infos();

    //     return $output;
    // }
 
    protected function extracted_json_infos(array $obj_json): string
    {
        $output = "Demande de réaffectation :";

        if (empty($obj_json)) {
            $output .= "\tAucune réaffectation trouvée.\n";
        } else {
            $output .= "\n";
            foreach ($obj_json as $item) {
                $output .= "\t\t\t\tclé de répartition :\t" . ($item['accounting_key'] ?? '')
                    . "\t--\tSomme :\t" . ($item['Somme'] ?? '')
                    . "\t--\tRaison/Label :\t" . ($item['cause'] ?? '')
                    . "\n";
            }
        }
 
        return $output;
    }
}

class Rapport_line_changed_category extends Rapport_line_in_error
{
    public function __construct(array $lines_in_error)
    {
        parent::__construct($lines_in_error);
    }

    // public function to_string(): string
    // {
    //     $output = "\t\t - [" . $this->LabelFact . " | " . $this->NumPiece . " | " . $this->NameFournisseur . " | " . $this->DateOpe . " |  " . $this->Tva . " | " . $this->Charges . " | " . $this->MontantTTC . "]\n" . $this->extracted_infos();
        
    //     return $output;
    // }
    
    protected function extracted_json_infos(array $obj_json): string
    {
        $output = "Demande de changement de categorie : ";
        if (isset($obj_json['categorie'])) {
            $output .= "Nouvelle categorie : " . $obj_json['categorie'];
        } else {
            $output .= "Aucune catégorie spécifiée.";
        }
        if ( isset( $obj_json[ 'cause' ] ) ) {
            $output .= "\t| Raison : " . $obj_json['cause'];
        } else {
            $output .= "\t| Aucune raison spécifiée.";
        }
        return $output;
    }
}

?>