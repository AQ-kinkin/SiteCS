<?php

require('/home/csresip/www/objets/mysql.sessions.php');
require_once('/home/csresip/www/objets/database.class.php');

class Site
{
    public const DROIT_INVITE    = 0;
    public const DROIT_LOCATAIRE = 1;
    public const DROIT_PROPRIO   = 2;
    public const DROIT_CS        = 3;

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



    public function IsAsPriv(int $niv_req): bool
    {
        if (isset($_SESSION['user_type'])) {
            return ((int)$_SESSION['user_type'] >= $niv_req);
        }

        return false;
    }

    /**
     * Vérifie si l'utilisateur est connecté, sinon redirige vers déconnexion
     * @param int $niv_req Niveau de privilège requis
     * @param bool $is_ajax Si true, retourne une erreur HTTP 401 au lieu de rediriger
     */
    public function requireAuth(int $niv_req = self::DROIT_CS, bool $is_ajax = false): void
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
                $now + $this->maxTimeSession,
                '/',
                '',
                isset($_SERVER['HTTPS']),
                true
            );
            $_SESSION['last_activity'] = $now;
        }
    }


    public function connection($ident, $passwd)
    {
        $answer = $this->session->connection($ident, $passwd);

        return $answer;
    }

}
