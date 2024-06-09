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
        !isset($jsonData->name) || empty($jsonData->name) || !isset($jsonData->email) || empty($jsonData->email) || !isset($jsonData->gender) || empty($jsonData->gender)
        || !isset($jsonData->contact) || empty($jsonData->contact)
        || !isset($jsonData->password) || empty($jsonData->password)
    ) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        (!isset($jsonData->name) ? $response->addMessage("name filed is not supplied") : false);
        (empty($jsonData->name) ? $response->addMessage("name is empty and must not be empty") : false);
        (!isset($jsonData->contact) ? $response->addMessage("contact filed is not supplied") : false);
        (empty($jsonData->contact) ? $response->addMessage("contact is empty and must not be empty") : false);
        (!isset($jsonData->email) ? $response->addMessage("email filed is not supplied") : false);
        (empty($jsonData->email) ? $response->addMessage("email is empty and must not be empty") : false);
        (!isset($jsonData->gender) ? $response->addMessage("gender filed is not supplied") : false);
        (empty($jsonData->gender) ? $response->addMessage("gender is empty and must not be empty") : false);
        (!isset($jsonData->password) ? $response->addMessage("password filed is not supplied") : false);
        (empty($jsonData->password) ? $response->addMessage("password is empty and must not be empty") : false);

        $response->send();
        exit;
    }
    // echo $jsonData->university;
    // exit;
    $writeDB->beginTransaction();

    $password = password_hash($jsonData->password, PASSWORD_DEFAULT);
    $random = rand(1001, 9999);

    $query = $writeDB->prepare('INSERT INTO users(user_fullname, user_email, user_contact, user_password, user_gender,user_code)
           VALUES(:name, :email, :contact, :password, :gender, :code)');
    $query->bindParam(':name', $jsonData->name, PDO::PARAM_STR);
    $query->bindParam(':contact', $jsonData->contact, PDO::PARAM_STR);
    $query->bindParam(':email', $jsonData->email, PDO::PARAM_INT);
    $query->bindParam(':gender', $jsonData->gender, PDO::PARAM_INT);
    $query->bindParam(':password', $password, PDO::PARAM_INT);
    $query->bindParam(':code', $random, PDO::PARAM_INT);
    $query->execute();

    $rowCount = $query->rowCount();

    if ($rowCount === 0) {
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->addMessage("Internal Server Error");
        $response->send();
        exit;
    }

    $lastID = $writeDB->lastInsertId();
    
    sendSMS( $jsonData->contact,"Your University Course Calculator OTP is: ".$random);

    $writeDB->commit();

    $returnData = array();
    $returnData['rows_returned'] = $rowCount;
    //set up response for successful return
    $response = new Response();
    $response->setHttpStatusCode(201);
    $response->setSuccess(true);
    $response->addMessage("sign up success");
    $response->send();
    exit;

} catch (PDOException $ex) {
    $writeDB->rollBack();
    error_log("query error: {$ex}", 3, "../../app/logs/error.log");
    $response = new Response();
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->addMessage("internal server error");
    $response->send();
    exit;
}
