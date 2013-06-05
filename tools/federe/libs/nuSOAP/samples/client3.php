<?php
/*
 *	$Id: client3.php,v 1.2 2004/01/26 07:15:20 snichol Exp $
 *
 *	Client sample.
 *
 *	Service: SOAP endpoint
 *	Payload: rpc/encoded
 *	Transport: http
 *	Authentication: none
 */
require_once('../lib/nusoap.php');
$proxyhost = isset($_POST['proxyhost']) ? $_POST['proxyhost'] : '';
$proxyport = isset($_POST['proxyport']) ? $_POST['proxyport'] : '';
$proxyusername = isset($_POST['proxyusername']) ? $_POST['proxyusername'] : '';
$proxypassword = isset($_POST['proxypassword']) ? $_POST['proxypassword'] : '';
$client = new soapclient("http://api.google.com/search/beta2", false,
						$proxyhost, $proxyport, $proxyusername, $proxypassword);
$err = $client->getError();
if ($err) {
	echo '<h2>Constructor error</h2><pre>' . $err . '</pre>';
}
$client->soap_defencoding = 'UTF-8';

echo 'You must set your own Google key in the source code to run this client!'; exit();
$params = array(
	'Googlekey'=>'set your own Google key',
	'queryStr'=>'robotics',
	'startFrom'=>0,
	'maxResults'=>10,
	'filter'=>true,
	'restrict'=>'',
	'adultContent'=>true,
	'language'=>'',
	'iencoding'=>'',
	'oendcoding'=>''
);
$result = $client->call("doGoogleSearch", $params, "urn:GoogleSearch", "urn:GoogleSearch");
if ($client->fault) {
	echo '<h2>Fault</h2><pre>'; print_r($result); echo '</pre>';
} else {
	$err = $client->getError();
	if ($err) {
		echo '<h2>Error</h2><pre>' . $err . '</pre>';
	} else {
		echo '<h2>Result</h2><pre>'; print_r($result); echo '</pre>';
	}
}
echo '<h2>Request</h2><pre>' . htmlspecialchars($client->request, ENT_QUOTES) . '</pre>';
echo '<h2>Response</h2><pre>' . htmlspecialchars($client->response, ENT_QUOTES) . '</pre>';
echo '<h2>Debug</h2><pre>' . htmlspecialchars($client->debug_str, ENT_QUOTES) . '</pre>';
?>
