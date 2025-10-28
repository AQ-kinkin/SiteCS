<?php
    $pathHome = '/home/csresip/www';
	require_once( $pathHome . '/objets/compta.php');

class Compta_Rapport extends Compta
{
    private $tables_name=[];
    private bool $log;
    private $query;
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

        // "SELECT `Compta_factures_2024_2025_lines`.*, `Compta_factures_2024_2025_validations`.`state_id`, `Compta_factures_2024_2025_validations`.`infos` FROM `Compta_factures_2024_2025_lines` INNER JOIN `Compta_factures_2024_2025_validations` ON id_validation = validation_id WHERE validation_id IS NOT null AND key_id = :key AND `Compta_factures_2024_2025_validations`.`state_id` != 1";
        $this->query = "SELECT `" . $TableLines . "`.*, `" . $TableValidations . "`.`state_id`, `" . $TableValidations . "`.`infos`";
        //  WHERE `Compta_factures_2024_2025_validations`.`state_id` != 1";
        $this->query .= "FROM `" . $TableLines . "` ";
        $this->query .= "INNER JOIN `" . $TableValidations . "` ON id_validation = validation_id ";
        // $sql .= "LEFT JOIN `" .  . "` ON validation_id = id_validation ";
        // $sql .= "LEFT JOIN `" . $this->getNameTableVouchers($_SESSION['selectedyear']) . "` ON voucher_id = id_voucher ";
        $this->query .= "WHERE validation_id IS NOT null AND key_id = :key AND `" . $TableValidations . "`.`state_id` != 1";

        $this->setKeyComptable();
        foreach ($_SESSION['ArrayKeyComptable'] as $key) {
            $elements = $this->get_key_comptable_elements_in_error($key['id_key']);
        }

    // // Étape 5 : Préparer le mail
    // $mailContent = "Rapport pour l'année $selectedYear\n\n";
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
    }

    public function get_key_comptable_elements_in_error($key): array
    {
        // $this->InfoLog('get_key_comptable_elements_in_error :: key = ' . print_r($key, true) );
        $params = [ ':key' => $key ];
        
        $this->objdb->query($this->query);
        if ($this->objdb->execute($params)) {
            // $this->InfoLog('get_key_comptable_elements_in_error :: passge par execute avec succès ');
            $elements = $this->objdb->fetchall();
        } else {
            $this->InfoLog('get_key_comptable_elements_in_error :: no elements found ');
        }

        // $this->InfoLog('get_key_comptable_elements_in_error :: key = ' . print_r($params, true) . ' --- elements : ' . print_r($elements, true) . 'query :: ' . $query );
        return $elements;
    }
}


?>