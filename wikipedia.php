<?php
// BryceBot's Simple Wikipedia Proxy
// Written to easily chain together lookups
// -- Rather than 2 or 3 socket connections, taking 3+ seconds to complete,
// -- this will perform all lookups in a keepalive session.
// Parameters: bot=, server=, user=, channel= -- Used to identify the source
//   (cont'd): search= The search term, lang= the Wikipedia language
//   (cont'd): returnurl= Whether to include the Wikipedia URL in the response
//
// Todo/Ideas: On images, fetch action=query&prop=imageinfo list=imageusage
//             Return only the relevant section when #XXX is passed -- prop=revisions? rvsection?
//             Fetch a random page list=random

$start_time = microtime(true);

$useragent = "BryceBot-Wikipedia/1.0 (Bot: {$_REQUEST['bot']}@{$_REQUEST['server']}) (User: {$_REQUEST['nick']}@{$_REQUEST['channel']})";
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

// First try for a direct fetch
$wiki = get_wiki($_REQUEST['search']);
if(!$wiki)
{
	die("Error in Wikipedia's response: $body");
	return;
} elseif(!isset($wiki['query']['pages'][-1]))	// Found!
{
	print_wiki($wiki);
	exit(0);
} else	// If we're this far, no article was found. Time to do some digging
{
	$autocomplete = get_autocomplete($_REQUEST['search']);
	if(sizeof($autocomplete[1]) > 0)	// Got a completion/new name
	{
		$wiki = get_wiki($autocomplete[1][0]);
		print_wiki($wiki);
		exit(0);
	} else	// Didn't get an autocomplete result, so perform a search
	{
		$search = wiki_search($_REQUEST['search']);
		if(isset($search['query']['searchinfo']['suggestion']))
			echo "No exact title match. Searching... Maybe you meant '{$search['query']['searchinfo']['suggestion']}'? Here is Wikipedia's first search result.\n";
		if(sizeof($search['query']['search']) > 0)
		{
			$wiki = get_wiki($search['query']['search'][0]['title']);
			print_wiki($wiki);
			exit(0);
		} else
		{
			echo "After searching extensively, I was not able to find an article matching '{$_REQUEST['search']}'";
			exit(1);
		}
	}
}
die();

function get_wiki_section_list($title)
{
	global $ch;
	// Should return an array matching section titles to section IDs
	// but doesn't (yet)
	// http://en.wikipedia.org/w/api.php?format=dump&action=mobileview&page=Ages%20of%20consent%20in%20North%20America&sections=all&prop=sections
	// http://en.wikipedia.org/w/api.php?format=dump&action=query&prop=revisions&titles=Ages%20of%20consent%20in%20North%20America&rvprop=content&rvcontentformat=text/x-wiki&rvlimit=1&rvdir=older&rvsection=38
	curl_setopt($ch, CURLOPT_URL, "http://{$_REQUEST['lang']}.wikipedia.org/w/api.php?action=mobileview".
					"&prop=sections".
					"&format=php".
					"&sections=all".
					"&page=".rawurlencode($title)
					);
	$body = curl_exec($ch);
	$wiki = unserialize($body);
	$list = array();
	foreach($wiki['mobileview']['sections'] as $id => $a)
		$list[$a['line']] = $id;
	if($_REQUEST['debug'])
		var_dump(curl_getinfo($ch), $body, $wiki, $list);
	return $list;
}

