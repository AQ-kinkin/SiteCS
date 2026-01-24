<?php

require(PATH_HOME_CS . '/objets/compta.php');

class Compta_Validations extends Compta
{
    private $reopen;
    private bool $log;
    private array $SousCategorie = [];

    public function __construct(Database $refdb, bool $trace = true)
    {
        $this->objdb = $refdb;
        $this->log = $trace;
        if ($trace) {
            $this->PrepareLog('Validation');
        }
    }

    public function run_action($formulaire): string
    {
        $response = '';
        $this->reopen = -1;

        // $this->InfoLog('run_action :: formulaire ' . print_r($formulaire,true ) );

        if (!isset($formulaire['action']) || empty($formulaire['action'])) {
            // $this->InfoLog('run_action :: formulaire is empty' . print_r($formulaire,true ) );
            http_response_code(500);
            return 'data not transmitted.';
        }

        switch ($formulaire['action']) {
            case 'keylst':
                $this->setselectedyear();
                break;
            case 'show':
                $response = $this->showlistfacture();
                break;
            case 'update':
                $this->UpdateDB();
                $response = $this->showsinglefacture();
                break;
            case 'reopen':
                $this->reopen = $_POST['id_line'] ?? -1;
                // $this->InfoLog('run_action :: reopen : id = ' . $this->reopen . " ---- \t\t" . print_r($formulaire, true) );
                $response = $this->showsinglefacture();
                break;
            default:
                http_response_code(400);
                $response = "Action non reconnue. ['action' => " . $formulaire['action'] . "]";
                break;
        }

        return $response;
    }

    private function getKeyValidation($index, $info_validation = null): string
    {

        $this->setKeyValidation();

        $list_elements_select = "<select name=\"statut\" id=\"id_statut_" . $index . "\" onchange=\"toggleBySelect(this,'" . $index . "')\">" . PHP_EOL;
        foreach ($_SESSION['ArrayValidation'] as $selectInfo) {
            if ($info_validation !== null) {
                $list_elements_select .= "<option value=\"" . $selectInfo['numkey'] . "\" " . (($info_validation['num_state'] == $selectInfo['numkey']) ? 'selected="selected"' : '') . '>' . $selectInfo['namekey'] . "</option>" . PHP_EOL;
            } else {
                $list_elements_select .= "<option value=\"" . $selectInfo['numkey'] . "\" " . ($selectInfo['default'] ? 'selected="selected"' : '') . '>' . $selectInfo['namekey'] . "</option>" . PHP_EOL;
            }
        }
        $list_elements_select .= "</select>";

        return $list_elements_select;
    }



    private function setselectedyear(): void
    {
        // $this->InfoLog('setselectedyear :: periode ' . $periode );
        // $this->InfoLog('setselectedyear :: Post ' . $_POST['periode'] );
        $_SESSION['selectedyear'] = $_POST['periode']; // On stocke la période sélectionnée dans la session
    }



    private function showlistfacture(): string
    {

        $html_data = '';

        // $this->InfoLog('showlistfacture :: Post ' . print_r($_POST, true) );

        if (!isset($_SESSION['selectedyear']) || empty($_SESSION['selectedyear'])) {
            // $this->InfoLog('showlistfacture :: Aucune période sélectionnée.');
            http_response_code(400);
            return 'showlistfacture :: Aucune période sélectionnée.';
        }

        if (!isset($_POST['cle']) || empty($_POST['cle'])) {
            http_response_code(400);
            // $this->InfoLog('showlistfacture :: Aucune clé sélectionnée.');
            return 'showlistfacture :: Aucune clé sélectionnée.';
        }



        if (!isset($_POST['titre']) || empty($_POST['titre'])) {
            // $this->InfoLog('showlistfacture :: Aucune titre sélectionnée.');
            http_response_code(400);
            return 'showlistfacture :: Aucune titre sélectionnée.';
        }



        $html_data .= "<div class=\"title-with-refresh\">" . PHP_EOL;
        $html_data .= "<div class=\"titreSC\"><span>" . $_POST['titre'] . "</span></div>" . PHP_EOL;
        $html_data .= "<button id=\"refreshButton\" title=\"Recharger\"><img src=\"./icons/refresh-30x30.png\" alt=\"Refresh\" width=\"33\" height=\"30\"></button>" . PHP_EOL;
        $html_data .= "</div>" . PHP_EOL;
        $html_data .= "<div class=\"entries\">" . PHP_EOL;

        $this->get_infos_keys();
        if (empty($this->SousCategorie)) {
            $html_data .= "<div class=\"SCvide\">Pas de factures présente dans cette clé de répartition ...</div>" . PHP_EOL;
        } else {
            // $this->InfoLog('showlistfacture :: SousCategorie already set.');
            foreach ($this->SousCategorie as $cat => $data) {
                $id_category_div = "_" . $_POST['cle'] . "_" . $cat;
                $html_data .= "<div class=\"SC\" onclick=\"toggleVisibility('" . $id_category_div . "')\">" . $cat . " : " . $data['libelle'] . "</div>\n";
                $html_data .= "<div class=\"factures\" id=\"" . $id_category_div . "\">\n";
                foreach ($data['lignes'] as $ligne) {
                    $html_data .= $this->showfacture($ligne, $id_category_div);
                }
                $html_data .= "</div> <!-- Fin div : Factures -->\n"; // fin div Factures
            }
        }

        $html_data .= "</div>" . PHP_EOL; // fin div entries

        return $html_data;
    }

