<?php
date_default_timezone_set('America/New_York');

// libs
include('src/reaper.class.php');

/**
* Cron
*/
class Cron extends Reaper {

  function __construct(){
		$this->ensureDbTables();
    $this->data = array();
  }

  /* Reaping Phase */
  function reap($type) {
    if(!isset($type)) die(print('no feed specified'));
    else{
      print(date('U').": Reaping ".$type."<br />");
      if($type=='feeds')    return $this->feeds();
      if($type=='listings') return $this->listings();
      else                  return $this->data;
    }
  }

  //reap feeds
  function feeds() {
    $reaps = json_decode(file_get_contents(getcwd()."/../../misc/configs/reaper.js"),true); //json digest
    $out = [];
    foreach($reaps['cities'] as $city){
      foreach($reaps['boards'] as $board){
        $reap = $city.'.en.craigslist.org/'.$board.'/index.rss';
        print("Reaping: $reap ...<br />");
        $out = array_merge($out, json_decode(json_encode($this->get_data($reap, 'xml')),TRUE)['item']);
      }
    }
  
    return $this->data = $out;
  }

  /* Scrubbing Phase */
  function scrub() {
    // clean dupes (by description) from current results
    $this->data = $this->clean($this->data, 'description');
    
    // filter against existing database entries by remote creation date
    $this->data = $this->filter($this->data);
    
    // populate some meta data
    $this->data = $this->meta($this->data);

    // storing processed info
    $this->save($this->data);

    return $this->data;
  }

  function listings($x=0) {
    if($x==0) $x = rand(1,3);
    // reap record
    $this->data = $this->get_details($this->fetch($x));
    $this->save($this->data);
    return $this->data;
  }
}
?>
