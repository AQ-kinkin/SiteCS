<?php
require_once(PATH_HOME_CS . '/objets/compta.php');
require_once(PATH_HOME_CS . '/objets/rapport_import.php');
use PhpOffice\PhpSpreadsheet\IOFactory;

class Compta_Imports extends Compta
{
    use Logs;

    public const EXCEL_COL_NUMCOMPTE = 0;
    public const EXCEL_COL_LIBCOMPTE = 1;
    public const EXCEL_COL_KEY       = 2;
    public const EXCEL_COL_LIBELLE   = 3;
    public const EXCEL_COL_VOUCHIER  = 4;
    public const EXCEL_COL_FOURNI    = 5;
    public const EXCEL_COL_DATE      = 6;
    public const EXCEL_COL_TVA       = 7;
    public const EXCEL_COL_CHARGES   = 8;
    public const EXCEL_COL_TTC       = 9;

    private const COL_GAUCHE = 0;
    private const COL_DROITE = 1;
    
    private const MODE_SEARCH_S = 0;
    private const MODE_SEARCH_M = 1;
    private const MODE_SEARCH_L = 2;
    private const MODE_SEARCH_X = 3;

    private const STATE_IMPORT_FOUND    = 0;   // 0 & not null = Found (Relié)
    private const STATE_IMPORT_MODIFIED = 1;   // 1 & not null = Déplacé.
    private const STATE_IMPORT_STEP_1   = 2;   // 2 & null = not Found (peux-être new)
    private const STATE_IMPORT_STEP_2   = 3;   // 3 & null = not Found (peux-être new)
    private const STATE_IMPORT_STEP_3   = 4;   // 4 & null = not Found (peux-être new)
    private const STATE_IMPORT_NEW      = 5;   // 5 & null = new ligne
    private const STATE_IMPORT_CONFLIT  = 255; // 255 & nul = Conflit

    // private $objdb;
    private $tables_name=[];
    private $NameColLst = array( 'N°Compte', 'Compte', 'Clé', 'Libelle', 'Pièce', 'Fournisseur', 'Date', 'dontTVA', 'ChargesLocatives' , 'MontantTTC' );
    private $counts = [
            '001' => 0,
            '003' => 0,
            '004' => 0,
            '005' => 0,
            '006' => 0,
            '007' => 0,
            '008' => 0,
            '009' => 0,
            '010' => 0,
            '011' => 0,
            '014' => 0,
            '999' => 0
        ];
    private $periodes=[];
    private int $last_annee = 0;
    private $erreurs = [];
    private $Periode;
    private $logPathRapport;
    private $obj_sync_import;
    private Rapport_Import $rapport;
    private bool $log;
    
    private int $test = 0;              // MODE DEBUG EN DUR (0 = production, 1 = debug)
    private int $debug_line_count = 0;  // Compteur lignes Excel traitées
    private int $debug_line_limit = 10; // Limite en mode debug

    public function __construct(Database $refdb, bool $trace = false)
    {
        $this->objdb = $refdb;
        $this->log = $trace;
        if ( $trace ) { 
            $this->PrepareLog('Import', 'i'); // Log par jour (Ymd)
        }
        $this->obj_sync_import = new Syncho_import($this->objdb);
    }  

    public function ShowForm(int $num_form, int $step): string
    {
        $this->StepLog("ShowForm Entry with num_form : " . $num_form );
        $this->InfoLog("ShowForm Entry with step : " . $step );
        $this->InfoLog("ShowForm formulaire : " . print_r($_POST,true) );

        if ( $num_form == 0 && $step == 0 ) {
            $en_cours = $this->obj_sync_import->import_exits();
            if ( $en_cours != 0 ) {
                return $this->show_form_resume($en_cours);
            } else {
                return $this->show_form_start();
            }
        }

        if ( $num_form == 2 ) {
            if ( isset( $_POST['answer'] ) && $_POST['answer'] === 'non' ) {
                return $this->clear_import();
            }
        }

        $this->obj_sync_import->init_session();
        $this->Periode = $this->obj_sync_import->get_periode();
        $this->tables_name['lines'] = $this->getNameTableLines($this->Periode);
        $this->tables_name['infos'] = $this->getNameTableInfos($this->Periode);
        $this->tables_name['validations'] = $this->getNameTableValidations($this->Periode);
        $this->tables_name['tempo'] = $this->obj_sync_import->get_table_tempo();
        $this->tables_name['keys'] = $this->getNameTableKeys();
        switch ( $step ) {
            case 1:
                return $this->show_form_moved(Compta_Imports::MODE_SEARCH_S);
            case 2:
                return $this->resume_import(Compta_Imports::MODE_SEARCH_M);
            case 3:
                return $this->show_form_moved(Compta_Imports::MODE_SEARCH_M);
            case 4:
                return $this->resume_import(Compta_Imports::MODE_SEARCH_L);
            case 5:
                return $this->show_form_moved(Compta_Imports::MODE_SEARCH_L);
            case 6:
                return $this->resume_import(Compta_Imports::MODE_SEARCH_X);
            case 7:
                return $this->show_form_moved(Compta_Imports::MODE_SEARCH_X);
            case 8:
                return $this->show_form_lost();
            case 9:
                return $this->clear_import();
                // return $this->show_form_moved(Compta_Imports::MODE_SEARCH_L);
                // return $this->show_form_deleted();
            // case 6:
            default:
                $answer = "<h2 class=\"home-title\">Etape inconue (" . $step . ") transmit dans ShowForm ...</h2>" . PHP_EOL;
        }

        return $answer;
    }

    private function show_form_start(): string
    {
        $annee = 0;
        $annee_en_cours = (int)date('Y');

        $answer = "\t\t<h2 class=\"home-title\">Import d'une extraction LLGestion</h2>" . PHP_EOL;
        if (count($this->periodes) === 0) {
            $this->objdb->query("SELECT periode FROM `Compta_years`");
            $this->objdb->execute();
        }
        $answer .= "\t\t<form method=\"post\" enctype=\"multipart/form-data\" id=\"form_imports\">" . PHP_EOL;
        $answer .= "\t\t\t<input type=\"hidden\" name=\"form_num\" value=\"1\">" . PHP_EOL;
        $answer .= "\t\t\t<input type=\"hidden\" name=\"step\" value=\"0\">" . PHP_EOL;
        $answer .= "\t\t\t<span><label>Select la période :</label><select name=\"periode\" id=\"periode\">" . PHP_EOL;

        while( $row = $this->objdb->fetch() )
        {
            $annee = (int)explode("_", $row['periode'])[1];
            if ( $annee > $this->last_annee ) $this->last_annee = $annee;
            $answer .= "\t\t<option value=\"" . $row['periode'] . '" ' . (($annee == $annee_en_cours)?'selected="selected">':'>') . $row['periode'] . "</option>" . PHP_EOL;
        }

        $new_periode = $this->last_annee . "_" . ($this->last_annee + 1);
        $answer .= "\t\t<option value=\"" . $new_periode . '">' . $new_periode . "</option>" . PHP_EOL;
        $answer .= "\t\t</select></span>" . PHP_EOL;
        $answer .= "\t\t\t<p><span>Select an Excel file to upload for accounting import :</span><br/><input type=\"file\" name=\"fileToUpload\" id=\"fileToUpload\"/></p>" . PHP_EOL;
        $answer .= "\t\t\t<span>&nbsp;</span><input style=\"float: right;\" type=\"submit\" value=\"Import Excel File\" name=\"submit\"/>" . PHP_EOL; // Attention : Submit intercepté par js (imports.js)
        $answer .= "\t\t</form>" . PHP_EOL;

        return $answer;
    }

    private function show_form_moved($mode_import): string
    {
        $this->rapport = new Rapport_Import(PATH_HOME_CS . '/logs/', 'Rapport-Import'. "__--__" . date('YmdHis'), false);
        $this->rapport->Add_Titre(Rapport_Import::FOUND, 'Lignes trouvé');
        $this->rapport->Add_Titre(Rapport_Import::ERROR, 'Lignes en erreurs');

        $this->StepLog("AFFICHAGE FORMULAIRE CONFLITS - Période " . $this->Periode);

        // return "\t\t<h2 class=\"imports-box-title\">" . $this->Periode . "</h2>" . PHP_EOL;
        try { $conflits = $this->get_clonflits_compte(); }
        catch  (CompatibilityException $e) {
            $answer = "\t\t<h2 class=\"imports-box-title\">Gestion des conflits d'import</h2>" . PHP_EOL;
            $answer .= $this->get_error_mess( get_class($e) . ' : ' . $e->getMessage() );
            return $answer; 
        }
        catch (Exception $e) {
            $answer = "\t\t<h2 class=\"imports-box-title\">Gestion des conflits d'import</h2>" . PHP_EOL;
            $answer .= $this->get_error_mess( get_class($e) . ' : ' . $e->getMessage() );
            return $answer;
        }

        
        // Résolution automatique des conflits avec boucle de relecture
        $conflits = $this->resolve_conflicts_auto($conflits, $mode_import);
        
        $this->rapport->printRapport();

        // Affichage des conflits restants pour résolution manuelle
        $this->Update_Years_table( $this->Periode, $this->tables_name['tempo'] );
        return $this->display_manual_conflicts($conflits, $mode_import);
    }

    /**
     * Résout automatiquement les conflits détectables (1-to-1, x-to-0, 0-to-x)
     * Boucle jusqu'à ce qu'il n'y ait plus de résolution automatique possible
     * 
     * @param array $conflits Liste des conflits détectés
     * @return array Conflits restants nécessitant une résolution manuelle
     */
    private function resolve_conflicts_auto(array $conflits, $mode_import): array
    {
        $this->StepLog("RÉSOLUTION AUTOMATIQUE DES CONFLITS");

        $total_auto_resolved = 0;
        $iterations = 0;
        $max_iterations = 10000; // Sécurité anti-boucle infinie

        do {
            $has_auto_resolved = false;
            $iterations++;
            
            if ($iterations > $max_iterations) {
                $this->ErrorLog("Limite d'itérations atteinte (" . $max_iterations . ") - arrêt forcé");
                break;
            }
            
            foreach ($conflits as $conflit) {
                // Recherche des éléments gauche et droite pour ce conflit
                $elements_gauche = $this->search_line_in_tempo_for_comp($conflit, $mode_import);
                $elements_droite = $this->search_line_in_lines_for_comp($conflit, $mode_import);
                
                $count_gauche = count($elements_gauche);
                $count_droite = count($elements_droite);

                // Gestion du cas 1-to-1 : Association automatique
                if ($count_gauche == 1 && $count_droite == 1) {
                    $left = $elements_gauche[0];
                    $right = $elements_droite[0];
                    
                    // Mise à jour table tempo : line_id
                    $sql_update_tempo = "UPDATE `" . $this->tables_name['tempo'] . "` SET `line_id` = :id_line, `imported` = :state WHERE `Index` = :index;";
                    $params_tempo = [':id_line' => $right['id_line'], ':index' => $left['Index'] , ':state' => Compta_Imports::STATE_IMPORT_MODIFIED ];

                    $sql_update_lines = "UPDATE `" . $this->tables_name['lines'] . "` SET `import` = :index WHERE `id_line` = :id_line;";
                    $params_lines = [':id_line' => $right['id_line'], ':index' => $left['Index'] ];
                    
                    $this->InfoLog("############### Résolution auto réalisé"); 
                    $this->SqlLog($sql_update_tempo, $params_tempo); 
                    $this->objdb->exec($sql_update_tempo, $params_tempo);
                    
                    $this->SqlLog($sql_update_lines, $params_lines);
                    $this->objdb->exec($sql_update_lines, $params_lines);
                    
                    $message = sprintf(
                        "1:1 AUTO - %s | %s | %s€ | Index:%d → Line:%d",
                        $conflit[':label'],
                        $conflit[':Fournisseur'],
                        $conflit[':Ttc'],
                        $left['Index'],
                        $right['id_line']
                    );
                    $this->rapport->Add_Ligne_found($message);
                    
                    $has_auto_resolved = true;
                    $total_auto_resolved++;
                    break; // Sortir et relire les conflits
                }
            }
            
            if ($has_auto_resolved) {
                // Relire les conflits après résolution automatique
                try {
                    $conflits = $this->get_clonflits_compte();
                } catch (Exception $e) {
                    $this->ErrorLog("Erreur relecture conflits: " . $e->getMessage());
                    break;
                }
            }
            
        } while ($has_auto_resolved);

        // Message de synthèse final (UNE SEULE FOIS)
        if ($total_auto_resolved > 0) {
            $this->InfoLog(sprintf(
                "✓ Résolutions auto: %d conflit(s) en %d itération(s)",
                $total_auto_resolved,
                $iterations
            ));
        } else {
            $this->InfoLog("Aucune résolution automatique possible");
        }

        return $conflits;
    }

    private function get_step_form($mode_import): int
    {
        switch ($mode_import) {
            case Compta_Imports::MODE_SEARCH_S:
                return 1;
            case Compta_Imports::MODE_SEARCH_M:
                return 3;
            case Compta_Imports::MODE_SEARCH_L:
                return 5;
            case Compta_Imports::MODE_SEARCH_X:
                return 7;
        }

        return 255;
    }