    private function showsinglefacture(): string
    {
        // Retourne uniquement le HTML d'une seule facture après update
        if (!isset($_POST['id_line']) || empty($_POST['id_line'])) {
            http_response_code(400);
            return 'showsinglefacture :: id_line manquant.';
        }

        $id_line = $_POST['id_line'];
        $this->InfoLog('showsinglefacture :: id_line = ' . $id_line);

        // Récupérer les infos de cette facture via get_infos_key (avec cache)
        $ligne = $this->get_infos_key($id_line);
        
        if (empty($ligne)) {
            http_response_code(404);
            return 'showsinglefacture :: facture non trouvée.';
        }

        // Construire l'ID de base pour la div (comme dans showlistfacture)
        $base_id_facture_div = "_" . $ligne['key_id'] . "_" . $ligne['num_account'];
        
        return $this->showfacture($ligne, $base_id_facture_div);
    }



    private function showfacture($ligne, $base_id_facture_div): string
    {
        $this->InfoLog("showfacture :: Affichage de la facture " . print_r($ligne, true));

        $html = "<div class=\"facture\" id=\"facture_" . $ligne['id_line'] . "\">\n";
        $html .= "\t<span><label>Pièce comptable :</label> " . htmlspecialchars($ligne['NumPiece']) . "</span>\n";
        $html .= "\t<span><label>Fournisseur :</label> " . htmlspecialchars($ligne['NameFournisseur']) . "</span>\n";
        $html .= "\t<span><label>Date :</label> " . htmlspecialchars($ligne['DateOpe']) . "</span>\n";
        $html .= "\t<span><label>TVA :</label> " . htmlspecialchars($ligne['Tva']) . " €</span>\n";
        $html .= "\t<span><label>Charge :</label> " . htmlspecialchars($ligne['Charges']) . " €</span>\n";
        $html .= "\t<div><label>Libellé :</label> " . htmlspecialchars($ligne['LabelFact']) . "</div>\n";
        $html .= "\t<div><label>Somme :</label> <span class=\"somme " . (($ligne['MontantTTC'] >= 0) ? 'positif' : 'negatif') . "\">" . htmlspecialchars($ligne['MontantTTC']) . " €</span></div>" . PHP_EOL;
        
        // Affichage de l'URL de la PJ si elle existe ET que le formulaire n'est pas en mode "reopen"
        $isFormClosed = ($ligne['validation_id'] !== null && $ligne['id_line'] != $this->reopen);
        if (!empty($ligne['url']) && $isFormClosed) {
            $url = htmlspecialchars($ligne['url']);
            $html .= "\t<div><label>URL facture :</label> <a href=\"" . $url . "\" target=\"_blank\" class=\"pj-link\">" . $url . "</a></div>" . PHP_EOL;
        }
        
        $html .= $this->showformfacture($ligne, $base_id_facture_div);
        $html .= "</div>\n"; // Fin div facture 

        return $html;
    }



