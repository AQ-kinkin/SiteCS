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
  
        // Debug Log
		// $this->write_info('Constructeur Session appelé - Session en base de données');
	}

    public function open($savePath, $sessionName): bool
    {
        // Connexion déjà établie via Database dans le constructeur

        // Debug Log
        // $this->write_info("Session open: savePath=$savePath, sessionName=$sessionName");

        return true;
    }

    public function close(): bool
    {
        // Pas besoin de fermer, PDO gère ça automatiquement

        // Debug Log
        // $this->write_info("Session open: savePath=$savePath, sessionName=$sessionName");

        return true;
    }

    public function read($id): string
    {
        // Debug Log
        $this->write_info("Session read: id=$id");
        
        // Vérifier si la session existe et son âge
        $sql = "SELECT `data`, TIMESTAMPDIFF(SECOND, `access`, NOW()) as age_seconds FROM `sessions` WHERE `id` = :id LIMIT 1";
        $params = [':id' => $id];
        $result = $this->objdb->execonerow($sql, $params);
        
        if (!empty($result) && isset($result['data'])) {
            $max_lifetime = ini_get('session.gc_maxlifetime');
            
            // Si la session est trop vieille, la détruire
            if ($result['age_seconds'] > $max_lifetime) {
                $this->write_info("Session read: session expirée (âge: {$result['age_seconds']}s, max: {$max_lifetime}s)");
                $this->destroy($id);
                return '';
            }
            
            // Session valide : mettre à jour le timestamp
            $sql_touch = "UPDATE `sessions` SET `access` = CURRENT_TIMESTAMP WHERE `id` = :id";
            $this->objdb->exec($sql_touch, [':id' => $id]);
            
            // Debug Log
            // $this->write_info("Session read: données trouvées pour id=$id");
            return $result['data'];
        }
        
        // Debug Log
        // $this->write_info("Session read: aucune donnée pour id=$id");
        return '';
    }

    public function write($id, $data): bool
    {
        // Debug Log
        $this->write_info("Session write: id=$id, data_length=" . strlen($data));
        
        // INSERT ou UPDATE - updated_at se met à jour automatiquement
        $sql = "INSERT INTO `sessions` (`id`, `data`) VALUES (:id, :data) 
                ON DUPLICATE KEY UPDATE `data` = :data";
        $params = [
            ':id' => $id,
            ':data' => $data
        ];
        
        try {
            $this->objdb->exec($sql, $params);
            // Debug Log
            // $this->write_info("Session write: succès pour id=$id");
            return true;
        } catch (Exception $e) {
            $message = $e->getMessage();
            $this->write_info("Session write ERROR: " . $message);
            error_log("Session write error: " . $message);
            return false;
        }
    }

    public function destroy($id): bool
    {
        // Debug Log
        $this->write_info("Session destroy: id=$id");
        
        $sql = "DELETE FROM `sessions` WHERE `id` = :id";
        $params = [':id' => $id];
        
        try {
            $this->objdb->exec($sql, $params);
            $this->write_info("Session destroy: succès pour id=$id");
            return true;
        } catch (Exception $e) {
            $message = $e->getMessage();
            $this->write_info("Session destroy ERROR: " . $message());
            error_log("Session destroy error: " . $message);
            return false;
        }
    }

    public function gc($max_lifetime): int|false
    {
        // Debug Log
        $this->write_info("Session gc: nettoyage des sessions expirées (max_lifetime=$max_lifetime)");
        
        // Supprime les sessions dont access est trop ancien
        $sql = "DELETE FROM `sessions` WHERE `access` < DATE_SUB(NOW(), INTERVAL :max_lifetime SECOND)";
        $params = [':max_lifetime' => $max_lifetime];
        
        try {
            $this->objdb->exec($sql, $params);
            $this->write_info("Session gc: nettoyage terminé");
            return 0; // Retourne le nombre de sessions supprimées (ou 0 si non disponible)
        } catch (Exception $e) {
            $message = $e->getMessage();
            $this->write_info("Session gc ERROR: " . $message);
            error_log("Session gc error: " . $message);
            return false;
        }
    }

    public function connection($ident,$passwd)
    {
        // Debug Log
        $this->write_info("Connection attempt: ident=$ident");
        $answer = false;

        $sql = "SELECT `id_user`,`user_type`,`passwd` FROM `connexion` WHERE ident = LOWER('$ident') AND passwd='$passwd';";
        $result = $this->objdb->execonerow($sql);
        if ( isset( $result['passwd'] ) )
        {
            if ( str_contains($passwd, $result['passwd']) )
            {
                $_SESSION['user_id'] = $result['id_user'];
                $_SESSION['user_type'] = $result['user_type'];
                $answer = true;
                // $this->write_info("Connection SUCCESS: user_id=" . $result['id_user'] . ", user_type=" . $result['user_type']);
            } else {
                // Debug Log
                $this->write_info("Connection FAILED: mot de passe incorrect pour ident=$ident");
            }
        } else {
            // Debug Log
            $this->write_info("Connection FAILED: utilisateur non trouvé pour ident=$ident");
        }

        return $answer;
    }
}	
?>