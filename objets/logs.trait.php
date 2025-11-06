<?php

/* -----------------------------------------------------------------------------------
# Trait de gestion des logs
# Permet d'écrire des logs dans des fichiers horodatés
------------------------------------------------------------------------------------*/
trait Logs {
    private $logsPath;

    protected function PrepareLog(string $identifiant): void {
        $this->logsPath = '/home/csresip/www/logs/' . $identifiant . "_ " . date('YmdH');

        // Créer le dossier s'il n'existe pas
        $dossier = dirname($this->logsPath);
        if (!is_dir($dossier)) {
            mkdir($dossier, 0775, true);
        }
    }

    protected function write_info($message): void
    {
        // Format du message (date + contenu)
        $date = date('Y-m-d H:i:s');
        $ligne = "[$date] [Info] $message\n";

        // Écriture dans le fichier (append)
        file_put_contents($this->logsPath, $ligne, FILE_APPEND | LOCK_EX);
    }
}