    private function showformfacture($ligne, $base_id_facture_div): string
    {
        // $this->InfoLog('showformfacture :: ligne ' . print_r($ligne, true) );
        $id_facture_div = $base_id_facture_div . "_" . $ligne['id_line'];
        $html = "<form class=\"formfacture\" id=\"" . $id_facture_div . "\" >" . PHP_EOL;
        $html .= "\t<input type=\"hidden\" name=\"id_line\" value=\"" . htmlspecialchars($ligne['id_line']) . "\">" . PHP_EOL;
        $html .= "\t<input type=\"hidden\" name=\"name_attach\" value=\"" . htmlspecialchars($ligne['NumPiece']) . "\">\n";

        if ($ligne['validation_id'] !== null) {
            $info_validation = $this->get_in_validation($ligne['validation_id']);
            // $this->InfoLog('showformfacture :: info_validation : ' . var_export($info_validation,true) );
            if ($ligne['id_line'] != $this->reopen) {
                $html .= "\t<input type=\"hidden\" name=\"id_validation\" value=\"" . htmlspecialchars($ligne['validation_id']) . "\">" . PHP_EOL;
                $html .= "<div class=\"validated\"><table><tr>";
                $html .= "<td width=\"75\" class=\"validated_l1\"><button type=\"submit\" name=\"reopen\">Changer</button></td>";
                $html .= "<td width=\"180\" class=\"validated_l1\">" . $info_validation['state'] . "</td>";
                $html .= "<td class=\"validated_l1\"><span>" . $info_validation['commentaire'] . "</span></td>";

                switch ($info_validation['num_state']) {
                    // case '000': Rien à faire, aucune info supémentaire à afficher
                    case '001': // Rejeter : affichage cause du rejet
                    case '002': // A verifier : affichage cause de la vérifiquation
                        $html .= "</tr><tr>";
                        $html .= "<td width=\"75\" class=\"validated_l2\">Raison :</td>";
                        if (isset($info_validation['info'])) {
                            $html .= "<td colspan=\"2\" class=\"validated_l2\">" . $info_validation['info']->{'cause'} . "</td>";
                        } else {
                            $html .= "<td colspan=\"2\" class=\"validated_l2\"></td>";
                        }
                        break;
                    case '004': // A Déplacer
                        $html .= "</tr><tr>";
                        $html .= "<td width=\"75\" class=\"validated_l2\">---</td>";
                        if (isset($info_validation['info'])) {
                            $html .= "<td width=\"180\" class=\"validated_l2\">" . $this->find_label_key_Comptable($info_validation['info']->{'destination'}) . "</td>";
                            $html .= "<td class=\"validated_l2\">" . $info_validation['info']->{'regle'} . "</td>";
                        } else {
                            $html .= "<td colspan=\"2\" class=\"validated_l2\"></td>";
                        }
                        break;
                    case '003': // Changement de catégorie
                    case '005': // Erreur répartition
                    case '006': // Changement de catégorie
                        // $this->InfoLog('showformfacture :: switch case: 005 - Erreur répartition');
                        if (isset($info_validation['info'])) {
                            if (is_array($info_validation['info'])) {
                                foreach ($info_validation['info'] as $data_json) {
                                    $html .= "</tr><tr><td colspan=\"3\" class=\"validated_l2\">" . json_encode($data_json) . "</td>";
                                }
                            } else {
                                $html .= "</tr><tr><td colspan=\"3\" class=\"validated_l2\">" . json_encode($info_validation['info']) . "</td>";
                            }
                        } else {
                            $html .= "</tr><tr><td colspan=\"3\" class=\"validated_l2\"></td>";
                        }
                        break;
                }
                $html .= "</tr></table></div>" . PHP_EOL;
            } else {
                $html .= "\t<input type=\"hidden\" name=\"id_validation\" value=\"" . htmlspecialchars($ligne['validation_id']) . "\">" . PHP_EOL;
                $html .= "<div style=\"display: flex; align-items: flex-start; gap: 40px;\">" . PHP_EOL;
                $html .= "<span><label>Statut :</label>" . PHP_EOL;
                $html .= $this->getKeyValidation($ligne['id_line'], $info_validation) . "</span>" . PHP_EOL;
                $html .= "<span style=\"margin-left:40px\"><label for=\"commentaire" . $ligne['id_line'] . "\">Commentaire :</label><textarea id=\"commentaire" . $ligne['id_line'] . "\" name=\"commentaire\" rows=\"1\" cols=\"113\">" . $info_validation['commentaire'] . "</textarea></span>" . PHP_EOL;
                $html .= "</div>" . PHP_EOL;
                $html .= $this->generateURLField($ligne);
                $html .= $this->showDetailsPb($ligne['id_line'], $info_validation);
                $html .= "<div>";
                $html .= "<button type=\"submit\" name=\"update\">Valider/Update</button></div>" . PHP_EOL;
            }
        } else {
            $html .= "<div style=\"display: flex; align-items: flex-start; gap: 40px;\">" . PHP_EOL;
            $html .= $this->getKeyValidation($ligne['id_line']) . "</span>" . PHP_EOL;
            $html .= "<span style=\"margin-left:40px\"><label for=\"commentaire" . $ligne['id_line'] . "\">Commentaire :</label><textarea id=\"commentaire" . $ligne['id_line'] . "\" name=\"commentaire\" rows=\"1\" cols=\"113\" placeholder=\"Ce commentaire n'est pas visible par LLgestion -- uniquement visible pas les membre du CS\"></textarea></span>" . PHP_EOL;
            $html .= "</div>" . PHP_EOL;
            $html .= $this->generateURLField($ligne);
            $html .= $this->showDetailsPb($ligne['id_line']);
            $html .= "<div>";
            $html .= "<button type=\"submit\" name=\"update\">Valider</button></div>" . PHP_EOL;
        }
        $html .= "</form>" . PHP_EOL;
        return $html;
    }



    // private function PrepareLog(): void
    // {
    //     $this->logsPath = '/home/csresip/www/logs/' . date('YMDH');
    //     // $this->logsPath = '/home/csresip/www/logs/' . date('YmdH');
    //     // $this->logsPath = '/home/csresip/www/logs/' . date('YmdHi');

    //     // Créer le dossier s’il n'existe pas
    //     $dossier = dirname($this->logsPath);
    //     if (!is_dir($dossier)) {
    //         mkdir($dossier, 0775, true);
    //     }

    //     // Format du message (date + contenu)
    //     $date = date('Y-m-d H:i:s');
    //     $ligne = "[$date] [Sart] ---------------------------------------------\n";

    //     // Écriture dans le fichier (append)
    //     file_put_contents($this->logsPath, $ligne, FILE_APPEND | LOCK_EX);
    // }

    private function InfoLog($message): void
    {
        // Format du message (date + contenu)
        $date = date('Y-m-d H:i:s');
        $ligne = "[$date] [Info] $message\n";

        // Écriture dans le fichier (append)
        $this->write_info($ligne);
    }



