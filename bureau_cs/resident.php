<?php

require_once( PATH_HOME_CS . '/objets/gestion_site.php' );
$objsite->requireAuth(Site::CS);

require_once(PATH_HOME_CS . '/objets/halls.php');

$db = new Database();
$halls = new Halls($db, false);

$halls_list = $halls->get_name_halls();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des halls et appartements</title>
    <link rel="stylesheet" href="../css/principal.css">
    <link rel="stylesheet" href="../css/maint_lots.css">
</head>
<body>
    <div class="page">
        <div class="header-card">
            <h1>Gestion des halls et appartements</h1>
            <p>Affichage des appartements par hall</p>
        </div>

        <?php if (empty($halls_list)): ?>
            <p class="no-data">Aucun hall trouvé</p>
        <?php else: ?>
            <div class="tabs-container">
                <div class="tabs-header">
                    <?php foreach ($halls_list as $index => $id_hall): ?>
                        <button class="tab-btn <?php echo $index === 0 ? 'active' : ''; ?>" data-tab="hall-<?php echo $id_hall; ?>">
                            Hall <?php echo $id_hall; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <div class="tabs-content">
                    <?php foreach ($halls_list as $index => $id_hall): ?>
                        <div class="tab-pane <?php echo $index === 0 ? 'active' : ''; ?>" id="hall-<?php echo $id_hall; ?>">
                            <?php
                            $data = $halls->get_page_hall($id_hall);
                            ?>
                            <div class="hall-content">
                                <h3>Appartements du Hall <?php echo $id_hall; ?></h3>
                                <?php if (empty($data)): ?>
                                    <p class="no-data">Aucun appartement trouvé pour ce hall</p>
                                <?php else: ?>
                                    <table class="appartements-table">
                                        <thead>
                                            <tr>
                                                <th>Repère</th>
                                                <th>Nom</th>
                                                <th>Étage</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($data as $row): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($row['mark'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($row['name'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($row['floor'] ?? ''); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <script src="../js/resident.js"></script>
</body>
</html>