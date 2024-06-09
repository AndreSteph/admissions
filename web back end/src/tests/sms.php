<?php


function SendSMS($number, $message)
{

    $username = '0756557694';

    $password = 'amjunior';
    $message_category = "bulk";
    $message_type = "info";

    $sender = 'emma'; //(not more than 20 characters i.e letters and digits)

    $url = "sms.thepandoranetworks.com/API/send_sms/?";

    $parameters = "number=[number]&message=[message]&username=[username]&password=[password]&sender=[sender]&message_type=[message_type]&message_category=[message_category]";

    $parameters = str_replace("[message]", urlencode($message), $parameters);

    $parameters = str_replace("[sender]", urlencode($sender), $parameters);

    $parameters = str_replace("[number]", urlencode($number), $parameters);

    $parameters = str_replace("[username]", urlencode($username), $parameters);

    $parameters = str_replace("[password]", urlencode($password), $parameters);

    $parameters = str_replace("[message_type]", urlencode($message_type), $parameters);

    $parameters = str_replace("[message_category]", urlencode($message_category), $parameters);

    $live_url = "https://" . $url . $parameters;

    $parse_url = file($live_url);

    $response = $parse_url[0];

    return json_decode($response, true);

}

print_r( SendSMS("0740692054", "Your pearl rides otp is: 03238"));