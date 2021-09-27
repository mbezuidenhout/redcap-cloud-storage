<?php

namespace Stanford\GoogleStorage;

use MicrosoftAzure\Storage\Blob\Internal\BlobResources;
use MicrosoftAzure\Storage\Common\Internal\Authentication\SharedKeyAuthScheme;
use MicrosoftAzure\Storage\Common\Internal\Resources;
use Stanford\CloudStorage\CloudStorage;

/** @global CloudStorage $module */

try {
    if (isset($_GET['action']) && $_GET['action'] == 'upload') {
        $contentType = filter_var($_GET['content_type'], \FILTER_SANITIZE_STRING);
        $fileName    = filter_var($_GET['file_name'], \FILTER_SANITIZE_STRING);
        $fileSize    = filter_var($_GET['file_size'], \FILTER_SANITIZE_NUMBER_INT);
        $fieldName   = filter_var($_GET['field_name'], \FILTER_SANITIZE_STRING);
        $recordId    = filter_var($_GET['record_id'], \FILTER_SANITIZE_STRING);
        $eventId     = filter_var($_GET['event_id'], \FILTER_SANITIZE_NUMBER_INT);
        $instanceId  = filter_var($_GET['instance_id'], \FILTER_SANITIZE_NUMBER_INT);
        $prefix      = $module->getFieldUploadPrefix($fieldName);
        $storagePlatform = $module->getPlatformByFieldName($fieldName);
        $path            = $module->buildUploadPath($prefix, $fieldName, $fileName, $recordId, $eventId, $instanceId);
        $bucketOrContainerName = $module->getBucketOrContainerNameByFieldName($fieldName);
        $upload          = $storagePlatform->createUpload($bucketOrContainerName, $path, $contentType);
        $httpHeaders     = $upload->getHeaders();
        $url             = $upload->getUrl();
        /*
        switch ($module->getPlatformNameByFieldName($fieldName)) {
            case GoogleStorage::PLATFORM_GOOGLE:
            default:
                $path        = $module->buildUploadPath($prefix, $fieldName, $fileName, $recordId, $eventId, $instanceId);
                $bucket = $module->getBucket($fieldName);
                $url = $module->getGoogleStorageSignedUploadUrl($bucket, $path, $contentType);
                break;
        }
        */
        \REDCap::logEvent(USERID . " generated Upload signed URL for $fileName ", '', null, null);
        echo json_encode(array('status' => 'success', 'url' => $url, 'path' => $path, 'platform' => $module->getPlatform($fieldName), 'headers' => $httpHeaders));
    } elseif (isset($_GET['action']) && $_GET['action'] == 'download') {
        $fileName = filter_var($_GET['file_name'], \FILTER_SANITIZE_STRING);
        $fieldName = filter_var($_GET['field_name'], \FILTER_SANITIZE_STRING);
        $bucketOrContainerName = $module->getBucketOrContainerNameByFieldName($fieldName);
        $platform = $module->getPlatformByFieldName($fieldName);
        $now = new \DateTime();
        $link = $platform->getSignedUrl($bucketOrContainerName, $fileName, $now);
        /*
        if($platform == 'AZURE') {
            $link = $module->getAzureStorageDownloadURL($fileName);
        } elseif($platform == 'GOOGLE') {
            $bucket = $module->getBucket($fieldName);
            $link = $module->getGoogleStorageSignedUrl($bucket, trim($fileName));
        }
        */
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