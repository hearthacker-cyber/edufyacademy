<?php
require_once '../../vendor/autoload.php';

$client = new Google_Client();
// $client->setClientId('902927455984-aq7hrkrojic09434p2pab84njhsula1s.apps.googleusercontent.com');
// $client->setClientSecret('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');
// $client->setRedirectUri('https://edufyacademy.com/app/auth/google-callback.php');
$client->addScope('email');
$client->addScope('profile');

$authUrl = $client->createAuthUrl();
header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
exit();
?>