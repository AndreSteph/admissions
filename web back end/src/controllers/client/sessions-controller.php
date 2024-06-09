<?php

require_once '../database.php';
require_once '../../app/functions/general-functions.php';
require_once '../../model/response.php';

// attempt to set up connections to read and write db connections

try {
    $writeDB = DB::connectWriteDB();
    $readDB = DB::connectReadDB();
} catch (PDOException $ex) {

    // log connection error for troubleshooting and return a json error response
    error_log("Connection Error: {$ex}", 3, "../../app/logs/error.log");
    $response = new Response();
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->addMessage("database connection error");
    $response->send();
    exit;
}
if (array_key_exists("sessionid", $_GET)) {
    // get sessions id from query string
    $sessionid = $_GET['sessionid'];

    // check to see if sessions id in query string is not empty and is number, if not return json error
    if ($sessionid == '' || !is_numeric($sessionid)) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        ($sessionid == '' ? $response->addMessage("Session ID cannot be blank") : false);
        (!is_numeric($sessionid) ? $response->addMessage("Session ID must be numeric") : false);
        $response->send();
        exit;
    }

    // check to see if access token is provided in the HTTP Authorization header and that the value is longer than 0 chars
    // 401 error is for authentication failed or has not yet been provided
    if (!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) {
        $response = new Response();
        $response->setHttpStatusCode(401);
        $response->setSuccess(false);
        (!isset($_SERVER['HTTP_AUTHORIZATION']) ? $response->addMessage("Access token is missing from the header") : false);
        (strlen($_SERVER['HTTP_AUTHORIZATION']) < 1 ? $response->addMessage("Access token cannot be blank") : false);
        $response->send();
        exit;
    }

    $accesstoken = $_SERVER['HTTP_AUTHORIZATION'];
    // echo $accesstoken;
    // if request is a DELETE, e.g. delete session
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // attempt to query the database to check token details - use write connection as it needs to be synchronous for token
        try {
            // create db query to delete session where access token is equal to the one provided (leave other sessions active)
            // doesn't matter about if access token has expired as we are deleting the session
            $query = $writeDB->prepare('delete from sessions where session_id = :sessionid and access_token = :accesstoken');
            $query->bindParam(':sessionid', $sessionid, PDO::PARAM_INT);
            $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
            $query->execute();

            // get row count
            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                // set up response for unsuccessful log out response
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->addMessage("Internal server error");
                $response->send();
                exit;
            }

            // build response data array which contains the session id that has been deleted (logged out)
            $returnData = array();
            $returnData['session_id'] = intval($sessionid);

            // send successful response for log out
            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->setData($returnData);
            $response->send();
            exit;
        } catch (PDOException $ex) {
            error_log("query Error: {$ex}", 3, "../../app/logs/error.log");
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("internal server error");
            $response->send();
            exit;
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') {

        // check request's content type header is JSON
        if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
            // set up response for unsuccessful request
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage("Content Type header not set to JSON");
            $response->send();
            exit;
        }

        // get PATCH request body as the PATCHed data will be JSON format
        $rawPatchdata = file_get_contents('php://input');

        if (!$jsonData = json_decode($rawPatchdata)) {
            // set up response for unsuccessful request
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage("Request body is not valid JSON");
            $response->send();
            exit;
        }

        // check if patch request contains access token
        if (!isset($jsonData->refresh_token) || strlen($jsonData->refresh_token) < 1) {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            (!isset($jsonData->refresh_token) ? $response->addMessage("Refresh Token not supplied") : false);
            (strlen($jsonData->refresh_token) < 1 ? $response->addMessage("Refresh Token cannot be blank") : false);
            $response->send();
            exit;
        }

        // attempt to query the database to check token details - use write connection as it needs to be synchronous for token
        try {

            $refreshtoken = $jsonData->refresh_token;
            // get user record for provided session id, access AND refresh token
            // create db query to retrieve user details from provided access and refresh token
            $query = $writeDB->prepare('SELECT sessions.session_id as sessionid, sessions.users_user_id as userid, access_token, refresh_token, user_login_attempts, access_token_expiry, refresh_token_expiry from sessions, users where users.user_id = sessions.users_user_id and sessions.session_id = :sessionid and sessions.access_token = :accesstoken and sessions.refresh_token = :refreshtoken');
            $query->bindParam(':sessionid', $sessionid, PDO::PARAM_INT);
            $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
            $query->bindParam(':refreshtoken', $refreshtoken, PDO::PARAM_STR);
            $query->execute();

            // get row count
            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                // set up response for unsuccessful access token refresh attempt
                $response = new Response();
                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->addMessage("unathorized");
                $response->send();
                exit;
            }

            // get returned row
            $row = $query->fetch(PDO::FETCH_ASSOC);

            // save returned details into variables
            $returned_session_id = $row['sessionid'];
            $returned_user_id = $row['userid'];
            $returned_accesstoken = $row['access_token'];
            $returned_refreshtoken = $row['refresh_token'];
            $returned_login_attempts = $row['user_login_attempts'];
            $returned_accesstokenexpiry = $row['access_token_expiry'];
            $returned_refreshtokenexpiry = $row['refresh_token_expiry'];

            // check if account is locked out
            if ($returned_login_attempts >= 3) {
                $response = new Response();
                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->addMessage("account is currently locked out");
                $response->send();
                exit;
            }

            // check if refresh token has expired
            if (strtotime($returned_refreshtokenexpiry) < time()) {
                $response = new Response();
                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->addMessage("refresh token has expired");
                $response->send();
                exit;
            }

            //date and time generation
            $postdate = new DateTime();
            // set date for kampala
            $postdate->setTimezone(new DateTimeZone('Africa/Nairobi'));
            //formulate the new date
            $date = $postdate->format('Y-m-d H:i:s');

            // generate access token
            // use 24 random bytes to generate a token then encode this as base64
            // suffix with unix time stamp to guarantee uniqueness (stale tokens)
            $accesstoken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)) . time());

            // generate refresh token
            // use 24 random bytes to generate a refresh token then encode this as base64
            // suffix with unix time stamp to guarantee uniqueness (stale tokens)
            $refreshtoken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)) . time());

            // set access token and refresh token expiry in seconds (access token 20 minute lifetime and refresh token 14 days lifetime)
            // send seconds rather than date/time as this is not affected by timezones
            $access_token_expiry_seconds = 1200;
            $refresh_token_expiry_seconds = 3600;

            // create the query string to update the current session row in the sessions table and set the token and refresh token as well as their expiry dates and times
            $query = $writeDB->prepare('update sessions set access_token = :accesstoken, access_token_expiry = date_add(:date, INTERVAL :accesstokenexpiryseconds SECOND), refresh_token = :refreshtoken, refresh_token_expiry = date_add(:date_two, INTERVAL :refreshtokenexpiryseconds SECOND) where session_id = :sessionid and users_user_id = :userid and access_token = :returnedaccesstoken and refresh_token = :returnedrefreshtoken');
            // bind the user id
            $query->bindParam(':userid', $returned_user_id, PDO::PARAM_INT);
            // bind the session id
            $query->bindParam(':sessionid', $returned_session_id, PDO::PARAM_INT);
            // bind the date
            $query->bindParam(':date', $date, PDO::PARAM_STR);
            // bind the date
            $query->bindParam(':date_two', $date, PDO::PARAM_STR);
            // bind the access token
            $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
            // bind the access token expiry date
            $query->bindParam(':accesstokenexpiryseconds', $access_token_expiry_seconds, PDO::PARAM_INT);
            // bind the refresh token
            $query->bindParam(':refreshtoken', $refreshtoken, PDO::PARAM_STR);
            // bind the refresh token expiry date
            $query->bindParam(':refreshtokenexpiryseconds', $refresh_token_expiry_seconds, PDO::PARAM_INT);
            // bind the old access token for where clause as user could have multiple sessions
            $query->bindParam(':returnedaccesstoken', $returned_accesstoken, PDO::PARAM_STR);
            // bind the old refresh token for where clause as user could have multiple sessions
            $query->bindParam(':returnedrefreshtoken', $returned_refreshtoken, PDO::PARAM_STR);
            // run the query
            $query->execute();

            // get count of rows updated - should be 1
            $rowCount = $query->rowCount();

            // check that a row has been updated
            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->addMessage("Please log in again");
                $response->send();
                exit;
            }

            // build response data array which contains the session id, access token and refresh token
            $returnData = array();
            $returnData['session_id'] = $returned_session_id;
            $returnData['access_token'] = $accesstoken;
            $returnData['access_token_expiry'] = $access_token_expiry_seconds;
            $returnData['refresh_token'] = $refreshtoken;
            $returnData['refresh_token_expiry'] = $refresh_token_expiry_seconds;

            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->setData($returnData);
            $response->send();
            exit;
        } catch (PDOException $ex) {
            error_log("query Error: {$ex}", 3, "../../app/logs/error.log");
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("internal server error");
            $response->send();
            exit;
        }
    }
    // error when not DELETE or PATCH
    else {
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage("request method not allowed");
        $response->send();
        exit;
    }
} elseif (empty($_GET)) {
    // check whether the post method is only given []
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage("Request method not allowed");
        $response->send();
        exit;
    }
    // delay login by 1 second to slow down any potential brute force attacks
    sleep(1);

    // check request's content type header is JSON
    if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
        // set up response for unsuccessful request
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("Content Type header not set to JSON");
        $response->send();
        exit;
    }

    // get POST request body as the POSTED data will be JSON format
    $rawPostData = file_get_contents('php://input');

    if (!$jsonData = json_decode($rawPostData)) {
        // set up response for unsuccessful request
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("Request body is not valid JSON");
        $response->send();
        exit;
    }

    // check if post request contains username and password in body as they are mandatory
    if (!isset($jsonData->username) || !isset($jsonData->password)) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        (!isset($jsonData->username) ? $response->addMessage("username not supplied") : false);
        (!isset($jsonData->password) ? $response->addMessage("password not supplied") : false);
        $response->send();
        exit;
    }

    // check to make sure that username and password are not empty and empty
    if (strlen($jsonData->username) < 0 || strlen($jsonData->password) < 0) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        (strlen($jsonData->username) < 0 ? $response->addMessage("username cannot be blank") : false);
        (strlen($jsonData->password) < 0 ? $response->addMessage("password cannot be blank") : false);
        $response->send();
        exit;
    }

    try {
        $username = $jsonData->username;
        $password = $jsonData->password;
       
        // create db query
        $query = $writeDB->prepare('SELECT * FROM users WHERE user_email = :username');
        $query->bindParam(':username', $username, PDO::PARAM_STR);
        $query->execute();

        // get row count
        $rowCount = $query->rowCount();

        if ($rowCount === 0) {
            // set up response for unsuccessful login attempt - obscure what is incorrect by saying username or password is wrong
            $response = new Response();
            $response->setHttpStatusCode(401);
            $response->setSuccess(false);
            $response->addMessage("Username or password is incorrect");
            $response->send();
            exit;
        }

        // get first row returned
        $row = $query->fetch(PDO::FETCH_ASSOC);
        // save returned details into variables
        $returned_id = $row['user_id'];
        $returned_username = $row['user_email'];
        $returned_password = $row['user_password'];
        $returned_login_attempts = $row['user_login_attempts'];
        $returned_user_status = $row['user_status'];

        // check if account is locked out
        if ($returned_login_attempts >= 10) {
            $response = new Response();
            $response->setHttpStatusCode(401);
            $response->setSuccess(false);
            $response->addMessage("Account is currently locked out");
            $response->send();
            exit;
        }

        if ($returned_user_status !== "active") {
            $response = new Response();
            $response->setHttpStatusCode(401);
            $response->setSuccess(false);
            $response->addMessage("Account not active, please contact system admin");
            $response->send();
            exit;
        }

        // check if password is the same using the hash
        if (!password_verify($password, $returned_password)) {
            // create the query to increment attempts figure
            $query = $writeDB->prepare('update users set user_login_attempts = user_login_attempts+1 where user_id = :id');
            // bind the sacco id
            $query->bindParam(':id', $returned_id, PDO::PARAM_INT);
            // run the query
            $query->execute();

            // send response
            $response = new Response();
            $response->setHttpStatusCode(401);
            $response->setSuccess(false);
            $response->addMessage("Username or password is incorrect");
            $response->send();
            exit;
        }

        // generate access token
        // use 24 random bytes to generate a token then encode this as base64
        // suffix with unix time stamp to guarantee uniqueness (stale tokens)
        $accesstoken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)) . time());
        // generate refresh token
        // use 24 random bytes to generate a refresh token then encode this as base64
        // suffix with unix time stamp to guarantee uniqueness (stale tokens)
        $refreshtoken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)) . time());
        // set access token and refresh token expiry in seconds (access token 20 minute lifetime and refresh token 14 days lifetime)
        // send seconds rather than date/time as this is not affected by timezones
        $access_token_expiry_seconds = 103680;
        $refresh_token_expiry_seconds = 203680;

    } catch (PDOException $ex) {
        error_log("query Error: {$ex}", 3, "../../app/logs/error.log");
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->addMessage("internal server error");
        $response->send();
        exit;
    }
    // new try catch as this is a transaction so should include roll back if error
    try {
        // start transaction as two queries should run one after the other
        $writeDB->beginTransaction();
        // create the query string to reset attempts figure after successful login
        $query = $writeDB->prepare('update users set user_login_attempts = 0 where user_id = :id');
        // bind the sacco id
        $query->bindParam(':id', $returned_id, PDO::PARAM_INT);
        // run the query
        $query->execute();
        //date and time generation
        $postdate = new DateTime();
        // set date for kampala
        $postdate->setTimezone(new DateTimeZone('Africa/Nairobi'));
        //formulate the new date
        $date = $postdate->format('Y-m-d H:i:s');
        // create the query string to insert new session into sessions table and set the token and refresh token as well as their expiry dates and times
        $query = $writeDB->prepare('INSERT INTO sessions (users_user_id, access_token, access_token_expiry, refresh_token, refresh_token_expiry) VALUES (:userid, :accesstoken, date_add(:date, INTERVAL :accesstokenexpiryseconds SECOND), :refreshtoken, date_add(:date2, INTERVAL :refreshtokenexpiryseconds SECOND))');
        // bind the sacco id
        $query->bindParam(':userid', $returned_id, PDO::PARAM_INT);
        // bind the access token
        $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
        // bind the date
        $query->bindParam(':date', $date, PDO::PARAM_STR);
        // bind the date
        $query->bindParam(':date2', $date, PDO::PARAM_STR);
        // bind the access token expiry date
        $query->bindParam(':accesstokenexpiryseconds', $access_token_expiry_seconds, PDO::PARAM_INT);
        // bind the refresh token
        $query->bindParam(':refreshtoken', $refreshtoken, PDO::PARAM_STR);
        // bind the refresh token expiry date
        $query->bindParam(':refreshtokenexpiryseconds', $refresh_token_expiry_seconds, PDO::PARAM_INT);
        // run the query
        $query->execute();

        // get last session id so we can return the session id in the json
        $lastSessionID = $writeDB->lastInsertId();
        // commit new row and updates if successful
        $writeDB->commit();
        // build response data array which contains the access token and refresh tokens
        $returnData = array();
        $returnData['session_id'] = intval($lastSessionID);
        $returnData['access_token'] = $accesstoken;
        $returnData['access_token_expires_in'] = $access_token_expiry_seconds;
        $returnData['refresh_token'] = $refreshtoken;
        $returnData['refresh_token_expires_in'] = $refresh_token_expiry_seconds;

        $response = new Response();
        $response->setHttpStatusCode(201);
        $response->setSuccess(true);
        $response->setData($returnData);
        $response->send();
        exit;

    } catch (PDOException $ex) {
        // log connection error for troubleshooting and return a json error response
        error_log("query Error: {$ex}", 3, "../../app/logs/error.log");
        $writeDB->rollBack();
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->addMessage("internal server error");
        $response->send();
        exit;
    }
} else {
    $response = new Response();
    $response->setHttpStatusCode(404);
    $response->setSuccess(false);
    $response->addMessage("end point not found");
    $response->send();
    exit;
}
