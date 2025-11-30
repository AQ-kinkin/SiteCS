<?php

    $pathHome = '/home/csresip/www';

	require_once( $pathHome . '/objets/compta.php');



use PhpOffice\PhpSpreadsheet\IOFactory;



// /* -----------------------------------------------------------------------------------

// # Calss de gedtion des log

// ------------------------------------------------------------------------------------*/

// trait Logs {
//     private $logsPath;

//     protected function PrepareLog(string $identifiant): void {
//         $this->logsPath = '/home/csresip/www/logs/' . $identifiant . "_ " . date('YmdH');

//         // Créer le dossier s’il n'existe pas
//         $dossier = dirname($this->logsPath);
//         if (!is_dir($dossier)) {
//             mkdir($dossier, 0775, true);
//         }
//     }

//     protected function write_info($message): void
//     {
//         // Format du message (date + contenu)
//         $date = date('Y-m-d H:i:s');
//         $ligne = "[$date] [Info] $message\n";

//         // Écriture dans le fichier (append)
//         file_put_contents($this->logsPath, $ligne, FILE_APPEND | LOCK_EX);
//     }
// }


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
    //private $logsPath;
    private $obj_sync_import;
    private bool $log;

    public function __construct(Database $refdb, bool $trace = false)
    {
        $this->objdb = $refdb;
        $this->log = $trace;
        if ( $trace ) { 
            $this->PrepareLog('Import', 'd'); // Log par jour (Ymd)
        }
        $this->obj_sync_import = new Syncho_import($this->objdb,true);
    }  

    public function ShowForm(int $step): string
    {
        $this->InfoLog("ShowForm Entry with step : " . $step );
        $this->InfoLog("ShowForm formulaire : " . print_r($_POST,true) );

        if ( $step == 0 ) {
            $en_cours = $this->obj_sync_import->import_exits();
            if ( $en_cours != 0 ) {
                return $this->show_form_resume();
            } else {
                return $this->show_form_start();
            }
        }

        if ( $step == 255 ) {
            return $this->clear_import();
        }

        $this->obj_sync_import->init_session();
        $this->Periode = $this->obj_sync_import->get_periode();
        $this->tables_name['lines'] = $this->getNameTableLines($this->Periode);
        $this->tables_name['infos'] = $this->getNameTableInfos($this->Periode);
        $this->tables_name['tempo'] = $this->obj_sync_import->get_table_tempo();
        switch ( $step ) {
            case 1:
                if ( isset( $_POST['answer'] ) && $_POST['answer'] === 'oui' ) {
                    return $this->show_form_moved();
                } else {
                    return $this->clear_import();
                }
            case 2:
                return $this->show_form_deleted();
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
        $answer .= "\t\t\t<input type=\"hidden\" name=\"form_num\" value=\"0\">" . PHP_EOL;
        $answer .= "\t\t\t<span><label>Select la période :</label><select name=\"periode\" id=\"periode\">" . PHP_EOL;

        while( $row = $this->objdb->fetch() )
        {
            $annee = (int)explode("_", $row['periode'])[1];
            if ( $annee > $this->last_annee ) $this->last_annee = $annee;
            $answer .= "\t\t<option value=\"" . $row['periode'] . '" ' . (($annee == $annee_en_cours)?'selected="selected">':'>') . $row['periode'] . "</option>" . PHP_EOL;
        }

        $new_periode = $this->last_annee . "_" . $this->last_annee+1;
        $answer .= "\t\t<option value=\"" . $new_periode . '">' . $new_periode . "</option>" . PHP_EOL;
        $answer .= "\t\t</select></span>" . PHP_EOL;
        $answer .= "\t\t\t<p><span>Select an Excel file to upload for accounting import :</span><br/><input type=\"file\" name=\"fileToUpload\" id=\"fileToUpload\"/></p>" . PHP_EOL;
        $answer .= "\t\t\t<span>&nbsp;</span><input style=\"float: right;\" type=\"submit\" value=\"Import Excel File\" name=\"submit\"/>" . PHP_EOL; // Attention : Submit intercepté par js (imports.js)
        $answer .= "\t\t</form>" . PHP_EOL;

        return $answer;
    }

    private function show_form_moved(): string
    {

        $this->StepLog("AFFICHAGE FORMULAIRE CONFLITS - Période " . $this->Periode);

        

        // return "\t\t<h2 class=\"imports-box-title\">" . $this->Periode . "</h2>" . PHP_EOL;

        $en_cours = $this->obj_sync_import->import_exits();



        try { $conflits = $this->get_clonflits_compte(); }

        catch  (ComtabilityException $e) {

            $answer = "\t\t<h2 class=\"imports-box-title\">Gestion des conflit d'import</h2>" . PHP_EOL;

            $answer .= $this->get_error_mess( get_class($e) . ' : ' . $e->getMessage() );

            return $answer; 

        }

        catch (Exception $e) {

            $answer = "\t\t<h2 class=\"imports-box-title\">Gestion des conflit d'import</h2>" . PHP_EOL;

            $answer .= $this->get_error_mess( get_class($e) . ' : ' . $e->getMessage() );

            return $answer;

        }

        
        // Résolution automatique des conflits avec boucle de relecture
        $conflits = $this->resolve_conflicts_auto($conflits);
        
        // Affichage des conflits restants pour résolution manuelle
        return $this->display_manual_conflicts($conflits);
    }

    /**
     * Résout automatiquement les conflits détectables (1-to-1, x-to-0, 0-to-x)
     * Boucle jusqu'à ce qu'il n'y ait plus de résolution automatique possible
     * 
     * @param array $conflits Liste des conflits détectés
     * @return array Conflits restants nécessitant une résolution manuelle
     */
    private function resolve_conflicts_auto(array $conflits): array
    {
        $total_auto_resolved = 0;
        
        do {
            $has_auto_resolved = false;
            
            foreach ($conflits as $conflit) {
                // Recherche des éléments gauche et droite pour ce conflit
                $elements_gauche = $this->search_line_in_tempo_for_comp($conflit);
                $elements_droite = $this->search_line_in_lines_for_comp($conflit);
                
                $count_gauche = count($elements_gauche);
                $count_droite = count($elements_droite);
                
                $this->InfoLog("✓ Counters:: Right : " . $count_droite . ", Left : " . $count_gauche . " (" . $conflit[':label'] . ")");

                // Gestion des différents cas de résolution automatique
                if ($count_gauche == 1 && $count_droite == 1) {
                    // CAS 1-to-1 : Association automatique
                    $left = $elements_gauche[0];
                    $right = $elements_droite[0];
                    
                    // Mise à jour table tempo : line_id
                    $sql_update_tempo = "UPDATE `" . $this->tables_name['tempo'] . "` 
                                        SET `line_id` = :id_line 
                                        WHERE `Index` = :index;";
                    $params_tempo = [':id_line' => $right['id_line'], ':index' => $left['Index']];
                    $this->objdb->exec($sql_update_tempo, $params_tempo);
                    
                    // Mise à jour table lines : import
                    $sql_update_lines = "UPDATE `" . $this->tables_name['lines'] . "` 
                                        SET `import` = :index 
                                        WHERE `id_line` = :id_line;";
                    $params_lines = [':index' => $left['Index'], ':id_line' => $right['id_line']];
                    $this->objdb->exec($sql_update_lines, $params_lines);
                    
                    $this->InfoLog("✓ Association automatique 1-to-1 : Index " . $left['Index'] . 
                                " → id_line " . $right['id_line'] . 
                                " (" . $conflit[':label'] . ")");
                    
                    $has_auto_resolved = true;
                    $total_auto_resolved++;
                    break; // Sortir et relire les conflits
                }
                elseif ($count_gauche > 0 && $count_droite == 0) {
                    // CAS x-to-0 : Nouvelles lignes à créer (split de ligne ou nouvelle facture)
                    // TODO: Implémenter la création automatique des lignes
                    // Pour chaque ligne LEFT dans $elements_gauche :
                    //   1. Récupérer les données de la ligne (key_id, num_account, label_account)
                    //   2. Récupérer l'info_id correspondant (via search_id_in_info avec $conflit)
                    //   3. Appeler add_entree_Line() pour créer la ligne dans _lines
                    //   4. Mettre à jour line_id dans la table tempo
                    
                    $this->InfoLog("⚠ TODO: Création automatique nécessaire (x-to-0) : " . 
                                $count_gauche . " ligne(s) à créer (" . $conflit[':label'] . ")");
                    
                    // Temporairement, ne rien faire (pas de résolution auto)
                    // Ces conflits seront affichés pour résolution manuelle
                }
                elseif ($count_gauche == 0 && $count_droite > 0) {
                    // CAS 0-to-x : Aucune ligne LEFT, mais lignes RIGHT existent
                    // Normalement impossible car on part des lignes LEFT (table tempo)
                    // Si ça arrive : supprimer les lignes RIGHT (facture supprimée de l'import)
                    
                    $this->InfoLog("⚠ CAS ANORMAL (0-to-" . $count_droite . ") : Suppression des lignes RIGHT (" . 
                                $conflit[':label'] . ")");
                    
                    foreach ($elements_droite as $right) {
                        $sql_delete = "DELETE FROM `" . $this->tables_name['lines'] . "` 
                                      WHERE `id_line` = :id_line;";
                        $this->objdb->exec($sql_delete, [':id_line' => $right['id_line']]);
                        $this->InfoLog("✓ Ligne supprimée : id_line " . $right['id_line']);
                    }
                    
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
                    $this->InfoLog("Erreur lors de la relecture des conflits : " . $e->getMessage());
                    break;
                }
            }
            
        } while ($has_auto_resolved);
        
        if ($total_auto_resolved > 0) {
            $this->InfoLog("Résolutions automatiques totales : " . $total_auto_resolved);
        }
        
        return $conflits;
    }

    /**
     * Affiche le formulaire pour la résolution manuelle des conflits
     * 
     * @param array $conflits Liste des conflits à afficher
     * @return string HTML du formulaire
     */
    private function display_manual_conflicts(array $conflits): string
    {
        $answer = "\t\t<h2 class=\"imports-box-title\">Gestion des conflit d'import</h2>" . PHP_EOL;

        $answer .= "\t\t<form method=\"post\" enctype=\"multipart/form-data\" id=\"form_imports\">" . PHP_EOL;

        $count_manual_conflicts = 0;
        
        foreach ($conflits as $conflit) {
            // Afficher uniquement les conflits nécessitant une résolution manuelle
            $answer .= $this->get_element_conflit($conflit) . PHP_EOL;
            $count_manual_conflicts++;
        }
        
        if ($count_manual_conflicts > 0) {
            $this->InfoLog("Conflits manuels à résoudre : " . $count_manual_conflicts);
        } else {
            $this->InfoLog("✓ Aucun conflit manuel à résoudre");
        }

        

        $answer .= "\t\t\t<input style=\"float: right;\" type=\"submit\" value=\"Continuer\" name=\"submit\"/>" . PHP_EOL; // Attention : Submit intercepté par js (imports.js)

        $answer .= "\t\t\t<input type=\"hidden\" name=\"step\" value=\"255\">" . PHP_EOL;

        $answer .= "\t\t</form>" . PHP_EOL;

        

        // $this->InfoLog("show_form_moved formulaire : " . print_r($answer,true) );

        

        return $answer;

    }



    private function get_element_conflit($conflit): string

    {

        $answer = "<div class=\"conflit\">" . PHP_EOL;

        

        // $this->InfoLog("get_element_conflit ::  conflit : " . print_r($conflit,true) );



        $answer .= "<div class=\"conflit_titre\">" . PHP_EOL;

        $answer .= "<span>" . $conflit[':label'] . "</span><span>" . $conflit[':Fournisseur'] . "</span><span>" . $conflit[':Date'] . "</span><span>" . $conflit[':Ttc'] . "€</span>" . PHP_EOL;

        $answer .= "</div>" . PHP_EOL;



         $answer .= "<div class=\"zone\">" . PHP_EOL;

            

        $answer .= "<div class=\"col gauche\">" . PHP_EOL;

            $answer .= $this->get_items_conflit($conflit, self::COL_GAUCHE);

            $answer .= "</div>" . PHP_EOL;

            

            $answer .= "<svg></svg>" . PHP_EOL;



            $answer .= "<div class=\"col droite\">" . PHP_EOL;

            $answer .= $this->get_items_conflit($conflit, self::COL_DROITE);

            $answer .= "</div>" . PHP_EOL;



        $answer .= "</div>" . PHP_EOL;

        

        $answer .= "<div class=\"actions\">" . PHP_EOL;

        $answer .= "<button class=\"valider-btn\">Valider</button>" . PHP_EOL;

        $answer .= "<button class=\"clear-btn\">Clear</button>" . PHP_EOL;

        $answer .= "</div>" . PHP_EOL;

        

        $answer .= "</div>" . PHP_EOL;



        return $answer;

    }



    private function get_items_conflit($criteria, int $col): string

    {

        $answer = "";



        if ( $col == self::COL_GAUCHE )

        {

            // selection des lignes problématiques dans la table temporraire

            $data = $this->search_line_in_tempo_for_comp( $criteria ); 

            // $this->InfoLog("get_items_conflit :: infos left : " . print_r($data,true) );

            foreach ($data as $infos) {

                $this->InfoLog("get_items_conflit :: infos left : " . print_r($infos,true) );

                $answer .= "<div class=\"item\" data-side=\"left\" data-index=\"" . $infos['Index'] . "\"><span class=\"tooltip\" data-tooltip=\"" . $infos['shortname'] . "\">" . $infos['Key'] . "</span><span class=\"tooltip\" data-tooltip=\"" . $infos['CompteLib'] . "\">" . $infos['CompteNum'] . "</span></div>";

            }

        }

        else

        {

            // selection des lignes problématiques dans la table temporraire

            // $this->InfoLog("get_items_conflit :: table : " . $this->tables_name['lines'] );

            $data = $this->search_line_in_lines_for_comp( $criteria ); 

            // $this->InfoLog("get_items_conflit :: infos rihgt : " . print_r($data,true) );

            foreach ($data as $infos) {

                // $this->InfoLog("get_items_conflit :: infos right : " . print_r($infos,true) );

                $answer .= "<div class=\"item\" data-side=\"right\" data-index=\"" . $infos['id_line'] . "\"><span class=\"tooltip\" data-tooltip=\"" . $infos['shortname'] . "\">" . $infos['typekey'] . "</span><span class=\"tooltip\" data-tooltip=\"" . $infos['label_account'] . "\">" . $infos['num_account'] . "</span></div>";

            }

        }





        return $answer;

    }



    private function show_form_resume(): string

    {

        $this->InfoLog("show_form_resume Entry .." );



        $answer = "<form method=\"post\" enctype=\"multipart/form-data\" id=\"form_imports\">" . PHP_EOL;

        // $answer .= "<fieldset>";

        // $answer .= "<legend>Détection d'un import en cours. Que voulez-vous faire ?</legend>";



        // $answer .= "<div>";

        // $answer .= "<input type=\"radio\" id=\"yes\" name=\"answer\" value=\"oui\" checked />";

        // $answer .= "<label for=\"yes\">Oui</label>";

        // $answer .= "</div>";



        // $answer .= "<div>";

        // $answer .= "<input type=\"radio\" id=\"no\" name=\"answer\" value=\"non\" />";

        // $answer .= "<label for=\"yes\">Non</label>";

        // $answer .= "</div>";



        // $answer .= "</fieldset>";

        $answer .= "<input type=\"hidden\" name=\"form_num\" value=\"1\">" . PHP_EOL;

        $answer .= "\t\t\t<p><span>Voulez vous continuer l'import des données ?</span><span>&nbsp;&nbsp;&nbsp;&nbsp;</span><input type=\"radio\" name=\"answer\" id=\"oui\"/ value=\"oui\" checked><label for=\"oui\">Oui</label><span>&nbsp;&nbsp;&nbsp;</span><input type=\"radio\" name=\"answer\" id=\"non\"/ value=\"non\"><label for=\"non\">Non</label></p>" . PHP_EOL;

        $answer .= "<input style=\"float: right;\" type=\"submit\" value=\"continue\" name=\"submit\"/>" . PHP_EOL; // Attention : Submit intercepté par js (imports.js)



        $answer .= "</form>" . PHP_EOL;

        

        return $answer;

    }



    // Formulaire de delete d'un import (je ne sais plus porquoi !!!) 

    private function show_form_deleted(): string

    {

        $this->InfoLog("show_form_deleted Entry with formulaire : " . print_r($_POST,true) );

        $answer = "<h2 class=\"home-title\">Fonction \"show_form_deleted\" toujours pas implémenté ...</h2>" . PHP_EOL;

    

        // $answer = "\t\t<h2 class=\"home-title\">Voulez-vous </h2>" . PHP_EOL;

        // $answer .= "\t\t<form method=\"post\" enctype=\"multipart/form-data\" id=\"form_imports\">" . PHP_EOL;

        // $answer .= "\t\t\t<span>&nbsp;</span><input style=\"float: right;\" type=\"submit\" value=\"Je ne ferait rien (voir je sais pas ce que ça fait)\" name=\"submit\"/>" . PHP_EOL; // Attention : Submit intercepté par js (imports.js)

        // $answer .= "\t\t</form>" . PHP_EOL;

    

        return $answer;

    }



    // Fonction qui permet de valider les conflits bizard

    public function Validation_conflit(): string

    {

        $this->obj_sync_import->init_session();

        

        $this->tables_name['tempo'] = $this->obj_sync_import->get_table_tempo();

        $this->Periode = $this->obj_sync_import->get_periode();

        $this->tables_name['lines'] = $this->getNameTableLines($this->Periode);

        $this->tables_name['infos'] = $this->getNameTableInfos($this->Periode);

        $answer = "<div>" . PHP_EOL;



        foreach ($_POST as $key => $value) {

            

            // $answer .= "<p>" . $key . ' : ' . $value . '</p>' . PHP_EOL;



            if ( str_starts_with($key, 'data') ) {

                $data = explode(",", $value);

                // $answer .= "<div>décomposition = " . print_r( $data , true ) . '</div>' . PHP_EOL;

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



        //$answer .= "<p>Session = " . print_r( $relations, true ) . '</p>' . PHP_EOL;



        foreach ($relations as $relation) {



            $result = $this->import_line_by_id( $relation['right'], $relation['left'] );

            if ( $result[0] ) {

                $answer .= "<p>Import de " . $relation['right'] . ' vers ' . $relation['left'] . ' : OK</p>' . PHP_EOL;

            } else {

                $this->objdb->cancelTransaction();

                $answer .= "<p>[ERROR] lors de l'import de " . $relation['right'] . ' vers ' . $relation['left'];

                $answer .= "<p>[Retour] :<br/>" . $result[1] . '</p>';

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

            

        } catch (\Throwable $e) {

            $this->InfoLog("ERREUR lors du nettoyage : " . $e->getMessage());

            return false;

        }

    }



    private function clear_import(): string

    {

        // $answer = "<h2 class=\"home-title\">Fonction \"show_form_deleted\" toujours pas implémenté ...</h2>" . PHP_EOL;

   

        $answer = "\t\t<h2 class=\"home-title\">Finalisation de l'import</h2>" . PHP_EOL;

        

        $this->obj_sync_import->init_session();



        $this->Periode = $this->obj_sync_import->get_periode();



        // Nettoyage des données obsolètes AVANT de finaliser

        if ( !$this->cleanup_obsolete_data($this->Periode) )

        {

            $answer = "\t\t<div class=\"imports-message\">ERREUR lors du nettoyage des données obsolètes.</div>" . PHP_EOL;

            return $answer;

        }



        if ( $this->resetImport($this->Periode) ) 

        {

            $answer = "\t\t<div class=\"imports-message\">Vous avez finalisé l'import.</div>" . PHP_EOL;

        }

        else 

        {

            $answer = "\t\t<div class=\"imports-message\">ERREUR lors de la finalisation l'import.</div>" . PHP_EOL;

        }



        return $answer;

    }



    public function start_Import(): string

    {

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



            // // Init Table for import

            // if ( !$this->Init_Tables_For_Import($Formulaire['periode']) )

            // {

            //     return $this->get_error_mess('Error : Init_Tables_For_Import : ' . $this->erreurs );

            // }



            // Création des tables d'imports pour l'année

            if ( !$this->Create_All_Table_For_Import($Formulaire['periode']) )

            {

                return $this->get_error_mess('Error : Create_All_Table_For_Import : ' . $this->erreurs );

            }



            $this->setKeyComptable();



            $sql = "SELECT * FROM `" . $this->tables_name['tempo'] . "` where `key` = :key ;";

            // $this->InfoLog('Import :: sql : ' . $sql);

            foreach($_SESSION['ArrayKeyComptable'] as $cle)

            // foreach( [[ 'typekey' => '001' ],[ 'typekey' => '002' ]] as $cle )

            {

                try

                {

                    $this->objdb->query($sql);

                    $this->objdb->execute([ ':key' => $cle['typekey'] ]);

                    // $this->InfoLog('clé : ' . $cle['typekey'] . "\t\t----\t\t" . $this->objdb->rowCount());

                    $all_line = $this->objdb->fetchall();

                    foreach($all_line as $line)

                    {

                        $this->InfoLog('line : ' . print_r($line,true) );

                        if ( !$this->inject_line($line) )

                        {

                            throw new Exception("Erreur lors de l'injection de la ligne :  - [ " . print_r($line, true) . " ]");

                        }

                    }

                }

                catch (PDOExection $e)

                {

                    $this->error = $e->getMessage();

                    $answer['error_mess'] = $this->error;

                    $answer['error_list'] = $this->erreurs;

                }

            }



            // // Check Import

            // $row_result = $this->objdb->execonerow("SELECT count(*) as count FROM `" . $this->tables_name['tempo'] . "` WHERE `imported` is null and `line_id` is null;");

            // $this->InfoLog('row_result : ' . print_r($row_result,true) );

            // if ( $row_result['count'] > 0 ) { $answer['error_mess'] = $this->error; }

            // else

            // {

            //     return $this->check_line_deplaced($Formulaire['periode']);

            // }

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

                $this->DBClear_And_SetNewYear( $Formulaire['periode'], $this->tables_name['tempo'] );

            }

            catch (PDOExection $e)

            {

                // $this->InfoLog('Import error : ' . $answer['error_mess']);

                return $this->get_error_mess('Error message : ' . $answer['error_mess'] );

            }

        }

       

        return "\t\t<h2 class=\"home-title\">Import terminé ...</h2>" . PHP_EOL;

    }



    private function get_clonflits_compte():array

    {

        $this->StepLog("DETECTION CONFLITS - Recherche lignes non reliées");

        

        $answer = [];

        // Récupération de la totalité des lignes non relier (qui ont donc été déplacé)

        $sql = "SELECT `Index`, `CompteNum`, `CompteLib`, `Key`, `Libelle`, `NumVoucher`, `Fournisseur`, `Date`, `TVA`, `Charges`, `TTC` FROM `" . $this->tables_name['tempo'] . "` where imported is not null and line_id is null;";

        // VALUES ( :key_id, :num, :label, :info_id, :index );";

        $this->SqlLog($sql);

        

        $list_line_not_imported = $this->objdb->ExecWithFetchAll($sql);
        
        $this->InfoLog("Nombre de lignes non reliées détectées : " . count($list_line_not_imported));



        foreach ( $list_line_not_imported as $line_line_not_imported )

        {

            $criteria = [

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
            try { $line_values[7] = str_replace([ ' ', ',', '€' ], [ '', '.', '' ], $line_values[7]); }
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



    // 

    public function inject_line($line):bool

    {



        $this->InfoLog( PHP_EOL . '********************************************************************************************************' . PHP_EOL );

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

            $line_info = [

                ':label' => $line['Libelle'],

                ':voucher' => $line['NumVoucher'],

                ':Fournisseur' => $line['Fournisseur'],

                ':Date' => $line['Date'],

                ':Tva' => $line['TVA'],

                ':Charges' => $line['Charges'],

                ':Ttc' => $line['TTC'],

            ];

            $index_id_info = $this->search_id_in_info($line_info);

            if ( $index_id_info == -1 ) {

                $this->InfoLog('inject_line :: Création new entry : ' . print_r($line_info,true) . "   --- Index = " . $line['Index']);

                $line_entry['info_id'] = $this->add_entree_Info($line_info);

                $this->add_entree_Line($line_entry, $line['Index']);

            } else {

                $line_entry['info_id'] = $index_id_info;

                

                // On memorise que l'on a trouver quelque chose

                $sql = 'update `' . $this->tables_name['tempo'] . '` set `imported` = 0 where `Index` = :index ;';

                $this->InfoLog('inject_line :: Update entry in table tempo : index=' . $line['Index']  . '   --- ' . $sql );

                $this->objdb->exec( $sql, [ ':index' => $line['Index'] ] );



                // On recherche si on retrouve la ligne précise .... 

                $sql = 'select `id_line`, `validation_id`, `voucher_id` from `' . $this->tables_name['lines'] . '` where `info_id` = :id_info and `key_id` = :id_key and `num_account` = :num;';

                $this->InfoLog('inject_line :: Select si line existe : ' . print_r($line_info,true) . '   ---  ' . $sql );

                $this->objdb->query($sql);

                $this->objdb->execute( [ ':id_info' => $line_entry['info_id'], ':id_key' => $line_entry['key_id'], ':num' => $line['CompteNum'] ] );

                $countrow = $this->objdb->rowCount();

                $this->InfoLog('inject_line :: countrow : ' . $countrow );

                if ( $countrow == 1 ) {

                    $id_line = $this->objdb->fetch()['id_line'];

                    $sql = 'update `' . $this->tables_name['lines'] . '` set `import` = :index where id_line = :id_line ;';

                    $this->InfoLog('inject_line :: Update entry : id_line=' . $id_line . '   --- index=' . $line['Index']  . '   --- ' . $sql );

                    $params = [ ':id_line' => $id_line, ':index' => $line['Index'] ];

                    $this->objdb->exec( $sql, $params );

                    $sql = 'update `' . $this->tables_name['tempo'] . '` set `line_id` = :id_line where `Index` = :index ;';

                    $this->InfoLog('inject_line :: Update entry : id_line=' . $id_line . '   --- index=' . $line['Index']  . '   --- ' . $sql );

                    $this->objdb->exec( $sql, $params );

                }

            }

        }

        catch (PDOExection $e)

        {

            $this->error = $e->getMessage();

            $answer = false;

            $this->erreurs[] = $this->error;

        }

        

        $this->InfoLog( PHP_EOL . '********************************************************************************************************' . PHP_EOL );



        return $answer;

    }



    public function add_entree_Info($entry_info):string

    {

        // $sql = "INSERT INTO `" . $this->tables_name['infos'] . "`(`LabelFact`, `NumPiece`, `NameFournisseur`, `DateOpe`, `Tva`, `Charges`, `MontantTTC`) VALUES ('" . $this->escapeApostrophes($entry_info[':label']) . "','" . $this->escapeApostrophes($entry_info[':num']) . "','" . $this->escapeApostrophes($entry_info[':Fournisseur']) . "','" . $this->escapeApostrophes($entry_info[':Date']) . "','" . $entry_info[':Tva'] . "','" . $entry_info[':Charges'] . "','" . $entry_info[':Ttc'] . "');";

        // $this->InfoLog('add_entree_Info :: sql : ' . print_r($sql,true) );

        // $this->objdb->query($sql);

        $sql = "INSERT INTO `" . $this->tables_name['infos'] . "`(`LabelFact`, `NumPiece`, `NameFournisseur`, `DateOpe`, `Tva`, `Charges`, `MontantTTC`) VALUES ( :label, :voucher, :Fournisseur, :Date, :Tva, :Charges, :Ttc );";

        $answer = $this->objdb->exec($sql, $entry_info, true );



        return $answer;

    }



    public function add_entree_Line($entry_line, $index ):void

    {

        // Insert new line

        // $this->InfoLog('add_entree_Line :: add_entree_Line : ' . print_r($entry_line,true) );

        // $sql = "INSERT INTO `" . $this->tables_name['lines'] . "`(`key_id`, `num_account`, `label_account`, `info_id`) VALUES ('" . $this->escapeApostrophes($entry_line['key_id']) . "','" . $this->escapeApostrophes($entry_line['num']) . "','" . $this->escapeApostrophes($entry_line['label']) . "','" . $this->escapeApostrophes($entry_line['info_id']) . "');";

        $sql = "INSERT INTO `" . $this->tables_name['lines'] . "`(`key_id`, `num_account`, `label_account`, `info_id`, `import`) VALUES ( :key_id, :num, :label, :info_id, :index );";

        $this->InfoLog('add_entree_Line :: sql : ' . print_r($sql,true) );

        $this->InfoLog('add_entree_Line :: params : ' . print_r([ ':key_id' => $entry_line['key_id'], ':num' => $entry_line['num'], ':label' => $entry_line['label'], ':info_id' => $entry_line['info_id'], ':index' => $index ],true) );

        $answer = $this->objdb->exec($sql, [ ':key_id' => $entry_line['key_id'], ':num' => $entry_line['num'], ':label' => $entry_line['label'], ':info_id' => $entry_line['info_id'] , ':index' => $index ], true );

        if ( $answer > 0 )

        {

            $this->InfoLog('add_entree_Line :: Update entry : id_line=' . $answer . '   --- index=' . $index  . '   --- ' . $sql );

            $this->objdb->exec('update `' . $this->tables_name['tempo'] . "` set `line_id` = :id where `Index` = :index;", [ ':id' => $answer, ':index' => $index ] );

        }

    }



    // public function set_found_entree_Info($index):string

    // {

    // }



    // private function escapeApostrophes(string $texte): string {

    //     return str_replace("'", "''", $texte);

    // }



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

        if (!is_numeric($val)) {

            $this->InfoLog('format_montant_comptable :: is_numeric : ' . $val );
            return $val; // ou throw exception selon ton contexte

        }



        // Conversion en string

        $stringValue = (string)$val;

        

        // Détection du '.'

        if (strpos($stringValue, '.') !== false)

        {

            // Séparer partie entière et décimale

            $parts = explode('.', $stringValue);

            $entiere = $parts[0];

            $decimale = $parts[1];

            

            // Forcer 2 décimales

            if (strlen($decimale) == 1) {

                $decimale = $decimale . '0';

            }

            

            $formatted = $entiere . ',' . $decimale;

        }

        else {

            // Ajouter ',00'

            $formatted = $stringValue . ',00';

        }



        return $formatted;

    }



    public function DBClear_And_SetNewYear(string $periode, string $tablename)

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



    // public function check_line_deplaced(string $Periode): string

    // {

        

    //     $answer = "\t\t<h2 class=\"home-title\">Validation des déplacement</h2>" . PHP_EOL;

    //     $answer .= "\t\t<form method=\"post\" enctype=\"multipart/form-data\" id=\"form_imports\">" . PHP_EOL;

    //     $answer .= "\t\t\t<span><label>Select la période :</label><select name=\"periode\" id=\"periode\">" . PHP_EOL;

    //     while( $row = $this->objdb->fetch() )

    //     {

    //         $annee = (int)explode("_", $row['periode'])[1];

    //         if ( $annee > $this->last_annee ) $this->last_annee = $annee;

    //         $answer .= "\t\t<option value=\"" . $row['periode'] . '" ' . (($annee == $annee_en_cours)?'selected="selected">':'>') . $row['periode'] . "</option>" . PHP_EOL;

    //     }

    //     $new_periode = $this->last_annee . "_" . $this->last_annee+1;

    //     $answer .= "\t\t<option value=\"" . $new_periode . '">' . $new_periode . "</option>" . PHP_EOL;

    //     $answer .= "\t\t</select></span>" . PHP_EOL;

    //     $answer .= "\t\t\t<p><span>Select an Excel file to upload for accounting import :</span><br/><input type=\"file\" name=\"fileToUpload\" id=\"fileToUpload\"/></p>" . PHP_EOL;

    //     $answer .= "\t\t\t<span>&nbsp;</span><input style=\"float: right;\" type=\"submit\" value=\"Import Excel File\" name=\"submit\"/>" . PHP_EOL; // Attention : Submit intercepté par js (imports.js)

    //     $answer .= "\t\t</form>" . PHP_EOL;

        

    //     return $answer;

    // }



    // public function check_line_deplaced(string $Periode)

    // {

    //     // Selection des ligne qui reste à traiter

    //     $sql = "SELECT * FROM `" . $this->tables_name['tempo'] . "` WHERE `imported` is not null and `line_id` is null";

    //     $this->objdb->query($sql);

    //     $this->objdb->execute();

    //     $all_line = $this->objdb->fetchall();

        

    //     // Pour chaque ligne

    //     foreach( $all_line as $line )

    //     {



    //         // Recheche la 

    //         $sql_selinfo = "SELECT id_info FROM `" . $this->tables_name['infos'] . "` where LabelFact = :label and NumPiece = :voucher and NameFournisseur = :Fournisseur and DateOpe = :Date and  Tva = :Tva and  Charges = :Charges and  MontantTTC = :Ttc;";

    //         $this->objdb->query($sql);

    //         $this->objdb->execute( [ 

    //             ':label' => $line['Libelle'],

    //             ':voucher' => $line['NumVoucher'],

    //             ':Fournisseur' => $line['Fournisseur'],

    //             ':Date' => $line['Date'],

    //             ':Tva' => $line['TVA'],

    //             ':Charges' => $line['Charges'],

    //             ':Ttc' => $line['TTC']

    //             ]

    //         );

    //         $countrow = $this->objdb->rowCount();

    //         $this->InfoLog('check_line_deplaced :: countrow 1 : ' . $countrow );

    //         if ( $countrow == 0 ) {

    //             $this->InfoLog('check_line_deplaced :: ERREUR -----' );

    //         } else {

    //             $all_info = $this->objdb->fetchall();

    //         }

    //         foreach( $all_info as $row2 )

    //         {

    //             $this->InfoLog('check_line_deplaced :: row result : ' . print_r($row2,true) . '   ---  ' . $sql );

    //         }

    //         $sql = 'select `id_line`, `validation_id`, `voucher_id`, `key_id`, `num_account` from `' . $this->tables_name['lines'] . '` where `info_id` = :id_info;';

    //         $this->objdb->query($sql);

    //         $this->objdb->execute( [ ':id_info' => $row['info_id'] ] );

    //         $all_info = $this->objdb->fetchall();

    //         foreach( $all_info as $row2 )

    //         {

    //             $this->InfoLog('check_line_deplaced :: row result : ' . print_r($row2,true) . '   ---  ' . $sql );

    //         }

    //     }

    // }



    //A vérifier

    public function delete_ligne()

    {



    }

    

    //A vérifier

    public function count_new_ligne()

    {



    }

    

    // recherche à l'aide de [ ':label' => LabelFact, ':voucher' => NumPiece, ':Fournisseur' => NameFournisseur, ':Date' => DateOpe, ':Tva' => Tva, ':Charges' => Charges, ':Ttc' => MontantTTC ]

    // l'id de la table info qui coresponds au critère de recherche

    // Attention exception si plusieur sont trouvé et return -1 si aucun n'est trouvé

    private function search_id_in_info($criteria) : int

    {

        $solodb = new Database($this->objdb);

        $answer = -1;



        $sql = "SELECT id_info FROM `" . $this->tables_name['infos'] . "` where LabelFact = :label and NumPiece = :voucher and NameFournisseur = :Fournisseur and DateOpe = :Date and  Tva = :Tva and  Charges = :Charges and  MontantTTC = :Ttc;";

        $solodb->query($sql);

        $solodb->execute($criteria);

        $countrow = $solodb->rowCount();

        // $this->InfoLog('inject_line :: countrow : ' . $countrow );

        if ( $countrow > 1 ) {

            throw new ComtabilityException("plusieur ligne d'information ont été trouvées avec vos critères. c'est anormal! --> liste des critères : " . r_print($criteria, true) );

        }

        if ( $countrow > 0 ) {

            $answer = $solodb->fetch()['id_info'];

        }



        return $answer;

    }



    private function search_line_in_tempo_for_comp($criteria) : array

    {

        $tb_tempo = '`' . $this->tables_name['tempo'] . '`';

        $sql =  'SELECT ' . $tb_tempo . '.*, `Compte_Key_type`.`shortname`';

        $sql .= 'FROM ';

        $sql .= $tb_tempo . ', ';

        $sql .= '`Compte_Key_type`';

        $sql .= 'WHERE '; 

        $sql .= '`Libelle` = :label and `NumVoucher` = :voucher and `Fournisseur` = :Fournisseur and `Date` = :Date and `TVA` = :Tva and `Charges` = :Charges and `TTC` = :Ttc and imported is not null and line_id is null ';

        $sql .= 'AND ' . $tb_tempo . '.`Key` = `Compte_Key_type`.`typekey`';

        $sql .= ";"; 

        return $this->objdb->ExecWithFetchAll($sql, $criteria);

    }



    private function search_line_in_lines_for_comp($criteria) : array

    {

        // récupération de l'id dans info qui coresponds à notre ligne à importer. Attention : Exception levé si plusieurs id trouvé.

        $index_id_info = $this->search_id_in_info( $criteria );

        if ( $index_id_info == -1 ) {

            $this->InfoLog('search_id_in_info :: ERREUR IMPOSSIBLE : ' . print_r($criteriao,true) );

            throw new ComtabilityException("Détection d'une nouvelle ligne dans le cadre de la gestion des ligne déplacé. Incohérence non traité. Effacez l'import et recommencez."); // Renvoie une exception si ça arrive

        }

        $params = [ 'info_id' => $index_id_info ];

        // $sql = "SELECT * FROM `" . $this->tables_name['lines'] . "` WHERE `info_id` = :info_id and `import` is null;";

        $tb_lines = '`' . $this->tables_name['lines'] . '`';

        $tb_valid = '`' . $this->getNameTableValidations($this->Periode) . '`';

        $sql =  'SELECT ';

        $sql .= $tb_lines . '.`id_line`, ';

        $sql .= $tb_lines . '.`num_account`, ';

        $sql .= $tb_lines . '.`label_account`, ';

        $sql .= '`Compte_Key_type`.`shortname`, ';

        $sql .= '`Compte_Key_type`.`typekey`, ';

        $sql .= $tb_valid . '.`state_id`, ';

        $sql .= $tb_valid . '.`infos` ';

        $sql .= 'FROM ' . $tb_lines . " ";

        $sql .= 'LEFT JOIN ' . $tb_valid . ' ON ' . $tb_lines . ".`validation_id` = " . $tb_valid . '.`id_validation` ';

        $sql .= 'INNER JOIN `Compte_Key_type` ON ' . $tb_lines . '.`key_id` = `Compte_Key_type`.`id_key` ';

        $sql .= 'WHERE '; 

        $sql .= '`info_id` = :info_id ';

        $sql .= 'AND `import` IS NULL ';

        $sql .= ";"; 

        $this->InfoLog( 'search_line_in_infos_for_comp :: sql : ' . $sql . "   --- data = " . print_r($params,true) );

        return $this->objdb->ExecWithFetchAll($sql, $params);

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

        if ( $trace ) { $this->PrepareLog('Import','d'); }

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
        $this->set_session( self::POS_PERIODE, $this->line_periode );

        if ( $count_line == 0 ) return 0;
        return intval( $this->line_periode['state_compte'] );

    }



    public function get_periode():string

    {

        return $this->line_periode['periode'];

    }



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
            // $this->InfoLog('Set SESSION');
            $this->line_periode = $_SESSION['syncho_import'][self::POS_PERIODE];
        } else {
            // $this->InfoLog('Call import_exits');
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