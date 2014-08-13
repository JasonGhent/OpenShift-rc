<?php
date_default_timezone_set('America/New_York');

// Composer libs
include('../../libs/vendor/autoload.php');
use Sunra\PhpSimple\HtmlDomParser;

// Local libs
include('conn/pgsql.config.php');

/**
* rss reaper
*/
class Reaper extends Conn {

  function __construct() {
    $this->data = array();
  }

  function get_data($url, $form='') {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $xml = curl_exec($ch);
    curl_close($ch);
    
    if($form == 'xml') {
      $xml = preg_replace('/dc:date/', 'date', $xml);
      $xml = new SimpleXMLElement($xml, LIBXML_NOCDATA);
    }
  
    return $xml;
  }

  function get_test_data(){
    return $this->query("SELECT * FROM Records LIMIT 3");
  }

  // only removes dupes within a batch grabbed per hour, not rollovers.
  function clean($in, $dupeField) {
    $out = $in;
    foreach($in as $k=>$v) {
      foreach($out as $k1=>$v1) {
        if(($k1!==$k)&&($v1[$dupeField]===$v[$dupeField])) {
          unset($out[$k]);
        }
      }
    }
    return $out;
  }

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
    // delete oldest records if >100000 entries
    // doing this even when updating existing will ensure reduction
    $count = $this->count();
    if($count >= 100000+count($in))
      $this->query("DELETE FROM Records ORDER BY createddate ASC LIMIT ".count($in)." ;");

    // iterate over input
    foreach($in as $dbin) {
       print(date('U').": ".$dbin['title']."<br/>"); // echo for direct url calls

      // if these fields are preset, we know we are updating a record
      if(isset($dbin['id'])) {
        $this->query(
          "UPDATE Records SET contents = :contents WHERE id=:id",
          array(
            ':id'          =>  $dbin['id'],
            ':contents'    =>  $dbin['contents']
          )
        );
      }
      
      // if not, we are saving a new record
      else{
        $this->query(
          "INSERT INTO Records (title, createddate, description, link, contents, city, area, board, burb) ".
          "VALUES (:title, :createddate, :description, :link, :contents, :city, :area, :board, :burb)",
          array(
            ':city'        =>  $dbin['city'],
            ':area'        =>  $dbin['area'],
            ':board'       =>  $dbin['board'],
            ':burb'        =>  $dbin['burb'],
            ':title'       =>  $dbin['title'],
            ':createddate' =>  $dbin['date'],
            ':description' =>  $dbin['description'],
            ':link'        =>  $dbin['link'],
            ':contents'    =>  '' // PDO does NOT like NULL
          )
        );
      }
    }
  }

  // check for job existing already by date to avoid adding same job instance twice.
  function filter($in) {
    try { // connection
      $dbh = new PDO('pgsql:host='.MHN.';port='.MPT.';dbname='.MDB, MUN, MPW);
      $stmt= $dbh->prepare("SELECT * FROM Records WHERE createddate = :date");
      $out = array();
      
      //loop through new
      foreach($in as $job) {
        $stmt->bindParam(':date', $job['date']);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_OBJ);

        //if no old records found, keep new record in array of new
        if(empty($results)) array_push($out, $job);
      }
      unset($stmt);
    }
    catch (PDOException $e) { die(print "Error!: " . $e->getMessage() . "<br/>"); }

    return $out;
  }

  static function sortDates($a,$b) {
    $a_t = strtotime($a->createddate);
    $b_t = strtotime($b->createddate);

    if($a_t == $b_t ) return 0;
    return ($a_t > $b_t)? -1 : 1;  
  }

  function get_details($in) {
    // loop through listings
    if(!empty($in)) {
      foreach($in as $item) {
        $item = get_object_vars($item);

        // get actual contents
        $item['contents'] = utf8_encode(HtmlDomParser::file_get_html($item['link'])->plaintext);

        $out[] = $item;
      }
    }
    else $out = $in;
  
    return $this->data = $out;
  }
  
  function meta($in) {
    if(!empty($in)) {
      foreach($in as $item) {
        // get city info
        $details = parse_url($item['link']);

        $host = $details['host'];
        $item['city']  = explode('.', $host)[0];

        $path = $details['path'];
        $path = explode('/', $path);
        // 0 -> '', 1 -> burb, 2 -> board, 3 -> html
        array_pop($path); // remove last path node. ie foobar.htm

        // 0 -> '', 1 -> burb, 2 -> board
        if(count($path)>2) { // 1-indexed count
          $item['board'] = $path[2];
          $item['burb']  = $path[1];
        }
        // small city without burbs
        else {
          $item['burb']  = $item['city'];
          $item['board'] = $path[1];
        }

        // get area (last parans block in a title)
        $item['area'] = '';
        if(substr($item['title'],-1)===')'){ // area possible
          if(strrpos($item['title'],'(')){ // area likely
            // PHP is retarded with string manipulations
            $item['area'] = substr($item['title'], strrpos($item['title'],'(')+1, strlen($item['title'])-strrpos($item['title'],'(')-2);
          }
        }

        $out[] = $item;
      }
    }
    else $out = $in;

    return $this->data = $out;
  }

  // Get total processed
  function processed(){
    return $this->query("SELECT COUNT(*) FROM records WHERE contents != ''")[0]->count;
  }

  // Get total count
  function count(){
    return $this->query("SELECT COUNT(*) FROM Records")[0]->count;
  }

  // fetch unprocessed records up to $x
  function fetch($x){
    return $this->data = $this->query(
      "SELECT * FROM Records ".
      "WHERE (contents is null OR contents='') ".
      "ORDER BY createddate desc LIMIT ".$x
    );
  }

  function distinct($unq) {
    return $this->data = $this->query("SELECT DISTINCT ".$unq." FROM Records");
  }

  function getList() {
    return $this->data = $this->query("SELECT * FROM Records ORDER BY createddate DESC LIMIT 100000");
  }
}
?>
