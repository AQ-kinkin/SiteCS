<?php

require('/home/csresip/www/objets/mysql.sessions.php');
require_once('/home/csresip/www/objets/database.class.php');
require_once('/home/csresip/www/objets/types_acteur.php');
require_once('/home/csresip/www/objets/types_lot.php');
require_once('/home/csresip/www/objets/halls.php');
require_once('/home/csresip/www/objets/user.php');

class Site
{
    // Constantes de types d'acteurs (bitmask)
    public const LOCAT = 1;      // Locataire
    public const PROPRIO = 2;    // Propriétaire
    public const CS = 4;         // Conseil Syndical
    public const SYNDIC = 8;     // Syndic de copropriété
    public const AGENCE = 16;    // Agence de gérance
    public const SCT = 32;       // Société
    public const ACTIO = 64;     // Représentant de société

    private Session $session;
    private Database $objdb;
    
    // Durées de gestion de session (lues depuis session.cookie_lifetime)
    private readonly int $maxTimeSession;      // Durée max d'inactivité
    private readonly int $refreshTimeSession;  // Intervalle de refresh du cookie (53% du max)

    public function __construct()
    {
        $this->objdb = new Database();
        
        // Initialiser les durées depuis la config PHP
        $cookieLifetime = ini_get('session.cookie_lifetime');
        $this->maxTimeSession = (int)$cookieLifetime;
        $this->refreshTimeSession = (int)($this->maxTimeSession * 0.53);
        
        $this->init();
    }

    private function init()
    {
        $this->session = new Session($this->objdb);
        session_set_save_handler($this->session, true);
    }

    public function destroy()
    {
        unset($objdb);
    }

    public function getDB(): Database
    {
        return $this->objdb;
    }

    /**
     * Méthode magique pour accéder aux propriétés dynamiques avec cache en session
     * 
     * Usage:
     *   $objsite->typesActeur  // Retourne TypesActeur (cached)
     *   $objsite->user          // Retourne User (cached)
     */
    public function __get(string $name)
    {
        switch ($name) {
            case 'typesActeur':
                // Vérifier si TypesActeur existe en session
                if (!isset($_SESSION['__typesActeur'])) {
                    $_SESSION['__typesActeur'] = new TypesActeur($this->objdb, true);
                } else {
                    // Réinjecter Database après désérialisation
                    $typesActeur = $_SESSION['__typesActeur'];
                    $reflection = new ReflectionClass($typesActeur);
                    $property = $reflection->getProperty('db');
                    $property->setAccessible(true);
                    $property->setValue($typesActeur, $this->objdb);
                    
                    // Réinitialiser logsPath après désérialisation
                    $typesActeur->reinitLogs();
                }
                return $_SESSION['__typesActeur'];
            
            case 'typesLot':
                // Vérifier si TypesLot existe en session
                if (!isset($_SESSION['__typesLot'])) {
                    $_SESSION['__typesLot'] = new TypesLot($this->objdb, true);
                } else {
                    // Réinjecter Database après désérialisation
                    $typesLot = $_SESSION['__typesLot'];
                    $reflection = new ReflectionClass($typesLot);
                    $property = $reflection->getProperty('db');
                    $property->setAccessible(true);
                    $property->setValue($typesLot, $this->objdb);
                    
                    // Réinitialiser logsPath après désérialisation
                    $typesLot->reinitLogs();
                }
                return $_SESSION['__typesLot'];
            
            case 'halls':
                // Vérifier si Halls existe en session
                if (!isset($_SESSION['__halls'])) {
                    $_SESSION['__halls'] = new Halls($this->objdb, true);
                } else {
                    // Réinjecter Database après désérialisation
                    $halls = $_SESSION['__halls'];
                    $reflection = new ReflectionClass($halls);
                    $property = $reflection->getProperty('db');
                    $property->setAccessible(true);
                    $property->setValue($halls, $this->objdb);
                    
                    // Réinitialiser logsPath après désérialisation
                    $halls->reinitLogs();
                }
                return $_SESSION['__halls'];
            
            case 'user':
                // Vérifier si User existe en session
                if (!isset($_SESSION['__user'])) {
                    // Charger l'utilisateur uniquement si connecté
                    if (isset($_SESSION['user_id'])) {
                        $_SESSION['__user'] = User::loadById($this->objdb, (int)$_SESSION['user_id']);
                    } else {
                        return null;
                    }
                } else {
                    // Réinjecter Database dans Lots après désérialisation
                    $_SESSION['__user']->setDatabase($this->objdb);
                }
                return $_SESSION['__user'];
            
            default:
                trigger_error("Property {$name} does not exist", E_USER_WARNING);
                return null;
        }
    }

