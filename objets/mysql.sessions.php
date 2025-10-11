<?php

	/*
	CREATE TABLE sessions
	(
		id varchar(32) NOT NULL,
		access int(10) unsigned,
		data text,
		PRIMARY KEY (id)
	);

	+--------+------------------+------+-----+---------+-------+
	| Field  | Type             | Null | Key | Default | Extra |
	+--------+------------------+------+-----+---------+-------+
	| id     | varchar(32)      |      | PRI |         |       |
	| access | int(10) unsigned | YES  |     | NULL    |       |
	| data   | text             | YES  |     | NULL    |       |
	+--------+------------------+------+-----+---------+-------+

	*/


class Session implements SessionHandlerInterface
{
    private string $savePath;
    private Database $objdb;

	public function __construct(Database $srcdb)
	{
		$this->savePath = '/home/csresip/sessions';
		$this->objdb = $srcdb;

        if (!is_dir($this->savePath)) {
        	mkdir($this->savePath, 0777, true);
        }

		#echo "<H1>(constructeur)List :</H1><ul>";
		#$files = scandir($this->savePath); // ou n'importe quel chemin
		#foreach ($files as $file) {
    	#	echo "<li>'" . $file . "'</li>";
		#}
		#echo "</ul>";
	}

    public function open($savePath, $sessionName): bool
    {
        // echo "<H1>(open)'$savePath' --- '$this->savePath'</H1><br/><h2>'$sessionName'</h2>";
        return true;
    }

    public function close(): bool
    {
        // echo "<H1>Session->close()</H1>";
        return true;
    }

    public function read($id): string
    {
		$file = "$this->savePath/sess_$id";
        #echo "<H1>(read)'$file'</H1>";

        if (file_exists($file)) {
            return file_get_contents($file);
        }

        return '';
    }

    public function write($id, $data): bool
    {
		$file = "$this->savePath/sess_$id";
        #echo "<H1>(write)'$file'</H1>";
        return file_put_contents($file, $data) !== false;
    }

    public function destroy($id): bool
    {
		$file = "$this->savePath/sess_$id";
        // echo "<H1>Session->destroy() -- '$file'</H1>";

        if (file_exists($file)) {
            unlink($file);
        }

        return true;
    }

    public function gc($max_lifetime): int|false
    {
        #echo "<H1>(gc)</H1>";
        foreach (glob("$this->savePath/sess_*") as $file) {
            if (filemtime($file) + $max_lifetime < time()) {
                unlink($file);
            }
        }

        return true;
    }

    public function connection($ident,$passwd)
    {
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
            }
        }

        return $answer;
    }
}	
?>