<?php

if (!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) {
    $response = new Response();
    $response->setHttpStatusCode(401);
    $response->setSuccess(false);
    (!isset($_SERVER['HTTP_AUTHORIZATION']) ? $response->addMessage("Access token is missing from the header") : false);
    (strlen($_SERVER['HTTP_AUTHORIZATION']) < 1 ? $response->addMessage("Access token cannot be blank") : false);
    $response->send();
    exit;
}

// get supplied access token from authorisation header - used for delete (log out) and patch (refresh)
$accesstoken = $_SERVER['HTTP_AUTHORIZATION'];

// attempt to query the database to check token details - use write connection as it needs to be synchronous for token
try {
    // create db query to check access token is equal to the one provided
    $query = $writeDB->prepare('SELECT user_id, access_token_expiry, user_login_attempts FROM sessions, users WHERE sessions.users_user_id = users.user_id  AND access_token = :accesstoken');
    $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
    $query->execute();

    // get row count
    $rowCount = $query->rowCount();

    if ($rowCount === 0) {
        // set up response for unsuccessful log out response
        $response = new Response();
        $response->setHttpStatusCode(401);
        $response->setSuccess(false);
        $response->addMessage("Invalid access token");
        $response->send();
        exit;
    }

    // get returned row
    $row = $query->fetch(PDO::FETCH_ASSOC);

    // save returned details into variables
    $returned_id = $row['user_id'];
    $returned_access_token_expiry = $row['access_token_expiry'];
    $returned_attempts = $row['user_login_attempts'];

    // check if account is locked out
    if ($returned_attempts >= 10) {
        $response = new Response();
        $response->setHttpStatusCode(401);
        $response->setSuccess(false);
        $response->addMessage("users account is currently locked out");
        $response->send();
        exit;
    }

    // check if access token has expired
    if (strtotime($returned_access_token_expiry) < time()) {
        $response = new Response();
        $response->setHttpStatusCode(401);
        $response->setSuccess(false);
        $response->addMessage("Access token has expired");
        $response->send();
        exit;
    }
} catch (PDOException $ex) {
    error_log("query error: {$ex}", 3, "../app/logs/error.log");
    $response = new Response();
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->addMessage("internal server errror");
    $response->send();
    exit;
}
