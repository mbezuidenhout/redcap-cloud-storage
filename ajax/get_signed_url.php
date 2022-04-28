<?php

namespace Stanford\GoogleStorage;

use MicrosoftAzure\Storage\Blob\Internal\BlobResources;
use MicrosoftAzure\Storage\Common\Internal\Authentication\SharedKeyAuthScheme;
use MicrosoftAzure\Storage\Common\Internal\Resources;
use Stanford\CloudStorage\CloudStorage;

/** @global CloudStorage $module */

try {
    if (isset($_GET['action']) && $_GET['action'] == 'upload') {
        $contentType     = filter_var($_GET['content_type'], \FILTER_SANITIZE_STRING);
        $fileName        = filter_var($_GET['file_name'], \FILTER_SANITIZE_STRING);
        //$fileSize    = filter_var($_GET['file_size'], \FILTER_SANITIZE_NUMBER_INT);
        $fieldName       = filter_var($_GET['field_name'], \FILTER_SANITIZE_STRING);
        $recordId        = filter_var($_GET['record_id'], \FILTER_SANITIZE_STRING);
        $eventId         = filter_var($_GET['event_id'], \FILTER_SANITIZE_NUMBER_INT);
        $instanceId      = filter_var($_GET['instance_id'], \FILTER_SANITIZE_NUMBER_INT);
        $storagePlatform = $module->getPlatformByFieldName($fieldName);
        $prefix          = $storagePlatform->getUploadPrefix($fieldName);
        $path            = $module->buildUploadPath($prefix, $fieldName, $fileName, $recordId, $eventId, $instanceId);
        $bucketOrContainerName = $module->getBucketOrContainerNameByFieldName($fieldName);
        $upload          = $storagePlatform->createUpload($bucketOrContainerName, $path, $contentType);
        $httpHeaders     = $upload->getHeaders();
        $url             = $upload->getUrl();
        if (defined('USERID')) {
            \REDCap::logEvent(USERID . " generated an upload signed URL for $fileName ", '', null, null);
        } else {
            \REDCap::logEvent("Generated an upload signed URL for $fileName ", '', null, null);
        }
        echo json_encode(array('status' => 'success', 'url' => $url, 'path' => $path, 'platform' => $storagePlatform->getPlatformName(), 'headers' => $httpHeaders));
    } elseif (isset($_GET['action']) && $_GET['action'] == 'download') {
        $fileName = filter_var($_GET['file_name'], \FILTER_SANITIZE_STRING);
        $fieldName = filter_var($_GET['field_name'], \FILTER_SANITIZE_STRING);
        $bucketOrContainerName = $module->getBucketOrContainerNameByFieldName($fieldName);
        $platform = $module->getPlatformByFieldName($fieldName);
        $now = new \DateTime();
        // Azure only allows lowercase paths.
        $link = $platform->getSignedUrl($bucketOrContainerName, $fileName, $now);
        \REDCap::logEvent("Generated signed download URL for $fileName by user id " . USERID, '', null, null);

        echo json_encode(array('status' => 'success', 'link' => $link));
    } else {
        throw new \Exception("No such action");
    }

} catch (\LogicException $e) {
    $module->emError($e->getMessage());
    http_response_code(404);
    echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
} catch (\Exception $e) {
    $module->emError($e->getMessage());
    http_response_code(404);
    echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
}
?>