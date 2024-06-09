<?php

require_once 'database.php';
require_once '../app/functions/general-functions.php';
require_once '../model/response.php';

// attempt to set up connections to read and write db connections
try {
    $writeDB = DB::connectWriteDB();
    $readDB = DB::connectReadDB();
} catch (PDOException $ex) {
    // log connection error for troubleshooting and return a json error response
    error_log("Connection Error: {$ex}", 3, "../app/logs/error.log");
    $response = new Response();
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->addMessage("database connection error");
    $response->send();
    exit;
}

// require_once '../app/auth/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $response = new Response();
    $response->setHttpStatusCode(405);
    $response->setSuccess(false);
    $response->addMessage("request method not allowed");
    $response->send();
    exit;
}

try {
    $query = $readDB->prepare('SELECT * FROM users WHERE user_role = "normal" ORDER BY user_id DESC');
    $query->execute();

    // get row count
    $rowCount = $query->rowCount();

    // create  array to store returned
    $userArray = array();

    while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
        extract($row);
        $client = array(
            "id" => $user_id,
            "name" => $user_fullname,
            "contact" => $user_contact,
            "email" => $user_email,
            "gender" => $user_gender,
            "timestamp" => $user_timestamp,
            "onUpdate" => $user_onUpdate,

        );

        $userArray[] = $client;
    }

    $returnData = array();
    $returnData['rows_returned'] = $rowCount;
    $returnData['users'] = $userArray;
    //set up response for successful return
    $response = new Response();
    $response->setHttpStatusCode(200);
    $response->setSuccess(true);
    $response->setData($returnData);
    $response->send();
    exit;
} catch (PDOException $ex) {
    error_log("query error: {$ex}", 3, "../app/logs/error.log");
    $response = new Response();
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->addMessage("internal server error");
    $response->send();
    exit;
}
