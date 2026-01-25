<?php

require_once(__DIR__ . '/logs.trait.php');

/* -----------------------------------------------------------------------------------
# Classe Rapport
# Classe de base pour la gestion des rapports avec système de logs
------------------------------------------------------------------------------------*/
class Rapport {
    use Logs;
    
    protected bool $log;
    protected string $fullPathRapport;

    /* -----------------------------------------------------------------------------------
    # Constructeur
    # @param string $logPathRapport - Chemin du répertoire des logs
    # @param string $nameRapport - Nom du rapport
    # @param bool $trace - Active/désactive les logs (false par défaut)
    ------------------------------------------------------------------------------------*/
    public function __construct(string $logPathRapport, string $nameRapport, bool $trace = false)
    {
        $this->fullPathRapport = $logPathRapport . $nameRapport;
        $this->log = $trace;
        
        if ($trace) {
            $this->PrepareLog('rapport_log', 'i');
            $this->write_info('full path du rapport : ' . $this->fullPathRapport);
        }
    }

    /* -----------------------------------------------------------------------------------
    # Function InfoLog
    # Écrit un message d'information dans les logs si activés
    # @param string $message - Message à logger
    ------------------------------------------------------------------------------------*/
    protected function InfoLog(string $message): void
    {
        if ($this->log === false) return;
        $this->write_info($message);
    }

    /* -----------------------------------------------------------------------------------
    # Function write_message
    # Écrit un message directement dans le fichier de logs
    # @param string $message - Message à écrire
    ------------------------------------------------------------------------------------*/
    protected function write_message(string $message): void
    {
        $date = date('Y-m-d H:i:s');
        $ligne = "[$date] $message\n";
        file_put_contents($this->logsPath, $ligne, FILE_APPEND | LOCK_EX);
    }

    /* -----------------------------------------------------------------------------------
    # Function write_rapport
    # Écrit le contenu du rapport dans le fichier
    # @param string $content - Contenu à écrire
    ------------------------------------------------------------------------------------*/
    protected function write_rapport(string $content): void
    {
        file_put_contents($this->fullPathRapport, $content, FILE_APPEND | LOCK_EX);
    }
}