    private function get_infos_keys(): void
    {
        $this->InfoLog('get_infos_keys :: $_POST => ' . print_r($_POST, true));
        // $this->InfoLog('get_infos_keys :: ArrayKeyComptable ' . print_r($_SESSION['ArrayKeyComptable'], true) );
        // $this->InfoLog('get_infos_keys :: key ' . $_POST['cle']);
        // $this->InfoLog('get_infos_keys :: key_id ' . $this->find_id_key($_POST['cle']) );

        $sql = "SELECT id_line, key_id, num_account, label_account , validation_id, voucher_id, nom, url, LabelFact, NumPiece, NameFournisseur, DateOpe, Tva, Charges, MontantTTC, state_id, infos, commentaire ";
        $sql .= "FROM `" . $this->getNameTableLines($_SESSION['selectedyear']) . "` ";
        $sql .= "INNER JOIN `" . $this->getNameTableInfos($_SESSION['selectedyear']) . "` ON info_id = id_info ";
        $sql .= "LEFT JOIN `" . $this->getNameTableValidations($_SESSION['selectedyear']) . "` ON validation_id = id_validation ";
        $sql .= "LEFT JOIN `" . $this->getNameTableVouchers($_SESSION['selectedyear']) . "` ON voucher_id = id_voucher ";
        $sql .= "WHERE `Key_id` = " . $this->find_id_key($_POST['cle']);

        // Ajout du filtrage selon le paramètre filtre
        if (isset($_POST['filtre']) && $_POST['filtre'] !== '0') {
            $this->InfoLog('get_infos_keys :: filtre => ' . $_POST['filtre']);
            switch ($_POST['filtre']) {
                case '1': // À saisir (sans validation)
                    $sql .= " AND validation_id IS NULL";
                    $this->InfoLog('get_infos_keys :: Filtre À saisir appliqué');
                    break;
                case '2': // À vérifier (state_id = 3)
                    $sql .= " AND state_id = 3";
                    $this->InfoLog('get_infos_keys :: Filtre À vérifier appliqué');
                    break;
                case '3': // Not OK (tous sauf state_id = 1 qui est OK)
                    $sql .= " AND validation_id IS NOT NULL AND state_id != 1";
                    $this->InfoLog('get_infos_keys :: Filtre Not OK appliqué');
                    break;
            }
        } else {
            $this->InfoLog('get_infos_keys :: Aucun filtre appliqué');
        }
        
        $sql .= ";";
        $this->InfoLog('get_infos_keys :: SQL => ' . $sql);

        $this->objdb->query($sql);
        if ($this->objdb->execute()) {
            while ($row = $this->objdb->fetch()) {
                // $this->InfoLog('get_infos_keys :: fetch ' . print_r($row, true) );
                $num_account = $row['num_account'];
                if (isset($this->SousCategorie[$num_account])) {
                    $this->SousCategorie[$num_account]['lignes'][] = $row;
                } else {
                    $this->SousCategorie[$num_account]['libelle'] = $row['label_account'];
                    $this->SousCategorie[$num_account]['lignes'][] = $row;
                }
            }

            // $this->InfoLog('get_infos_keys :: SousCategorie ' . print_r($this->SousCategorie, true) );
        }
    }

    private function get_infos_key($id_line): array
    {
        // Récupère les infos d'UNE SEULE facture par requête SQL
        // La requête SQL est mise en cache dans la session pour éviter de la reconstruire
        $this->InfoLog('get_infos_key :: Chargement facture ' . $id_line);
        
        $cache_key = 'sql_get_facture_' . $_SESSION['selectedyear'];
        
        if (!isset($_SESSION[$cache_key])) {
            $sql = "SELECT id_line, key_id, num_account, label_account , validation_id, voucher_id, nom, url, LabelFact, NumPiece, NameFournisseur, DateOpe, Tva, Charges, MontantTTC, state_id, infos, commentaire ";
            $sql .= "FROM `" . $this->getNameTableLines($_SESSION['selectedyear']) . "` ";
            $sql .= "INNER JOIN `" . $this->getNameTableInfos($_SESSION['selectedyear']) . "` ON info_id = id_info ";
            $sql .= "LEFT JOIN `" . $this->getNameTableValidations($_SESSION['selectedyear']) . "` ON validation_id = id_validation ";
            $sql .= "LEFT JOIN `" . $this->getNameTableVouchers($_SESSION['selectedyear']) . "` ON voucher_id = id_voucher ";
            $sql .= "WHERE `id_line` = :id_line LIMIT 1;";
            
            $_SESSION[$cache_key] = $sql;
            $this->InfoLog('get_infos_key :: Requête SQL mise en cache');
        } else {
            $sql = $_SESSION[$cache_key];
            $this->InfoLog('get_infos_key :: Requête SQL récupérée depuis le cache');
        }
        
        $this->InfoLog('get_infos_key :: SQL => ' . $sql);
        
        $ligne = $this->objdb->execonerow($sql, [':id_line' => $id_line]);
        
        return $ligne ?? [];
    }



    // ****************************************************************************

    // élément de validation : OK, Rejeté, A vérifier, ...

    // ****************************************************************************

    private function find_key_validation($id): string

    {

        $this->setKeyValidation();
        $numkey = '';

        foreach ($_SESSION['ArrayValidation'] as $data) {

            if ($data['id_state'] === $id) {

                $numkey = $data['numkey'];

                break;
            }
        }

        return $numkey;
    }



    private function find_id_validation($numkey): string

    {

        // $this->InfoLog('find_id_validation :: numkey ' . $numkey );

        $this->setKeyValidation();



        $id_val = '-1';

        foreach ($_SESSION['ArrayValidation'] as $data) {

            if ($data['numkey'] === $numkey) {

                $id_val = $data['id_state'];

                break;
            }
        }

        return $id_val;
    }



    private function find_label_validation($id): string

