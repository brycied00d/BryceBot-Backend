<?php
// BryceBot's SSL proxy for the GitHub API
// Written to overcome the lack of SSL support in BryceBot
// Parameters: bot=, server=, user=, channel= -- Used to identify the source
//   (cont'd): ghurl= The GitHub API URL to fetch

$start_time = microtime(true);

$useragent = "BryceBot-GitHub/1.0 (Bot: {$_REQUEST['bot']}@{$_REQUEST['server']}) (User: {$_REQUEST['nick']}@{$_REQUEST['channel']})";
$referer = "irc://{$_REQUEST['server']}/{$_REQUEST['channel']}";
if(!isset($_REQUEST['debug']))
	$_REQUEST['debug'] = false;

if($_REQUEST['debug'])
	echo "<pre>\n";

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

curl_setopt($ch, CURLOPT_URL, $_REQUEST['ghurl']);
$body = curl_exec($ch);
$json = json_decode($body);
if($_REQUEST['debug'])
	var_dump(curl_getinfo($ch), $body, $json);

echo $body;
?>
