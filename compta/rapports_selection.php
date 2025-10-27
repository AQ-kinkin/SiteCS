<?php
require_once __DIR__ . '/../objets/gestion_site.php';
if ( !isset($objsite) || !$objsite->IsAsPriv(Site::DROIT_CS) ) {
    Header('Location:/');
}
require_once __DIR__ . '/../objets/compta_imports.php';

$objimport = new Compta_Imports($objsite->getDB(), true);

// Affichage du formulaire de sélection de l'année (déjà présent dans votre code)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedYear = $_POST['year'];

    // Étape 3 : Boucle sur $_SESSION['ArrayKeyComptable']
    if (isset($_SESSION['ArrayKeyComptable'])) {
        $elements = [];
        foreach ($_SESSION['ArrayKeyComptable'] as $key) {
            // Étape 4 : Sélection des éléments non validés
            $query = "SELECT * FROM table_elements WHERE key_comptable = :key AND validated = 0 ORDER BY libele, id";
            // Préparer et exécuter la requête ici
            // Exemple avec PDO
            $stmt = $pdo->prepare($query);
            $stmt->execute(['key' => $key]);
            $elements = array_merge($elements, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        // Étape 5 : Préparer le mail
        $mailContent = "Rapport pour l'année $selectedYear\n\n";
        foreach ($elements as $element) {
            $mailContent .= "Élément : " . $element['libele'] . "\n";
        }

        // Étape 6 : Envoyer le mail
        $to = "destinataire@example.com";
        $subject = "Rapport pour l'année $selectedYear";
        $headers = "From: expéditeur@example.com";

        if (mail($to, $subject, $mailContent, $headers)) {
            echo "Mail envoyé avec succès.";
        } else {
            echo "Échec de l'envoi du mail.";
        }
    } else {
        echo "Aucune clé comptable trouvée.";
    }
} else {
    $objimport->displayYearSelectionForm();
}