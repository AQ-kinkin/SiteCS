<?php

require_once(__DIR__ . '/rapport.php');

/* -----------------------------------------------------------------------------------
# Classe Rapport_Import
# Gestion des rapports d'importation avec différents types de lignes
------------------------------------------------------------------------------------*/
class Rapport_Import extends Rapport {
    
    // Constantes pour les types de lignes
    const NEW = 'new';
    const DELETE = 'delete';
    const FOUND = 'found';
    const CONFLIT = 'conflit';
    const ERROR = 'error';
    
    // Propriété pour stocker les lignes du rapport
    protected array $Lines = [];
    protected array $types = [];

    /* -----------------------------------------------------------------------------------
    # Constructeur
    # @param string $logPathRapport - Chemin du fichier de rapport
    # @param string $nameRapport - Nom du rapport
    # @param bool $trace - Active/désactive les logs (false par défaut)
    ------------------------------------------------------------------------------------*/
    public function __construct(string $logPathRapport, string $nameRapport, bool $trace = false)
    {
        parent::__construct($logPathRapport, $nameRapport, $trace);
        
        // Création du tableau de types
        $this->types = [self::NEW, self::FOUND, self::CONFLIT, self::DELETE, self::ERROR];

        // Initialisation du tableau Lines pour chaque type
        $this->Lines[self::NEW] = ['titre' => '', 'lignes' => []];
        $this->Lines[self::DELETE] = ['titre' => '', 'lignes' => []];
        $this->Lines[self::FOUND] = ['titre' => '', 'lignes' => []];
        $this->Lines[self::CONFLIT] = ['titre' => '', 'lignes' => []];
    }

    /* -----------------------------------------------------------------------------------
    # Function Add_Ligne
    # Ajoute une ligne dans la catégorie passé en paramètre
    # @param string $type - Type de rapport (NEW, DELETE, FOUND, CONFLIT, ...)
    # @param string $ligne - Ligne à ajouter
    ------------------------------------------------------------------------------------*/
    private function Add_Ligne(string $type, string $ligne): void
    {
        $this->Lines[$type]['lignes'][] = preg_replace('/\s+/', ' ', $ligne);
    }

    /* -----------------------------------------------------------------------------------
    # Function Add_Ligne_new
    # Ajoute une ligne dans la catégorie NEW
    # @param string $ligne - Ligne à ajouter
    ------------------------------------------------------------------------------------*/
    public function add_ligne_next_step(string $ligne): void
    {
        $this->Add_Ligne(self::NEW, $ligne);
        $this->InfoLog("Ligne NEXT STEP ajoutée: $ligne");
    }
    
    /* -----------------------------------------------------------------------------------
    # Function Add_Ligne_new
    # Ajoute une ligne dans la catégorie NEW
    # @param string $ligne - Ligne à ajouter
    ------------------------------------------------------------------------------------*/
    public function add_ligne_new(string $ligne): void
    {
        $this->Add_Ligne(self::NEW, $ligne);
        $this->InfoLog("Ligne NEW ajoutée: $ligne");
    }

    /* -----------------------------------------------------------------------------------
    # Function Add_Ligne_delete
    # Ajoute une ligne dans la catégorie DELETE
    # @param string $ligne - Ligne à ajouter
    ------------------------------------------------------------------------------------*/
    public function Add_Ligne_delete(string $ligne): void
    {
        $this->Add_Ligne(self::DELETE, $ligne);
        $this->InfoLog("Ligne DELETE ajoutée: $ligne");
    }

    /* -----------------------------------------------------------------------------------
    # Function Add_Ligne_found
    # Ajoute une ligne dans la catégorie FOUND
    # @param string $ligne - Ligne à ajouter
    ------------------------------------------------------------------------------------*/
    public function Add_Ligne_found(string $ligne): void
    {
        $this->Add_Ligne(self::FOUND, $ligne);
        $this->InfoLog("Ligne FOUND ajoutée: $ligne");
    }

    /* -----------------------------------------------------------------------------------
    # Function Add_Ligne_conflit
    # Ajoute une ligne dans la catégorie CONFLIT
    # @param string $ligne - Ligne à ajouter
    ------------------------------------------------------------------------------------*/
    public function add_ligne_conflit(string $ligne): void
    {
        $this->Add_Ligne(self::CONFLIT, $ligne);
        $this->InfoLog("Ligne CONFLIT ajoutée: $ligne");
    }

    /* -----------------------------------------------------------------------------------
    # Function Add_Ligne_error
    # Ajoute une ligne dans la catégorie ERROR
    # @param string $ligne - Ligne à ajouter
    ------------------------------------------------------------------------------------*/
    public function Add_Ligne_error(string $ligne): void
    {
        $this->Add_Ligne(self::ERROR, $ligne);
        $this->InfoLog("Ligne ERROR ajoutée: $ligne");
    }

    /* -----------------------------------------------------------------------------------
    # Function Add_Titre
    # Ajoute un titre pour un type donné
    # @param string $type - Type de rapport (NEW, DELETE, FOUND, CONFLIT)
    # @param string $titre - Titre à ajouter
    ------------------------------------------------------------------------------------*/
    public function Add_Titre(string $type, string $titre): void
    {
        if (isset($this->Lines[$type])) {
            $this->Lines[$type]['titre'] = $titre;
            $this->InfoLog("Titre ajouté pour $type: $titre");
        } else {
            $this->InfoLog("Erreur: Type '$type' invalide pour Add_Titre");
        }
    }

    /* -----------------------------------------------------------------------------------
    # Function printRapport
    # Écrit tous les types dans le fichier de rapport
    ------------------------------------------------------------------------------------*/
    public function printRapport(): void
    {
        $this->InfoLog("Génération du rapport...");
        
        // En-tête du rapport
        $rapport = str_repeat('=', 80) . "\n";
        $rapport .= "Date: " . date('Y-m-d H:i:s') . "\n";
        $rapport .= str_repeat('=', 80) . "\n\n";
        
        foreach ($this->types as $type) {
            if (!empty($this->Lines[$type]['lignes']) || !empty($this->Lines[$type]['titre'])) {
                // Afficher le titre si présent
                if (!empty($this->Lines[$type]['titre'])) {
                    $rapport .= "\n" . str_repeat('-', 80) . "\n";
                    $rapport .= strtoupper($type) . ": " . $this->Lines[$type]['titre'] . "\n";
                    $rapport .= str_repeat('-', 80) . "\n";
                } else {
                    $rapport .= "\n" . str_repeat('-', 80) . "\n";
                    $rapport .= strtoupper($type) . "\n";
                    $rapport .= str_repeat('-', 80) . "\n";
                }

                // Afficher les lignes
                if (!empty($this->Lines[$type]['lignes'])) {
                    foreach ($this->Lines[$type]['lignes'] as $index => $ligne) {
                        $rapport .= ($index + 1) . ". " . $ligne . "\n";
                    }
                    $rapport .= "\nTotal: " . count($this->Lines[$type]['lignes']) . " ligne(s)\n";
                } else {
                    $rapport .= "Aucune ligne\n";
                }
            }
        }

        // Pied de rapport
        $rapport .= "\n" . str_repeat('=', 80) . "\n";
        $rapport .= "FIN DU RAPPORT\n";
        $rapport .= str_repeat('=', 80) . "\n";

        // Écriture du rapport dans le fichier
        $this->write_rapport($rapport);
        $this->InfoLog("Rapport généré avec succès dans: " . $this->fullPathRapport);
    }
}
