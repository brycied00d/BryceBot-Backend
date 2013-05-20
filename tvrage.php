<?php
// BryceBot's Simple TVRage Proxy
// Written to easily chain together lookups
// -- Rather than 2 or 3 socket connections, taking 3+ seconds to complete,
// -- this will perform all lookups in a keepalive session.
// Parameters: bot=, server=, user=, channel= -- Used to identify the source
//   (cont'd): search= The search term
//   (cont'd): nextair= Whether to return just the information about the next airing
//
/*
tvrage
Fetch TV show information and the air-date of the next expisode.

http://services.tvrage.com/feeds/search.php?show=Last%20Man%20Standing
showid, name, country, started-year, ended=0, seasons, genres
http://services.tvrage.com/feeds/full_search.php?show=Last%20Man%20Standing
showid, name, country, started-date, ended=b, seasons, runtime, airtime/date, genres

http://services.tvrage.com/feeds/episodeinfo.php?sid=28386&ep=

Results -> show -> showid
$name ($genres->genre), started $started, (ended? "ended $ended, ")

*/
if(isset($_REQUEST['nextair']) && $_REQUEST['nextair'] == "true")
	$_REQUEST['nextair'] = true;
else
	$_REQUEST['nextair'] = false;

$start_time = microtime(true);

$useragent = "BryceBot-TVRage/1.0 (Bot: {$_REQUEST['bot']}@{$_REQUEST['server']}) (User: {$_REQUEST['nick']}@{$_REQUEST['channel']})";
$referer = "irc://{$_REQUEST['server']}/{$_REQUEST['channel']}";
if(!isset($_REQUEST['debug']))
	$_REQUEST['debug'] = false;

if($_REQUEST['debug'])
	echo "<pre>\n";

if($_REQUEST['debug'])
	echo "Request: ".print_r($_REQUEST, true);

// Clean up the search request string -- otherwise it ends up being double-urlencoded
$_REQUEST['search'] = rawurldecode($_REQUEST['search']);

// cURL handle is global
$ch = curl_init();
curl_setopt_array($ch, array(
	CURLOPT_AUTOREFERER => true,
	CURLOPT_DNS_USE_GLOBAL_CACHE => true,
	CURLOPT_FOLLOWLOCATION => true,
	CURLOPT_FORBID_REUSE => false,
	CURLOPT_FRESH_CONNECT => false,
	CURLOPT_HEADER => false,
	CURLOPT_HTTPGET => true,
	//CURLOPT_MUTE => true,
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_CONNECTTIMEOUT => 2,
	CURLOPT_PROTOCOLS => CURLPROTO_HTTP,
	CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP,
	CURLOPT_TIMEOUT => 3,
	CURLOPT_ENCODING => "",
	CURLOPT_INTERFACE => "ircbot.cobryce.com",
	CURLOPT_REFERER => $referer,
	CURLOPT_USERAGENT => $useragent,
	));

curl_setopt($ch, CURLOPT_VERBOSE, true);

// First we need to perform a search and get back the show's TVRage ID and info
$show = get_show($_REQUEST['search']);
if(!$show)
{
	// TVRage fails sometimes... Let's try up to 2 times
	curl_setopt_array($ch, array(	// While we're retrying, open fresh connections
		CURLOPT_FORBID_REUSE => true,
		CURLOPT_FRESH_CONNECT => true)
		);
/*	$show = get_show($_REQUEST['search']);
	if(!$show)
	{*/
		$show = get_show($_REQUEST['search']);
		if(!$show)
		{
			die("Error, the TVRage API has failed.");
			return;
		}
	/*}*/
}
// Return to connection pooling/reuse
curl_setopt_array($ch, array(
	CURLOPT_FORBID_REUSE => false,
	CURLOPT_FRESH_CONNECT => false)
	);

// Second get the information about the next episode
$nextep = get_nextep($show->showid);
if(!$nextep)
{
	die("TVRage Error. Response: $nextep");
	return;
}

/*
Always:		print show name
Sometimes:	print show info
Always: 	print next air date
*/

echo "{$show->name} ({$show->country}): ";
// Only print the "extra info" if they didn't want the short version
if(!$_REQUEST['nextair'])
{
	$endings = "s";
	if(stristr($show->status, "ended") !== false)	// Show ended, use past-tense
		$endings = "ed";
	echo "Status: {$show->status}. ";
	if($show->genres)
	{
		$g = (array)$show->genres;
		if(is_array($g['genre']))
			$g = "s: ".implode(' | ', $g['genre']);
		else
			$g = ": {$g['genre']}";
		echo "Genre{$g}. ";
	}
	if($show->runtime)
		echo "Runtime: {$show->runtime} minutes. ";
	if($show->airday && $show->airtime)
		echo "Air{$endings} {$show->airday}s at {$show->airtime} on {$show->network}. ";
	elseif((string)$show->network)
		echo "Air{$endings} on {$show->network}. ";
	if((string)$show->started)
		echo "Premiered {$show->started}. ";
	if((string)$show->ended)
		echo "End{$endings} {$show->ended}. ";
	if((string)$show->seasons)
		echo "{$show->seasons} season".($show->seasons == 1 ? "" : "s").". ";
	if($show->akas->aka)
	{
		$a = (array)$show->akas;
		if(is_array($a['aka']))
			$a = implode(", ", $a['aka']);
		else
			$a = $a['aka'];
		echo "Also known as: {$a}. ";
	}
	
	if($_REQUEST['debug'])
	{
		echo "<pre>";
		//var_dump($g['genre'], implode(' | ', $g['genre']));
		var_dump($g, $a);
	}
}

// Print full info + nextair?
if($nextep->nextepisode)
	// NB We have to type-cast the airtime into a string so SimpleXML will give us a string and not an XMLObject
	echo "Next episode: {$nextep->nextepisode->number} \"{$nextep->nextepisode->title}\", airs ".date('l, F jS.', "{$nextep->nextepisode->airtime[1]}");
else
	echo "No next air date known.";


die();

function get_show($search)
{
	global $ch;
	curl_setopt($ch, CURLOPT_URL, "http://services.tvrage.com/feeds/full_search.php".
					"?show=".rawurlencode($search)
					);
	$body = curl_exec($ch);
	libxml_use_internal_errors(true);	// Don't barf out errors
	$show = simplexml_load_string($body);
	$show = $show->show;	// As a side-effect of the SimpleXMLObject being iterable
							// this is set to the first child object.
	if($_REQUEST['debug'])
		var_dump(curl_getinfo($ch), $body, $show);
	return $show;
}

function get_nextep($showid)
{
	global $ch;
	curl_setopt($ch, CURLOPT_URL, "http://services.tvrage.com/feeds/episodeinfo.php".
					"?sid=".rawurlencode($showid).
					"&ep="	// Returns just the next
					);
	$body = curl_exec($ch);
	libxml_use_internal_errors(true);	// Don't barf out errors
	$nextep = simplexml_load_string($body);
	if($_REQUEST['debug'])
		var_dump(curl_getinfo($ch), $body, $nextep);
	return $nextep;
}
?>