    /**
     * Affiche le formulaire pour la résolution manuelle des conflits
     * 
     * @param array $conflits Liste des conflits à afficher
     * @return string HTML du formulaire
     */
    private function display_manual_conflicts(array $conflits, $mode_import): string
    {
        $count_total = count($conflits);
        
        // UN SEUL message d'entrée avec toutes les infos
        $this->InfoLog(sprintf("Affichage formulaire conflits: %d conflit(s) potentiel(s)", $count_total));

        $answer = "\t\t<h2 class=\"imports-box-title\">Gestion des conflits d'imports</h2>" . PHP_EOL;

        $answer .= "\t\t<form method=\"post\" enctype=\"multipart/form-data\" id=\"form_imports\">" . PHP_EOL;
        $answer .= "\t\t<input type=\"hidden\" name=\"form_num\" value=\"2\">" . PHP_EOL;
        $answer .= "\t\t<input type=\"hidden\" name=\"step\" value=\"" . ($this->get_step_form($mode_import)+1) . "\">" . PHP_EOL;

        $count_manual_conflicts = 0;
        $count_filtered = 0;
        
        // Afficher uniquement les conflits nécessitant une résolution manuelle
        foreach ($conflits as $conflit) {
            // try
            // {
                $answer .= $this->get_element_conflit_for_form($conflit, $mode_import) . PHP_EOL;
                $count_manual_conflicts++;
            // }
            // catch (LeftColumnEmptyException $e) {
            //     // Pas de log individuel, juste compter
            //     $count_filtered++;
            // }
        }
        
        // Message de synthèse final (UNE SEULE FOIS avec toutes les stats)
        if ($count_manual_conflicts > 0) {
            $this->InfoLog(sprintf(
                "Résultat: %d conflit(s) manuel(s) à résoudre (%d filtré(s))",
                $count_manual_conflicts,
                $count_filtered
            ));
            
            $answer .= "\t\t\t<p class=\"info\">💡 " . $count_manual_conflicts . " conflit(s) nécessitent votre attention</p>" . PHP_EOL;
        } else {
            $this->InfoLog("✓ Tous les conflits résolus automatiquement");
            $answer .= "\t\t\t<p class=\"success\">✓ Aucun conflit manuel à résoudre</p>" . PHP_EOL;
        }

        $answer .= "\t\t\t<input style=\"float: right;\" type=\"submit\" value=\"Continuer\" name=\"submit\"/>" . PHP_EOL;
        $answer .= "\t\t</form>" . PHP_EOL;
        
        return $answer;
    }


    private function get_element_conflit_for_form($conflit, $mode_import): string
    {
        // $this->InfoLog("get_element_conflit ::  conflit : " . print_r($conflit,true) );
        $answer = "<div class=\"conflit\">" . PHP_EOL;
        
            $answer .= "<div class=\"conflit_titre\">" . PHP_EOL;
                $answer .= "<span>" . $conflit[':label'] . "</span><span>" . $conflit[':Fournisseur'] . "</span><span>" . $conflit[':Date'] . "</span><span>" . $conflit[':Ttc'] . "€</span>" . PHP_EOL;
            $answer .= "</div>" . PHP_EOL;
            
            $answer .= "<div class=\"zone\">" . PHP_EOL;

                $answer .= "<div class=\"col gauche\">" . PHP_EOL;
                $answer .= $this->get_items_conflit($conflit, self::COL_GAUCHE, $mode_import);
                $answer .= "</div>" . PHP_EOL;
                    
                $answer .= "<svg></svg>" . PHP_EOL;
                
                $answer .= "<div class=\"col droite\">" . PHP_EOL;
                $answer .= $this->get_items_conflit($conflit, self::COL_DROITE, $mode_import);
                $answer .= "</div>" . PHP_EOL;

            $answer .= "</div>" . PHP_EOL;

            $answer .= "<div class=\"actions\">" . PHP_EOL;
                $answer .= "<button class=\"valider-btn\">Valider</button>" . PHP_EOL;
                $answer .= "<button class=\"clear-btn\">Clear</button>" . PHP_EOL;
                $answer .= "<button class=\"forced-btn\">Forced</button>" . PHP_EOL;
            $answer .= "</div>" . PHP_EOL;

        $answer .= "</div>" . PHP_EOL;

        return $answer;
    }

    private function get_items_conflit($criteria, int $col, $mode_import): string
    {
        $answer = "";

        if ($col == self::COL_GAUCHE)
        {
            // Sélection des lignes problématiques dans la table temporaire
            $data = $this->search_line_in_tempo_for_comp($criteria, $mode_import); 
            
            // if (count($data) == 0) {
            //     throw new LeftColumnEmptyException("Pas de donnée dans la table d'import à afficher");
            // }
            
            foreach ($data as $infos) {
                // SUPPRIMÉ: $this->InfoLog("get_items_conflit :: infos left : " . print_r($infos,true) );
                $answer .= "<div class=\"item\" data-side=\"left\" data-index=\"" . $infos['Index'] . "\">";
                $answer .= "<span class=\"tooltip\" data-tooltip=\"" . $infos['shortname'] . "\">" . $infos['Key'] . "</span>";
                $answer .= "<span class=\"tooltip\" data-tooltip=\"" . $infos['CompteLib'] . "\">" . $infos['CompteNum'] . "</span>";
                $answer .= "</div>";
            }
        }
        else {
            // Sélection des lignes problématiques dans la table lines
            $data = $this->search_line_in_lines_for_comp($criteria, $mode_import); 
            
            // if (count($data) == 0) {
            //     throw new RightColumnEmptyException("Pas de donnée dans la table ligne à afficher");
            // }
            
            foreach ($data as $infos) {
                // SUPPRIMÉ: log individuel
                $answer .= "<div class=\"item\" data-side=\"right\" data-index=\"" . $infos['id_line'] . "\">";
                $answer .= "<span class=\"tooltip\" data-tooltip=\"" . $infos['shortname'] . "\">" . $infos['typekey'] . "</span>";
                $answer .= "<span class=\"tooltip\" data-tooltip=\"" . $infos['label_account'] . "\">" . $infos['num_account'] . "</span>";
                $answer .= "</div>";
            }
        }

        return $answer;
    } 

    private function show_form_resume($en_cours): string
    {
        $this->InfoLog("show_form_resume Entry .." );
        $answer = "<form method=\"post\" enctype=\"multipart/form-data\" id=\"form_imports\">" . PHP_EOL;

        $answer .= "<input type=\"hidden\" name=\"form_num\" value=\"2\">" . PHP_EOL;
        $answer .= "<input type=\"hidden\" name=\"step\" value=\"" . $en_cours . "\">" . PHP_EOL;

        $answer .= "\t\t\t<p><span>Voulez vous continuer l'import des données ?</span><span>&nbsp;&nbsp;&nbsp;&nbsp;</span><input type=\"radio\" name=\"answer\" id=\"oui\"/ value=\"oui\" checked><label for=\"oui\">Oui</label><span>&nbsp;&nbsp;&nbsp;</span><input type=\"radio\" name=\"answer\" id=\"non\"/ value=\"non\"><label for=\"non\">Non</label></p>" . PHP_EOL;
        $answer .= "<input style=\"float: right;\" type=\"submit\" value=\"continue\" name=\"submit\"/>" . PHP_EOL; // Attention : Submit intercepté par js (imports.js)

        $answer .= "</form>" . PHP_EOL;
        return $answer;
    }



    // Formulaire de delete d'un import (Voir les lignes qui vont être supprimer) 
    private function show_form_deleted(): string
    {
        $this->InfoLog("show_form_deleted Entry with formulaire : " . print_r($_POST,true) );

        $answer = "\t\t<h2 class=\"imports-box-title\">Gestion des deletes</h2>" . PHP_EOL;
        $answer .= "\t\t<form method=\"post\" enctype=\"multipart/form-data\" id=\"form_imports\">" . PHP_EOL;

        // $count_manual_conflicts = 0;
     //    
     //    foreach ($conflits as $conflit) {
     //        // Afficher uniquement les conflits nécessitant une résolution manuelle
     //        try
     //        {
     //            $answer .= $this->get_element_conflit($conflit) . PHP_EOL;
     //            $count_manual_conflicts++;
     //        }
     //        catch (CompatibilityException $e)
     //        {
     //            $this->InfoLog("Conflit filtré : " . $e->getMessage());
     //        }
     //    }
     //    
     //    if ($count_manual_conflicts > 0) {
     //        $this->InfoLog("Conflits manuels à résoudre : " . $count_manual_conflicts);
     //    } else {
     //        $this->InfoLog("✓ Aucun conflit manuel à résoudre");
     //    }

        $answer .= "\t\t\t<input style=\"float: right;\" type=\"submit\" value=\"Continuer\" name=\"submit\"/>" . PHP_EOL; // Attention : Submit intercepté par js (imports.js)
        $answer .= "\t\t\t<input type=\"hidden\" name=\"step\" value=\"255\">" . PHP_EOL;
        $answer .= "\t\t</form>" . PHP_EOL;
        // $answer = "\t\t<h2 class=\"home-title\">Voulez-vous </h2>" . PHP_EOL;
        // $answer .= "\t\t<form method=\"post\" enctype=\"multipart/form-data\" id=\"form_imports\">" . PHP_EOL;
        // $answer .= "\t\t\t<span>&nbsp;</span><input style=\"float: right;\" type=\"submit\" value=\"Je ne ferait rien (voir je sais pas ce que ça fait)\" name=\"submit\"/>" . PHP_EOL; // Attention : Submit intercepté par js (imports.js)
        // $answer .= "\t\t</form>" . PHP_EOL;

        return $answer;

    }



    // Fonction qui permet de valider les conflits bizard

    public function Validation_conflit(): string
    {
        $this->StepLog("Validation conflit");

        $this->obj_sync_import->init_session();

        $this->tables_name['tempo'] = $this->obj_sync_import->get_table_tempo();
        $this->Periode = $this->obj_sync_import->get_periode();
        $this->tables_name['lines'] = $this->getNameTableLines($this->Periode);
        $this->tables_name['infos'] = $this->getNameTableInfos($this->Periode);
        
        $answer = "<div>" . PHP_EOL;
        foreach ($_POST as $key => $value) {
            $answer .= "<p>" . $key . ' : ' . $value . '</p>' . PHP_EOL;
            if ( str_starts_with($key, 'data') ) {
                $data = explode(",", $value);
                $answer .= "<div>décomposition = " . print_r( $data , true ) . '</div>' . PHP_EOL;
                $infos = [];
                
                foreach( $data as $line ) {
                    $element = explode(":", $line);
                    if ( $element !== [] ) {
                        // $answer .= "<p>element = " . print_r( $element , true ) . '</p>' . PHP_EOL;
                        // $answer .= "<p>" . $element[0] . ' : ' . $element[1] . '</p>' . PHP_EOL;
                        $infos[ $element[0] ] = $element[1];
                    }
                }                

                if ( $infos !== [] ) {
                    $relations[] = $infos;
                }
            }
        }        


        foreach ($relations as $relation) {
            try {
                $this->relied_line_for_import( $relation['right'], $relation['left'] );
                $answer .= "<p>Import de " . $relation['right'] . ' vers ' . $relation['left'] . ' : OK</p>' . PHP_EOL;
            }
            catch (\Throwable $e) {
                $answer .= "<p>[ERROR] lors de l'import de " . $relation['right'] . ' vers ' . $relation['left'];
                $answer .= '</p>' . PHP_EOL;
            }
                 
        }        

        $answer .= "</div>" . PHP_EOL;
        return $answer;
    }

    public function forced_conflit(): string
    {
        $this->StepLog("forced conflit");

        $this->obj_sync_import->init_session();
        $this->tables_name['tempo'] = $this->obj_sync_import->get_table_tempo();
        $this->Periode = $this->obj_sync_import->get_periode();
        $this->tables_name['lines'] = $this->getNameTableLines($this->Periode);
        $this->tables_name['infos'] = $this->getNameTableInfos($this->Periode);
        
        $this->InfoLog("Data _POST = " . print_r( $_POST , true ) . '');
        $step = $_POST['step'];
        $indexes = [];
        $answer = "<div>" . PHP_EOL;
        
        foreach ($_POST as $key => $value) {
            if (str_starts_with($key, 'index')) {
                $indexes[] = trim($value);
            }
        }

        if (empty($indexes)) {
            throw new Exception("Aucun index reçu");
        }

        foreach ($indexes as $index) {
            $answer .= $this->cancel_conflit($index, $step) . PHP_EOL;;
        }

        $answer .= "</div>" . PHP_EOL;
        return $answer;
    }

