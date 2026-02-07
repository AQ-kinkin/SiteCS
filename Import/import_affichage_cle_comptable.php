<?php
    $pathHome = '/home/csresip/www';
    require( $pathHome . '/objets/database.class.php');
	require( $pathHome . '/objets/mysql.sessions.php');

    $connected = false;
    $session = new Session();
    session_set_save_handler($session, true);
    session_start();

    if (isset($_SESSION['user_id'])) {
        $connected = true;
        $objdb = new Database;
        $SousCategorie = [];
    }
    else
    {
	    http_response_code(400);
        echo "appelant non connecté.";
        exit;
    }
?>


<?php
    $objdb->query("SELECT `Index`, `CompteNum`, `CompteLib`, `Libelle`, `NumVoucher`, `Fournisseur`, `Date`, `TVA`, `Charges`, `TTC` FROM IMPORT_EXCEL_20250527184101 WHERE `Key` = :id;");
    if ($objdb->execute([ ':id' => $_POST['cle'] ]))
    {
        while ( $row = $objdb->fetch())
        {
            $cat = $row['CompteNum'];
            if ( isset( $SousCategorie[$cat] ) ) {
                $SousCategorie[$cat]['lignes'][] = $row;
            } else {
                $SousCategorie[$cat]['libelle'] = $row['CompteLib'];
                $SousCategorie[$cat]['lignes'][] = $row;
            }
        }
    }
?>
    <div class="titreSC"><?= $_POST['titre'] ?></div>
    <!-- <div class="titreSC"><?php
        foreach ($_SESSION['ArrayValidation'] as $data) {
            echo "\t<h1>" . $data['numkey'] . "\t-:-\t" . $data['namekey'] . "\t-:-\t" . $data['default'] . "</h1>\n";
        }
    ?></div> -->
<?php
    foreach ($SousCategorie as $cat => $data)
    {
        echo "<div class=\"SC\" onclick=\"toggleVisibility('_" . $_POST['cle'] . "_" . $cat . "')\">" . $cat . " : " . $data['libelle'] . "</div>\n";
        echo "<div class=\"entries\" id=\"_" . $_POST['cle'] . "_". $cat . "\">\n";
        foreach ($data['lignes'] as $ligne)
        {
?>
            <div class="facture">
                <span><label>Pièce comptable :</label> <?= $ligne['NumVoucher'] ?></span>
                <span><label>Fournisseur :</label> <?= $ligne['Fournisseur'] ?></span>
                <span><label>Date :</label> <?= $ligne['Date'] ?></span>
                <span><label>TVA :</label> <?= $ligne['TVA'] ?> €</span>
                <span><label>Charge :</label> <?= $ligne['Charges'] ?> €</span>
                <!-- <span><label>Charge :</label> <?= $ligne['Charges'] ?> €</span> -->
                <div><label>Libellé :</label> <?= $ligne['Libelle'] ?></div>
                <div><label>Somme :</label> <span class="somme <?= ($ligne['TTC'] >= 0)?'positif':'negatif' ?>"><?= $ligne['TTC'] ?> €</span></div>
                
                <form class="formulaire" method="post" action="traitement.php">
                    <input type="hidden" name="id_piece" value="<?= $ligne['Index'] ?>">
                    
                    <div style="display: flex; align-items: flex-start; gap: 40px;">
                        <span><label>Statut :</label>
                        <select name="statut_<?= $ligne['Index'] ?>" id="id_statut_<?= $ligne['Index'] ?>" onchange="toggleBySelect(this,'<?= $ligne['Index'] ?>')">
                            <?php
                                foreach($_SESSION['ArrayValidation'] as $selectInfo) {
                                    echo '<option value="' . $selectInfo['numkey'] . '" ' . ($selectInfo['default']?'selected="selected"':'') . '>' . $selectInfo['namekey'] . "</option>\n";
                                }
                            ?> 
                        </select></span>
                        <span style="margin-left:40px"><label for="commentaire<?= $ligne['Index'] ?>">Commentaire :</label><textarea id="commentaire<?= $ligne['Index'] ?>" name="commentaire" rows="1" cols="113"></textarea></span>
                    </div>
                    
                    <div>
                        <label for="url_facture<?= $ligne['Index'] ?>">URL facture :</label>
                        <input class="url_facture" type="url" id="url_facture<?= $ligne['Index'] ?>" name="url_facture" placeholder="--- (s'il n'y a pas de facture)" required>
                    </div>
                    
                    <div id="details_pb_<?= $ligne['Index'] ?>" class="hidden">
                        <div id="id_cause_<?= $ligne['Index'] ?>" class="hidden">
                            <label for="id_textarea_<?= $ligne['Index'] ?>">Cause :</label>
                            <textarea id="id_textarea_<?= $ligne['Index'] ?>" name="cause_textarea_<?= $ligne['Index'] ?>" rows="2" cols="165"></textarea>
                        </div>
                        <div id="id_reafectation_<?= $ligne['Index'] ?>" class="hidden">
                            <h1>Non déveloper : mettre a vérifier et remplisser la cause</h1>
                        </div>
                        <div id="id_deplacement_<?= $ligne['Index'] ?>" class="hidden">
                            <span><label>Cible :</label><select name="destination_<?= $ligne['Index'] ?>" id="id_destination_<?= $ligne['Index'] ?>">
                            <?php
                                foreach ($_SESSION['ArrayKeyComptable'] as $selectInfo) {
                                    echo '<option value="' . $selectInfo['typekey'] . '">' . $selectInfo['shortname'] . "</option>\n";
                                    // echo '<option value="' . $selectInfo['typekey'] . '" ' . ($selectInfo['default']?'selected="selected"':'') . '>' . $selectInfo['namekey'] . "</option>\n";
                                }
                            ?>
                            </select></span><label for="id_regle_textarea_<?= $ligne['Index'] ?>">Règle :</label>
                            <textarea id="id_regle_textarea_<?= $ligne['Index'] ?>" name="regle_textarea_<?= $ligne['Index'] ?>" rows="1" cols="127"></textarea>
                        </div>
                        <div id="id_repartition_<?= $ligne['Index'] ?>" class="hidden">
                            <h1>Non déveloper : mettre a vérifier et remplisser la cause</h1>
                        </div>
                        <div id="id_change_cat_<?= $ligne['Index'] ?>" class="hidden">
                            <h1>Non déveloper : mettre a vérifier et remplisser la cause</h1>
                        </div>
                    </div>
                    <div>
                        <button type="submit">Valider</button>
                    </div>
                </form>
            </div> <!-- Fin div : facture -->
<?php
        } // Fin foeach lignes SousCategorie
        echo "</div> <!-- Fin div : entries -->\n"; // fin div entries
    } // Fin foeach SousCategorie    
?>        

    