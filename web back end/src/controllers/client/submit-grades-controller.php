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

// Function to fetch subject IDs based on titles
function fetchSubjectIds($pdo, $selectedSubjects)
{
    $subjectIds = [];
    foreach ($selectedSubjects as $title => $grade) {
        $stmt = $pdo->prepare("SELECT subject_id FROM subjects WHERE subject_title = ?");
        $stmt->execute([$title]);
        $subjectIds[$title] = $stmt->fetchColumn();
    }
    return $subjectIds;
}

// Function to calculate points based on grades
function calculatePoints($selectedGrades)
{
    $gradePoints = ['A' => 6, 'B' => 5, 'C' => 4, 'D' => 3, 'E' => 2, 'F' => 0];
    $totalPoints = 0;
    foreach ($selectedGrades as $grade) {
        $totalPoints += $gradePoints[$grade] ?? 0; // Using null coalescing operator to handle unexpected grades
    }
    return $totalPoints * 3;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response = new Response();
    $response->setHttpStatusCode(405);
    $response->setSuccess(false);
    $response->addMessage("Request method not allowed");
    $response->send();
    exit;
}

try {

    // get POST request body as the POSTED data will be JSON format
    $rawPostData = file_get_contents('php://input');
    // print_r($rawPostData);

    $jsonData = json_decode($rawPostData, true);
    // print_r($jsonData);

    $universityId = $jsonData['universityId'];
    $grades = $jsonData['grades'];
    $numDs = $grades['numDs'];
    $numCs = $grades['numCs'];
    $numPs = $grades['numPs'];
    $numFs = $grades['numFs'];
    $selectedSubjects = $grades['selectedSubjects'];
    // print_r($selectedSubjects);
    $subSubjectGrade = $grades['subSubjectGrade'];
    $generalPaperGrade = $grades['generalPaperGrade'];

    // Calculate the weighted scores
    $totalScore = ($numDs * 0.3) + ($numCs * 0.2) + ($numPs * 0.1) + ($numFs * 0);

    // Include scores for Subsidiary and General Paper if they are 'O'
    $totalScore += ($subSubjectGrade == 'O' ? 1 : 0) + ($generalPaperGrade == 'O' ? 1 : 0);

    $subjectIds = fetchSubjectIds($writeDB, $selectedSubjects);
    $totalPoints = calculatePoints($selectedSubjects) + $totalScore; // Assuming selectedSubjects contains titles mapped to grades

    // echo $totalPoints;
 // Prepare the points values for the query (this step needs clarification on how points are mapped to subjects)
    // $pointsSubjectOne = $totalPoints; // Placeholder
    // $pointsSubjectTwo = $totalPoints; // Placeholder

    // Assuming subject IDs are ordered and only two subjects are considered
    $subjectOneId = array_values($subjectIds)[0] ?? null;
    $subjectTwoId = array_values($subjectIds)[1] ?? null;

    $stmt = $writeDB->prepare("SELECT course_id, course_title, course_weights
                               FROM courses
                               WHERE course_subject_one = :subjectOneId AND
                                     course_subject_two = :subjectTwoId AND
                                      course_weights <= :points");
    $stmt->execute([
        ':subjectOneId' => $subjectOneId,
        ':subjectTwoId' => $subjectTwoId,
        ':points' => $totalPoints,
    ]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $returnData = array();
    $returnData['rows_returned'] = 0;
    //set up response for successful return
    $response = new Response();
    $response->setHttpStatusCode(201);
    $response->setSuccess(true);
    $response->setData($courses);
    $response->send();
    exit;

} catch (PDOException $ex) {
    // $writeDB->rollBack();
    error_log("query error: {$ex}", 3, "../../app/logs/error.log");
    $response = new Response();
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->addMessage("internal server error");
    $response->send();
    exit;
}
