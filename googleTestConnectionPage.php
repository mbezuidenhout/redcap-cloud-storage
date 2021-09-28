<?php

/**
 * @global \Stanford\CloudStorage\CloudStorage $module
 *
 * @author Marius Bezuidenhout
 */

$status = 'success';
$message = "Successfully connected to Google datastore<br>";

try {
    $result = $module->testConnection(\Stanford\CloudStorage\CloudStorage::PLATFORM_GOOGLE);
    if(false === $result) {
        $status = 'warning';
        $message = "Unknown error occurred while testing connection.";
    }
} catch(\Exception $e) {
    $status = 'danger';
    $message = $e->getMessage();
    if ($e instanceof GuzzleHttp\Exception\ConnectException) {
        $message = "Failed to connect to Google datastore";
    }
}

//$message .= "Types of status: <ul><li><strong>success</strong></li><li><strong>warning</strong></li><li><strong>danger</strong></li></ul>";

echo json_encode(array(
    'status' => $status,
    'message' => $message
));