    {

        // $this->InfoLog('find_label_validation :: id ' . $id );

        $this->setKeyValidation();



        $str_val = '';

        // $this->InfoLog('find_label_validation :: ArrayValidation ' . print_r($_SESSION['ArrayValidation'],true) );

        foreach ($_SESSION['ArrayValidation'] as $data) {

            // $this->InfoLog('find_label_validation :: data ' . print_r($data,true) );

            // $this->InfoLog('find_label_validation :: cmp ' . $data['id_state'] . ' == ' . $id );

            if ($data['id_state'] == $id) {

                $str_val = $data['namekey'];

                break;
            }
        }

        // $this->InfoLog('find_label_validation :: str_val ' . $str_val );

        return $str_val;
    }

    // ****************************************************************************



    // ****************************************************************************

    // élément de validation : OK, Rejeté, A vérifier, ...

    // ****************************************************************************

    private function find_label_key_Comptable($typekey): string

    {

        // $this->InfoLog('find_label_key_Comptable :: typekey ' . $typekey );

        $this->setKeyComptable();



        $str_val = '';

        // $this->InfoLog('find_label_key_Comptable :: ArrayKeyComptable ' . print_r($_SESSION['ArrayKeyComptable'],true) );

        foreach ($_SESSION['ArrayKeyComptable'] as $data) {

            // $this->InfoLog('find_label_key_Comptable :: data ' . print_r($data,true) );

            // $this->InfoLog('find_label_key_Comptable :: cmp ' . $data['typekey'] . ' == ' . $typekey );

            if ($data['typekey'] === $typekey) {

                $str_val = $data['shortname'];

                break;
            }
        }

        // $this->InfoLog('find_label_key_Comptable :: str_val ' . $str_val );

        return $str_val;
    }



    private function generateURLField($ligne): string
    {
        $id_line = $ligne['id_line'];
        $url = !empty($ligne['url']) ? htmlspecialchars($ligne['url']) : '';
        
        $html = "<div>" . PHP_EOL;
        $html .= "<label for=\"url_facture" . $id_line . "\">URL facture :</label>" . PHP_EOL;
        $html .= "<input class=\"url_facture\" type=\"url\" id=\"url_facture" . $id_line . "\" name=\"url_facture\" value=\"" . $url . "\" placeholder=\"http::--- (s'il n'y a pas de facture)\">" . PHP_EOL;
        
        // Champ caché pour l'ID du voucher si présent
        if (!empty($ligne['voucher_id'])) {
            $html .= "<input type=\"hidden\" name=\"voucher_id\" value=\"" . htmlspecialchars($ligne['voucher_id']) . "\">" . PHP_EOL;
        }
        
        $html .= "</div>" . PHP_EOL;
        
        return $html;
    }

    private function showDetailsPb($id_line, $info_validation = null): string