    public function Validation_lost(): string
    {
        $this->InfoLog("Validation_lost : " . print_r($_POST,true) );

        $this->obj_sync_import->init_session();
        $this->tables_name['tempo'] = $this->obj_sync_import->get_table_tempo();
        $this->Periode = $this->obj_sync_import->get_periode();
        $this->tables_name['lines'] = $this->getNameTableLines($this->Periode);
        $this->tables_name['infos'] = $this->getNameTableInfos($this->Periode);

        $this->InfoLog("Data _POST = " . print_r( $_POST , true ) . '');
        $step = $_POST['step'];
        $indexes = [];
        $answer = "<div>" . PHP_EOL;

        foreach ($_POST as $key => $value) {
            $answer .= "<p>" . $key . ' : ' . $value . '</p>' . PHP_EOL;
            if ( str_starts_with($key, 'data') ) {
                $data = explode(",", $value);
                $answer .= "<div>décomposition = " . print_r( $data , true ) . '</div>' . PHP_EOL;
                $infos = [];
                
                foreach( $data as $line ) {
                    $element = explode(":", $line);
                    if ( $element !== [] ) {
                        $answer .= "<p>element = " . print_r( $element , true ) . '</p>' . PHP_EOL;
                        $answer .= "<p>" . $element[0] . ' : ' . $element[1] . '</p>' . PHP_EOL;
                        $infos[ $element[0] ] = $element[1];
                    }
                }                

                if ( $infos !== [] ) {
                    $relations[] = $infos;
                }
            }
        }        


        foreach ($relations as $relation) {
            try {
                // $this->relied_line_for_import( $relation['right'], $relation['left'] );
                $answer .= "<p>Import de " . $relation['right'] . ' vers ' . $relation['left'] . ' : OK</p>' . PHP_EOL;
            }
            catch (\Throwable $e) {
                $answer .= "<p>[ERROR] lors de l'import de " . $relation['right'] . ' vers ' . $relation['left'];
                $answer .= '</p>' . PHP_EOL;
            }
                 
        }

        $answer .= "</div>" . PHP_EOL;
        return $answer;
    }

    private function delete_validation(int $id_validation):array
    {

        // $answer = 'function delete_validation is not implemented yet';

        $answer = '';

        $params[':id_validation'] = $id_validation;



        // $SQL1 = "SELECT count(validation_id) FROM `" . $this->tables_name['lines'] . "` WHERE `validation_id` = :id_validation;";

        // // $answer .= "<p>SQL = " . $SQL1 . "with params : " . print_r($params, true) . "</p>" . PHP_EOL;

        // try {

        //     $result = $this->objdb->execonerow($SQL1, $params);

        // } catch (\Throwable $e) {

        //     $answer .= "<p>Exception : <p>$e</p><p>SQL : $SQL1</p></p>" . PHP_EOL;

        //     $result = -1;

        // }



        // if ( $result[0] == 1 )

        // {

            $SQL2 = "DELETE FROM `" . $this->getNameTableValidations($this->Periode) . "` WHERE `id_validation` = :id_validation;";

            // $answer .= "<p>SQL = " . $SQL2 . "with params : " . print_r($params, true) . "</p>" . PHP_EOL;

            try {

                $result = $this->objdb->exec($SQL2, $params);

            } catch (\Throwable $e) {

                $answer .= "<p>Exception : <p>$e</p><p>SQL : $SQL2</p></p>" . PHP_EOL;

                $result = -1;

            }

            if ( $result > 0 ) {

                $answer .= "<p>Error requête delete line on validation (id_validation = $id_validation). Params =  " . print_r($params, true) . " (Requête : $SQL2)</p>" . PHP_EOL;

                return [ false, $answer ];

            }

        // }



        return [ true, $answer ];

    }

    private function delete_voucher(int $id_voucher):array
    {

        $answer = "";

        $params[':id_voucher'] = $id_voucher;



        $SQL1 = "SELECT count(voucher_id) FROM `" . $this->tables_name['lines'] . "` WHERE `voucher_id` = :id_voucher;";

        // $answer .= "<p>SQL = " . $SQL1 . "with params : " . print_r($params, true) . "</p>" . PHP_EOL;

        try {

            $result = $this->objdb->execonerow($SQL1, $params);

        } catch (\Throwable $e) {

            $answer .= "<p>Exception : <p>$e</p><p>SQL : $SQL1</p></p>" . PHP_EOL;

            $result = -1;

        }



        if ( $result[0] == 1 )

        {

            $SQL2 = "DELETE FROM `" . $this->getNameTableVouchers($this->Periode) . "` WHERE `id_voucher` = :id_voucher;";

            // $answer .= "<p>SQL = " . $SQL2 . "with params : " . print_r($params, true) . "</p>" . PHP_EOL;

            try {

                $result = $this->objdb->exec($SQL2, $params);

            } catch (\Throwable $e) {

                $answer .= "<p>Exception : <p>$e</p><p>SQL : $SQL2</p></p>" . PHP_EOL;

                $result = -1;

            }

            if ( $result > 0 ) {

                $answer .= "<p>Error requête delete line on voucher (id_voucher = $id_voucher). Params =  " . print_r($params, true) . " (Requête : $SQL2)</p>" . PHP_EOL;

                return [ false, $answer ];

            }

        }



        return [ true, $answer ];

    }

    private function relied_line_for_import(string $id_line, string $id_import)
    {
        $this->InfoLog("############### Résolution manuelle réalisé"); 

        // $Param_update_lines[':idline'] = $id_line;
        // $SQL1 = "SELECT state_id, infos FROM `" . $this->tables_name['lines'];
        // $SQL1 .= "INNER JOIN `" . $this->tables_name['validations'] . "` ON `id_validation` = `validation_id` ";
        // $SQL1 .= "WHERE id_line = :idline;";
        
        // $Param_update_temps[':idimport'] = $id_import;
        // $SQL2 = "SELECT * FROM `" . $this->tables_name['tempo'] . "` WHERE `Index` = :idimport;";

        // Mise à jour table tempo : line_id
        $sql_update_tempo = "UPDATE `" . $this->tables_name['tempo'] . "` SET `line_id` = :id_line, `imported` = :state WHERE `Index` = :index;";
        $params_tempo = [':id_line' => $id_line, ':index' => $id_import , ':state' => Compta_Imports::STATE_IMPORT_MODIFIED ];

        $sql_update_lines = "UPDATE `" . $this->tables_name['lines'] . "` SET `import` = :index WHERE `id_line` = :id_line;";
        $params_lines = [':id_line' => $id_line, ':index' => $id_import ];
        
        $this->SqlLog($sql_update_tempo, $params_tempo); 
        $this->objdb->exec($sql_update_tempo, $params_tempo);
        
        $this->SqlLog($sql_update_lines, $params_lines);
        $this->objdb->exec($sql_update_lines, $params_lines);
    }

    private function import_line_by_id(string $id_line, string $id_import):array
    {

            $answer = '';

            // $answer .= '<p>Table tempo => ' . $this->tables_name['tempo'] . '</p>' . PHP_EOL;

            // $answer .= '<p>Table lines => ' . $this->tables_name['lines'] . '</p>' . PHP_EOL;

            // $answer .= '<p>Table infos => ' . $this->tables_name['infos'] . '</p>' . PHP_EOL;

            

            // recherche des information à mettre à jour dans la table import

            // $params[':idline'] = $id_line;

            $Param_update_lines[':idline'] = $id_line;

            $SQL1 = "SELECT * FROM `" . $this->tables_name['lines'] . "` WHERE id_line = :idline;";

            // $answer .= "<p>SQL = " . $SQL1 . "</p>" . PHP_EOL;

           

            try {

                $row_table_lines = $this->objdb->execonerow($SQL1, $Param_update_lines);

                if ( empty($row_table_lines) ) {

                    $answer .= "<p>Error requête select line (id_line = $id_line) in lines</p>" . PHP_EOL;

                    return [ false, $answer ];

                }

                // $answer .= "<p>row_result = " . print_r($row_table_lines, true) . "</p>" . PHP_EOL;

            } catch (PDOExection $e) {

                $answer .= "<p>Error requête select line (Index = $id_line) in  " . $this->tables_name['lines'] . " (Requête : $SQL1)</p>" . PHP_EOL;

                return [ false, $answer ];

            }



            // recherche Key_id avec key de table import

            $params[':idimport'] = $id_import;

            $Param_update_lines[':idimport'] = $id_import;

            $SQL2 = "SELECT * FROM `" . $this->tables_name['tempo'] . "` WHERE `Index` = :idimport;";

            //$answer .= "<p>SQL = " . $SQL2 . "with params : " . print_r($params, true) . "</p>" . PHP_EOL;

           

            try {

                $row_table_import = $this->objdb->execonerow($SQL2, $params);

            } catch (\Throwable $e) {

                $answer .= "<p>Exception : $e</p>" . PHP_EOL;

                $row_result = [];

            }

            if ( empty($row_table_import) ) {

                $answer .= "<p>Error requête select line (Index = $id_import) in " . $this->tables_name['tempo'] . " (Requête : $SQL2)</p>" . PHP_EOL;

                return [ false, $answer ];

            }

            // $answer .= "<p>row_result = " . print_r($row_table_import, true) . "</p>" . PHP_EOL;

            // recherche les informations dans la table lignes



            // try {

            $idkey = $this->find_id_key( $row_table_import['Key'] );

            if ( $idkey < 0 )

            { 

                $answer .= "<p>Error requête select line (Index = $id_import) in " . $this->tables_name['tempo'] . " (Requête : $SQL2)</p>" . PHP_EOL;

                return [ false, $answer ];

            }

            // } catch (\Throwable $e) {

            //     $answer .= "<p>Error manque element Key in : " . print_r($row_table_import, true) . "</p>" . PHP_EOL;

            //     return [ false, $answer ];

            // }

            

            $this->objdb->beginTransaction();



            // test si validation_id

            if ( $row_table_lines['validation_id'] >= 1 && $row_table_lines['validation_id'] <= PHP_INT_MAX) {

                $result = $this->delete_validation( $row_table_lines['validation_id'] );

                if ( !$result[0] )

                    {

                    $answer .= "<p>delete_validation = " . $result[1] . "</p>" . PHP_EOL;

                    return [ false, $answer ];

                }

            }



            // Delete le pièce joint 

            // $answer .= "<p>row_table_lines = " . print_r($row_table_lines, true) . "</p>" . PHP_EOL;

            if ( $row_table_lines['voucher_id'] >= 1 && $row_table_lines['voucher_id'] <= PHP_INT_MAX) {

                $result = $this->delete_voucher( $row_table_lines['voucher_id'] );

                if ( !$result[0] )

                {

                    $answer .= "<p>delete_voucher = " . $result[1] . "</p>" . PHP_EOL;

                    return [ false, $answer ];

                }

            }



            // update table lignes avec :

            $Param_update_lines[':compte_num'] = $row_table_import['CompteNum'];

            $Param_update_lines[':compte_lib'] = $row_table_import['CompteLib'];

            $Param_update_lines[':validation'] = null;

            $Param_update_lines[':voucher'] = null;



            $Param_update_lines[':key'] = $idkey;

            $SQL3 = "UPDATE `" . $this->tables_name['lines'] . "` SET `key_id` = :key, `num_account` = :compte_num, `label_account` = :compte_lib, `validation_id` = :validation, `voucher_id` = :voucher, `import` = :idimport WHERE id_line = :idline;";

            // $answer .= "<p>SQL = " . $SQL3 . "</p>" . PHP_EOL;

            try {

                $result = $this->objdb->exec($SQL3, $Param_update_lines);

            } catch (\Throwable $e) {

                $answer .= "<p>Exception : $e</p>" . PHP_EOL;

                $result = -1;

            }

            if ( $result > 0 ) {

                $answer .= "<p>Error requête update line (id_line = $id_line). Params =  " . print_r($Param_update_lines, true) . " (Requête : $SQL3)</p>" . PHP_EOL;

                return [ false, $answer ];

            }



            // Update table import avec ():

            // Réutilisation de params car il contient déjà id_import

            $params[':idline'] = $id_line;

            $SQL4 = "UPDATE `" . $this->tables_name['tempo'] . "` SET `line_id` = :idline WHERE `Index` = :idimport;";

            // $answer .= "<p>SQL = " . $SQL4 . "with params : " . print_r($params, true) . "</p>" . PHP_EOL;

            try {

                $result = $this->objdb->exec($SQL4, $params);

            } catch (\Throwable $e) {

                $answer .= "<p>Exception : $e</p>" . PHP_EOL;

                $result = -1;

            }

            if ( $result > 0 ) {

                $answer .= "<p>Error requête update Import (Index = $id_import). Params =  " . print_r($params, true) . " (Requête : $SQL4)</p>" . PHP_EOL;

                return [ false, $answer ];

            }

            $this->objdb->endTransaction();

            return [ true, $answer ];
    }

