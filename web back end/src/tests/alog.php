<?php

$messageIdentifier = "";
$message = "[#][TikTok] 3193 is your verification code 3gg+Nv9RHae";
$searchTerms = ["3gg", "#rCwy5zs", "fJpzQvK2eu1"];
foreach ($searchTerms as $searchTerm) {
    if (strpos($message, $searchTerm) !== false) {
        if ($searchTerm == "3gg") {
            $messageIdentifier = "3gg+Nv9RHae";
        } else {
            $messageIdentifier = $searchTerm;
        }
    }
}

echo $messageIdentifier;