    {

        $seize1 = 115;

        $seize2 = 72;



        $CauseInfo = '';

        $regle_textarea = '';

        $reafectation_textarea = htmlspecialchars("[\n\t{\n\t\t\"lot\":\"123\",\n\t\t\"Somme\":\"1000,00\"\n\t}\n]");

        $repart_textarea = htmlspecialchars("[\n\t{\n\t\t\"accounting_key\":\"001\",\n\t\t\"cause\":\"parking\",\n\t\t\"Somme\":\"1000,00\"\n\t}\n]");

        $change_cat_textarea = htmlspecialchars("{\n\t\"categorie\":\"61100000\",\n\t\"cause\":\"parce que\"\n}");


        if ($info_validation != null) {

            $this->InfoLog('showDetailsPb :: info_validation : ' . var_export($info_validation, true));

            switch ($info_validation['num_state']) {

                case '001':
                case '002':

                    $CauseInfo = $info_validation['info']->cause;
                    $this->InfoLog('showDetailsPb :: info_validation : 002&001 = ' . $reafectation_textarea);
                    break;

                case '003':

                    $reafectation_textarea = htmlspecialchars(json_encode($info_validation['info'], JSON_PRETTY_PRINT));
                    $this->InfoLog('showDetailsPb :: info_validation : 003 = ' . $reafectation_textarea);
                    break;

                case '004':

                    $this->InfoLog('showDetailsPb :: info_validation : 004');
                    $regle_textarea = $info_validation['info']->regle;
                    break;

                case '005':

                    $repart_textarea = htmlspecialchars(json_encode($info_validation['info'], JSON_PRETTY_PRINT));
                    $this->InfoLog('showDetailsPb :: info_validation : 005 = ' . $repart_textarea);
                    break;

                case '006':

                    $change_cat_textarea = htmlspecialchars(json_encode($info_validation['info'], JSON_PRETTY_PRINT));
                    $this->InfoLog('showDetailsPb :: info_validation : 006 = ' . $change_cat_textarea);
                    break;

                default:

                    $this->InfoLog('showDetailsPb :: default : ' . $info_validation['num_state']);
            }
        }



        $pb_elements_form = "<div id=\"details_pb_" . $id_line . "\" class=\"hidden\">" . PHP_EOL;

        $pb_elements_form .= "<div id=\"id_cause_" . $id_line . "\" class=\"hidden\">" . PHP_EOL;



        $pb_elements_form .= "<label for=\"id_textarea_" . $id_line . "\">Cause :</label>" . PHP_EOL;

        $pb_elements_form .= "<textarea id=\"id_textarea_" . $id_line . "\" name=\"cause_textarea\" rows=\"2\" cols=\"" . $seize1 . "\">" . $CauseInfo . "</textarea>" . PHP_EOL;

        $pb_elements_form .= "</div>" . PHP_EOL; // Fin div id_cause



        $pb_elements_form .= "<div id=\"id_reafectation_" . $id_line . "\" class=\"hidden\">" . PHP_EOL;

        // $pb_elements_form .= "<h1>Non déveloper : mettre a vérifier et remplisser la cause</h1>" . PHP_EOL;

        $pb_elements_form .= "<textarea id=\"id_reafectation_" . $id_line . "\" name=\"reafectation_textarea\" rows=\"" . substr_count($reafectation_textarea, "\n") . "\" cols=\"" . $seize1 . "\">" . $reafectation_textarea . "</textarea>" . PHP_EOL;

        $pb_elements_form .= "</div>" . PHP_EOL; // Fin div id_reafectation



        $pb_elements_form .= "<div id=\"id_deplacement_" . $id_line . "\" class=\"hidden\">" . PHP_EOL;

        $pb_elements_form .= "<span><label>Cible :</label><select name=\"destination\" id=\"id_destination_" . $id_line . "\">" . PHP_EOL;



        foreach ($_SESSION['ArrayKeyComptable'] as $selectInfo) {

            $pb_elements_form .= "<option value=\"" . $selectInfo['typekey'] . "\">" . $selectInfo['shortname'] . "</option>" . PHP_EOL;

            // echo '<option value="' . $selectInfo['typekey'] . '" ' . ($selectInfo['default']?'selected="selected"':'') . '>' . $selectInfo['namekey'] . "</option>\n";

        }

        $pb_elements_form .= "</select></span><label for=\"id_regle_textarea_" . $id_line . "\">Règle :</label>" . PHP_EOL;

        $pb_elements_form .= "<textarea id=\"id_regle_textarea_" . $id_line . "\" name=\"regle_textarea\" rows=\"1\" cols=\"" . $seize2 . "\">" . $regle_textarea . "</textarea>" . PHP_EOL;

        $pb_elements_form .= "</div>" . PHP_EOL; // Fin div id_reafectation



        $pb_elements_form .= "<div id=\"id_repartition_" . $id_line . "\" class=\"hidden\">" . PHP_EOL;

        // $pb_elements_form .= "<h1>Non déveloper : mettre a vérifier et remplisser la cause</h1>" . PHP_EOL;

        $pb_elements_form .= "<textarea id=\"id_repartition_" . $id_line . "\" name=\"repart_textarea\" rows=\"" . substr_count($repart_textarea, "\n") . "\" cols=\"" . $seize1 . "\">" . $repart_textarea . "</textarea>" . PHP_EOL;

        $pb_elements_form .= "</div>" . PHP_EOL; // Fin div id_reafectation



        $pb_elements_form .= "<div id=\"id_change_cat_" . $id_line . "\" class=\"hidden\">" . PHP_EOL;

        // $pb_elements_form .= "<h1>Non déveloper : mettre a vérifier et remplisser la cause</h1>" . PHP_EOL;

        $pb_elements_form .= "<textarea id=\"id_change_cat_" . $id_line . "\" name=\"change_cat_textarea\" rows=\"" . substr_count($change_cat_textarea, "\n") . "\" cols=\"" . $seize1 . "\">" . $change_cat_textarea . "</textarea>" . PHP_EOL;

        $pb_elements_form .= "</div>" . PHP_EOL; // Fin div id_reafectation



        $pb_elements_form .= "</div>" . PHP_EOL; // Fin div details_pb



        return $pb_elements_form;
    }



    private function UpdateDB(): void
    {
        $this->InfoLog('UpdateDB :: Post ' . print_r($_POST, true));

        if (!isset($_POST['id_line']) || empty($_POST['id_line'])) {
            // $this->InfoLog('UpdateDB :: Aucune ligne sélectionnée.');
            http_response_code(400);
            return;
        }

        $id_attachment = $this->add_attachement();
        $id_validdation = -1;
        $id_state = $this->find_id_validation($_POST['statut']);
        // $this->InfoLog("UpdateDB :: result find_id_validation : " . $id_state );

        $this->objdb->beginTransaction();
        
        switch ($id_state) {
            case 1: // OK
                $str_json = '{}';
                break;
            
            case 2: // Rejeté
            case 3: // a verifier
                $this->InfoLog("UpdateDB :: a verifier  ou Rejeté : " . $_POST['cause_textarea']);
                $obj_json = new stdClass();
                $obj_json->cause = $_POST['cause_textarea'];
                $str_json = json_encode($obj_json);
                break;

            case 4: // A réafecter
                $this->InfoLog("UpdateDB :: entry in réafect : " . $_POST['reafectation_textarea']);
                $obj_json = json_decode($_POST['reafectation_textarea']);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Erreur JSON : ' . json_last_error_msg());
                }
                $str_json = json_encode($obj_json);
                if ($str_json === false) {
                    throw new Exception('Erreur JSON : ' . json_last_error_msg());
                }
                break;
            
            case 5: // A déplacer
                $obj_json = new stdClass();
                $obj_json->destination = $_POST['destination'];
                $obj_json->regle = $_POST['regle_textarea'];
                $str_json = json_encode($obj_json);
                break;
            
            case 6: // A répartir
                $this->InfoLog("UpdateDB :: entry in réafect : " . $_POST['repart_textarea']);
                $obj_json = json_decode($_POST['repart_textarea']);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Erreur JSON : ' . json_last_error_msg());
                }
                $str_json = json_encode($obj_json);
                if ($str_json === false) {
                    throw new Exception('Erreur JSON : ' . json_last_error_msg());
                }
                break;
            