    private function cleanup_obsolete_data(string $periode): bool
    {
        $this->StepLog("NETTOYAGE DONNEES OBSOLETES - Période " . $periode);

        try {
            // 1. Supprimer les lignes avec import IS NULL (lignes supprimées du nouvel Excel)
            $sql1 = "DELETE FROM `" . $this->getNameTableLines($periode) . "` WHERE `import` IS NULL";
            $this->SqlLog($sql1);
            $result1 = $this->objdb->exec($sql1);
            $this->InfoLog("Lignes supprimées (import IS NULL) : " . $result1);
            
            // 2. Supprimer les infos orphelines (dont aucune ligne n'existe plus)
            $sql2 = "DELETE i FROM `" . $this->getNameTableInfos($periode) . "` i 
                     WHERE NOT EXISTS (
                         SELECT 1 FROM `" . $this->getNameTableLines($periode) . "` l 
                         WHERE l.info_id = i.id_info
                     )";
            $this->SqlLog($sql2);
            $result2 = $this->objdb->exec($sql2);
            $this->InfoLog("Infos supprimées (orphelines) : " . $result2);

            // 3. Supprimer les validations orphelines
            $sql3 = "DELETE v FROM `" . $this->getNameTableValidations($periode) . "` v 
                     WHERE NOT EXISTS (
                         SELECT 1 FROM `" . $this->getNameTableLines($periode) . "` l 
                         WHERE l.validation_id = v.id_validation
                     )";
            $this->SqlLog($sql3);
            $result3 = $this->objdb->exec($sql3);
            $this->InfoLog("Validations supprimées (orphelines) : " . $result3);

            // 4. Supprimer les vouchers orphelins
            $sql4 = "DELETE vo FROM `" . $this->getNameTableVouchers($periode) . "` vo 
                     WHERE NOT EXISTS (
                         SELECT 1 FROM `" . $this->getNameTableLines($periode) . "` l 
                         WHERE l.voucher_id = vo.id_voucher
                     )";
            $this->SqlLog($sql4);
            $result4 = $this->objdb->exec($sql4);
            $this->InfoLog("Vouchers supprimés (orphelins) : " . $result4);

            $this->InfoLog("Nettoyage terminé avec succès");
            return true;
        }
        catch (\Throwable $e) {
            $this->InfoLog("ERREUR lors du nettoyage : " . $e->getMessage());
            return false;
        }
    }

    private function cancel_conflit($index, $step)
    {
        $this->StepLog("cancel conflit");

        $this->InfoLog("cancel_conflit :: index = " . $index . ' Step = ' . $step);
        $sql = 'UPDATE `' . $this->tables_name['tempo'] . '` SET `imported` = :step WHERE `Index` = :index;';
        $this->SqlLog($sql);
        if ( $this->objdb->exec($sql, [ ':index' => $index, ':step' => $step ]) >= 0 ) {
            return "<p>ok</p>";
        } 

        return "<p>erreur</p>";
    }

    private function import_lignes()
    {
        $this->StepLog("Import des lignes - Période " . $this->Periode);
        
        $sql = "SELECT * FROM `IMPORT_EXCEL_2024_2025_20260119220507` WHERE `imported` != 0;";
        $lines_to_import = $this->objdb->ExecWithFetchAll($sql);

        foreach($lines_to_import as $line_to_import) {
            switch ( $line_to_import['imported'] )
            {
                case 5:
                    $this->rapport->add_ligne_new( print_r($line_to_import,true) );
                    $this->objdb->beginTransaction();
                    try {
                        $id_info = $this->add_entree_Info($line_to_import);
                        $this->add_entree_Line( $line_to_import, $id_info );
                        $this->objdb->endTransaction();                            
                    } catch (Exception $e) {
                        $this->objdb->cancelTransaction();
                        throw new CompatibilityException( "Impossible d'ajouter la ligne. Data : " . print_r($line_to_import,true),0 ,$e);
                    }
                    break;
                case 1:
                    $this->rapport->Add_Ligne_found( print_r($line_to_import,true) );
                    $this->objdb->beginTransaction();
                    try {
                        $this->move_ligne($line_to_import);
                        $this->objdb->endTransaction();
                    } catch (Exception $e) {
                        $this->objdb->cancelTransaction();
                        throw new CompatibilityException( "Impossible de déplacer la ligne. Data : " . print_r($line_to_import,true),0 ,$e);
                    }
                    break;
                default:
                    throw new CompatibilityException( "Ligne ignorée - imported=" . $line_to_import['imported'] . " : " . print_r($line_to_import,true) );
            }
        }
    
        $this->InfoLog("Import_lignes terminé avec succès");
        return true;
    }

    private function clear_import(): string
    {
        // $answer = "<h2 class=\"home-title\">Fonction \"show_form_deleted\" toujours pas implémenté ...</h2>" . PHP_EOL;
        $answer = "\t\t<h2 class=\"home-title\">Finalisation de l'import</h2>" . PHP_EOL;

        $this->rapport = new Rapport_Import(PATH_HOME_CS . '/logs/', 'Rapport-Import'. "__--__" . date('YmdHis'), false);
        $this->obj_sync_import->init_session();
        
        $this->rapport->Add_Titre(Rapport_Import::NEW, 'Lignes en next step');
        $this->rapport->Add_Titre(Rapport_Import::FOUND, 'Lignes reliées');

        // Import des lignes
        try { $this->import_lignes($this->Periode); }
        catch (Exception $e) {
            $answer = "\t\t<div class=\"imports-message\">Exception levé durant clear_import : " . $e->getMessage() . "</div>" . PHP_EOL;
            return $answer;
        }

        // Nettoyage des données obsolètes AVANT de finaliser
        if ( !$this->cleanup_obsolete_data($this->Periode) )
        {
            $answer = "\t\t<div class=\"imports-message\">ERREUR lors du nettoyage des données obsolètes.</div>" . PHP_EOL;
            return $answer;
        }

        if ( $this->obj_sync_import->resetImport( $this->obj_sync_import->get_periode() ) ) 
        {
            $answer = "\t\t<div class=\"imports-message\">Vous avez finalisé l'import.</div>" . PHP_EOL;
        }
        else 
        {
            $answer = "\t\t<div class=\"imports-message\">ERREUR lors de la finalisation l'import.</div>" . PHP_EOL;
        }

        $this->rapport->printRapport();
        $this->InfoLog("Fin du traitement : " . $answer);
        return $answer;
    }

    private function search_entry_not_relied_in_table_tempo($mode_import): array
    {
            $sql = "SELECT * FROM `" . $this->tables_name['tempo'] . '` ';
            switch ($mode_import) {
                case Compta_Imports::MODE_SEARCH_S:
                    $sql .= ";"; // Imported is null
                    break;
                case Compta_Imports::MODE_SEARCH_M:
                    $sql .= "WHERE `imported` = " . Compta_Imports::STATE_IMPORT_STEP_1 . ";";
                    break;
                case Compta_Imports::MODE_SEARCH_L:
                    $sql .= "WHERE `imported` = " . Compta_Imports::STATE_IMPORT_STEP_2 . ";";
                    break;
                case Compta_Imports::MODE_SEARCH_X:
                    $sql .= "WHERE `imported` = " . Compta_Imports::STATE_IMPORT_STEP_3 . ";";
                    break;
            }
            $this->InfoLog('Import :: sql : ' . $sql);

        return $this->objdb->ExecWithFetchAll($sql);
    }

    private function search_and_relied_entries_not_associed_in_tempo($mode_import) 
    {
        $all_line = $this->search_entry_not_relied_in_table_tempo($mode_import);
        // if ( $this->test == 0 ) {
            foreach($all_line as $line)
            {
                // $this->InfoLog('line : ' . print_r($line,true) );

                if ( !$this->inject_line($line, $mode_import) )
                {
                    throw new CompatibilityException("Erreur lors de l'injection de la ligne :  - [ " . print_r($line, true) . " ]");
                }
            }
        // }
        // else
        // {
        //     while($this->debug_line_count < $this->debug_line_limit)
        //     {
        //         $line = $all_line[$this->debug_line_count];
        //         $this->InfoLog('line : ' . print_r($line,true) );
        //         if ( !$this->inject_line($line) )
        //         {
        //             throw new Exception("Erreur lors de l'injection de la ligne :  - [ " . print_r($line, true) . " ]");
        //         }
        //         $this->debug_line_count++;
        //     }
        // }
    }

    public function resume_import($mode_import): string
    {
        $this->rapport = new Rapport_Import(PATH_HOME_CS . '/logs/', 'Rapport-Import'. "__--__" . date('YmdHis'), false);
        $this->StepLog("RESUME IMPORT - Recherche avec des critères inférieur");

        $answer = [];
        $Formulaire = $_POST;
        
        $this->DataLog("Formulaire reçu", $Formulaire);

        if ( empty($Formulaire) )
        {
            $this->InfoLog("ERREUR : Formulaire ou fichiers manquants");
            $answer['error_mess'] = 'Error : Les informations formulaire ne sont pas présente ...';
        }
        else
        {
            $this->InfoLog("Période sélectionnée : " . $this->Periode);
            try
            {
                $this->search_and_relied_entries_not_associed_in_tempo($mode_import);
            }
            catch (PDOExection $e)
            {
                $this->erreurs = $e->getMessage();
                $answer['error_mess'] = $this->erreurs;
                $answer['error_list'] = $this->erreurs;
            }
        }

        if ( !empty($answer) )
        {
            // $this->InfoLog('Import error : ' . $answer['error_mess']);
            if ( isset($answer['error_list']) ) {
                return $this->get_error_mess('Error message : ' . $answer['error_mess'] . ' - Liste error : ' . implode(', ', $answer['error_list']) );
            } else {
                return $this->get_error_mess('Error message : ' . $answer['error_mess'] );
            }
        }
        else
        {
            try
            {
                $this->Update_Years_table( $this->Periode, $this->tables_name['tempo'] );
            }
            catch (PDOExection $e)
            {
                return $this->get_error_mess('Error message : ' . $answer['error_mess'] );
            }
        }

        $this->rapport->printRapport();
        unset($_SESSION['syncho_import']);
        $answer = "\t\t<h2 class=\"imports-box-title\">Import step " . ($this->get_step_form($mode_import)-1) . " terminé</h2>" . PHP_EOL;
        $answer .= "\t\t<form method=\"post\" enctype=\"multipart/form-data\" id=\"form_imports\">" . PHP_EOL;
        
        $answer .= "<input type=\"hidden\" name=\"form_num\" value=\"2\">" . PHP_EOL;
        $answer .= "<input type=\"hidden\" name=\"step\" value=\"" . $this->get_step_form($mode_import) . "\">" . PHP_EOL;

        $answer .= "\t\t\t<input style=\"float: right;\" type=\"submit\" value=\"Continuer\" name=\"submit\"/>" . PHP_EOL; // Attention : Submit intercepté par js (imports.js)
        $answer .= "\t\t</form>" . PHP_EOL;

        return $answer;
    }
    
    public function start_Import(): string
    {
        $this->rapport = new Rapport_Import(PATH_HOME_CS . '/logs/', 'Rapport-Import'. "__--__" . date('YmdHis'), false);
        $this->StepLog("DEBUT IMPORT - Upload et validation fichier Excel");

        $answer = [];
        $Formulaire = $_POST;
        $Files = $_FILES;
        
        $this->DataLog("Formulaire reçu", $Formulaire);
        $this->DataLog("Fichiers reçus", $Files);

        // $this->InfoLog("Formulaire : " . print_r($Formulaire,true) );
        // $this->InfoLog("Files : " . print_r($Files,true) );
        // $this->InfoLog("Files : " . $Files['fileToUpload']['size'] );

        if ( empty($Formulaire) || empty($Files) )
        {

            $this->InfoLog("ERREUR : Formulaire ou fichiers manquants");

            $answer['error_mess'] = 'Error : Les informations formulaire ne sont pas présente ...';

        }
        elseif ( $Files['fileToUpload']['size'] <= 0 || $Files['fileToUpload']['error'] != 0 )
        {
            $this->InfoLog("ERREUR : Fichier vide ou erreur de chargement (size=" . $Files['fileToUpload']['size'] . ", error=" . $Files['fileToUpload']['error'] . ")");
            $answer['error_mess'] = 'Error : Le fichier vide ou il y a eu un erreur de chargement ...';
        }
        elseif ( strtolower(pathinfo($Files['fileToUpload']['name'], PATHINFO_EXTENSION)) != 'xls' )
        {
            $this->InfoLog("ERREUR : Extension non autorisée (" . pathinfo($Files['fileToUpload']['name'], PATHINFO_EXTENSION) . ")");
            $answer['error_mess'] = 'Error : Extension non autorisée.';
        }
        else
        {
            $this->InfoLog("Fichier validé : " . $Files['fileToUpload']['name'] . " (" . $Files['fileToUpload']['size'] . " octets)");
            $this->InfoLog("Période sélectionnée : " . $Formulaire['periode']);
            
            // Creation Table et replissage
            if ( !$this->Create_PreImport_DB($Formulaire['periode'], $Files["fileToUpload"]["tmp_name"]) )
            {
                $this->InfoLog("ERREUR Create_PreImport_DB : " . $this->erreurs);
                return $this->get_error_mess('Error aqui : Create_PreImport_DB : ' . $this->erreurs );
            }
            $this->InfoLog("Table temporaire créée et remplie avec succès");


            // Création des tables d'imports pour l'année
            if ( !$this->Create_All_Table_For_Import($Formulaire['periode']) )
            {
                return $this->get_error_mess('Error : Create_All_Table_For_Import : ' . $this->erreurs );
            }

            $this->setKeyComptable();
            
            try
            {
                $this->search_and_relied_entries_not_associed_in_tempo(Compta_Imports::MODE_SEARCH_S);
            }
            catch (PDOExection $e)
            {
                $this->erreurs = $e->getMessage();
                $answer['error_mess'] = $this->erreurs;
                $answer['error_list'] = $this->erreurs;
            }
        }


        if ( !empty($answer) )
        {
            // $this->InfoLog('Import error : ' . $answer['error_mess']);
            if ( isset($answer['error_list']) ) {
                return $this->get_error_mess('Error message : ' . $answer['error_mess'] . ' - Liste error : ' . implode(', ', $answer['error_list']) );
            } else {
                return $this->get_error_mess('Error message : ' . $answer['error_mess'] );
            }
        }
        else
        {
            try
            {
                $this->Update_Years_table( $Formulaire['periode'], $this->tables_name['tempo'] );
            }
            catch (PDOExection $e)
            {
                // $this->InfoLog('Import error : ' . $answer['error_mess']);
                return $this->get_error_mess('Error message : ' . $answer['error_mess'] );
            }
        }

        $this->rapport->printRapport();
        unset($_SESSION['syncho_import']);
        $answer = "\t\t<h2 class=\"imports-box-title\">Import fichier Excel terminé</h2>" . PHP_EOL;
        $answer .= "\t\t<form method=\"post\" enctype=\"multipart/form-data\" id=\"form_imports\">" . PHP_EOL;
        
        $answer .= "<input type=\"hidden\" name=\"form_num\" value=\"2\">" . PHP_EOL;
        $answer .= "<input type=\"hidden\" name=\"step\" value=\"1\">" . PHP_EOL;

        $answer .= "\t\t\t<input style=\"float: right;\" type=\"submit\" value=\"Continuer\" name=\"submit\"/>" . PHP_EOL; // Attention : Submit intercepté par js (imports.js)
        $answer .= "\t\t</form>" . PHP_EOL;

        return $answer;
    }

