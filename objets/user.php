<?php

require_once(__DIR__ . '/database.class.php');
require_once(__DIR__ . '/lots.php');

/**
 * Classe User - Représente un utilisateur du système (Active Record pattern)
 * 
 * Gère les informations d'un utilisateur (contact, lots, rôles) et leur persistance en DB
 * Le mot de passe n'est jamais stocké ici (virtuel, géré par les méthodes statiques)
 */
class User
{
    private int $id;
    private ?string $ident;
    private int $type;           // Bitmask des rôles
    private string $nom;
    private string $prenom;
    private ?string $email;
    private ?string $telephone;
    private ?string $adresse;
    private ?Lots $lots = null;  // Collection de lots (Appartement|Cave|Parking)

    public function __construct(
        int $id,
        ?string $ident,
        int $type,
        string $nom,
        string $prenom,
        ?string $email = null,
        ?string $telephone = null,
        ?string $adresse = null,
        ?Lots $lots = null
    ) {
        $this->id = $id;
        $this->ident = $ident;
        $this->type = $type;
        $this->nom = $nom;
        $this->prenom = $prenom;
        $this->email = $email;
        $this->telephone = $telephone;
        $this->adresse = $adresse;
        $this->lots = $lots;
    }

    // ==========================================
    // MÉTHODES STATIQUES (Active Record)
    // ==========================================

    /**
     * Charge un utilisateur par son ID avec ses lots
     * 
     * @param Database $objdb
     * @param int $id
     * @return User|null
     */
    public static function loadById(Database $objdb, int $id): ?User
    {
        // Requête pour charger les infos utilisateur
        $sql = "SELECT 
                    `id_user`,
                    `ident`,
                    `type_acteur`,
                    `nom`,
                    `email`,
                    `Telephone`,
                    `Adresse`
                FROM `acteurs`
                WHERE `id_user` = :id
                LIMIT 1";
        
        $result = $objdb->execonerow($sql, [':id' => $id]);
        
        if (empty($result)) {
            return null;
        }

        // Parser le champ JSON nom {"BRUT_DATA": "Prénom Nom"}
        list($prenom, $nom) = self::parseNomJson($result['nom']);

        // Parser les champs JSON optionnels
        $email = $result['email'] ?? null;
        $telephone = self::parsePhoneJson($result['Telephone']);
        $adresse = self::parseAddressJson($result['Adresse']);

        // Créer l'objet User
        $user = new User(
            (int)$result['id_user'],
            $result['ident'],
            (int)$result['type_acteur'],
            $nom,
            $prenom,
            $email,
            $telephone,
            $adresse
        );

        // Charger les lots associés via Lots::loadForUser
        $user->lots = Lots::loadForUser($objdb, $user->getId(), true); // Activer logs

        return $user;
    }

    /**
     * Parse le champ JSON nom {"BRUT_DATA": "Prénom Nom"}
     * 
     * @param string|null $nomJson
     * @return array [prenom, nom]
     */
    private static function parseNomJson(?string $nomJson): array
    {
        if (empty($nomJson)) {
            return ['', ''];
        }

        $data = json_decode($nomJson, true);
        if (!isset($data['BRUT_DATA'])) {
            return ['', ''];
        }

        $fullName = trim($data['BRUT_DATA']);
        
        // Séparer prénom et nom (premier mot = prénom, reste = nom)
        $parts = explode(' ', $fullName, 2);
        $prenom = $parts[0] ?? '';
        $nom = $parts[1] ?? '';

        return [$prenom, $nom];
    }

    /**
     * Parse le champ JSON Telephone {"tel": "0612345678"}
     * 
     * @param string|null $phoneJson
     * @return string|null
     */
    private static function parsePhoneJson(?string $phoneJson): ?string
    {
        if (empty($phoneJson)) {
            return null;
        }

        $data = json_decode($phoneJson, true);
        return $data['tel'] ?? null;
    }

    /**
     * Parse le champ JSON Adresse {"adresse": "..."}
     * 
     * @param string|null $addressJson
     * @return string|null
     */
    private static function parseAddressJson(?string $addressJson): ?string
    {
        if (empty($addressJson)) {
            return null;
        }

        $data = json_decode($addressJson, true);
        return $data['adresse'] ?? null;
    }

    /**
     * Vérifie le mot de passe d'un utilisateur
     * 
     * @param Database $objdb
     * @param int $userId
     * @param string $plainPassword Mot de passe en clair
     * @return bool
     */
    public static function verifyPassword(Database $objdb, int $userId, string $plainPassword): bool
    {
        $sql = "SELECT `passwd` FROM `acteurs` WHERE `id_user` = :id LIMIT 1";
        $result = $objdb->execonerow($sql, [':id' => $userId]);
        
        if (empty($result) || empty($result['passwd'])) {
            return false;
        }

        return password_verify($plainPassword, $result['passwd']);
    }

