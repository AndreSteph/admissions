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

if (array_key_exists("subjectid", $_GET)) {
    // get  id from query string
    $subjectid = $_GET['subjectid'];

    //check to see if  in query string is not empty and is number, if not return json error
    if ($subjectid == '' || !is_numeric($subjectid)) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("subject id cannot be blank or must be numeric");
        $response->send();
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        try {

            $query = $readDB->prepare('SELECT * FROM subjects WHERE subject_id=:id');
            $query->bindParam(':id', $subjectid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();
            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage("subject not found");
                $response->send();
                exit;
            }
            // $expenseArray = array();

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                $subject = array(
                    "id" => $subject_id,
                    "title" => $subject_title,
                    "timestamp" => $subject_timestamp,
                    "onUpdate" => $subject_onUpdate,
                );
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['subject'] = $subject;
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
            $response->addMessage("internal server errror");
            $response->send();
            exit;
        }

    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        try {

            $writeDB->beginTransaction();

            $query = $writeDB->prepare('DELETE subjects FROM subjects WHERE subject_id = :id');
            $query->bindParam(':id', $subjectid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->addMessage("internal server error");
                $response->send();
                exit;
            }

            $writeDB->commit();

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            // set up response for successful return
            $response = new Response();
            $response->setHttpStatusCode(201);
            $response->setSuccess(true);
            $response->addMessage("subject deleted successfully");
            $response->send();
            exit;
        } catch (PDOException $ex) {
            $writeDB->rollback();
            error_log("query error: {$ex}", 3, "../app/logs/error.log");
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("internal server error");
            $response->send();
            exit;
        }

    } elseif (($_SERVER['REQUEST_METHOD'] === 'PATCH')) {
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

            // get PATCH request body as the PATCHed data will be JSON format
            $rawPatchData = file_get_contents('php://input');

            if (!$jsonData = json_decode($rawPatchData)) {
                // set up response for unsuccessful request
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("Request body is not valid JSON");
                $response->send();
                exit;
            }

            $title_updated = false;

            $queryFields = "";
            if (isset($jsonData->title)) {
                // set status field updated to true
                $title_updated = true;
                // add status field to query field string
                $queryFields .= "subject_title = :title, ";
            }

            // remove the right hand comma and trailing space
            $queryFields = rtrim($queryFields, ", ");

            // check if any client fields supplied in JSON
            if ($title_updated === false) {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("No update fields provided");
                $response->send();
                exit;
            }
            $query = $writeDB->prepare('SELECT * from subjects where subject_id = :id');
            $query->bindParam(':id', $subjectid, PDO::PARAM_INT);
            $query->execute();

            // get row count
            $rowCount = $query->rowCount();

            // make sure that the task exists for a given task id
            if ($rowCount === 0) {
                // set up response for unsuccessful return
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage("No subject found to update");
                $response->send();
                exit;
            }
            $queryString = "UPDATE subjects set " . $queryFields . " WHERE subject_id = :id";
            $query = $writeDB->prepare($queryString);

            if ($title_updated === true) {
                $up_title = $jsonData->title;
                // bind the parameter of the new value from the object to the query (prevents SQL injection)
                $query->bindParam(':title', $up_title, PDO::PARAM_STR);
            }

            $query->bindParam(':id', $subjectid, PDO::PARAM_INT);
            // run the query
            $query->execute();

            $rowCount = $query->rowCount();

            // check if row was actually updated, could be that the given values are the same as the stored values
            if ($rowCount === 0) {
                // set up response for unsuccessful return
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("no changes have been detected");
                $response->send();
                exit;
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            //set up response for successful return
            $response = new Response();
            $response->setHttpStatusCode(201);
            $response->setSuccess(true);
            $response->addMessage("subject updated");
            $response->send();
            exit;
        } catch (PDOException $ex) {
            error_log("query error: {$ex}", 3, "../app/logs/error.log");
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("internal server errror");
            $response->send();
            exit;
        }
    } else {
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage('Request method not allowed');
        $response->send();
        exit;
    }
} elseif (empty($_GET)) {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        try {

            $query = $readDB->prepare('SELECT * FROM subjects');
            $query->execute();

            $rowCount = $query->rowCount();

            $subjectsArray = array();

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                $subjects = array(
                    "id" => $subject_id,
                    "title" => $subject_title,
                    "timestamp" => $subject_timestamp,
                    "onUpdate" => $subject_onUpdate,
                );
                $subjectsArray[] = $subjects;
            }
            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['subjects'] = $subjectsArray;
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
            $response->addMessage("internal server errror");
            $response->send();
            exit;
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                !isset($jsonData->title) || empty($jsonData->title)) {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                (!isset($jsonData->title) ? $response->addMessage("title filed is not supplied") : false);
                (empty($jsonData->title) ? $response->addMessage("title is empty and must not be empty") : false);
                $response->send();
                exit;
            }

            $writeDB->beginTransaction();

            $query = $writeDB->prepare('INSERT INTO subjects(subject_title)
           VALUES(:title)');
            $query->bindParam(':title', $jsonData->title, PDO::PARAM_STR);
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
            $writeDB->commit();

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            //set up response for successful return
            $response = new Response();
            $response->setHttpStatusCode(201);
            $response->setSuccess(true);
            $response->addMessage("new subject added");
            $response->send();
            exit;

        } catch (PDOException $ex) {
            $writeDB->rollBack();
            error_log("query error: {$ex}", 3, "../app/logs/error.log");
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("internal server error");
            $response->send();
            exit;
        }
    }
} else {
    $response = new Response();
    $response->setHttpStatusCode(404);
    $response->setSuccess(false);
    $response->addMessage("end point not found");
    $response->send();
    exit;
}
