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
function getSubject($dbConn, $subjectid)
{
    $query = $dbConn->prepare("SELECT * FROM subjects WHERE subject_id = :id");
    $query->bindParam(":id", $subjectid, PDO::PARAM_INT);
    $query->execute();
    $row = $query->fetch(PDO::FETCH_ASSOC);
    return $row['subject_title'] ?? "";
}
if (array_key_exists("courseid", $_GET)) {
    // get  id from query string
    $courseid = $_GET['courseid'];

    //check to see if  in query string is not empty and is number, if not return json error
    if ($courseid == '' || !is_numeric($courseid)) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("course id cannot be blank or must be numeric");
        $response->send();
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        try {

            $query = $readDB->prepare('SELECT * FROM courses, universities WHERE course_id=:id AND universities_university_id = university_id');
            $query->bindParam(':id', $courseid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();
            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage("course not found");
                $response->send();
                exit;
            }
            // $expenseArray = array();

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                $course = array(
                    "id" => $course_id,
                    "title" => $course_title,
                    "weights" => $course_weights,
                    "university" => $university_title,
                    "timestamp" => $course_timestamp,
                    "onUpdate" => $course_onUpdate,
                );
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['course'] = $course;
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

            $query = $writeDB->prepare('DELETE courses FROM courses WHERE course_id = :id');
            $query->bindParam(':id', $courseid, PDO::PARAM_INT);
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
            $response->addMessage("course deleted successfully");
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
            $weight_updated = false;

            $queryFields = "";
            if (isset($jsonData->title)) {
                // set status field updated to true
                $title_updated = true;
                // add status field to query field string
                $queryFields .= "course_title = :title, ";
            }
            if (isset($jsonData->weights)) {
                // set status field updated to true
                $weight_updated = true;
                // add status field to query field string
                $queryFields .= "course_weights = :weight, ";
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
            $query = $writeDB->prepare('SELECT * from courses where course_id = :id');
            $query->bindParam(':id', $courseid, PDO::PARAM_INT);
            $query->execute();

            // get row count
            $rowCount = $query->rowCount();

            // make sure that the task exists for a given task id
            if ($rowCount === 0) {
                // set up response for unsuccessful return
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage("No course found to update");
                $response->send();
                exit;
            }
            $queryString = "UPDATE courses set " . $queryFields . " WHERE course_id = :id";
            $query = $writeDB->prepare($queryString);

            if ($title_updated === true) {
                $up_title = $jsonData->title;
                // bind the parameter of the new value from the object to the query (prevents SQL injection)
                $query->bindParam(':title', $up_title, PDO::PARAM_STR);
            }

            if ($weight_updated === true) {
                $up_weight = $jsonData->weights;
                // bind the parameter of the new value from the object to the query (prevents SQL injection)
                $query->bindParam(':weight', $up_weight, PDO::PARAM_STR);
            }

            $query->bindParam(':id', $courseid, PDO::PARAM_INT);
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
            $response->addMessage("course updated");
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

            $query = $readDB->prepare('SELECT * FROM courses, universities, subjects WHERE universities_university_id = university_id');
            $query->execute();

            $rowCount = $query->rowCount();

            $coursesArray = array();

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                $courses = array(
                    "id" => $course_id,
                    "title" => $course_title,
                    "weights" => $course_weights,
                    "subject_one" => getSubject($writeDB, $course_subject_one),
                    "subject_two" => getSubject($writeDB, $course_subject_two),
                    "university" => $university_title,
                    "timestamp" => $course_timestamp,
                    "onUpdate" => $course_onUpdate,
                );
                $coursesArray[] = $courses;
            }
            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['courses'] = $coursesArray;
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
                !isset($jsonData->title) || empty($jsonData->title) || !isset($jsonData->weights) || empty($jsonData->weights) || !isset($jsonData->university) || empty($jsonData->university)
                || !isset($jsonData->subject_one) || empty($jsonData->subject_one)
                || !isset($jsonData->subject_two) || empty($jsonData->subject_two)
            ) {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                (!isset($jsonData->title) ? $response->addMessage("title filed is not supplied") : false);
                (empty($jsonData->title) ? $response->addMessage("title is empty and must not be empty") : false);
                (!isset($jsonData->weights) ? $response->addMessage("weights filed is not supplied") : false);
                (empty($jsonData->weights) ? $response->addMessage("weights is empty and must not be empty") : false);
                (!isset($jsonData->university) ? $response->addMessage("university filed is not supplied") : false);
                (empty($jsonData->university) ? $response->addMessage("university is empty and must not be empty") : false);
                (!isset($jsonData->subject_one) ? $response->addMessage("subject_one filed is not supplied") : false);
                (empty($jsonData->subject_one) ? $response->addMessage("subject_one is empty and must not be empty") : false);
                (!isset($jsonData->subject_two) ? $response->addMessage("subject_two filed is not supplied") : false);
                (empty($jsonData->subject_two) ? $response->addMessage("subject_two is empty and must not be empty") : false);

                $response->send();
                exit;
            }
            // echo $jsonData->university;
            // exit;
            $writeDB->beginTransaction();

            $query = $writeDB->prepare('INSERT INTO courses(course_title, course_weights, universities_university_id, course_subject_one, course_subject_two)
           VALUES(:title, :weights, :university, :subject_one, :subject_two)');
            $query->bindParam(':title', $jsonData->title, PDO::PARAM_STR);
            $query->bindParam(':weights', $jsonData->weights, PDO::PARAM_STR);
            $query->bindParam(':university', $jsonData->university, PDO::PARAM_INT);
            $query->bindParam(':subject_one', $jsonData->subject_one, PDO::PARAM_INT);
            $query->bindParam(':subject_two', $jsonData->subject_two, PDO::PARAM_INT);
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
            $response->addMessage("new course added");
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
