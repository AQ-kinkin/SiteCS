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

    // private Compta_Imports $objImports;



    public function __construct()

    {

        $this->objdb = new Database();

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
    }


    public function connection($ident, $passwd)
    {
        $answer = $this->session->connection($ident, $passwd);

        return $answer;
    }


}