    private function get_clonflits_compte():array
    {
        $this->StepLog("DETECTION CONFLITS - Recherche lignes non reliées");

        $answer = [];
        // Récupération de la totalité des lignes non relier (qui ont donc été déplacé)
        $sql = "SELECT `Index`, `CompteNum`, `CompteLib`, `Key`, `Libelle`, `NumVoucher`, `Fournisseur`, `Date`, `TVA`, `Charges`, `TTC` FROM `" . $this->tables_name['tempo'] . "` where imported = " . Compta_Imports::STATE_IMPORT_CONFLIT . " and line_id is null;";
        $this->SqlLog($sql);

        $list_line_not_imported = $this->objdb->ExecWithFetchAll($sql);
        $this->InfoLog("Nombre de lignes non reliées détectées : " . count($list_line_not_imported));

        foreach ( $list_line_not_imported as $line_line_not_imported )
        {
            $criteria = [
                ':index' => $line_line_not_imported['Index'],
                ':label' => $line_line_not_imported['Libelle'],
                ':voucher' => $line_line_not_imported['NumVoucher'],
                ':Fournisseur' => $line_line_not_imported['Fournisseur'],
                ':Date' => $line_line_not_imported['Date'],
                ':Tva' => $line_line_not_imported['TVA'],
                ':Charges' => $line_line_not_imported['Charges'],
                ':Ttc' => $line_line_not_imported['TTC'],
            ];
            $answer[] = $criteria;
        }

        return $answer;
    }



    // Fonction qui li le fichier Excel, créé la table et la remplie
    private function Create_PreImport_DB($Periode, $PathFile): bool
    {
        // $this->InfoLog('Entry in Create_PreImport_DB ... ');

        // Set Local
        $locales = ['fr_FR.UTF-8', 'fr_FR', 'fr'];
        foreach ($locales as $locale) {

            if (\PhpOffice\PhpSpreadsheet\Settings::setLocale($locale)) {

                $this->InfoLog("Locale définie : $locale");

                break;

            }

        }



        // Ouverture du fichier Excel

        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();

        $reader->setReadDataOnly(true);

        $spreadsheet = $reader->load($PathFile);

    

        // Recupère la feulle de calcul

        $worksheet = $spreadsheet->getActiveSheet();

        // Verif entête du fichier

        $answer = $this->check_Excel_import($worksheet);

        if ( !empty($answer) )

        {

            $this->erreurs = "check_Excel_import : " . implode(', ', $answer);

            return false;

        }

        // Cretation de la table d'import

        if (!$this->prepare_Import_Table($Periode)) {

            $this->erreurs = "prepare_Import_Table : " . $this->erreurs;

            return false;

        }

        

        $this->Periode = $Periode;



        //Lecture des lignes du tableau excel  

        $answer = true;

        $line_values = array();

        $highestRow = $worksheet->getHighestDataRow();

        for ($row = 2; $row <= $highestRow; ++$row) {

            reset($line_values);

            $line_values = array();

            for ($col = 1; $col <= 10; ++$col) {

                $value = $worksheet->getCell([$col, $row])->getValue();

                array_push($line_values, $value);

            }

            

            // Formatage des sommes d'argent
            $this->InfoLog('Create_PreImport_DB :: line_values[7] : ' . $line_values[7] );
            try { $line_values[7] = $this->format_montant_comptable($line_values[7]); }
            catch (Exception $e) {
                $this->erreurs = "Formatage des montants colone 7 : " . $e->getMessage();
                return false;
            }

            $this->InfoLog('Create_PreImport_DB :: line_values[8] : ' . $line_values[8] );
            try { $line_values[8] = $this->format_montant_comptable($line_values[8]); }
            catch (Exception $e) {
                $this->erreurs = "Formatage des montants colone 8 : " . $e->getMessage();
                return false;
            }
            

            $this->InfoLog('Create_PreImport_DB :: line_values[9] : ' . $line_values[9] );
            try { $line_values[9] = $this->format_montant_comptable($line_values[9]); }
            catch (Exception $e) {
                $this->erreurs = "Formatage des montants colone 9 : " . $e->getMessage();
                return false;
            }



            // Insertion dans la basr temporraire

            if ( !$this->insert_import_line($line_values) ) {

                $this->erreurs = "add_import_line : " . $this->erreurs;

                $answer = false;

                break;

            } 

        }



        return $answer;

    }


    private function check_Excel_import($worksheet):array

    {

        $answer = array();

        foreach ([Compta_Imports::EXCEL_COL_NUMCOMPTE, Compta_Imports::EXCEL_COL_LIBCOMPTE, Compta_Imports::EXCEL_COL_KEY, Compta_Imports::EXCEL_COL_LIBELLE, Compta_Imports::EXCEL_COL_VOUCHIER, Compta_Imports::EXCEL_COL_FOURNI, Compta_Imports::EXCEL_COL_DATE, Compta_Imports::EXCEL_COL_TVA, Compta_Imports::EXCEL_COL_CHARGES, Compta_Imports::EXCEL_COL_TTC] as $val) {

            try { 

                $value_excel = str_replace([ "\n", "\r", ' ' ], '', $worksheet->getCell([$val+1, 1])->getValue());

                if ($value_excel !== $this->NameColLst[$val])

                {

                    throw new Exception("La colone $val contient $value_excel au lieu de " . $this->NameColLst[$val] . "."); 

                }

            }

            catch (Exception $e) {

                array_push($answer, $e->getMessage());

            }

        }

        return $answer;

    }



    private function get_error_mess(string $message): string
    {
        return "\t\t<h2 class=\"home-title\">" . $message . "</h2>" . PHP_EOL;
    }

    

    private function prepare_Import_Table($Periode)
    {

        // $this->InfoLog("Entry in Prepare import Table ...");

        // table_name	

        $this->tables_name['tempo'] = 'IMPORT_EXCEL_' . $Periode . "_" . date('YmdHis');	

        // $sql = "CREATE TABLE `csresip1501`" . $this->tables_name['tempo'] . "(`Index` VARCHAR(8) NOT NULL, `CompteNum` INT UNSIGNED NULL , `CompteLib` TINYTEXT NULL , `Key` TINYTEXT NOT NULL , `Libelle` TEXT NOT NULL, `NumVoucher` TINYTEXT NOT NULL COMMENT 'N° pièce comptable', `Fournisseur` TINYTEXT NOT NULL, `Date` varchar(10) NOT NULL, `TVA` TINYTEXT NOT NULL, `Charges` TINYTEXT NOT NULL, `TTC` TINYTEXT NOT NULL, PRIMARY KEY (`Index`(8))) ENGINE = InnoDB;";

        // $sql = "CREATE TABLE `csresip1501`." . $this->tables_name['tempo'] . " (`Index` VARCHAR(8) NOT NULL, `CompteNum` INT UNSIGNED NULL , `CompteLib` TINYTEXT NULL , `Key` TINYTEXT NOT NULL , `Libelle` TEXT NOT NULL, `NumVoucher` TINYTEXT NOT NULL COMMENT 'N° pièce comptable', `Fournisseur` TINYTEXT NOT NULL, `Date` varchar(10) NOT NULL, `TVA` TINYTEXT NOT NULL, `Charges` TINYTEXT NOT NULL, `TTC` TINYTEXT NOT NULL, `imported` TINYTEXT DEFAULT NULL, `line_id` INT DEFAULT NULL, PRIMARY KEY (`Index`(8))) ENGINE = InnoDB;";

        $sql = "CREATE TABLE `csresip1501`." . $this->tables_name['tempo'] . " (

            `Index` VARCHAR(8) NOT NULL,

            `CompteNum` VARCHAR(8) NOT NULL DEFAULT '',

            `CompteLib` TINYTEXT NOT NULL DEFAULT '',

            `Key` VARCHAR(3) NOT NULL,

            `Libelle` TEXT NOT NULL DEFAULT '',

            `NumVoucher` TINYTEXT NOT NULL DEFAULT '' COMMENT 'N° pièce comptable',

            `Fournisseur` TINYTEXT NOT NULL DEFAULT '',

            `Date` VARCHAR(10) NOT NULL DEFAULT '',

            `TVA` VARCHAR(12) NOT NULL DEFAULT '',

            `Charges` VARCHAR(12) NOT NULL DEFAULT '',

            `TTC` VARCHAR(12) NOT NULL DEFAULT '',

            `imported` TINYINT DEFAULT NULL,

            `line_id` INT DEFAULT NULL,

            PRIMARY KEY (`Index`(8))

        ) ENGINE = InnoDB;";

        $answer = false;



        try

        {

            $count = $this->objdb->exec($sql);

            // $this->InfoLog("Exec Sql : ", $sql);

            // if ( $count == 0 ) throw new Exception('Erreur de création de la table temporraire');

            $sql = "INSERT INTO `" . $this->tables_name['tempo'] . "` ( `Index`, `CompteNum`, `CompteLib`, `Key`, `Libelle`, `NumVoucher`, `Fournisseur`, `Date`, `TVA`, `Charges`, `TTC`) VALUES ( :id, :numcompte, :libcompte, :key, :libelle, :numpiece, :fourn, :date, :tva, :charge, :ttc );"; 

            #echo "<H1>SQL 2: $sql</H1>";

            $this->objdb->query($sql);



            $answer = true;

        }

        catch (PDOExection $e)

        {

            $this->error = $e->getMessage();

        }

        

