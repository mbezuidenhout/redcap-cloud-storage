<?php

/**
 * @global \Stanford\CloudStorage\CloudStorage $module
 *
 * @author Marius Bezuidenhout
 */

$status = 'success';
$message = "Successfully connected to Azure<br>";

try {
    $module->testConnection(\Stanford\CloudStorage\CloudStorage::PLATFORM_AZURE);
} catch(\Exception $e) {
    $status = 'danger';
    $message = $e->getMessage();
    if ($e instanceof GuzzleHttp\Exception\ConnectException) {
        $message = "Failed to connect to " . $module->getPlatform(\Stanford\CloudStorage\CloudStorage::PLATFORM_AZURE)->getEndpoint();
    }
}

//$message .= "Types of status: <ul><li><strong>success</strong></li><li><strong>warning</strong></li><li><strong>danger</strong></li></ul>";

echo json_encode(array(
    'status' => $status,
    'message' => $message
));