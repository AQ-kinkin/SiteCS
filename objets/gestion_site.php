<?php
require( '/home/csresip/www/objets/mysql.sessions.php');
require_once( '/home/csresip/www/objets/database.class.php');


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
        // echo "<H1>Site->close()</H1>";
        session_unset();
        session_destroy();
        unset($this->session);
        $this->init();
    }

    public function IsAsPriv(int $niv_req): bool
    {
		if ( isset($_SESSION['user_type']) )
        {
            return ((int)$_SESSION['user_type'] >= $niv_req);
        }

        return false;
    }

    private function write($id, $data): bool
    {
		$file = "$this->savePath/sess_$id";
        #echo "<H1>(write)'$file'</H1>";
        return file_put_contents($file, $data) !== false;
    }

    public function connection($ident,$passwd)
    {
        $answer = $this->session->connection($ident,$passwd);

        return $answer;
    }

    // public function LoadImports():Compta_Imports
    // {
    //     if ( !isset($this->objImports) ) $this->objImports = new Compta_Imports();
    //     return $this->objImports; 
    // }
}	
?>