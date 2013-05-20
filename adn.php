<?php
// BryceBot's Simple App.net Proxy
// Written because app.net requires SSL, and BryceBot doesn't support SSL.
// Parameters: bot=, server=, user=, channel= -- Used to identify the source
//   (cont'd): url= The URL to grab
//

$start_time = microtime(true);

$useragent = "BryceBot-AppDotNetScraper/1.0 (Bot: {$_REQUEST['bot']}@{$_REQUEST['server']}) (User: {$_REQUEST['nick']}@{$_REQUEST['channel']})";
$referer = "irc://{$_REQUEST['server']}/{$_REQUEST['channel']}";
if(!isset($_REQUEST['debug']))
	$_REQUEST['debug'] = false;

if($_REQUEST['debug'])
	echo "<pre>\n";

if($_REQUEST['debug'])
	echo "Request: ".print_r($_REQUEST, true);

// Clean up the search request string -- otherwise it ends up being double-urlencoded
$_REQUEST['url'] = rawurldecode($_REQUEST['url']);

if(!$_REQUEST['url'])
	die();

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
curl_setopt($ch, CURLOPT_URL, $_REQUEST['url']);
$body = curl_exec($ch);
if($_REQUEST['debug'])
	var_dump(curl_getinfo($ch), $body);

$dom = new DOMDocument();
@$dom->loadHTML($body);
$xpath = new DOMXPath($dom);

$username = null;
$content = null;

//$spans = $dom->getElementsByTagName('span');
$spans = $xpath->query(
	"//div[contains(concat(' ', normalize-space(@class), ' '), ' single-post ')]//span[@class='username']".
	" | ".
	"//div[contains(concat(' ', normalize-space(@class), ' '), ' single-post ')]//span[@class='post-content']"
	);
foreach($spans as $span)
{
	$class = $span->getAttribute('class');
	if(!$class)
		continue;
	if($class == "username")
		$username = html_entity_decode(trim($span->nodeValue));
	elseif($class == "post-content")
		$content = str_ireplace(array("\n", "\r"), array('\n', '\r'),
			html_entity_decode(trim($span->nodeValue)));
	if($username && $content)
		break;
}
if($username && $content)
{
	echo "@{$username}: {$content}";
}
?>