function get_wiki_section($title, $section_id=0)
{
	global $ch;
	// Should return an array matching section titles to section IDs
	// but doesn't (yet)
	// http://en.wikipedia.org/w/api.php?format=dump&action=query&prop=revisions&titles=Ages%20of%20consent%20in%20North%20America&rvprop=content&rvcontentformat=text/x-wiki&rvlimit=1&rvdir=older&rvsection=38
	curl_setopt($ch, CURLOPT_URL, "http://{$_REQUEST['lang']}.wikipedia.org/w/api.php?action=query".
					"&prop=revisions".
					"&format=php".
					"&rvprop=content".
					"&rvcontentformat=text/x-wiki".
					"&rvlimit=1".
					"&rvdir=older".
					"&rvsection=".rawurlencode($section_id).
					"&title=".rawurlencode($title)
					);
	$body = curl_exec($ch);
	$wiki = unserialize($body);
	if($_REQUEST['debug'])
		var_dump(curl_getinfo($ch), $body, $wiki);
	return $wiki;
}

function wiki_search($search)
{
	global $ch;
	curl_setopt($ch, CURLOPT_URL, "http://{$_REQUEST['lang']}.wikipedia.org/w/api.php?action=query".
			"&list=search".
			"&srlimit=1".
			"&srinfo=suggestion".
			"&srprop=".
			"&format=php".
			"&srsearch=".rawurlencode($search)
			);
	$body = curl_exec($ch);
	$search = unserialize($body);
	if($_REQUEST['debug'])
		var_dump(curl_getinfo($ch), $body, $search);
	return $search;
}

function get_autocomplete($search)
{
	global $ch;
	// Try the autocomplete API first
	curl_setopt($ch, CURLOPT_URL, "http://{$_REQUEST['lang']}.wikipedia.org/w/api.php?action=opensearch".
				"&limit=1".
				"&namespace=0".
				"&format=json".
				"&search=".rawurlencode($search)
				);
	$autocomplete_body = curl_exec($ch);
	$autocomplete = json_decode($autocomplete_body);
	if($_REQUEST['debug'])
		var_dump(curl_getinfo($ch), $autocomplete_body, $autocomplete);
	return $autocomplete;
}

function get_wiki($search)
{
	global $ch;
	curl_setopt($ch, CURLOPT_URL, "http://{$_REQUEST['lang']}.wikipedia.org/w/api.php?action=query".
					"&prop=extracts".
					"&exchars=420".
					"&format=php".
					"&exsectionformat=plain".
					"&redirects=".
					"&iwurl=".
					"&explaintext=".
					"&titles=".rawurlencode($search)
					);
	$body = curl_exec($ch);
	$wiki = unserialize($body);
	if($_REQUEST['debug'])
		var_dump(curl_getinfo($ch), $body, $wiki);
	return $wiki;
}

function print_wiki($wiki)
{
	foreach($wiki['query']['pages'] as $page_id => $page)
	{
		if(!isset($page['extract']))	// No extract provided, skip
			continue;
		// It was being dumb, so I broke it all out.
		$extract = $page['extract'];
		$extract = trim($extract);
		$extract = strip_tags($extract);
		//$extract = html_entity_decode($extract, ENT_NOQUOTES | ENT_HTML401, 'UTF-8');	// Fucks up encoding
		$extract = trim($extract);
		//var_dump($extract);
		$extract = str_ireplace( array("&#x00A0;", "&#160;"), " ", $extract);	// These are other forms of $nbsp;
		$extract = str_ireplace( array(PHP_EOL, "\n", "\r"), "  ", $extract);
		//var_dump($extract);
		/*
		echo "{$page['title']} :: {$extract}".PHP_EOL;
		if($_REQUEST['returnurl'])
			echo "http://{$_REQUEST['lang']}.wikipedia.org/wiki/".rawurlencode($page['title']).PHP_EOL;
		*/
		if($_REQUEST['returnurl'] !== "false")
		{
			// Shorten the $extract and tack-on the url
			$returnurl = "http://{$_REQUEST['lang']}.wikipedia.org/wiki/".rawurlencode($page['title']);
			$extract = explode("\n", wordwrap($extract, 414-strlen($returnurl)));
			$extract = trim($extract[0])."... ".$returnurl;
		}
		echo "{$page['title']} :: {$extract}".PHP_EOL;
	}
}
?>
