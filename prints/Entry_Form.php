<?php
if (!defined('PATH_HOME')) {
    require_once __DIR__ . '/../../bootstrap.php';
}
require PATH_HOME_CS . '/vendor/autoload.php';

use Mpdf\Mpdf;

$mpdf = new Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'margin_left' => 22,
    'margin_right' => 22,
    'margin_top' => 25,
    'margin_bottom' => 22,
    'margin_header' => 0,
    'margin_footer' => 0,
]);

$mpdf->SetTitle("Liste des résidents de l'escalier - 3 rue des Millepertuis");
$mpdf->SetDisplayMode('fullpage');

// HTML + CSS pour reproduire le PDF
$html = <<<HTML
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: sans-serif; font-size: 12pt; }
    .title { text-align: center; font-weight: 700; font-size: 20pt; margin-top: 0; }
    .subtitle { text-align: center; font-weight: 700; font-size: 14pt; margin-top: 6pt; margin-bottom: 18pt; }

    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    th, td { border: 1px solid #000; padding: 10pt 8pt; vertical-align: middle; }
    th { font-weight: 700; text-align: center; font-size: 12pt; }
    td { font-weight: 700; font-size: 16pt; }

    .col-ref { width: 18%; text-align: center; }
    .col-name { width: 62%; text-align: left; }
    .col-floor { width: 20%; text-align: center; }

    .footer { margin-top: 18pt; }
    .footer-title { text-align: center; font-weight: 700; font-size: 14pt; margin-top: 18pt; }
    .footer-line { width: 100%; }
    .footer-left { float: left; font-weight: 700; font-size: 12pt; }
    .footer-right { float: right; font-weight: 700; font-size: 12pt; }
    .clearfix { clear: both; }
  </style>
</head>
<body>

  <div class="title">Liste des résidents de l'escalier</div>
  <div class="subtitle">3 rue des Millepertuis</div>

  <table>
    <thead>
      <tr>
        <th class="col-ref">Repère</th>
        <th class="col-name">Nom</th>
        <th class="col-floor">ETAGE</th>
      </tr>
    </thead>
    <tbody>
      <tr><td class="col-ref">306</td><td class="col-name">KOUAMO/PETNKEU</td><td class="col-floor">4-D</td></tr>
      <tr><td class="col-ref">304</td><td class="col-name">DIENG</td><td class="col-floor">4-G</td></tr>
      <tr><td class="col-ref">302</td><td class="col-name">DUARTE NUNES</td><td class="col-floor">3-D</td></tr>
      <tr><td class="col-ref">300</td><td class="col-name">LORTHOLARY</td><td class="col-floor">3-G</td></tr>
      <tr><td class="col-ref">298</td><td class="col-name">ARNOUD</td><td class="col-floor">2-D</td></tr>
      <tr><td class="col-ref">296</td><td class="col-name">ELHESSAK</td><td class="col-floor">2-G</td></tr>
      <tr><td class="col-ref">294</td><td class="col-name">DIAS PEREIRA</td><td class="col-floor">1-D</td></tr>
      <tr><td class="col-ref">292</td><td class="col-name">MOURA</td><td class="col-floor">1-G</td></tr>
      <tr><td class="col-ref">290</td><td class="col-name">LADOUCE</td><td class="col-floor">RC-D</td></tr>
      <tr><td class="col-ref">288</td><td class="col-name">FERRO</td><td class="col-floor">RC-G</td></tr>
    </tbody>
  </table>

  <div class="footer">
    <div class="footer-title">Conseil syndical N°54</div>

    <div class="footer-line">
      <div class="footer-left">cscourdimanche@netcourrier.com</div>
      <div class="footer-right">Tel : 09 52 49 60 08</div>
      <div class="clearfix"></div>
    </div>
  </div>

</body>
</html>
HTML;

$mpdf->WriteHTML($html);

// Génération du PDF
$outFile = __DIR__ . '/Liste par Escalier.pdf';
$mpdf->Output($outFile, \Mpdf\Output\Destination::FILE);

echo "OK => $outFile\n";