        return $answer;

    }


    public function insert_import_line($array_values)
    {	

        $answer = false;



        $id_import = $this->get_infos_key($array_values[2]);

        if ( isset( $id_import['error'] ) )

        {

            $this->error = $id_import['error'];

        }

        else

        {

                if ( $this->objdb->execute([

                    ':id' => $id_import['id'],

                    ':numcompte' => $array_values[0],

                    ':libcompte' => $array_values[1],

                    ':key' => $id_import['typekey'],

                    ':libelle' => $array_values[3],

                    ':numpiece' => $array_values[4],

                    ':fourn' => $array_values[5],

                    ':date' => $array_values[6],

                    ':tva' => $array_values[7],

                    ':charge' => $array_values[8],

                    ':ttc' => $array_values[9]

                ]) )

                {

                    $answer = true;

                } 

                else

                {

                    $this->error = $id_import['error'];

                }

        }

        

        return $answer;

    }


    private function get_infos_key($Key_Name)
    {

        $answer = [];



        $result = $this->get_typekey($Key_Name);

        if ( isset( $result['error'] ) )

        { 

            $answer['error'] = $result['error'];

        }

        else

        {

            $answer['typekey'] = $result['typekey'];

            $answer['id'] = $result['typekey'] . "_". ++$this->counts[$result['typekey']];

        }

        

        return $answer;

    }



    public function get_namekey($key_type)
    {

        $answer = [];



        $sql = "SELECT namekey FROM `Compte_Key_type` where typekey = '$key_type';";

        echo "SQL : $sql <br/>";



        try

        {

            $result = $this->objdb->execonerow($sql);

            $answer['namekey'] = $result['namekey'];

        }

        catch (PDOExection $e)

        {

            $this->error = $e->getMessage();

            $answer['error'] = $this->error;

        }

        

        return $answer;

    }


    public function get_typekey($Key_Name)
    {

        $answer = [];



        $sql = "SELECT typekey FROM `Compte_Key_type` where namekey = '$Key_Name';";

        #echo "SQL : $sql <br/>";



        try

        {

            $result = $this->objdb->execonerow($sql);

            if ( empty($result) ) throw new Exception("get_typekey : SQL = '" . $sql . "'\t\t Retour : " . print_r($result, true));

            

            $answer['typekey'] = $result['typekey'];

        }

        catch (PDOExection $e)

        {

            $this->error = $e->getMessage();

            $answer['error'] = $this->error;

        }

        

        return $answer;

    }


    public function inject_line($line, $mode_import):bool
    {
        $this->InfoLog( PHP_EOL . '********************************************************************************************************' . PHP_EOL );
        $this->rapport->Add_Titre(Rapport_Import::NEW, 'Lignes en next step');
        $this->rapport->Add_Titre(Rapport_Import::CONFLIT, 'Lignes en conflits');
        $this->rapport->Add_Titre(Rapport_Import::FOUND, 'Lignes reliées');

        $answer = true;
        $line_entry = [
                'num' => $line['CompteNum'],
                'label' => $line['CompteLib'],
            ];
        $sql_selkey  = "SELECT id_key FROM `" . $this->tables_name['keys'] . "` where typekey = '" . $line['Key'] . "';";

        try
        {
            // Rechercje de l'id de la clé de répartition
            // $this->InfoLog('inject_line :: sql 0 : ' . print_r($sql_selkey,true) );

            $sql_answer = $this->objdb->execonerow( $sql_selkey );
            // $this->InfoLog('inject_line :: sql_answer : ' . print_r($sql_answer,true) );

            if ( empty($sql_answer) ) {
                throw new Exception("Key not found in table : " . $line['Key'] . " - SQL : " . $sql_selkey);
            } else {
                $line_entry['key_id'] = $sql_answer['id_key'];
            }

            // recherche si l'information à déja été insérée 
            switch ($mode_import) {
                case Compta_Imports::MODE_SEARCH_S:
                    $line_info = [
                        ':label' => $line['Libelle'],
                        ':voucher' => $line['NumVoucher'],
                        ':Fournisseur' => $line['Fournisseur'],
                        ':Date' => $line['Date'],
                        ':Tva' => $line['TVA'],
                        ':Charges' => $line['Charges'],
                        ':Ttc' => $line['TTC'],
                    ];
                    $sql_set_next_step = 'update `' . $this->tables_name['tempo'] . '` set `imported` = ' . Compta_Imports::STATE_IMPORT_STEP_1 . ' where `Index` = :index ;';
                    break;
                case Compta_Imports::MODE_SEARCH_M:
                    $line_info = [
                        ':label' => $line['Libelle'],
                        ':voucher' => $line['NumVoucher'],
                        ':Fournisseur' => $line['Fournisseur'],
                        ':Ttc' => $line['TTC'],
                    ];
                    $sql_set_next_step = 'update `' . $this->tables_name['tempo'] . '` set `imported` = ' . Compta_Imports::STATE_IMPORT_STEP_2 . ' where `Index` = :index ;';
                    break;
                case Compta_Imports::MODE_SEARCH_L:
                    $line_info = [
                        ':voucher' => $line['NumVoucher'],
                        ':Ttc' => $line['TTC'],
                    ];
                    $sql_set_next_step = 'update `' . $this->tables_name['tempo'] . '` set `imported` = ' . Compta_Imports::STATE_IMPORT_STEP_3 . ' where `Index` = :index ;';
                    break;
                case Compta_Imports::MODE_SEARCH_X:
                    $line_info = [
                        ':Ttc' => $line['TTC'],
                    ];
                    $sql_set_next_step = 'update `' . $this->tables_name['tempo'] . '` set `imported` = ' . Compta_Imports::STATE_IMPORT_NEW . ' where `Index` = :index ;';
                    break;
            }

            $index_id_info = $this->search_id_in_info($line_info, $mode_import);
            $this->InfoLog( 'inject_line :: retour de search_id_in_info : ' . print_r($index_id_info,true) );
            $length_return = count($index_id_info);

            if ( $length_return == 0 )
            {
                $this->InfoLog('inject_line :: set_ligne_next_step : ' . print_r($line_info,true) . "   --- Index = " . $line['Index']);
                $this->rapport->add_ligne_next_step( print_r($line_info,true) );
                if ($this->test == 0) {
                    $this->objdb->exec( $sql_set_next_step, [ ':index' => $line['Index'] ] );
                }
                else { $line_entry['info_id'] = 0; }
            }
            elseif ( $length_return > 1 )
            {
                $this->InfoLog('inject_line :: detection double entry : ' . print_r($line_info,true) . "   --- Index = " . $line['Index']);
                $this->rapport->add_ligne_conflit( print_r($line_info,true) );

                if ($this->test == 0) {
                    // On memorise que l'on a trouver quelque chose
                    $sql = 'update `' . $this->tables_name['tempo'] . '` set `imported` = ' . Compta_Imports::STATE_IMPORT_CONFLIT . ' where `Index` = :index ;';
                    $this->objdb->exec( $sql, [ ':index' => $line['Index'] ] );
                }
                else { $line_entry['info_id'] = 0; }
            } else {

                $line_entry['info_id'] = $index_id_info[0];

                // On memorise que l'on a trouver quelque chose
                $sql = 'update `' . $this->tables_name['tempo'] . '` set `imported` = ' . Compta_Imports::STATE_IMPORT_CONFLIT . ' where `Index` = :index ;';
                $this->InfoLog('inject_line :: Update entry in table tempo IMPORTED : index=' . $line['Index']  . '   --- ' . $sql );

                if ($this->test == 0) {
                    $this->objdb->exec( $sql, [ ':index' => $line['Index'] ] );
                }

                // On recherche si on retrouve la ligne précise .... 
                $sql = 'select `id_line`, `validation_id`, `voucher_id` from `' . $this->tables_name['lines'] . '` where `info_id` = :id_info and `key_id` = :id_key and `num_account` = :num;';
                $this->objdb->query($sql);
                $this->objdb->execute( [ ':id_info' => $line_entry['info_id'], ':id_key' => $line_entry['key_id'], ':num' => $line['CompteNum'] ] );
                
                $countrow = $this->objdb->rowCount();
                $this->InfoLog('inject_line :: countrow : ' . $countrow );
                
                if ( $countrow == 1 ) {
                    $this->rapport->Add_Ligne_found( print_r($line_info,true) );
                    $id_line = $this->objdb->fetch()['id_line'];
                    $sql = 'update `' . $this->tables_name['lines'] . '` set `import` = :index where id_line = :id_line ;';
                    $this->InfoLog('inject_line :: Update entry : id_line=' . $id_line . '   --- index=' . $line['Index']  . '   --- ' . $sql );
                    
                    $params = [ ':id_line' => $id_line, ':index' => $line['Index'] ];
                    $this->InfoLog('inject_line :: Update entry : params=' . print_r($params,true) );
                    
                    if ($this->test == 0) {
                        $this->objdb->exec( $sql, $params );
                    }

                    $sql = 'update `' . $this->tables_name['tempo'] . '` set `line_id` = :id_line, `imported` = ' . Compta_Imports::STATE_IMPORT_FOUND . ' where `Index` = :index ;';
                    $this->InfoLog('inject_line :: Update entry : id_line=' . $id_line . '   --- index=' . $line['Index']  . '   --- ' . $sql );
                    if ($this->test == 0) {
                        $this->objdb->exec( $sql, $params );
                    }
                } else {
                    $this->rapport->Add_Ligne_conflit( print_r($line_info,true) );
                }
            }
        }
        catch (PDOExection $e)
        {
            $error = $e->getMessage();
            $answer = false;
            $this->erreurs[] = $error;
        }

        $this->InfoLog( PHP_EOL . '********************************************************************************************************' . PHP_EOL );

        return $answer;
    }


    public function add_entree_Info($entry_info):string
    {
        $this->InfoLog( "Entry in add_entree_Info : Ajout de la ligne dans la table infos" );
        $params_info = [
            ':label' => $entry_info['Libelle'],
            ':voucher' => $entry_info['NumVoucher'],
            ':Fournisseur' => $entry_info['Fournisseur'],
            ':Date' => $entry_info['Date'],
            ":Tva" => $entry_info['TVA'],
            ':Charges' => $entry_info['Charges'],
            ':Ttc' => $entry_info['TTC']
        ];
        $sql = "INSERT INTO `" . $this->tables_name['infos'] . "`(`LabelFact`, `NumPiece`, `NameFournisseur`, `DateOpe`, `Tva`, `Charges`, `MontantTTC`) VALUES ( :label, :voucher, :Fournisseur, :Date, :Tva, :Charges, :Ttc );";
        $this->SqlLog($sql,$params_info);
        $answer = $this->objdb->exec($sql, $params_info, true );
        return $answer;
    }


    public function add_entree_Line($entry_line, $id_info):void
    {
        $this->InfoLog( "Entry in add_entree_Line : Ajout de la ligne dans la table lines" );

        $id_key = $this->find_id_key($entry_line['Key']);
        if ( $id_key === (-1) ) {
            throw new CompatibilityException( "Impossible de trouvez la clé comptable. Entry : " . print_r($entry_line,true));
        }
        $params_line = [
            ':key_id' => $id_key,
            ':num' => $entry_line['CompteNum'],
            ':label' => $entry_line['CompteLib'],
            ':info_id' => $id_info,
            ':index' => $entry_line['Index']
        ];
        $sql = "INSERT INTO `" . $this->tables_name['lines'] . "`(`key_id`, `num_account`, `label_account`, `info_id`, `import`) VALUES ( :key_id, :num, :label, :info_id, :index );";
        $this->SqlLog($sql, $params_line);
        $this->objdb->exec( $sql, $params_line, true );
    }

    private function get_lines_lost(): array
    {

        // $sql  = "SELECT id_line, key_id, num_account, label_account, info_id, validation_id, voucher_id, LabelFact, NumPiece, NameFournisseur, DateOpe, tva, charges, MontantTTC, state_id, infos, shortname";
        // $sql .= " FROM `" . $this->tables_name['lines'] . "`";
        // $sql .= ' LEFT JOIN ' . $this->tables_name['infos'] . ' ON ' . $this->tables_name['lines'] . ".`info_id` = " . $this->tables_name['infos'] . '.`id_info`';
        // $sql .= ' LEFT JOIN ' . $this->tables_name['validations'] . ' ON ' . $this->tables_name['lines'] . ".`validation_id` = " . $this->tables_name['validations'] . '.`id_validation`';
        // $sql .= ' INNER JOIN `Compte_Key_type` ON ' . $this->tables_name['lines'] . '.`key_id` = `Compte_Key_type`.`id_key`';
        // $sql .= " WHERE `import` IS NULL";
        $sql  = "SELECT ";
        $sql .= "`Compte_Key_type`.`typekey`, ";
        $sql .= "`" . $this->tables_name['lines'] . "`.`num_account`, ";
        $sql .= "`" . $this->tables_name['lines'] . "`.`label_account`, ";
        $sql .= "`" . $this->tables_name['infos'] . "`.`NameFournisseur`, ";
        $sql .= "`" . $this->tables_name['infos'] . "`.`LabelFact`, ";
        $sql .= "`" . $this->tables_name['infos'] . "`.`NumPiece`, ";
        $sql .= "`" . $this->tables_name['infos'] . "`.`MontantTTC`, ";
        $sql .= "`Compte_Key_Validation`.`namekey`, ";
        $sql .= "`Compta_factures_2024_2025_validations`.`infos` ";
        $sql .= "FROM `" . $this->tables_name['lines'] . "` ";
        $sql .= "INNER JOIN `" . $this->tables_name['infos'] . "` ON `" . $this->tables_name['infos'] . "`.`id_info` = `" . $this->tables_name['lines'] . "`.`info_id` ";
        $sql .= "INNER JOIN `" . $this->tables_name['validations'] . "` ON `" . $this->tables_name['validations'] . "`.`id_validation` = `" . $this->tables_name['lines'] . "`.`validation_id` ";
        $sql .= "INNER JOIN `Compte_Key_type` ON `Compte_Key_type`.`id_key` = `" . $this->tables_name['lines'] . "`.`key_id` ";
        $sql .= "INNER JOIN `Compte_Key_Validation` ON `Compte_Key_Validation`.`id_state` = `" . $this->tables_name['validations'] . "`.`state_id` ";
        $sql .= "WHERE import is null and `" . $this->tables_name['validations'] . "`.`state_id` = 1;";
        $this->SqlLog($sql);
        return $this->objdb->ExecWithFetchAll($sql);
    }

    private function get_lines_not_imported(): array
    {
        # $sql = "SELECT `Index`, `CompteNum`, `CompteLib`, `Key`, `Libelle`, `NumVoucher`, `Fournisseur`, `Date`, `TVA`, `Charges`, `TTC` FROM `" . $this->tables_name['tempo'] . "` where `imported` = " . Compta_Imports::STATE_IMPORT_STEP_3 . " AND `line_id` is null;";
        $sql = "SELECT `Index`, `CompteNum`, `CompteLib`, `Key`, `Libelle`, `NumVoucher`, `Fournisseur`, `TTC` FROM `" . $this->tables_name['tempo'] . "` where `imported` = " . Compta_Imports::STATE_IMPORT_NEW . " AND `line_id` is null;";
        $this->SqlLog($sql);
        return $this->objdb->ExecWithFetchAll($sql);
    }

    private function show_form_lost(): string
    {
        $this->rapport = new Rapport_Import(PATH_HOME_CS . '/logs/', 'Rapport-Import'. "__--__" . date('YmdHis'), false);

        $left = $this->get_lines_not_imported();    // lignes tempo à supprimer (imported = TO_IMPORT)
        $right = $this->get_lines_lost(); // Donne les lignes non affecté à une ligne d'import dans la table lines

        $answer  = "\t\t<h2 class=\"imports-box-title\">Afichage des données non associé</h2>" . PHP_EOL;
        $answer .= "\t\t<form method=\"post\" enctype=\"multipart/form-data\" id=\"form_imports\">" . PHP_EOL;

        // Si vous souhaitez une étape dédiée, mettez un form_num distinct (ex: 11).
        // Sinon, mettez 10 pour rester aligné avec ConflitHandler/import_excel.php.
        $answer .= "\t\t\t<input type=\"hidden\" name=\"form_num\" value=\"2\">" . PHP_EOL;

        // Un seul bloc "conflit" qui permet de relier items gauche/droite
        $answer .= "\t\t\t<div class=\"conflit\" data-id=\"lost\">" . PHP_EOL;

        // Titre / résumé
        $answer .= "\t\t\t\t<div class=\"conflit_titre\">" . PHP_EOL;
        $answer .= "\t\t\t\t\t<span>Nouveaux: " . count($left) . "</span>" . PHP_EOL;
        $answer .= "\t\t\t\t\t<span>À supprimer: " . count($right) . "</span>" . PHP_EOL;
        $answer .= "\t\t\t\t</div>" . PHP_EOL;

        $answer .= "\t\t\t\t<div class=\"zone\">" . PHP_EOL;

        /* -------------------------
        Colonne gauche : nouveaux
        ------------------------- */
        $answer .= "\t\t\t\t\t<div class=\"col gauche\">" . PHP_EOL;

        if (count($left) === 0) {
            $answer .= "\t\t\t\t\t\t<p>Aucun nouvel élément.</p>" . PHP_EOL;
        } else {
            foreach ($left as $row) {
                $answer .= "\t\t\t\t\t\t<div class=\"item\" data-side=\"left\" data-index=\"" . htmlspecialchars($row['Index']) . "\">";
                    $answer .= "<span class=\"tooltip\">" . htmlspecialchars($row['Key']) . "</span>";
                    $answer .= "<span class=\"tooltip\" data-tooltip=\"" . htmlspecialchars($row['CompteLib']) . "\">" . htmlspecialchars($row['CompteLib']) . "</span>";
                    $answer .= "<span class=\"tooltip\" data-tooltip=\"" . htmlspecialchars($row['Fournisseur']) . "\">" . htmlspecialchars($row['Libelle']) . "</span>";
                    $answer .= "<span class=\"tooltip\">" . htmlspecialchars($row['NumVoucher']) . "</span>";
                    $answer .= "<span class=\"tooltip\">" . htmlspecialchars($row['TTC']) . "</span>";
                $answer .= "</div>" . PHP_EOL;
            }
        }

        $answer .= "\t\t\t\t\t</div>" . PHP_EOL;

        // SVG pour les traits
        $answer .= "\t\t\t\t\t<svg></svg>" . PHP_EOL;

        /* -------------------------
        Colonne droite : à supprimer
        ------------------------- */
        $answer .= "\t\t\t\t\t<div class=\"col droite\">" . PHP_EOL;

        if (count($right) === 0) {
            $answer .= "\t\t\t\t\t\t<p>Aucune ligne à supprimer.</p>" . PHP_EOL;
        } else {
            foreach ($right as $row) {
                // Index droite = Index (clé primaire de la table tempo)

                $answer .= "\t\t\t\t\t\t<div class=\"item\" data-side=\"right\" data-index=\"" . htmlspecialchars($row['label_account']) . "\">";
                $answer .= "<span class=\"tooltip\" data-tooltip=\"" . htmlspecialchars($row['namekey']) . "\">" . htmlspecialchars($row['typekey']) . "</span>";
                $answer .= "<span class=\"tooltip\" data-tooltip=\"" . htmlspecialchars($row['num_account']) . "\">" . htmlspecialchars($row['label_account']) . "</span>";
                $answer .= "<span class=\"tooltip\" data-tooltip=\"" . htmlspecialchars($row['NameFournisseur']) . "\">" . htmlspecialchars($row['LabelFact']) . "</span>";
                $answer .= "<span class=\"tooltip\">" . htmlspecialchars($row['NumPiece']) . "</span>";
                $answer .= "<span class=\"tooltip\">" . htmlspecialchars($row['MontantTTC']) . "</span>";
                $answer .= "</div>" . PHP_EOL;
            }
        }

        $answer .= "\t\t\t\t\t</div>" . PHP_EOL;

        $answer .= "\t\t\t\t</div>" . PHP_EOL; // .zone

        // Boutons, mêmes classes que get_element_conflit()
        $answer .= "\t\t\t\t<div class=\"actions\">" . PHP_EOL;
        $answer .= "\t\t\t\t\t<button class=\"valider-btn\">Valider</button>" . PHP_EOL;
        $answer .= "\t\t\t\t\t<button class=\"clear-btn\">Clear</button>" . PHP_EOL;
        $answer .= "\t\t\t\t</div>" . PHP_EOL;

        $answer .= "\t\t\t</div>" . PHP_EOL; // .conflit

        // Bouton continuer (submit global si vous avez une étape suivante)
        $answer .= "\t\t\t<input style=\"float: right;\" type=\"submit\" value=\"Continuer\" name=\"submit\"/>" . PHP_EOL;
        $answer .= "\t\t\t<input type=\"hidden\" name=\"step\" value=\"9\">" . PHP_EOL;
        $answer .= "\t\t</form>" . PHP_EOL;

        return $answer;
    }

    public function Create_All_Table_For_Import($periode)
    {

        $this->tables_name['keys'] = $this->getNameTableKeys();



        // Create Table info entry

        $this->tables_name['infos'] = $this->getNameTableInfos($periode);

        try

        {

            $sql = "CREATE TABLE IF NOT EXISTS `csresip1501`.`" . $this->tables_name['infos'] . "` (

                `id_info` int UNSIGNED NOT NULL AUTO_INCREMENT,

                `LabelFact` TINYTEXT NOT NULL DEFAULT '',

                `NumPiece` TINYTEXT NOT NULL DEFAULT '' COMMENT 'N° pièce comptable',

                `NameFournisseur` TINYTEXT NOT NULL DEFAULT '',

                `DateOpe` VARCHAR(10) NOT NULL DEFAULT '',

                `Tva` VARCHAR(12) NOT NULL DEFAULT '',

                `Charges` VARCHAR(12) NOT NULL DEFAULT '',

                `MontantTTC` VARCHAR(12) NOT NULL DEFAULT '',

                PRIMARY KEY (id_info)

            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;";

            $this->objdb->exec($sql);

        }

        catch (PDOExection $e)

        {

            $this->error = $e->getMessage();

            return false;

        }

        

        // Create Table entry

        $this->tables_name['lines'] = $this->getNameTableLines($periode);

        try

        {

            $sql = "CREATE TABLE IF NOT EXISTS `csresip1501`.`" . $this->tables_name['lines'] . "` (`id_line` int UNSIGNED NOT NULL AUTO_INCREMENT, `key_id` int UNSIGNED NOT NULL, `num_account` varchar(10) NOT NULL, `label_account` varchar(90) NOT NULL, `info_id` int UNSIGNED NOT NULL, `validation_id` int UNSIGNED DEFAULT NULL, `voucher_id` int UNSIGNED DEFAULT NULL, `import` VARCHAR(8) DEFAULT NULL, PRIMARY KEY (id_line) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;";

            $this->objdb->exec($sql);

            $this->objdb->exec("update `csresip1501`.`" . $this->tables_name['lines'] . "` set `import` = NULL;");

        }

        catch (PDOExection $e)

        {

            $this->error = $e->getMessage();

            return false;

        }



        // Create Table vaucher entry

        $this->tables_name['vouchers'] = "Compta_factures_" . $periode . "_vouchers";

        try
        {

            $sql = "CREATE TABLE IF NOT EXISTS `csresip1501`.`" . $this->tables_name['vouchers'] . "` (`id_voucher` int UNSIGNED NOT NULL AUTO_INCREMENT, `nom` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL, `url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL, PRIMARY KEY (id_voucher) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;";

            $this->objdb->exec($sql);

        }
        catch (PDOExection $e)
        {

            $this->error = $e->getMessage();

            return false;
        }



        // Create Table validation entry

        $this->tables_name['validations'] = "Compta_factures_" . $periode . "_validations";

        try

        {

            $sql = "CREATE TABLE IF NOT EXISTS `csresip1501`.`" . $this->tables_name['validations'] . "` (`id_validation` int UNSIGNED NOT NULL AUTO_INCREMENT, `state_id` tinyint UNSIGNED NOT NULL, `infos` json NOT NULL, `commentaire` text NOT NULL, PRIMARY KEY (id_validation)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;";

            $this->objdb->exec($sql);

        }

        catch (PDOExection $e)

        {

            $this->error = $e->getMessage();

            return false;

        }



        return true;

    }


    public function format_montant_comptable($val):string {

        $val = trim((string)$val);

        $val = str_replace([' ', '€'], ['', ''], $val);

        if (is_numeric($val))
        {
            $this->InfoLog('format_montant_comptable :: is_numeric : ');
            if (strpos($val, '.') !== false)
            {
                // Séparer partie entière et décimale
                $parts = explode('.', $val);
                $entiere = $parts[0];
                $decimale = $parts[1];

                // Forcer 2 décimales
                if (strlen($decimale) == 1) {
                    $decimale = $decimale . '0';
                    }
                elseif (strlen($decimale) == 3) {
                    $entiere = $entiere + $decimale;
                    $decimale = '00';
                }

                $formatted = $entiere . ',' . $decimale;
            }
            else {
                // Ajouter ',00'
                $formatted = $val . ',00';
            } 
        } else {
            $this->InfoLog('format_montant_comptable :: is not numeric : ');
            
            // Cas 1 : x,yy (ex: 0,00 ou 2471,00)
            if (preg_match('/^(\d+),(\d{2})$/', $val, $matches)) {
                $formatted = $matches[1] . ',' . $matches[2];
            }
            // Cas 2 : x,yyy (ex: 2,471 - virgule = milliers, pas de décimales)
            elseif (preg_match('/^(\d+),(\d{3})$/', $val, $matches)) {
                $formatted = $matches[1] . $matches[2] . ',00';
            }
            // Cas 3 et 4 regroupés : x[.,]yyy[.,]zz (ex: 2,471.00 ou 2.471,00)
            elseif (preg_match('/^(\d+)[\.,](\d{3})[\.,](\d{2})$/', $val, $matches)) {
                $formatted = $matches[1] . $matches[2] . ',' . $matches[3];
            } 
            else {
                // Format non reconnu
                $formatted = $val;
            }
        }

        return $formatted;
    }

    public function Update_Years_table(string $periode, string $tablename)
    {
        $row = $this->objdb->execonerow( 'select * from `Compta_years` where `periode` = :periode' , [ ':periode' => $periode ]);
        if ( empty($row) )
        {
            $this->objdb->exec( 'insert into `Compta_years` ( `periode`, `table_name`, `state_compte` ) values ( :periode, :jsoninfo , :count );' , [ ':periode' => $periode, ':jsoninfo' => json_encode( [ $tablename ] ), ':count' => 1 ] );
        }
        else
        {
            $count = intval( $row['state_compte'] ) + 1; 
            $jsonarray = json_decode( $row['table_name'] );
            $jsonarray[] = $tablename;
            $this->objdb->exec( 'update `Compta_years` set `table_name` = :jsoninfo , `state_compte` = :count where `periode` = :periode ;' , [ ':periode' => $periode, ':jsoninfo' => json_encode($jsonarray), ':count' => $count ] );
        }
    }

    public function move_ligne($entry_line)
    {
        $this->InfoLog('Entry in move Line');
        
        
        $this->InfoLog('Lecture informations validation');
        $params_get_info_validation = [
            ':id_line' => $entry_line['line_id'],
        ];
        $sql_select_info_validation = "SELECT validation_id, state_id, infos ";
        $sql_select_info_validation .= "FROM `" . $this->tables_name['lines'] . "` ";
        $sql_select_info_validation .= "INNER JOIN `" . $this->tables_name['validations'] . "` ON `" . $this->tables_name['validations'] . "`.`id_validation` = `" . $this->tables_name['lines'] . "`.`validation_id` ";
        $sql_select_info_validation .= "WHERE `id_line` = :id_line;";
        
        $this->SqlLog($sql_select_info_validation, $params_get_info_validation);
        $info_validation =  $this->objdb->execonerow($sql_select_info_validation, $params_get_info_validation);
        $this->InfoLog('resultat de la recherche des informations de validation : ' . print_r($info_validation,true) );
        if (empty($info_validation)) {
            throw new CompatibilityException("Aucune validation trouvée");
        }
        // if (!isset($info_validation[0]['state_id'])) {
        //     throw new CompatibilityException("Clé 'state_id' manquante dans les données de validation");
        // }

        $this->InfoLog('Update table validation');
        $obj_json = new stdClass();
        $obj_json->cause = 'Modifiez par l\'import. l\'ancienne valeur était :: ' . $this->get_validation_label($info_validation['state_id'])  . ' : '  . $info_validation['infos'];
        
        $id_state = $this->get_id_validation_with_key(Compta::STATE_VALIDATION_AV);
        if ( $id_state === (-1) ) {
            throw new CompatibilityException( "Impossible de trouvez l'id de la clé de validation. Entry : " . Compta::STATE_VALIDATION_AV );
        }
        $params_validation = [
            ':validation_id' => $info_validation['validation_id'],
            ':id_state' => $id_state,
            ':infos' => json_encode($obj_json)
        ];
        $sql_update_validations = "UPDATE " . $this->tables_name['validations'] . " 
                                        SET `state_id` = :id_state, `infos` = :infos
                                        WHERE `id_validation` = :validation_id;";
        $this->SqlLog($sql_update_validations, $params_validation);
        $this->objdb->exec($sql_update_validations, $params_validation);

        $this->InfoLog('Update table ligne');
        $id_key = $this->find_id_key($entry_line['Key']);
        if ( $id_key === (-1) ) {
            throw new CompatibilityException( "Impossible de trouvez la clé comptable. Entry : " . print_r($entry_line,true));
        }
        $params_line = [
            ':index' => $entry_line['Index'],
            ':id_line' => $entry_line['line_id'],
            ':id_key' => $id_key,
            ':num_account' => $entry_line['CompteNum'],
            ':label_account' => $entry_line['CompteLib']
        ];
        $sql_update_lines = "UPDATE `" . $this->tables_name['lines'] . "` 
            SET `import` = :index, `key_id` = :id_key, `num_account` = :num_account, `label_account` = :label_account
            WHERE `id_line` = :id_line;";
        $this->SqlLog($sql_update_lines, $params_line);
        $this->objdb->exec($sql_update_lines, $params_line);
    }

    public function delete_ligne()
    {
    }

    public function count_new_ligne()
    {
    }

    private function search_id_in_info($criteria, $mode_import) : array
    {
        $this->InfoLog('search_line_in_tempo_for_comp :: Entry' );
        $solodb = new Database($this->objdb);
        $answer = [];

        switch ( $mode_import ) {
            case Compta_Imports::MODE_SEARCH_S:
                $params = $criteria;
                $sql = "SELECT `id_info` FROM `" . $this->tables_name['infos'] . "` where LabelFact = :label and NumPiece = :voucher and NameFournisseur = :Fournisseur and DateOpe = :Date and  Tva = :Tva and  Charges = :Charges and  MontantTTC = :Ttc;";
                break;
            case Compta_Imports::MODE_SEARCH_M:
                $params = [
                    ':label' => $criteria[':label'],
                    ':voucher' => $criteria[':voucher'],
                    ':Fournisseur' => $criteria[':Fournisseur'],
                    ':Ttc' => $criteria[':Ttc']
                ];
                $sql = "SELECT `id_info` FROM `" . $this->tables_name['infos'] . "` ";
                $sql .= "INNER JOIN `" . $this->tables_name['lines'] . "` ON `id_info` = `info_id` ";
                $sql .= "WHERE `import` is null and LabelFact = :label and NumPiece = :voucher and NameFournisseur = :Fournisseur and MontantTTC = :Ttc;";
                break;
            case Compta_Imports::MODE_SEARCH_L:
                $params = [
                    ':voucher' => $criteria[':voucher'],
                    ':Ttc' => $criteria[':Ttc']
                ];
                $sql = "SELECT `id_info` FROM `" . $this->tables_name['infos'] . "` ";
                $sql .= "INNER JOIN `" . $this->tables_name['lines'] . "` ON `id_info` = `info_id` ";
                $sql .= "WHERE `import` is null and NumPiece = :voucher and MontantTTC = :Ttc;";
                break;
            case Compta_Imports::MODE_SEARCH_X:
                $params = [
                    ':Ttc' => $criteria[':Ttc']
                ];
                $sql = "SELECT `id_info` FROM `" . $this->tables_name['infos'] . "` ";
                $sql .= "INNER JOIN `" . $this->tables_name['lines'] . "` ON `id_info` = `info_id` ";
                $sql .= "WHERE `import` is null and MontantTTC = :Ttc;";
                break;
        }
        $this->SqlLog($sql,$params);
        $solodb->query($sql);
        $solodb->execute($params);

        $countrow = $solodb->rowCount();
        $this->InfoLog('search_id_in_info :: countrow : ' . $countrow );

        if ( $countrow > 0 ) {
            foreach ( $solodb->fetchall() AS $row_db ) {
                $answer[] = (int)$row_db['id_info'];
            }
        }

        return $answer;
    }

    private function search_line_in_tempo_for_comp($criteria, $mode_import) : array
    {
        $this->InfoLog('search_line_in_tempo_for_comp :: Entry' );

        // switch ( $mode_import ) {
        //     case Compta_Imports::MODE_SEARCH_S:
        //         $params = $criteria;
        //         $sql_crireria = '`Libelle` = :label and `NumVoucher` = :voucher and `Fournisseur` = :Fournisseur and `Date` = :Date and `TVA` = :Tva and `Charges` = :Charges and `TTC` = :Ttc and `imported` = ' . Compta_Imports::STATE_IMPORT_CONFLIT . ' and `line_id` is null';
        //         break;
        //     case Compta_Imports::MODE_SEARCH_M:
        //         $params = [
        //             ':label' => $criteria[':label'],
        //             ':voucher' => $criteria[':voucher'],
        //             ':Fournisseur' => $criteria[':Fournisseur'],
        //             ':Ttc' => $criteria[':Ttc']
        //         ];
        //         $sql_crireria = '`Libelle` = :label and `NumVoucher` = :voucher and `Fournisseur` = :Fournisseur and `TTC` = :Ttc and `imported` = ' . Compta_Imports::STATE_IMPORT_CONFLIT . ' and `line_id` is null';
        //         break;
        //     case Compta_Imports::MODE_SEARCH_L:
        //         $params = [
        //             ':voucher' => $criteria[':voucher'],
        //             ':Ttc' => $criteria[':Ttc']
        //         ];
        //         $sql_crireria = '`NumVoucher` = :voucher and `TTC` = :Ttc and `imported` = ' . Compta_Imports::STATE_IMPORT_CONFLIT . ' and `line_id` is null';
        //         break;
        //     case Compta_Imports::MODE_SEARCH_X:
        //         $params = [
        //             ':Ttc' => $criteria[':Ttc']
        //         ];
        //         $sql_crireria = '`TTC` = :Ttc and `imported` = ' . Compta_Imports::STATE_IMPORT_CONFLIT . ' and `line_id` is null';
        //         break;
        //   }
        
        $params = [ ':index' => $criteria[':index'] ];
        $tb_tempo = '`' . $this->tables_name['tempo'] . '`';
        $sql =  'SELECT ' . $tb_tempo . '.*, `Compte_Key_type`.`shortname`, `Compte_Key_type`.`id_key`';
        $sql .= ' FROM ';
        $sql .= $tb_tempo . ', ';
        $sql .= '`Compte_Key_type`';
        $sql .= ' WHERE '; 
        $sql .= '`Index` = :index';
        $sql .= ' AND ' . $tb_tempo . '.`Key` = `Compte_Key_type`.`typekey`';
        $sql .= ";"; 
        $this->SqlLog($sql,$params);
        return $this->objdb->ExecWithFetchAll($sql, $params);
    }

    private function search_line_in_lines_for_comp($criteria, $mode_import) : array
    {
        $this->InfoLog('search_line_in_lines_for_comp :: Entry' );
        
        // récupération de l'id dans info qui coresponds à notre ligne à importer.
        $index_id_info = $this->search_id_in_info( $criteria, $mode_import);
        if (empty($index_id_info)) {
            $this->InfoLog('search_id_in_info :: ERREUR IMPOSSIBLE : ' . print_r($criteria,true) );
            $this->rapport->Add_Ligne_error(print_r($criteria,true));
            throw new CompatibilityException("Détection d'une nouvelle ligne dans le cadre de la gestion des lignes déplacé ou en double. Incohérence non traité. Effacez l'import et recommencez.");
        }

        // $sql = "SELECT * FROM `" . $this->tables_name['lines'] . "` WHERE `info_id` = :info_id and `import` is null;";
        $tb_lines = '`' . $this->tables_name['lines'] . '`';
        $this->tables_name['validations'] = '`' . $this->getNameTableValidations($this->Periode) . '`';
        $sql =  'SELECT ';
        $sql .= $tb_lines . '.`id_line`, ';
        $sql .= $tb_lines . '.`num_account`, ';
        $sql .= $tb_lines . '.`label_account`, ';
        $sql .= '`Compte_Key_type`.`shortname`, ';
        $sql .= '`Compte_Key_type`.`typekey`, ';
        $sql .= $tb_lines . '.`validation_id`, ';  // Permet la mise à jour de la table validation si ligne déplacé
        $sql .= $this->tables_name['validations'] . '.`state_id`, ';
        $sql .= $this->tables_name['validations'] . '.`infos` ';
        $sql .= 'FROM ' . $tb_lines . " ";
        $sql .= 'LEFT JOIN ' . $this->tables_name['validations'] . ' ON ' . $tb_lines . ".`validation_id` = " . $this->tables_name['validations'] . '.`id_validation` ';
        $sql .= 'INNER JOIN `Compte_Key_type` ON ' . $tb_lines . '.`key_id` = `Compte_Key_type`.`id_key` ';
        $sql .= 'WHERE '; 
        $sql .= '`info_id` in (' . implode(',', $index_id_info) . ')';
        $sql .= 'AND `import` IS NULL ';
        $sql .= ";"; 
        $this->SqlLog($sql);
        return $this->objdb->ExecWithFetchAll($sql);
    }


    /* -----------------------------------------------------------------------------------
    # Function log
    ------------------------------------------------------------------------------------*/ 
    private function InfoLog($message): void
    {
        if ( $this->log === false ) return;
        $this->write_info($message);
    }
    
    private function SqlLog($sql, $params = []): void
    {
        if ( $this->log === false ) return;
        $this->write_sql($sql, $params);
    }
   
    private function ErrorLog(string $message): void
    {
        if ($this->log === false) return;
        $this->write_error($message);
    }

    private function DataLog($description, $data): void
    {
        if ( $this->log === false ) return;
        $this->write_data($description, $data);
    }
    
    private function StepLog($step_name): void
    {
        if ( $this->log === false ) return;
        $this->write_step($step_name);
    }

}	


