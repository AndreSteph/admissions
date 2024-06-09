<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
header("Access-Control-Allow-Credentials: true");
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
if ($method === "OPTIONS") {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method,Access-Control-Request-Headers, Authorization");
    header("HTTP/1.1 200 OK");
    die();
}

function SendSMS($number,$message) { 

$username = '0756557694';

$password = 'amjunior';
$message_category = "bulk";
$message_type = "info";

$sender = 'emma'; //(not more than 20 characters i.e letters and digits)

$url = "sms.thepandoranetworks.com/API/send_sms/?";

$parameters="number=[number]&message=[message]&username=[username]&password=[password]&sender=[sender]&message_type=[message_type]&message_category=[message_category]";

$parameters = str_replace("[message]", urlencode($message), $parameters);

$parameters = str_replace("[sender]", urlencode($sender),$parameters);

$parameters = str_replace("[number]", urlencode($number),$parameters);

$parameters = str_replace("[username]", urlencode($username),$parameters);

$parameters = str_replace("[password]", urlencode($password),$parameters);

$parameters = str_replace("[message_type]", urlencode($message_type),$parameters);

$parameters = str_replace("[message_category]", urlencode($message_category),$parameters);

$live_url="https://".$url.$parameters;

$parse_url=file($live_url);

 $response = $parse_url[0];

return json_decode($response, true);

}
