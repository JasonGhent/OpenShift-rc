<html>
<head>
</head>
<body>
<pre>
<?php
// error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// external scripts
include('./src/reaper.class.php');

// initialize db class
// @TODO move db work into reaper since we already are including it

//initialize extractor
$ops = new Reaper();


// set skills to measure including nested skills
$skills = array(
  'php' =>        ['php'],
  'javascript' => ['javascript',' js','jquery'],
  'node',
  'java' =>       ['java ','java,','java.'],
  'nosql' =>      ['nosql','mongo'],
  'sql' =>        [' sql','.sql','mysql','postgres'],
  'postgresql' => ['postgre'],
  'mysql',
  'c' =>          [' c ',' c,'],
  'obj-C' =>      ['objective-c','obj-c'],
  'c++',
  'c#',
  '.net' =>       [' .net'], // exclude '.net' to ignore vb.net, et al
  'asp.net',
  'vb.net',
  'bash',
  'shell',
  'perl',
  'ruby',
  'python',
  'chef',
  'puppet',
  'ios',
  'ios' =>          [' ios'],
  'oracle',
  'ipad'
);

// Notes
$jobs['notes'] = [
  "List updated twice daily.",
  "Entries populated for counters at random intervals via FIFO.",
  "Counters incremented once per containing post, not per mention in post.",
  "Limited to 100000 most recent listings.",
  "Displays last 20 jobs in list below",
];

// coallate by unq that currently exist in DB
$unq = 'burb';
$results = $ops->distinct($unq);
$coll =[]; foreach($results as $result){ array_push($coll, $result->$unq); }

// Query all jobs for this count
// @TODO : Move analysis into new scheduled db task
$jobs['list'] = $ops->getList();

// initialize skill counters
foreach($skills as $skill=>$matches) {

	// handle skill match arrays
  if(!is_array($matches)) $skill = $matches;

	// set region total counters
  $jobs['tallies']['all_regions'][$skill] = 0;

	// set all burb counters
	foreach($coll as $burb){
	 	$jobs['tallies'][$burb][$skill] = 0;
	}
}

// loop through all listings
foreach($jobs['list'] as $job){
	// count skills by passing in current skill count and replacing with result
	$jobs['tallies']['all_regions'] = $ops->accumulate_skills($job->contents, $jobs['tallies']['all_regions'], $skills);

	//organize by burb ( duplicates accounted for during insertion )
	$burb = $job->burb;
	$jobs['tallies'][$burb] = $ops->accumulate_skills($job->contents, $jobs['tallies'][$burb], $skills);
}

// @TODO: change this to run once on last loop iteration of each burb above??
foreach($coll as $burb){
	arsort($jobs['tallies'][$burb]);
}

//sorting
usort($jobs['list'], array('Reaper','sortDates'));
arsort($jobs['tallies']['all_regions']);
asort($jobs);

$jobs['processed'] = $ops->processed();
$jobs['total'] = $ops->count();

//trim results
$i = 0;
foreach($jobs['list'] as $k=>$job) {
  if($i > 19) unset($jobs['list'][$k]);
  $i++;
}

print(htmlspecialchars(json_encode($jobs, JSON_PRETTY_PRINT)));

?>
</pre>
</body>
</html>
