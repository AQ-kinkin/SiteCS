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
    private bool $log;

	public function __construct(Database $srcdb)
	{
		$this->objdb = $srcdb;
		$this->log = true;
		
		// Initialiser le système de logs (par jour pour suivre le parcours utilisateur)
		if ($this->log) {
			$this->PrepareLog('Session', 'd');
		}
  
        // Debug Log
		// if ($this->log) {
            // $this->write_info('Constructeur Session appelé - Session en base de données');
        // }
	}

    public function open($savePath, $sessionName): bool
    {
        // Connexion déjà établie via Database dans le constructeur

        // Debug Log
		// if ($this->log) {
        //     $this->write_info("Session open: savePath=$savePath, sessionName=$sessionName");
        // }

        return true;
    }

    public function close(): bool
    {
        // Pas besoin de fermer, PDO gère ça automatiquement

        // Debug Log
		// if ($this->log) {
        //     $this->write_info("Session open: savePath=$savePath, sessionName=$sessionName");
        // }

        return true;
    }

    public function read($id): string
    {
        // Debug Log avec utilisateur si activé
        if ($this->log) {
            $user = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 
                    (isset($_SESSION['user_id']) ? "user_id:{$_SESSION['user_id']}" : "anonymous");
            $this->write_info("Session read: id=$id, user=$user");
        }
        
        // Simple lecture des données
        $sql = "SELECT `data` FROM `sessions` WHERE `id` = :id LIMIT 1";
        $params = [':id' => $id];
        $result = $this->objdb->execonerow($sql, $params);
        
        if (!empty($result) && isset($result['data'])) {
            return $result['data'];
        }
        
        return '';
    }

    public function write($id, $data): bool {

        // Debug Log avec utilisateur si activé
        if ($this->log) {
            $user = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 
                    (isset($_SESSION['user_id']) ? "user_id:{$_SESSION['user_id']}" : "anonymous");
            $this->write_info("Session write: id=$id, user=$user, data_length=" . strlen($data));
        }
        
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
            if ($this->log) {
                $this->write_info("Session write ERROR: " . $message);
            }
            error_log("Session write error: " . $message);
            return false;
        }
    }
    
    public function destroy($id): bool
    {
        // Debug Log avec utilisateur si activé
        if ($this->log) {
            $user = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 
                    (isset($_SESSION['user_id']) ? "user_id:{$_SESSION['user_id']}" : "anonymous");
            $this->write_info("Session destroy: id=$id, user=$user");
        }
        
        $sql = "DELETE FROM `sessions` WHERE `id` = :id";
        $params = [':id' => $id];
        
        try {
            $this->objdb->exec($sql, $params);
            if ($this->log) {
                $this->write_info("Session destroy: succès pour id=$id");
            }
            return true;
        } catch (Exception $e) {
            $message = $e->getMessage();
            if ($this->log) {
                $this->write_info("Session destroy ERROR: " . $message());
            }
            error_log("Session destroy error: " . $message);
            return false;
        }
    }

    public function gc($max_lifetime): int|false
    {
        // Debug Log si activé
        if ($this->log) {
            $this->write_info("Session gc: nettoyage des sessions expirées (max_lifetime=$max_lifetime)");
        }
        
        // Supprime les sessions dont access est trop ancien
        $sql = "DELETE FROM `sessions` WHERE `access` < DATE_SUB(NOW(), INTERVAL :max_lifetime SECOND)";
        $params = [':max_lifetime' => $max_lifetime];
        
        try {
            $this->objdb->exec($sql, $params);
            if ($this->log) {
                $this->write_info("Session gc: nettoyage terminé");
            }
            return 0; // Retourne le nombre de sessions supprimées (ou 0 si non disponible)
        } catch (Exception $e) {
            $message = $e->getMessage();
            if ($this->log) {
                $this->write_info("Session gc ERROR: " . $message);
            }
            error_log("Session gc error: " . $message);
            return false;
        }
    }

    public function connection($ident,$passwd)
    {
        // Debug Log si activé
        if ($this->log) {
            $this->write_info("Connection attempt: ident=$ident");
        }
        $answer = false;

        $sql = "SELECT `id_user`,`type_acteur`,`passwd` FROM `acteurs` WHERE ident = LOWER(:ident);";
        $params = [':ident' => $ident];
        $result = $this->objdb->execonerow($sql, $params);
        
        if ( isset( $result['passwd'] ) && !empty($result['passwd']) )
        {
            // Vérifier le mot de passe avec password_verify (pour les hash)
            if ( password_verify($passwd, $result['passwd']) )
            {
                $_SESSION['user_id'] = $result['id_user'];
                $_SESSION['user_type'] = $result['type_acteur'];
                $_SESSION['user_name'] = $ident;
                $answer = true;
                if ($this->log) {
                    $this->write_info("Connection SUCCESS: user=$ident, user_id=" . $result['id_user']);
                }
            } else {
                // Debug Log si activé
                if ($this->log) {
                    $this->write_info("Connection FAILED: mot de passe incorrect pour ident=$ident");
                }
            }
        } else {
            // Debug Log si activé
            if ($this->log) {
                $this->write_info("Connection FAILED: utilisateur non trouvé pour ident=$ident");
            }
        }

        return $answer;
    }

}	
?>