    /**
     * Met à jour le mot de passe d'un utilisateur
     * 
     * @param Database $objdb
     * @param int $userId
     * @param string $newPasswordHash Hash du nouveau mot de passe (déjà hashé)
     * @return bool
     */
    public static function updatePassword(Database $objdb, int $userId, string $newPasswordHash): bool
    {
        $sql = "UPDATE `acteurs` SET `passwd` = :passwd WHERE `id_user` = :id";
        return $objdb->exec($sql, [':id' => $userId, ':passwd' => $newPasswordHash]);
    }

    // ==========================================
    // MÉTHODES D'INSTANCE
    // ==========================================

    /**
     * Sauvegarde les modifications de l'utilisateur en base
     * (Ne gère PAS le mot de passe, utiliser updatePassword())
     * 
     * @param Database $objdb
     * @return bool
     */
    public function save(Database $objdb): bool
    {
        // Reconstituer le JSON nom
        $nomJson = json_encode(['BRUT_DATA' => $this->getFullName()]);

        // Reconstituer les JSON telephone et adresse
        $telephoneJson = $this->telephone ? json_encode(['tel' => $this->telephone]) : null;
        $adresseJson = $this->adresse ? json_encode(['adresse' => $this->adresse]) : null;

        $sql = "UPDATE `acteurs` 
                SET 
                    `nom` = :nom,
                    `email` = :email,
                    `Telephone` = :telephone,
                    `Adresse` = :adresse
                WHERE `id_user` = :id";
        
        $params = [
            ':id' => $this->id,
            ':nom' => $nomJson,
            ':email' => $this->email,
            ':telephone' => $telephoneJson,
            ':adresse' => $adresseJson
        ];

        return $objdb->exec($sql, $params);
    }

    // Getters
    public function getId(): int
    {
        return $this->id;
    }

    public function getIdent(): ?string
    {
        return $this->ident;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function getPrenom(): string
    {
        return $this->prenom;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    /**
     * Retourne la collection de lots
     * 
     * @return Lots|null
     */
    public function getLots(): ?Lots
    {
        return $this->lots;
    }

    /**
     * Retourne le nom complet (Prénom Nom)
     */
    public function getFullName(): string
    {
        return trim($this->prenom . ' ' . $this->nom);
    }

    /**
     * Vérifie si l'utilisateur possède un rôle spécifique
     * Utilise le bitmask pour la vérification
     * 
     * @param int $role Constante Site::LOCAT, Site::PROPRIO, etc.
     * @return bool
     */
    public function hasRole(int $role): bool
    {
        return ($this->type & $role) !== 0;
    }

    /**
     * Vérifie si l'utilisateur possède au moins un des rôles fournis
     * 
     * @param int ...$roles Un ou plusieurs rôles à tester
     * @return bool
     */
    public function hasAnyRole(int ...$roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }
        return false;
    }

    // Setters (uniquement pour mise à jour du profil, pas de setter pour ID/ident/type)
    
    public function setNom(string $nom): void
    {
        $this->nom = $nom;
    }

    public function setPrenom(string $prenom): void
    {
        $this->prenom = $prenom;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function setTelephone(?string $telephone): void
    {
        $this->telephone = $telephone;
    }

    public function setAdresse(?string $adresse): void
    {
        $this->adresse = $adresse;
    }

    /**
     * Mise à jour des informations de contact
     * 
     * @param string|null $email
     * @param string|null $telephone
     * @param string|null $adresse
     */
    public function updateContact(?string $email, ?string $telephone, ?string $adresse): void
    {
        $this->email = $email;
        $this->telephone = $telephone;
        $this->adresse = $adresse;
    }

    /**
     * Méthode magique pour accéder aux propriétés non publiques
     * Permet l'accès à $user->Lots (capital L)
     * 
     * @param string $name
     * @return mixed
     */
    public function __get(string $name)
    {
        if ($name === 'Lots' && $this->lots !== null) {
            return $this->lots;
        }
        return null;
    }

    /**
     * Retourne les lots d'un type spécifique
     * 
     * @param int $typeLot 1=Appartement, 2=Cave, 3=Parking
     * @return array
     */
    public function getLotsByType(int $typeLot): array
    {
        if ($this->lots === null) {
            return [];
        }
        return $this->lots->filterByType($typeLot);
    }

    /**
     * Méthode magique appelée lors de la sérialisation
     * Sérialise toutes les propriétés (pas de Database dans User)
     */
    public function __sleep(): array
    {
        return ['id', 'ident', 'type', 'nom', 'prenom', 'email', 'telephone', 'adresse', 'lots'];
    }

    /**
     * Méthode magique appelée lors de la désérialisation
     * Réinjection de Database dans Lots sera gérée par gestion_site
     */
    public function __wakeup(): void
    {
        // La réinjection de Database dans Lots sera faite par gestion_site
    }

    /**
     * Réinjecte la référence Database dans la collection Lots
     */
    public function setDatabase(Database $db): void
    {
        if ($this->lots !== null) {
            $this->lots->setDatabase($db);
        }
    }
}
