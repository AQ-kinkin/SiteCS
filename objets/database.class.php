<?php



/*

	Revised code by Dominick Lee

	Original code derived from "Run your own PDO PHP class" by Philip Brown

	Last Modified 2/27/2017

	*/



// Define database configuration

define("DB_HOST", "csresip1501.mysql.db");

define("DB_USER", "csresip1501");

define("DB_PASS", "Iletait1fois1968");

define("DB_NAME", "csresip1501");



// /* -----------------------------------------------------------------------------------

// # Calss de gedtion des log

// ------------------------------------------------------------------------------------*/

// trait Logs2 {

// 	private $logsPath;



// 	protected function PrepareLog(string $identifiant): void {

// 		$this->logsPath = '/home/csresip/www/logs/' . $identifiant . "_ " . date('YmdH');



// 		// Créer le dossier s’il n'existe pas

// 		$dossier = dirname($this->logsPath);

// 		if (!is_dir($dossier)) {

// 			mkdir($dossier, 0775, true);

// 		}

// 	}



// 	protected function write_info($message): void

// 	{

// 		// Format du message (date + contenu)

// 		$date = date('Y-m-d H:i:s');

// 		$ligne = "[$date] [Info] $message\n";



// 		// Écriture dans le fichier (append)

// 		file_put_contents($this->logsPath, $ligne, FILE_APPEND | LOCK_EX);

// 	}



// }



class Database
{
	// use Logs2;

	private $host      = DB_HOST;
	private $user      = DB_USER;
	private $pass      = DB_PASS;
	private $dbname    = DB_NAME;
	private $dbh;
	private $error;
	private $stmt;

	public function __construct()
	{

		// Set DSN

		$dsn = 'mysql:host=' . $this->host . ';dbname=' . $this->dbname;



		// Set options

		$options = array(

			PDO::ATTR_PERSISTENT    => true,

			PDO::ATTR_ERRMODE       => PDO::ERRMODE_EXCEPTION

		);



		// Create a new PDO instanace

		try {

			$this->dbh = new PDO($dsn, $this->user, $this->pass, $options);

			// $this->PrepareLog('BDD');

		}

		// Catch any errors

		catch (PDOException $e) {

			$this->error = $e->getMessage();
		}
	}

	public function __destruct()
	{
		$this->close();
	}

	public function exec($query, $params = null, $getlastid = false): int|string
	{
		if (empty($params)) {
			$affected =  $this->dbh->exec($query);
			if ($affected === false) {
				// Erreur SQL
				return -1;
			} else {
				return $affected;
			}
		} else {
			// $this->write_info('SQL : ' . $query);
			// $this->write_info('params : ' . var_export($params, true));
			$stmt_int = $this->dbh->prepare($query);
			$affected = $stmt_int->execute($params);

			if ($affected) {
				if ($getlastid == true) {
					return $this->lastInsertId();
				} else {
					return $stmt_int->rowCount();
				}
			} else {
				return -1;
			};
		}
	}

	public function ExecWithFetchAll($query, $params = null): array
	{
		if (empty($params)) {
			return $this->dbh->query($query)->fetchall();
		} else {
			$stmt_int = $this->dbh->prepare($query);
			$stmt_int->execute($params);
			return $stmt_int->fetchall();
		}
	}

	public function execonerow($query, $params = null): array
	{
		if (empty($params)) {
			return $this->dbh->query($query)->fetch();
		} else {
			$stmt_int = $this->dbh->prepare($query);
			$stmt_int->execute($params);
			$answer = $stmt_int->fetch();
			return ($answer === false) ? [] : $answer;
		}
	}

	public function query($query)
	{

		$this->stmt = $this->dbh->prepare($query);
	}

	public function execute($params = null)
	{
		if (empty($params)) {
			return $this->stmt->execute();
		}

		#echo "add_import_line -1- ";
		#print_r($params);
		#echo "<br/>";

		return $this->stmt->execute($params);
	}



	public function fetch()
	{

		return $this->stmt->fetch();
	}



	public function fetchall()
	{

		return $this->stmt->fetchall();
	}



	public function rowCount(): int
	{

		return $this->stmt->rowCount();
	}



	public function lastInsertId(): string

	{

		return $this->dbh->lastInsertId();
	}



	public function beginTransaction()
	{

		return $this->dbh->beginTransaction();
	}



	public function endTransaction()
	{

		return $this->dbh->commit();
	}



	public function cancelTransaction()
	{

		return $this->dbh->rollBack();
	}



	public function close()
	{

		$this->dbh = null;
	}



	private function PrepareLog(): void
	{

		$this->logsPath = '/home/csresip/www/logs/' . date('YMDH');

		// $this->logsPath = '/home/csresip/www/logs/' . date('YmdH');

		// $this->logsPath = '/home/csresip/www/logs/' . date('YmdHi');



		// Créer le dossier s’il n'existe pas

		$dossier = dirname($this->logsPath);

		if (!is_dir($dossier)) {

			mkdir($dossier, 0775, true);
		}



		// Format du message (date + contenu)

		$date = date('Y-m-d H:i:s');

		$ligne = "[$date] [Sart] ---------------------------------------------\n";



		// Écriture dans le fichier (append)

		file_put_contents($this->logsPath, $ligne, FILE_APPEND | LOCK_EX);
	}



	private function InfoLog($message): void
	{



		// Format du message (date + contenu)

		$date = date('Y-m-d H:i:s');

		$ligne = "[$date] [Info] $message\n";



		// Écriture dans le fichier (append)

		file_put_contents($this->logsPath, $ligne, FILE_APPEND | LOCK_EX);
	}
}
