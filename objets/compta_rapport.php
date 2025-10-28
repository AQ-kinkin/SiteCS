<?php
    $pathHome = '/home/csresip/www';
	require_once( $pathHome . '/objets/compta.php');

class Compta_Rapport extends Compta
{
    private $tables_name=[];
    private bool $log;
    
    public function __construct(Database $refdb, bool $trace = false)
    {
        $this->objdb = $refdb;
        $this->log = $trace;
        if ( $trace ) { $this->PrepareLog('Import'); }
    }
    
    // ****************************************************************************
    //  fonction qui affiche le formulaire de sélection de l'année
    // ****************************************************************************
    protected function displayYearSelectionForm()
    {
        echo '<form method="POST" action="">';
        echo $this->getYearSelection();
        echo '<input type="submit" value="Envoyer">';
        echo '</form>';
    }	

}


?>