    public function open()
    {
        session_start();
        
        // Initialiser le compteur d'activité si nouvelle session
        if (!isset($_SESSION['last_activity'])) {
            $_SESSION['last_activity'] = time();
        }
    }

    public function close()
    {
        session_unset();
        session_destroy();
        unset($this->session);

        $this->init();
    }



    /**
     * Vérifie si l'utilisateur possède au moins un des privilèges requis
     * Supporte plusieurs formats :
     *   - IsAsPriv(Site::CS)                    // Un seul rôle
     *   - IsAsPriv(Site::CS, Site::SYNDIC)     // Plusieurs rôles (OR)
     *   - IsAsPriv([Site::CS, Site::SYNDIC])   // Tableau de rôles (OR)
     * 
     * @param int|array ...$required_types Un ou plusieurs types requis
     * @return bool True si l'utilisateur a au moins un des rôles
     */
    public function IsAsPriv(int|array ...$required_types): bool
    {
        if (!isset($_SESSION['user_type'])) {
            return false;
        }
        
        $userType = (int)$_SESSION['user_type'];
        
        // Cas 1 : Appelé avec un tableau : IsAsPriv([Site::CS, Site::SYNDIC])
        if (count($required_types) === 1 && is_array($required_types[0])) {
            $required_types = $required_types[0];
        }
        
        // Tester chaque type requis avec OR logique (masque binaire)
        foreach ($required_types as $requiredType) {
            if (($userType & $requiredType) !== 0) {
                return true; // Au moins un match
            }
        }
        
        return false; // Aucun match
    }

    /**
     * Vérifie si l'utilisateur est connecté, sinon redirige vers déconnexion
     * @param int $niv_req Niveau de privilège requis
     * @param bool $is_ajax Si true, retourne une erreur HTTP 401 au lieu de rediriger
     */
    public function requireAuth(int $niv_req = self::CS, bool $is_ajax = false): void
    {
        // Vérifier si la session existe
        $session_active = (session_status() === PHP_SESSION_ACTIVE);
        $user_connected = isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
        
        if (!$session_active || !$user_connected) {
            // Session expirée ou utilisateur non connecté
            if ($is_ajax) {
                http_response_code(401);
                echo json_encode(['error' => 'Session expirée', 'redirect' => '/?page=Disconnect&reason=expired']);
                exit;
            } else {
                header('Location: /?page=Disconnect&reason=expired');
                exit;
            }
        }
        
        // Vérifier l'inactivité (destroy si > maxTimeSession)
        if (isset($_SESSION['last_activity'])) {
            $inactivity = time() - $_SESSION['last_activity'];
            
            if ($inactivity > $this->maxTimeSession) {
                session_unset();
                session_destroy();
                
                if ($is_ajax) {
                    http_response_code(401);
                    echo json_encode(['error' => 'Session expirée', 'redirect' => '/?page=Disconnect&reason=expired']);
                    exit;
                } else {
                    header('Location: /?page=Disconnect&reason=expired');
                    exit;
                }
            }
        }
        
        // Vérifier les privilèges
        if (!$this->IsAsPriv($niv_req)) {
            if ($is_ajax) {
                http_response_code(403);
                echo json_encode(['error' => 'Droits insuffisants']);
                exit;
            } else {
                header('Location: /?page=Disconnect&reason=unauthorized');
                exit;
            }
        }
        
        // Refresh du cookie si inactivité ≥ 20 min
        $this->refreshSessionActivity();
    }

    /**
     * Refresh le compteur d'activité et prolonge le cookie si nécessaire
     */
    private function refreshSessionActivity(): void
    {
        $now = time();
        $inactivity = $now - $_SESSION['last_activity'];
        
        // Refresh cookie si ≥ refreshTimeSession d'inactivité
        if ($inactivity >= $this->refreshTimeSession) {
            setcookie(
                session_name(), 
                session_id(), 
                [
                    'expires' => $now + $this->maxTimeSession,
                    'path' => '/',
                    'domain' => '',
                    'secure' => isset($_SERVER['HTTPS']),
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]
            );
            $_SESSION['last_activity'] = $now;
        }
    }


    public function connection($ident, $passwd)
    {   
        // Vérifier que l'identifiant n'est pas vide ou null (accepte '0')
        if ($ident === null || $ident === '') {
            return false;
        }
        
        return $this->session->connection($ident, $passwd);
    }

}
