<?php
// error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// set overrides
date_default_timezone_set('America/New_York');
set_time_limit(1800);

// external scripts
include('src/cron.class.php');

$now   = gmdate('U');
$ninAM = gmdate('U', mktime(8));
$sixPM = gmdate('U', mktime(18));

# if 6AM or 1PM reap rss
if((gmdate('H')==gmdate('H',mktime(6)) && gmdate('i')=='00') || (gmdate('H')==gmdate('H',mktime(13)) && gmdate('i')=='00')) {
	$cron = new cron();
	$cron->reap('feeds');
	$cron->scrub();
}

//@TODO make this work for M-F only
# if BETWEEN 9AM and 6PM reap post if random is hit (20% chance)
$rand=rand(1,100);
$rand=$rand>80?true:false;
if($now > $ninAM && $now < $sixPM && $rand) {
	$cron = new cron();
	$cron->reap('listings');
	/* Analysis Phase IF empty record processed */
}

print('<pre>');
if(isset($_GET['listings'])||isset($_GET['feeds'])){
	$cron = new cron();

  if(isset($_GET['listings'])){
  	$cron->reap('listings');
  }

  if(isset($_GET['feeds'])){
  	$cron->reap('feeds');
  	$cron->scrub();
  }
}

print(gmdate('U').": Cron task run.");
?>
