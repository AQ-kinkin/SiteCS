<nav>

    <ul>

        <li><a href="index.php?page=Home">Acceuil</a></li>

        <?php

            if($objsite->IsAsPriv(3))

            {

                // echo "<li><a href=\"index.php?page=Gestion\">Gestion</a>\n";

                echo "<li><span>Gestion</span>\n";

                    echo "<ul>\n";

                    // echo "<li><a href=\"index.php?page=Gestion&Menu=Bilan\">Les Bilan</a></li>\n";

                    // echo "<li><a href=\"index.php?page=Compta&item=Factures\">Les Factures</a></li>\n";

                    echo "<li><a href=\"index.php?page=Compta&item=Validations\">Validations</a></li>\n";

                    echo "<li><a href=\"index.php?page=Compta&item=Imports\">Importation</a></li>\n";

                    // echo "<li><a href=\"index.php?page=Compta&item=ImportsCtls\">Importation Contrôle</a></li>\n";
                    echo "<li><a href=\"index.php?page=Compta&item=Rapports\">Rapports</a></li>\n";
 
                    echo "<li><a href=\"index.php?page=Compta&Menu=Lots\">Lots</a></li>\n";

                    // echo "<li><a href=\"index.php?page=Gestion&Menu=Treso\">La Tr?sorerie</a></li>\n";

                    // echo "<li><a href=\"index.php?page=Gestion&Menu=Contract\">Les Contrats</a></li>\n";

                    // echo "<li><a href=\"index.php?page=Gestion&Menu=Estimation\">Estimation Charge</a></li>\n";

                    echo "</ul>\n";

			    echo "</li>\n"; 

            }

        ?>

        <li><a href="index.php?page=Disconnect">Déconnection</a></li>

    </ul>

</nav>