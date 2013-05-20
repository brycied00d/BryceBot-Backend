<?php
//file_put_contents('/tmp/BryceBot_twilio_response_'.microtime(true), print_r($_POST,true));
$CallSid = $_POST['CallSid'];
$say = @file_get_contents('/tmp/BryceBot_twilio_'.$CallSid);
@unlink('/tmp/BryceBot_twilio_'.$CallSid);
require_once('twilio-twilio-php-3252c53/Services/Twilio.php');
$response = new Services_Twilio_Twiml;
if(!$CallSid)
{
	$response->say("Ooops. A Twilio error has occurred. Oh well, I'm a giant cock.");
} elseif(!$say)
{
	$response->say("Ummmmm I don't know what to say, soory.");
} else
{
	$response->say($say);
	//$response->redirect('http://twimlets.com/voicemail?Email=trafficone@gmail.com');
}
print $response;
?>
