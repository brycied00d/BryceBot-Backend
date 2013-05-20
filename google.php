<?php
// BryceBot's Google Backend/proxy
// Written to massage the data and handle multiple requests in the same connection
// to Google.
// Parameters: bot=, server=, user=, channel= -- Used to identify the source
//   (cont'd): results= The number of results to return.
//   (cont'd): sitesearch= If specified, limit search to the given site:.
//   (cont'd): apikey= The API key to use with Google.
//   (cont'd): debug= What do you think?

$start_time = microtime(true);

$useragent = "BryceBot-Google/1.0 (Bot: {$_REQUEST['bot']}@{$_REQUEST['server']}) (User: {$_REQUEST['nick']}@{$_REQUEST['channel']})";
$referer = "irc://{$_REQUEST['server']}/{$_REQUEST['channel']}";
if(!isset($_REQUEST['debug']))
	$_REQUEST['debug'] = false;

if($_REQUEST['debug'])
	echo "<pre>\n";

if($_REQUEST['debug'])
	echo "Request: ".print_r($_REQUEST, true);

if(!isset($_REQUEST['results']))
    $_REQUEST['results'] = 3;

if(!isset($_REQUEST['apikey']))
	die();

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

$json = @do_google($_REQUEST['search'], $_REQUEST['siteSearch']);

$ret = "";

// If we got a spelling suggestino, we need to re-Google using that.
if(isset($json->spelling))
{
    $ret .= "Searching for '{$json->spelling->correctedQuery}' instead.\n";
    $json = do_google($json->spelling->correctedQuery);
}

$r = (array)$json->queries->request;
$ret .= sprintf("%s total results returned for '%s', here's %d",
    number_format($json->searchInformation->totalResults, 0),
    $r[0]->searchTerms,
    ($_REQUEST['results'] < $json->searchInformation->totalResults ?
        $_REQUEST['results'] : $json->searchInformation->totalResults)
    );
if($json->searchInformation->totalResults > 0)
{
    foreach($json->items as $i)
        $ret .= sprintf("\n%s (%s) %s", $i->title, $i->link, $i->snippet);
}

echo $ret.PHP_EOL;


function do_google($search, $siteSearch=null)
{
    global $ch;
    
    curl_setopt($ch, CURLOPT_URL, "https://www.googleapis.com/customsearch/v1".
	    "?key=".$_REQUEST['apikey'].
	    "&cx=017954034305982908620:kz7zyzk-uqs".
	    "&alt=json".
	    "&filter=1".
	    "&num=".$_REQUEST['results'].
	    "&safe=off".
	    "&fields=".rawurlencode("items(link,snippet,title),".
		    "queries,".
		    "searchInformation/totalResults,".
		    "spelling/correctedQuery").
	    ($siteSearch ? "&siteSearch=$siteSearch" : "").
	    "&q=".rawurlencode($search)	// Yeah we're re-encoding
	    );
    $body = curl_exec($ch);
    $json = json_decode($body);
    if($_REQUEST['debug'])
	    var_dump(curl_getinfo($ch), $body, $json);
	return $json;
}
?>
