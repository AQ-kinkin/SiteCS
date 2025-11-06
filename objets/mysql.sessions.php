<?php

require_once(__DIR__ . '/logs.trait.php');
	/*
	CREATE TABLE sessions
	(
		id varchar(32) NOT NULL,
		data text,
		access TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		INDEX idx_access (access)
	);

	+-----------+-----------+------+-----+-------------------+-----------------------------+
	| Field     | Type      | Null | Key | Default           | Extra                       |
	+-----------+-----------+------+-----+-------------------+-----------------------------+
	| id        | varchar(32)|      | PRI |                   |                             |
	| data      | text      | YES  |     | NULL              |                             |
	| access    | timestamp | YES  | MUL | CURRENT_TIMESTAMP | on update CURRENT_TIMESTAMP |
	+-----------+-----------+------+-----+-------------------+-----------------------------+

	*/


class Session implements SessionHandlerInterface
{
	use Logs;
	
    private Database $objdb;

	public function __construct(Database $srcdb)
	{
		$this->objdb = $srcdb;
		
		// Initialiser le système de logs
		$this->PrepareLog('Session');
		// $this->write_info('Constructeur Session appelé - Session en base de données');
		
		#echo "<H1>(constructeur) Session en base de données</H1>";
	}

    public function open($savePath, $sessionName): bool
    {
        // $this->write_info("Session open: savePath=$savePath, sessionName=$sessionName");
        #echo "<H1>(open)'$savePath'</H1><br/><h2>'$sessionName'</h2>";
        // Connexion déjà établie via Database dans le constructeur
        return true;
    }

    public function close(): bool
    {
        #echo "<H1>Session->close()</H1>";
        // Pas besoin de fermer, PDO gère ça automatiquement
        return true;
    }

    public function read($id): string
    {
        // $this->write_info("Session read: id=$id");
        #echo "<H1>(read) Session ID: '$id'</H1>";
        
        // SELECT met à jour automatiquement access grâce à ON UPDATE CURRENT_TIMESTAMP
        // On fait un UPDATE pour forcer la mise à jour du timestamp même sur un simple read
        $sql_touch = "UPDATE `sessions` SET `access` = CURRENT_TIMESTAMP WHERE `id` = :id";
        $this->objdb->exec($sql_touch, [':id' => $id]);
        
        $sql = "SELECT `data` FROM `sessions` WHERE `id` = :id LIMIT 1";
        $params = [':id' => $id];
        $result = $this->objdb->execonerow($sql, $params);
        
        if (!empty($result) && isset($result['data'])) {
            // $this->write_info("Session read: données trouvées pour id=$id");
            return $result['data'];
        }
        
        // $this->write_info("Session read: aucune donnée pour id=$id");
        return '';
    }

    public function write($id, $data): bool
    {
        $this->write_info("Session write: id=$id, data_length=" . strlen($data));
        #echo "<H1>(write) Session ID: '$id'</H1>";
        
        // INSERT ou UPDATE - updated_at se met à jour automatiquement
        $sql = "INSERT INTO `sessions` (`id`, `data`) VALUES (:id, :data) 
                ON DUPLICATE KEY UPDATE `data` = :data";
        $params = [
            ':id' => $id,
            ':data' => $data
        ];
        
        try {
            $this->objdb->exec($sql, $params);
            // $this->write_info("Session write: succès pour id=$id");
            return true;
        } catch (Exception $e) {
            $this->write_info("Session write ERROR: " . $e->getMessage());
            error_log("Session write error: " . $e->getMessage());
            return false;
        }
    }

    public function destroy($id): bool
    {
        $this->write_info("Session destroy: id=$id");
        #echo "<H1>Session->destroy() -- Session ID: '$id'</H1>";
        $sql = "DELETE FROM `sessions` WHERE `id` = :id";
        $params = [':id' => $id];
        
        try {
            $this->objdb->exec($sql, $params);
            $this->write_info("Session destroy: succès pour id=$id");
            return true;
        } catch (Exception $e) {
            $this->write_info("Session destroy ERROR: " . $e->getMessage());
            error_log("Session destroy error: " . $e->getMessage());
            return false;
        }
    }

    public function gc($max_lifetime): int|false
    {
        $this->write_info("Session gc: nettoyage des sessions expirées (max_lifetime=$max_lifetime)");
        #echo "<H1>(gc) Nettoyage des sessions expirées</H1>";
        
        // Supprime les sessions dont access est trop ancien
        $sql = "DELETE FROM `sessions` WHERE `access` < DATE_SUB(NOW(), INTERVAL :max_lifetime SECOND)";
        $params = [':max_lifetime' => $max_lifetime];
        
        try {
            $this->objdb->exec($sql, $params);
            $this->write_info("Session gc: nettoyage terminé");
            return 0; // Retourne le nombre de sessions supprimées (ou 0 si non disponible)
        } catch (Exception $e) {
            $this->write_info("Session gc ERROR: " . $e->getMessage());
            error_log("Session gc error: " . $e->getMessage());
            return false;
        }
    }

    public function connection($ident,$passwd)
    {
        $this->write_info("Connection attempt: ident=$ident");
        $answer = false;

        // echo "<h1>'" .  $ident . "'</h1>";
        // echo "<h1>'" . $passwd . "'</h1>";

        $sql = "SELECT `id_user`,`user_type`,`passwd` FROM `connexion` WHERE ident = LOWER('$ident') AND passwd='$passwd';";
	    #echo "SQL 1 : $sql <br/>";
        $result = $this->objdb->execonerow($sql);
        if ( isset( $result['passwd'] ) )
        {
            // echo "<h3>passwd : " . $passwd . " = " . $result['passwd'] . "</h3><br/>";
            if ( str_contains($passwd, $result['passwd']) )
            {
                $_SESSION['user_id'] = $result['id_user'];
                $_SESSION['user_type'] = $result['user_type'];
                $answer = true;
                $this->write_info("Connection SUCCESS: user_id=" . $result['id_user'] . ", user_type=" . $result['user_type']);
            } else {
                $this->write_info("Connection FAILED: mot de passe incorrect pour ident=$ident");
            }
        } else {
            $this->write_info("Connection FAILED: utilisateur non trouvé pour ident=$ident");
        }

        return $answer;
    }
}	
?>