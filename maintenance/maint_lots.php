<?php

require_once( PATH_HOME_CS . '/objets/database.class.php');
require_once( PATH_HOME_CS . '/objets/gestion_site.php');
require_once( PATH_HOME_CS . '/maintenance/forms/ParkingForm.php');
require_once( PATH_HOME_CS . '/maintenance/forms/CaveForm.php');
require_once( PATH_HOME_CS . '/maintenance/forms/AppartementForm.php');
/**
 * Page de maintenance des lots
 * Permet la saisie et modification des lots par bâtiment et escalier
 */
class MaintLotsPage
{
    use Logs;
    
    private ?Database $db = null;
    private bool $log = false;
    private bool $filtreNonTraites = false;
    private array $batiments = []; // Structure : [nom_batiment => [escaliers]]
    
    public function __construct(bool $trace = false)
    {
        $this->db = new Database();
        $this->log = $trace;
        
        if ($trace) {
            $this->PrepareLog('MaintLots', 'd');
        }
        
        // Récupération du filtre
        $this->filtreNonTraites = isset($_GET['filtre']) && $_GET['filtre'] === 'non_traites';
        
        $this->InfoLog("Construction MaintLotsPage - filtre: " . ($this->filtreNonTraites ? 'OUI' : 'NON'));
        
        // Chargement de la structure bâtiments -> escaliers
        $this->loadBatimentsStructure();
    }
    
    /**
     * Charge la structure des bâtiments et leurs escaliers avec les lots existants
     */
    private function loadBatimentsStructure(): void
    {
        $this->InfoLog("Chargement de la structure bâtiments/escaliers avec lots");
        
        // Requête pour obtenir tous les escaliers avec leurs lots (s'ils existent)
        $whereClause = $this->filtreNonTraites ? "WHERE `lots`.`tantieme` IS NULL" : "";
        $sql = "
            WITH halls_enrichis AS (
            SELECT `halls`.id_hall, `halls`.esc, `batiment`.`nom`
            FROM `halls`
            LEFT JOIN `batiment` ON `halls`.bat = `batiment`.id_batiment
        )
        SELECT COALESCE(halls_enrichis.nom, 'Parking') AS nom, COALESCE(halls_enrichis.esc, 'Parking') AS esc, `lots`.`type_lot`, `lots`.lot, `lots`.`repere`, `lots`.`tantieme`, `lots`.`position_id`
        FROM `lots`
        LEFT JOIN halls_enrichis ON `lots`.position_id = halls_enrichis.id_hall AND `lots`.type_lot in (1,2)
        $whereClause
        ORDER BY nom,esc;
        ";
        
        $rows = $this->db->ExecWithFetchAll($sql);
        
        // Construction de la structure: batiments -> escaliers -> lots
        $halls = []; // Structure temporaire pour regrouper par hall
        
        foreach ($rows as $row) {
            $batNom = $row['nom'];
            $esc = $row['esc'];
            
            // Clé unique pour identifier un escalier
            $hallKey = $batNom . '_' . $esc;
            
            if (!isset($halls[$hallKey])) {
                $halls[$hallKey] = [
                    'nom' => $batNom,
                    'esc' => $esc,
                    'lots' => []
                ];
            }
            
            // Ajout du lot si présent (LEFT JOIN peut retourner NULL)
            if ($row['lot'] !== null) {
                $halls[$hallKey]['lots'][] = [
                    'lot' => $row['lot'],
                    'type_lot' => $row['type_lot'],
                    'repere' => $row['repere'],
                    'tantieme' => $row['tantieme'],
                    'position_id' => $row['position_id']
                ];
            }
        }
        
        // Regroupement par bâtiment
        foreach ($halls as $hallData) {
            $batNom = $hallData['nom'];
            if (!isset($this->batiments[$batNom])) {
                $this->batiments[$batNom] = [];
            }
            $this->batiments[$batNom][] = [
                'esc' => $hallData['esc'],
                'lots' => $hallData['lots']
            ];
        }
        
        $this->InfoLog("Structure chargée : " . count($this->batiments) . " bâtiments");
    }
    
    /**
     * Génère la page complète
     */
    public function render(): string
    {
        $html = $this->renderHeader();
        $html .= $this->renderBatimentTabs();
        $html .= $this->renderContent();
        $html .= $this->renderScripts();
        
        return $html;
    }
    
    /**
     * En-tête de la page
     */
    private function renderHeader(): string
    {
        return '
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance des lots</title>
    <link rel="stylesheet" href="../css/principal.css">
    <link rel="stylesheet" href="../css/maint_lots.css">
</head>
<body>
    <div class="page">
        <div class="header-card">
            <h1>Maintenance des lots</h1>
            <p>Saisie et modification des informations de lots par bâtiment et escalier</p>
        </div>
';
    }
    
