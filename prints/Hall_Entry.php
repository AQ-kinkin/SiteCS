<?php
if (!defined('PATH_HOME')) {
    require_once __DIR__ . '/../../bootstrap.php';
}
require PATH_HOME_CS . '/vendor/autoload.php';
require_once( PATH_HOME_CS . '/objets/gestion_site.php' );

$objsite = new Site;
$objsite->open();
$objsite->requireAuth(Site::CS);

require_once(PATH_HOME_CS . '/objets/halls.php');


use Mpdf\Mpdf;

$mpdf = new Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'margin_left' => 12,
    'margin_right' => 12,
    'margin_top' => 30,
    'margin_bottom' => 12,
    'margin_header' => 0,
    'margin_footer' => 0,
]);

if ( ! isset($_GET['hall']) )
{
  $html = <<<HTML
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
</head>
<body>
  <H1>ERROR : Pas de param hall</H1>
</body>
</html>
HTML;

echo $html;
exit();
}
$html = <<<HTML
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
</head>
<body>
  <H1>Merde : test</H1>
</body>
</html>
HTML;


// Il faut ajouter une colone rue ou adresse à la table hall (pour affichier 3 rue millepertuis)
$subtitle = 'Hall ' . $_GET['hall'] . ' Résidence Courdimanche';
$db = new Database();
// echo $html;
// exit();
$halls = new Halls($db, false);
$data = $halls->get_page_hall($_GET['hall']);

$mpdf->SetTitle("Liste des résidents de l'escalier - " . $subtitle );
$mpdf->SetDisplayMode('fullpage');

// HTML + CSS pour reproduire le PDF
$html_header = <<<HTML
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: sans-serif; font-size: 12pt; }
    .title { text-align: center; font-size: 18pt; margin-top: 14pt; font-weight: bold; }
    .subtitle { text-align: center; font-size: 18pt; margin-top: 26pt; margin-bottom: 58pt; }

    .main-table table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .main-table th, .main-table td { border: 1px solid #000; padding: 10pt 8pt; vertical-align: middle;  }
    .main-table th { text-align: center; font-size: 20pt; font-weight: bold; }
    .main-table td { font-weight: bold; font-size: 26pt; margin: 0pt 0pt 0pt 0pt; padding: 0pt 0pt 0pt 0pt; height: 44pt; }

    .col-ref { width: 16%; text-align: center; }
    .col-name { width: 68%; text-align: left; }
    .col-floor { width: 16%; text-align: center; }

    .footer { margin-top: 38pt; }
    .footer-title { text-align: center; font-weight: bold; font-size: 20pt; margin-top: 1pt; }
    .footer-footer { text-align: center; font-size: 16pt; margin-top: 1pt; }
    /* Tableau in footer */
    .footer table { width: 100%; border-collapse: collapse; }
    .footer td { border: none; font-size: 16pt; font-weight: normal; padding: 0; }
    .footer .footer-left { text-align: left; }
    .footer .footer-right { text-align: right; }
  </style>
</head>
<body>

  <div class="title">Liste des résidents de l'escalier</div>
  <div class="subtitle">$subtitle</div>

  <div class="main-table">
    <table>
      <thead>
        <tr>
          <th class="col-ref">Repère</th>
          <th class="col-name">Nom</th>
          <th class="col-floor">ETAGE</th>
        </tr>
      </thead>
      <tbody>
HTML;

      $html_body = '';
      foreach ($data as $row) { 
        $html_body .= "<tr><td class=\"col-ref\">" . htmlspecialchars($row['mark'] ?? '') . '</td><td class="col-name">KOUAMO/PETNKEU</td><td class="col-floor">4-D</td></tr>';
        // <tr><td class="col-ref">304</td><td class="col-name">DIENGDIENDIENDIENGGG</td><td class="col-floor">4-G</td></tr>
        // <tr><td class="col-ref">302</td><td class="col-name">DUARTE NUNES</td><td class="col-floor">3-D</td></tr>
        // <tr><td class="col-ref">300</td><td class="col-name">LORTHOLARY</td><td class="col-floor">3-G</td></tr>
        // <tr><td class="col-ref">298</td><td class="col-name">ARNOUD</td><td class="col-floor">2-D</td></tr>
        // <tr><td class="col-ref">296</td><td class="col-name">ELHESSAK</td><td class="col-floor">2-G</td></tr>
        // <tr><td class="col-ref">294</td><td class="col-name">DIAS PEREIRA</td><td class="col-floor">1-D</td></tr>
        // <tr><td class="col-ref">292</td><td class="col-name">MOURA</td><td class="col-floor">1-G</td></tr>
        // <tr><td class="col-ref">290</td><td class="col-name">LADOUCE</td><td class="col-floor">RC-D</td></tr>
        // <tr><td class="col-ref">288</td><td class="col-name">FERRO</td><td class="col-floor">RC-G</td></tr>
      }

$html_footer = <<<HTML
      </tbody>
    </table>
  </div>

  <div class="footer">
    <div class="footer-title">Conseil syndical N°54</div>
    <table>
      <tr>
        <td class="footer-left">cscourdimanche@netcourrier.com</td>
        <td class="footer-right">Tel : 09 52 49 60 08</td>
      </tr>
    </table>
    <div class="footer-footer">https://cs-residence-courdimanche.ovh/</div>
  </div>

</body>
</html>
HTML;

$mpdf->WriteHTML($html_header . $html_body . $html_footer);

// Génération du PDF
// $outFile = __DIR__ . '/Liste par Escalier.pdf';
// $mpdf->Output($outFile, \Mpdf\Output\Destination::FILE);
$mpdf->Output();

// echo "OK => $outFile\n";
