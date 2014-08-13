<?php
if(gethostname()==='Themis'||gethostname()==='zergling'){
	$cfg = json_decode(file_get_contents(getcwd()."/../../misc/configs/localhost.js"),true);
}
else{
	$cfg = [];
	$cfg['MHN']='';
	$cfg['MPT']='';
	$cfg['MUN']='';
	$cfg['MPW']='';
}

// settings
define('MHN', $cfg['MHN']);
define('MPT', $cfg['MPT']);
define('MUN', $cfg['MUN']);
define('MPW', $cfg['MPW']);
define('MDB', 'cla');

class Conn
{
	function __construct() {
		$this->ensureDbTables();
	}

	function ensureDbTables() {
		$query = "
			CREATE TABLE IF NOT EXISTS Records (
				id            serial      PRIMARY KEY,
				city          text        NOT NULL,
				burb          text        NOT NULL,
				area          text        NOT NULL,
				board         text        NOT NULL,
				contents      text        NOT NULL,
				description   text        NOT NULL,
				link          text        NOT NULL UNIQUE,
				createdDate   timestamptz NOT NULL,
				withdrawnDate timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
				title         text        NOT NULL
			);
		";
    $this->query($query);
	}

	function query($query, $data=null) {
		try { // connection
			$dbh = new PDO('pgsql:host='.MHN.';port='.MPT.';dbname='.MDB, MUN, MPW);
			$stmt = $dbh->prepare($query);
			if (!$stmt->execute($data)) {
        print(json_encode($stmt->errorInfo()));
      }
      else{
			  $data = array();
			  foreach($stmt->fetchAll(PDO::FETCH_OBJ) as $row) {
			  	$data[] = $row;
			  }
      }
			unset($stmt);
		}
		catch (PDOException $e) { die(print "Error!: " . $e->getMessage() . "<br/>"); }

		return $data;
	}

}
?>
