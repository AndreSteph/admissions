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

// check whether the post method is only given []
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response = new Response();
    $response->setHttpStatusCode(405);
    $response->setSuccess(false);
    $response->addMessage("Request method not allowed");
    $response->send();
    exit;
}

try {
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

    if (
        !isset($jsonData->code) || empty($jsonData->code)) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        (!isset($jsonData->code) ? $response->addMessage("code filed is not supplied") : false);
        (empty($jsonData->code) ? $response->addMessage("code is empty and must not be empty") : false);
      
        $response->send();
        exit;
    }
    // echo $jsonData->university;
    // exit;
    // $writeDB->beginTransaction();

    $query = $writeDB->prepare('SELECT * FROM users WHERE user_code = :code');
    $query->bindParam(':code', $jsonData->code, PDO::PARAM_STR);
    $query->execute();

    $rowCount = $query->rowCount();

    if ($rowCount === 0) {
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->addMessage("wrong verification code");
        $response->send();
        exit;
    }

   
    $returnData = array();
    $returnData['rows_returned'] = $rowCount;
    //set up response for successful return
    $response = new Response();
    $response->setHttpStatusCode(201);
    $response->setSuccess(true);
    $response->addMessage("verification success");
    $response->send();
    exit;

} catch (PDOException $ex) {
    error_log("query error: {$ex}", 3, "../../app/logs/error.log");
    $response = new Response();
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->addMessage("internal server error");
    $response->send();
    exit;
}
