<?php
date_default_timezone_set('America/New_York');

// libs
include('../../libs/vendor/autoload.php');
include('conn/mysql.config.php');


class analyze {

  // for each skill mentioned, +1 to that skill
  function accumulate_skills($listing, $skills, $hashLookup) {
    $listing = strtolower($listing);
    foreach($skills as $skillName=>$skillCount) {
  
      // array of lookups
      if(isset($hashLookup[$skillName]) && is_array($hashLookup[$skillName])){
        $lookups = $hashLookup[$skillName];
        $count = false;
        foreach($lookups as $lookup){
          if(preg_match("/".preg_quote($lookup)."/", $listing)) {
            $count = true;
          }
        }
        if($count){
          $skills[$skillName] = $skillCount+1;
        }
      }
  
      // single lookup
      else{
        if(preg_match("/".preg_quote($skillName)."/", $listing)) {
          $skills[$skillName] = $skillCount+1;
        }
      }
    }
    return $skills;
  }
  
  // save each
  function save($in) {
    try { // connection
      $dbh = new PDO('mysql:host='.MHN.';port='.MPT.';dbname='.MDB, MUN, MPW);

      // Get row count
      $stmt0 = $dbh->prepare("SELECT COUNT(*) FROM `Records`");
      $stmt0->execute();
      $count = $stmt0->fetch(PDO::FETCH_NUM)[0];
      unset($stmt0);

			// iterate over new
      foreach($in['jobs'] as $dbin) {

      	// delete oldest record if >100000 entries
      	if($count >= 100000){
      	  $stmt1 = $dbh->prepare("DELETE FROM `Records` ORDER BY createdDate ASC LIMIT 1 ;");
      	  $stmt1->execute();
      	  unset($stmt1);
      	}

      	// insert new row
      	$stmt2 = $dbh->prepare("INSERT INTO `Records` (title, createdDate, `desc`, link, contents, city, area, board, burb) VALUES (:title, :createdDate, :desc, :link, :contents, :city, :area, :board, :burb)");
        print $dbin['title']."<br/>"; // echo for direct url calls
        $stmt2->execute(array(
          ':city'        =>  $dbin['city'],
          ':area'        =>  $dbin['area'],
          ':board'       =>  $dbin['board'],
          ':burb'        =>  $dbin['burb'],
          ':title'       =>  $dbin['title'],
          ':createdDate' =>  $dbin['date'],
          ':desc'        =>  $dbin['description'],
          ':link'        =>  $dbin['link'],
          ':contents'    =>  $dbin['contents']
        ));
      }
      unset($stmt2);
    }
    catch (PDOException $e) { die(print "Error!: " . $e->getMessage() . "<br/>"); }
  }

	// check for job existing already by date to avoid adding same job instance twice.
  function check_existing($in) {
    try { // connection
      $dbh = new PDO('mysql:host='.MHN.';port='.MPT.';dbname='.MDB, MUN, MPW);
      $stmt= $dbh->prepare("SELECT * FROM `Records` WHERE createdDate = :date");
      $out['jobs'] = array();
      
      //loop through new
      foreach($in as $job) {
      	$stmt->bindParam(':date', $job['date']);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_OBJ);

        //if no old records found, keep new record in array of new
        if(empty($results)) $out['jobs'][] = $job;
        else {
					/* @TODO proper check by description like in batch checking at dupe removal stage
						check $job's desc vs $rows. same, toss. diff, keep.*/
				}
      }
   		unset($stmt);
    }
    catch (PDOException $e) { die(print "Error!: " . $e->getMessage() . "<br/>"); }

    return $out;
  }

  static function sortDates($a,$b) {
    $a_t = strtotime($a->createdDate);
    $b_t = strtotime($b->createdDate);

    if($a_t == $b_t ) return 0;
    return ($a_t > $b_t)? -1 : 1;  
  }

// Get total count
	function count(){
		$dbh = new PDO('mysql:host='.MHN.';port='.MPT.';dbname='.MDB, MUN, MPW);
		$stmt0 = $dbh->prepare("SELECT COUNT(*) FROM `Records`");
		$stmt0->execute();
		$count = $stmt0->fetch(PDO::FETCH_NUM)[0];
		unset($stmt0);
		return $count;
	}
}
?>