class Syncho_import
{
    use Logs;

    private $objdb;
    private bool $log;
    private $line_periode;

    private const POS_PERIODE = 0;

    public function __construct(Database $refdb, bool $trace = false)
    {
        $this->objdb = $refdb;
        $this->log = $trace;
        if ( $trace ) { $this->PrepareLog('Syncho_import','d'); }
    }  

    public function resetImport():bool
    {
        if ( $this->objdb->exec( "update `Compta_years` set `state_compte` = 0 where `periode` = :periode;", [ ':periode' => $this->get_periode() ] ) > 0 )
        {
            return true;
        } else {
            return false;
        }
    }

    public function import_exits():int
    {
        $this->InfoLog('Entry in import_exits');

        $sql = "SELECT * FROM `Compta_years` WHERE state_compte != 0;";
        $this->objdb->query($sql);
        $this->objdb->execute();
        $count_line = $this->objdb->rowCount();
        $this->InfoLog('count : ' . $count_line );

        if ( $count_line > 1 ) throw new Exception('Détection de plusieur import simultané. Action interdite pour le moment ...');
        if ( $count_line > 0 ) $this->line_periode = $this->objdb->fetch();

        $this->InfoLog('periode : ' . print_r($this->line_periode, true) );
        $this->set_session( self::POS_PERIODE);

        if ( $count_line == 0 ) return 0;
        return $this->get_state();

    }

