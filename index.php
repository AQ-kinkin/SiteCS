<?php
    $pathHome = '/home/csresip/www';
	require_once( $pathHome . '/objets/database.class.php');
    require_once( $pathHome . '/objets/gestion_site.php');

    $PathIncludeCss = 'css/connection.css';
    $PathIncludeJs = '';
    $PathIncludePage = $pathHome . '/templates/connection.html';
    $objsite = new Site;
    $objsite->open();

    if (isset($_SESSION['user_id']))
    {
        if( isset($_GET['page']) )
        {
            switch($_GET['page'])
            {
                case "Home":
                    // echo '<link href="css/Home.css" rel="stylesheet" type="text/css">' . "\n";
                    $PathIncludeCss = 'css/home.css';
                    $PathIncludePage = $pathHome . '/templates/home.html';
                    break;
                case "Compta":
                    if( isset($_GET['item']) )
                    {
                        switch($_GET['item'])
                        {
                            case "Factures":
                                // echo '<link href="css/Idees.css" rel="stylesheet" type="text/css">' . "\n";
                                $PathIncludeJs = "js/factures.js";
                                $PathIncludeCss = "css/factures.css";
                                $PathIncludePage = $pathHome . '/compta/factures.php';
                                break;
                            case "Validations":
                                // echo '<link href="css/Idees.css" rel="stylesheet" type="text/css">' . "\n";
                                $PathIncludeJs = "js/validations.js";
                                $PathIncludeCss = "css/validations.css";
                                $PathIncludePage = $pathHome . '/compta/validations_selection.php';
                                break;
                            case "Imports":
                                $PathIncludeJs = "js/imports.js";
                                $PathIncludeCss = "css/imports.css";
                                $PathIncludePage = $pathHome . '/compta/imports_selection.php';
                                break;
                            case "ImportsCtls":
                                $PathIncludeJs = "js/imports.js";
                                $PathIncludeCss = "css/imports.css";
                                $PathIncludePage = $pathHome . '/compta/imports_control.php';
                                break;
                            default:
                                $PathIncludeCss = 'css/error.css';
                                $PathIncludePage = $pathHome . '/templates/error.html';
                                break;
                        }
                    }
                    else
                    {
                        $PathIncludeCss = 'css/error.css';
                        $PathIncludePage = $pathHome . '/templates/error.html';
                    }
                    break;
                case "Disconnect":
                    $objsite->close();
                    $PathIncludeCss = 'css/disconnection.css';
                    $PathIncludePage = $pathHome . '/templates/disconnection.html';
                    break;
                default:
                    $PathIncludeCss = 'css/error.css';
                    $PathIncludePage = $pathHome . '/templates/error.html';
                    break;
            }
        }
        else
        {
            $PathIncludeCss = 'css/home.css';
            $PathIncludePage = $pathHome . '/templates/home.html';
        }
    }
    else
    {
        $pathIndex = getcwd();
        if (  $pathIndex !== $pathHome )
        {
            Header('Location:/');
        }
        else
        {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if ( isset($_POST) && isset($_POST["ident"]) && isset($_POST["password"]) ) {
                    if ( $objsite->connection($_POST["ident"],$_POST["password"]) )
                    {
                        $PathIncludeCss = 'css/home.css';
                        $PathIncludePage = $pathHome . '/templates/home.html';
                    }
                }
            }
        }
    }
?>
<!DOCTYPE html>
<html>
    <head>
        <title>Conseil Syndical</title>
        <link type="text/css" rel="stylesheet" href="css/deco.css">
        <link type="text/css" rel="stylesheet" href="css/sidebar.css">
        <?php
            // echo '<link href="css/Home.css" rel="stylesheet" type="text/css">' . "\n";
            if ( $PathIncludeCss !== '' )
            {
                echo '<link type="text/css" rel="stylesheet" href="' . $PathIncludeCss . '">' . "\n";
            }
            // <link rel="stylesheet" href="css/connection.css">
            // <link rel="stylesheet" href="css/factures.css">
            if ( $PathIncludeJs !== '' )
            {
                echo '<script src="' . $PathIncludeJs . '"></script>' . "\n";
            }
        ?>
    </head>
    <body>
        <div class="sidebar1">
            <?php include("templates/menu.php"); ?>
        </div> <!-- Fin sidebar1 -->
        <div class="container">
            <header>
                <h1>Site du conseil syndical de la résidence courdimanche</h1>
            </header>
            <div class="content">
                <?php
                    include($PathIncludePage);
                    echo "\n";
                ?>
            </div> <!-- Fin content -->
            <footer>
                <table>
                    <tbody width="100%">
                        <tr>
                            <td width="16%"><span><a href="https://www.llgestion.fr" target="_blank">LLGestion</a></span></td>
                            <td width="16%"><span><a href="http://www.lesulis.fr" target="_blank">Mairie des ULIS</a></span></td>
                            <td width="16%"><span><a href="http://www.siom.fr/" target="_blank">SIOM</a></span></td>
                            <td width="16%"><span><a href="http://www.thermulis.fr/" target="_blank">Thermulis</a></span></td>
                            <td width="16%"><span><a href="index.php?page=ContactExt">Contact Externe Util</a></span></td>
                            <td><span><a href="index.php?page=ContactInt">Contact R&eacute;sidence Courdimanche</a></span></td>
                        </tr>
                        <tr>
                            <td colspan="2"><span class="personalisation">Site en cour de d&eacute;velopement</span></td>
                            <td colspan="2"></td>
                            <td colspan="2"><span class="personalisation"><address>Cr&eacute;&eacute; et administr&eacute; par <a href="mailto:alexandre.quinzin@free.fr">Alexandre QUINZIN</a>.</address></span></td>
                        </tr> <!-- colspan="2"  -->
                    </tbody>
                </table>
            </footer>
        </div> <!-- Fin container -->
	</body>
</html>