            case 7: // Changement de cathégorie
                $this->InfoLog("UpdateDB :: entry in réafect : " . $_POST['change_cat_textarea']);
                $obj_json = json_decode($_POST['change_cat_textarea']);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Erreur JSON : ' . json_last_error_msg());
                }
                $str_json = json_encode($obj_json);
                if ($str_json === false) {
                    throw new Exception('Erreur JSON : ' . json_last_error_msg());
                }
                break;
            
            default:
                $id_validdation = 0;
                $this->InfoLog("UpdateDB :: default case not found : " . $_POST['statut'] . "\t---\t Post : " . print_r($_POST, true));
        }



        if (isset($_POST['id_validation']) && $_POST['id_validation'] > 0) {
            if ($id_validdation < 0) {
                $id_validdation = $this->set_validation(
                    Compta::MODE_UPDATE,
                    "UPDATE `" . $this->getNameTableValidations($_SESSION['selectedyear']) . "` SET `state_id`=:id_val, `infos`=:json_str, `commentaire`=:comment WHERE id_validation = :id_validation;",
                    [':id_val' => $id_state, ':json_str' => $str_json, ':comment' => $_POST['commentaire'], ':id_validation' => $_POST['id_validation']]
                );
                
                if ($id_validdation < 0) {
                    // gestion de l'erreur...
                    $this->InfoLog("UpdateDB :1: AVANT cancelTransaction (UPDATE failed) - id_validdation = " . $id_validdation);
                    $this->objdb->cancelTransaction();
                    $this->InfoLog("UpdateDB :1: APRES cancelTransaction (UPDATE failed)");
                } else {
                    // UPDATE réussi (retourne 0), mais validation_id existe déjà dans lines, pas besoin d'update
                    // Mettre à jour seulement le voucher_id si nécessaire
                    if ($id_attachment > 0) {
                        $this->add_validation_in_lines(0, $id_attachment);
                    }
                    $id_validdation = 1; // Forcer à 1 pour indiquer le succès
                }
            }
        } else {
            if ($id_validdation < 0) {
                $id_validdation = 0;
                $id_validdation = $this->set_validation(
                    Compta::MODE_INSERT,
                    "INSERT INTO `" . $this->getNameTableValidations($_SESSION['selectedyear']) . "`(`state_id`, `infos`, `commentaire`) VALUES ( :id_val, :json_str, :comment );",
                    [':id_val' => $id_state, ':json_str' => $str_json, ':comment' => $_POST['commentaire']]
                );
                $this->InfoLog('UpdateDB :: return set_validation ' . $id_validdation . ' - :id_val ' . $id_state . ' - :json_str ' . $str_json . ' - :comment ' . $_POST['commentaire']);
                
                if ($id_validdation > 0) {
                    $this->add_validation_in_lines($id_validdation, $id_attachment);
                } else {
                    $this->InfoLog("UpdateDB :2: AVANT cancelTransaction (INSERT failed) - id_validdation = " . $id_validdation);
                    $this->objdb->cancelTransaction();
                    $this->InfoLog("UpdateDB :2: APRES cancelTransaction (INSERT failed)");
                }
            } else {
                $this->add_validation_in_lines(0, $id_attachment);
                $id_validdation = 1;
            }
        }
        
        if ($id_validdation > 0) {
            $this->InfoLog('UpdateDB :: endTransaction send - id_validdation = ' . $id_validdation);
            $this->objdb->endTransaction();
        } else {
            $this->InfoLog('UpdateDB :: PAS de endTransaction - id_validdation = ' . $id_validdation);
        }
    }



    private function add_attachement(): int
    {
        $this->InfoLog('add_attachement :: Post ' . print_r($_POST, true));
        $id_attachment = -1;

        if (isset($_POST['url_facture'])  && $_POST['url_facture'] !== 'http://---' && !empty($_POST['url_facture'])) {

            // Cas 1 : Mise à jour d'une pièce jointe existante
            if (isset($_POST['voucher_id']) && !empty($_POST['voucher_id'])) {
                $id_attachment = $_POST['voucher_id'];
                // Vérifier si l'URL a changé
                $params = [':id' => $id_attachment];
                $sql = "SELECT url FROM `" . $this->getNameTableVouchers($_SESSION['selectedyear']) . "` WHERE id_voucher = :id;";
                $this->InfoLog("SQL 0 (check existing) : " . $sql . ' -- params: ' . print_r($params, true));
                $answer = $this->objdb->execonerow($sql, $params);

                if (!empty($answer) && $answer['url'] !== $_POST['url_facture']) {
                    // L'URL a changé, mettre à jour
                    $params = [':url' => $_POST['url_facture'], ':name' => $_POST['name_attach'], ':id' => $id_attachment];
                    $sql = "UPDATE `" . $this->getNameTableVouchers($_SESSION['selectedyear']) . "` SET url = :url, nom = :name WHERE id_voucher = :id;";
                    $this->InfoLog("SQL 0b (update) : " . $sql . ' -- params: ' . print_r($params, true));
                    $this->objdb->exec($sql, $params);
                }
            }
            // Cas 2 : Ajout d'une nouvelle pièce jointe
            else {
                // 1 : check if the attachment already exists
                $params = [':url' => $_POST['url_facture']];
                $sql = "SELECT * FROM `" . $this->getNameTableVouchers($_SESSION['selectedyear']) . "` WHERE url = :url;";
                $this->InfoLog("SQL 1 : " . $sql . ' -- params: ' . print_r($params, true));
                $answer = $this->objdb->execonerow($sql, $params);

                if (empty($answer)) {
                    $params[':name'] = $_POST['name_attach'];
                    $sql = "INSERT INTO `" . $this->getNameTableVouchers($_SESSION['selectedyear']) . "` (nom,url) values ( :name , :url );";
                    $this->InfoLog("SQL 2 : " . $sql . ' -- params: ' . print_r($params, true));
                    $this->objdb->exec($sql, $params);
                    $id_attachment = $this->objdb->lastInsertId();
                    $this->InfoLog('Add PJ :: id_attachment (new) ' . $id_attachment);
                } else {
                    $id_attachment = $answer['id_voucher'];
                    $this->InfoLog('Add PJ :: id_attachment already exists ' . $id_attachment);
                }
            }
        }

        return $id_attachment;
    }



    private function set_validation($mode, $sql, $params): int

    {

        if ($this->log == true) {

            $str_mode = 'not found';

            switch ($mode) {

                case Compta::MODE_UPDATE:

                    $str_mode = 'Compta::MODE_UPDATE';

                    break;

                case Compta::MODE_INSERT:

                    $str_mode = 'Compta::MODE_INSERT';

                    break;
            }

            $this->InfoLog('set_validation :: mode: ' . $str_mode);
        }

        $this->objdb->query($sql);

        $this->InfoLog('set_validation :: query : ' . $sql);

        $this->InfoLog('set_validation :: params : ' . print_r($params, true));

        if ($this->objdb->execute($params)) {

            if ($mode == Compta::MODE_INSERT) return $this->objdb->lastInsertId();

            else return 0;
        } else {

            // $this->InfoLog('add_validation :: Error executing query: ' . $this->objdb->error());

            return -1;
        }
    }



    private function add_validation_in_lines($id_validdation, $id_attachment): bool

    {

        $answer = false;



        $this->InfoLog('add_validation_in_lines :: id_validdation ' . $id_validdation . ' - id_attachment ' . $id_attachment);

        if ($id_validdation > 0 || $id_attachment > 0) {

            $sql = "UPDATE `" . $this->getNameTableLines($_SESSION['selectedyear']) . "` SET ";



            if ($id_validdation > 0) {

                $sql .= "`validation_id`=" . $id_validdation . ",";
            }



            if ($id_attachment > 0) {

                $sql .= "`voucher_id`=" . $id_attachment;
            } else {

                $sql = chop($sql, ",");
            }



            $sql .= " WHERE `id_line`=" . $_POST['id_line'] . ";";

            $this->InfoLog('add_validation_in_lines :: ' . $sql);

            $this->objdb->exec($sql);

            $answer = true;
        }



        return $answer;
    }



    private function get_in_validation($id_validation): array

    {

        $info = [];

        $answer = $this->objdb->execonerow("SELECT * FROM `" . $this->getNameTableValidations($_SESSION['selectedyear']) . "` WHERE id_validation = " . $id_validation . ";");

        if (!empty($answer)) {

            // $this->InfoLog('get_in_validation :: result requête : ' . print_r($answer,true) );

            return [

                'state' => $this->find_label_validation($answer['state_id']),

                'num_state' => $this->find_key_validation($answer['state_id']),

                'info' => $this->get_json_validation_info($answer['infos']),

                'commentaire' => $answer['commentaire'] ?? '',

            ];
        } else {

            return [];
        }



        return $info;
    }



    private function get_json_validation_info($json_info): object|array
    {

        $info = null;

        if (!empty($json_info)) {

            // $this->InfoLog('get_json_validation_info :: json_info ' . var_export($json_info, true) );

            $obj_json = json_decode($json_info);

            if (json_last_error() !== JSON_ERROR_NONE) {

                throw new Exception('JSON invalide : ' . json_last_error_msg());
            }

            // $this->InfoLog('get_json_validation_info :: obj_json ' . var_export($obj_json, true) );



            if (isset($obj_json)) {

                $info = $obj_json;

                // $this->InfoLog('get_json_validation_info :: obj_json ' . var_export($info, true) );

            }



            // switch($id)
            // {

            //     case 1: // OK

            //         break;

            //     case 2: // Rejeté

            //         break;

            //     // if ( is_array($data) && !empty($data) )

            //     // {

            //     //     foreach ($data as $key => $value) {

            //     //         $info .= "<span class=\"info_validation\">" . htmlspecialchars($key) . " : " . htmlspecialchars($value) . "</span><br>" . PHP_EOL;

            //     //     }

            //     // }

            //     // else

            //     // {

            //     //     $info = htmlspecialchars($json_info);

            //     // }

            // }

        }

        // $info .= "</span>";



        return $info;
    }
}