    /**
     * Onglets des bâtiments
     */
    private function renderBatimentTabs(): string
    {
        $this->InfoLog("Rendu des onglets bâtiments");
        
        if (empty($this->batiments)) {
            return '<p class="no-data">Aucun bâtiment trouvé</p>';
        }
        
        $html = '<!-- DEBUG: ' . count($this->batiments) . ' bâtiments -->' . "\n";
        $html .= '<div class="tabs-container">' . "\n";
        $html .= '    <div class="tabs-header">' . "\n";
        
        $index = 0;
        foreach ($this->batiments as $batNom => $escaliers) {
            $active = $index === 0 ? 'active' : '';
            $batId = 'bat-' . preg_replace('/[^a-zA-Z0-9]/', '-', $batNom);
            $html .= '        <button class="tab-btn ' . $active . '" data-tab="' . $batId . '">';
            $html .= htmlspecialchars($batNom);
            $html .= '</button>' . "\n";
            $index++;
        }
        
        $html .= '    </div>' . "\n";
        $html .= '    <div class="tabs-content">' . "\n";
        
        $index = 0;
        foreach ($this->batiments as $batNom => $escaliers) {
            $active = $index === 0 ? 'active' : '';
            $batId = 'bat-' . preg_replace('/[^a-zA-Z0-9]/', '-', $batNom);
            $html .= '        <div class="tab-pane ' . $active . '" id="' . $batId . '">' . "\n";
            $html .= $this->renderHallTabs($batNom);
            $html .= '        </div>' . "\n";
            $index++;
        }
        
        $html .= '    </div>' . "\n";
        $html .= '</div>' . "\n";
        
        return $html;
    }
    
    /**
     * Onglets des halls (escaliers) pour un bâtiment
     */
    private function renderHallTabs(string $batimentNom): string
    {
        $this->InfoLog("Rendu des halls pour bâtiment: $batimentNom");
        
        if (!isset($this->batiments[$batimentNom]) || empty($this->batiments[$batimentNom])) {
            return '<p class="no-data">Aucun escalier trouvé pour ce bâtiment</p>';
        }
        
        $escaliers = $this->batiments[$batimentNom];
        
        $html = '<!-- DEBUG: ' . count($escaliers) . ' escaliers pour ' . $batimentNom . ' -->' . "\n";
        $html .= '<div class="halls-tabs">' . "\n";
        $html .= '    <div class="halls-header">' . "\n";
        
        foreach ($escaliers as $index => $esc) {
            $active = $index === 0 ? 'active' : '';
            $html .= '        <button class="hall-btn ' . $active . '" data-hall="hall-' . $esc['esc'] . '">';
            $html .= 'Escalier ' . htmlspecialchars($esc['esc']);
            $html .= '</button>' . "\n";
        }
        
        // Bouton de filtrage
        $filtreClass = $this->filtreNonTraites ? 'active' : '';
        $html .= '        <button class="filter-btn ' . $filtreClass . '" onclick="toggleFiltre()">';
        $html .= '📋 Lots non traités';
        $html .= '</button>' . "\n";
        
        $html .= '    </div>' . "\n";
        $html .= '    <div class="halls-content">' . "\n";
        
        foreach ($escaliers as $index => $esc) {
            $active = $index === 0 ? 'active' : '';
            $html .= '        <div class="hall-pane ' . $active . '" id="hall-' . $esc['esc'] . '">' . "\n";
            $html .= $this->renderFormsForHall($esc);
            $html .= '        </div>' . "\n";
        }
        
        $html .= '    </div>' . "\n";
        $html .= '</div>' . "\n";
        
        return $html;
    }
    
