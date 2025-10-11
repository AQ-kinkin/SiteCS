<?php
// Paramètres de connexion (modifie selon ton environnement)
$host = "csresip1501.mysql.db";
$dbname = "csresip1501";
$user = "csresip1501";
$pass = "Iletait1fois1968";

// Requête SQL (doit retourner au moins 8 colonnes)
$sql = "SELECT * FROM `Compta_factures_2025_2026_infos` LIMIT 10";

try {
    // Connexion PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Exécution
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur de connexion ou requête : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Résultat de la requête</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f4f4f4; }
    </style>
</head>
<body>
    <h1>Résultat de la requête</h1>

    <?php if (count($rows) > 0): ?>
        <table>
            <thead>
                <tr>
                    <?php foreach (array_keys($rows[0]) as $col): ?>
                        <th><?= htmlspecialchars($col) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach ($row as $cell): ?>
                            <td><?= htmlspecialchars($cell) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Aucune donnée trouvée.</p>
    <?php endif; ?>
</body>
</html>