<?php

/* -----------------------------------------------------------------------------------
# Trait de gestion des logs
# Permet d'écrire des logs dans des fichiers horodatés
------------------------------------------------------------------------------------*/
trait Logs {
    private $logsPath;

    protected function PrepareLog(string $identifiant, ?string $consigne = null): void {
        // Déterminer le format de date selon la consigne
        $dateFormat = match($consigne) {
            'i' => 'YmdHi',  // Année Mois Jour Heure Minute
            'd' => 'Ymd',    // Année Mois Jour
            default => 'YmdH' // Année Mois Jour Heure (par défaut)
        };
        
        $this->logsPath = '/home/csresip/www/logs/' . $identifiant . "_ " . date($dateFormat);

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
        $ligne = "[$date] [INFO] $message\n";

        // Écriture dans le fichier (append)
        file_put_contents($this->logsPath, $ligne, FILE_APPEND | LOCK_EX);
    }
    
    protected function write_sql($sql, $params = []): void
    {
        // Format du message SQL
        $date = date('Y-m-d H:i:s');
        $ligne = "[$date] [SQL] $sql\n";
        if (!empty($params)) {
            $ligne .= "[$date] [SQL_PARAMS] " . json_encode($params, JSON_UNESCAPED_UNICODE) . "\n";
        }

        // Écriture dans le fichier (append)
        file_put_contents($this->logsPath, $ligne, FILE_APPEND | LOCK_EX);
    }
    
    protected function write_data($description, $data): void
    {
        // Format du message DATA
        $date = date('Y-m-d H:i:s');
        $ligne = "[$date] [DATA] $description\n";
        $ligne .= "[$date] [DATA] " . print_r($data, true) . "\n";

        // Écriture dans le fichier (append)
        file_put_contents($this->logsPath, $ligne, FILE_APPEND | LOCK_EX);
    }
    
    protected function write_step($step_name): void
    {
        // Format du message STEP
        $date = date('Y-m-d H:i:s');
        $ligne = "\n" . str_repeat('=', 80) . "\n";
        $ligne .= "[$date] [STEP] >>> $step_name <<<\n";
        $ligne .= str_repeat('=', 80) . "\n";

        // Écriture dans le fichier (append)
        file_put_contents($this->logsPath, $ligne, FILE_APPEND | LOCK_EX);
    }
}