    public function get_periode():string
    {
        return $this->line_periode['periode'];
    }

    private function get_state():int
    {
        return intval( $this->line_periode['state_compte'] );
    }

    // public function inc_Step()
    // {
    //     $this->objdb->exec( 'update `Compta_years` set `state_compte` = :count where `periode` = :periode ;' , [ ':periode' => $this->get_periode(), ':count' => ($this->get_state() + 1) ] );
    // }

    public function get_table_tempo():string
    {

        $array_json = json_decode($this->line_periode['table_name']);

        return $array_json[count($array_json) - 1];

    }

    private function set_session(?int $pos)
    {
        // $this->InfoLog('Entry in set_session');
        if ( $pos === null || !isset( $_SESSION['syncho_import'] )) {
            $_SESSION['syncho_import'] = [ $this->line_periode ];
        } else {
            switch( $pos ) 
            {
                case self::POS_PERIODE:
                {
                    $_SESSION['syncho_import'][self::POS_PERIODE] = $this->line_periode;
                    break;
                }
            }
        }
    }

    public function init_session()
    {
        $this->InfoLog('Entry in init_session');

        if ( isset( $_SESSION['syncho_import'] ) ) {
            $this->InfoLog('SESSION is set');
            $this->InfoLog('_SESSION = ' . print_r($_SESSION['syncho_import'],true) );
            $this->line_periode = $_SESSION['syncho_import'][self::POS_PERIODE];
            $this->InfoLog('Set line_periode = ' . print_r($this->line_periode,true) );
        } else {
            $this->InfoLog('Call import_exits');
            $this->import_exits();
        }

    }

    private function free_session()
    {
        unset($_SESSION['syncho_import']);
    }

    /* -----------------------------------------------------------------------------------
    # Function log
    ------------------------------------------------------------------------------------*/ 
    private function InfoLog($message): void
    {
        if ( $this->log === false ) return;

        $this->write_info($message);
    }
}

?>