    /**
     * Génère l'affichage des lots par type (horizontal)
     * @param array $escalier Données de l'escalier avec ses lots
     */
    private function renderFormsForHall(array $escalier): string
    {
        $this->InfoLog("Génération des formulaires pour hall " . $escalier['esc']);
        
        $html = '<!-- DEBUG: Formulaires pour hall_id=' . $escalier['esc'] . ' -->' . "\n";
        
        // Séparation des lots par type
        $appartements = [];
        $caves = [];
        $parkings = [];
        
        if (isset($escalier['lots'])) {
            foreach ($escalier['lots'] as $lot) {
                switch ($lot['type_lot']) {
                    case 1: // Appartement
                        $appartements[] = $lot;
                        break;
                    case 2: // Cave
                        $caves[] = $lot;
                        break;
                    case 3: // Parking
                        $parkings[] = $lot;
                        break;
                }
            }
        }
        
        // Section Appartements
        if (!empty($appartements)) {
            $html .= '<div class="lots-section appartements-section">' . "\n";
            $html .= '    <h3>🏠 Appartements</h3>' . "\n";
            $html .= '    <div class="lots-vertical">' . "\n";
            foreach ($appartements as $lot) {
                $html .= '        <div class="lot-card appartement-card">' . "\n";
                $html .= '            <div class="lot-header">Lot ' . $lot['lot'] . '</div>' . "\n";
                $appartementForm = new AppartementForm($lot['lot'], 1, $lot['repere'], $lot['position_id'], $lot['tantieme'], $this->db, $this->log);
                $formHtml = $appartementForm->render();
                if ($lot['tantieme'] !== null) {
                    $formHtml = str_replace(
                        '<button type="button" class="btn btn-save" onclick="submitLotForm(this)">Valider</button>',
                        '<button type="button" class="btn btn-save btn-modify" onclick="submitLotForm(this)">Modifier</button>',
                        $formHtml
                    );
                    $formHtml = str_replace('<input ', '<input disabled ', $formHtml);
                }
                $html .= $formHtml;
                $html .= '        </div>' . "\n";
            }
            $html .= '    </div>' . "\n";
            $html .= '</div>' . "\n";
        } else {
            $html .= '<div class="lots-section hidden">' . "\n";
            //$html .= '    <p class="no-lots">Aucun appartement</p>' . "\n";
            $html .= '</div>' . "\n";
        }
        
        // Section Caves
        if (!empty($caves)) {
            $html .= '<div class="lots-section caves-section">' . "\n";
            $html .= '    <h3>📦 Caves</h3>' . "\n";
            $html .= '    <div class="lots-vertical">' . "\n";
            foreach ($caves as $lot) {
                $html .= '        <div class="lot-card cave-card">' . "\n";
                $html .= '            <div class="lot-header">Lot ' . $lot['lot'] . '</div>' . "\n";
                $caveForm = new CaveForm($lot['lot'], 2, $lot['repere'], $lot['position_id'], $lot['tantieme'], $this->db, $this->log);
                $formHtml = $caveForm->render();
                if ($lot['tantieme'] !== null) {
                    $formHtml = str_replace(
                        '<button type="button" class="btn btn-save" onclick="submitLotForm(this)">Valider</button>',
                        '<button type="button" class="btn btn-save btn-modify" onclick="submitLotForm(this)">Modifier</button>',
                        $formHtml
                    );
                    $formHtml = str_replace('<input ', '<input disabled ', $formHtml);
                }
                $html .= $formHtml;
                $html .= '        </div>' . "\n";
            }
            $html .= '    </div>' . "\n";
            $html .= '</div>' . "\n";
        } else {
            $html .= '<div class="lots-section hidden">' . "\n";
            //$html .= '    <p class="no-lots">Aucune cave</p>' . "\n";
            $html .= '</div>' . "\n";
        }
        
        // Section Parkings
        if (!empty($parkings)) {
            $html .= '<div class="lots-section parkings-section">' . "\n";
            $html .= '    <h3>🚗 Parkings</h3>' . "\n";
            $html .= '    <div class="lots-vertical">' . "\n";
            foreach ($parkings as $lot) {
                $html .= '        <div class="lot-card parking-card">' . "\n";
                $html .= '            <div class="lot-header">Lot ' . $lot['lot'] . '</div>' . "\n";
                $parkingForm = new ParkingForm($lot['lot'], 3, $lot['repere'], $lot['position_id'], $lot['tantieme'], $this->db, $this->log);
                $formHtml = $parkingForm->render();
                if ($lot['tantieme'] !== null) {
                    // Remplacer le bouton Valider par Modifier
                    $formHtml = str_replace(
                        '<button type="button" class="btn btn-save" onclick="submitLotForm(this)">Valider</button>',
                        '<button type="button" class="btn btn-save btn-modify" onclick="submitLotForm(this)">Modifier</button>',
                        $formHtml
                    );
                    // Désactiver les champs
                    $formHtml = str_replace('<input ', '<input disabled ', $formHtml);
                    $formHtml = str_replace('<select ', '<select disabled ', $formHtml);
                }
                $html .= $formHtml;
                $html .= '        </div>' . "\n";
            }
            $html .= '    </div>' . "\n";
            $html .= '</div>' . "\n";
        } else {
            $html .= '<div class="lots-section hidden">' . "\n";
            // $html .= '    <p class="no-lots">Aucun parking</p>' . "\n";
            $html .= '</div>' . "\n";
        }
        
        return $html;
    }
    
    /**
     * Contenu principal
     */
    private function renderContent(): string
    {
        return '';
    }
    
    /**
     * Scripts JavaScript
     */
    private function renderScripts(): string
    {
        return '
    </div>
    <script src="../js/maint_lots.js"></script>
</body>
</html>
';
    }
    
    /**
     * Wrapper conditionnel pour les logs
     */
    private function InfoLog(string $message): void
    {
        if ($this->log === false) return;
        
        $this->write_info($message);
    }
}

// Génération de la page
$page = new MaintLotsPage(false); // Mettre true pour activer les logs
echo $page->render();
