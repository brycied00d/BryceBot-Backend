<?php
set_time_limit(20);	// Time-limit hard-coded to 20 seconds.

// Cleanup the input
$num = @trim($_REQUEST['num']);
if(!$num) die("");

// Check the cache first - Is there one younger than 12 hours?
$cachefile = "/tmp/packagetrackr/packagetrackr_".md5($num);
if( !isset($_REQUEST['force']) && is_readable($cachefile) && (time()-filemtime($cachefile)) < (12*60*60) )
	$json = file_get_contents($cachefile);
else
{
	// Setup the request: 15s timeout
	$stream_context = stream_context_create( array( 'http'=>array('timeout'=>15) ) );
	
	// Fetch!
	$json = file_get_contents("http://www.packagetrackr.com/track/{$num}.json", false, $stream_context);
	//$json = file_get_contents("http://www.packagetrackr.com/track/{$num}.json!!".microtime(true));
	
	// Process the response
	if($json)
		file_put_contents($cachefile, $json);
	else	// We didn't get a response? Use the cache then, if we can.
		$json = file_get_contents($cachefile);
	if(!$json) die("No server response.");	// Still no response???
}

$track = json_decode(trim($json))->track;

// Temporary - Only UPS tracking seems to be working
//if($track->carrier !== "UPS")
//	die("FedEx tracking temporarily unavailable.");

// Prefix with the carrier (if it's not part of the serviceType)
if(stristr($track->serviceType, $track->carrier) === false)
	echo "{$track->carrier} ";

// Core status print
echo "{$track->serviceType}: {$track->statusDescription}";

// Include the delivery date (if we know it)
if(@$track->deliveryDate->display)
	echo " ({$track->deliveryDate->display})";
elseif(@$track->eSTDeliveryDate->display)
	echo " (eta {$track->eSTDeliveryDate->display})";

if(isset($_REQUEST['dump']))
{
	echo "<br>\n<pre>\n";
	var_dump($track);
	echo "\n</pre>";
}
// Misc crap
//echo "Carrier: {$track->carrier}\n";
//echo "On-Time Message: {$track->onTimeMessage}\n";
//echo "Status: {$track->statusDescription}\n";
//echo "Service: {$track->serviceType}\n";
//echo "Del.Loc: {$track->deliveryLocation}\n";
//echo "SignedBy: {$track->signedBy}\n";
?>
