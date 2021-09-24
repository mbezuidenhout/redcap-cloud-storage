<?php

namespace Stanford\GoogleStorage;

use MicrosoftAzure\Storage\Blob\Internal\BlobResources;
use MicrosoftAzure\Storage\Common\Internal\Authentication\SharedKeyAuthScheme;
use MicrosoftAzure\Storage\Common\Internal\Resources;
use function Sabre\HTTP\toDate;

/** @var \Stanford\GoogleStorage\GoogleStorage $module */

try {
    if (isset($_GET['action']) && $_GET['action'] == 'upload') {
        $contentType = filter_var($_GET['content_type'], \FILTER_SANITIZE_STRING);
        $fileName    = filter_var($_GET['file_name'], \FILTER_SANITIZE_STRING);
        $fileSize    = filter_var($_GET['file_size'], \FILTER_SANITIZE_NUMBER_INT);
        $fieldName   = filter_var($_GET['field_name'], \FILTER_SANITIZE_STRING);
        $recordId    = filter_var($_GET['record_id'], \FILTER_SANITIZE_STRING);
        $eventId     = filter_var($_GET['event_id'], \FILTER_SANITIZE_NUMBER_INT);
        $instanceId  = filter_var($_GET['instance_id'], \FILTER_SANITIZE_NUMBER_INT);
        $prefix      = $module->getFieldBucketPrefix($fieldName);
        $httpHeaders = array();
        switch ($module->getPlatform($fieldName)) {
            case GoogleStorage::PLATFORM_AZURE:
                $connectionString = 'DefaultEndpointsProtocol=http;AccountName=devstoreaccount1;AccountKey=Eby8vdM02xNOcqFlqUwJPLlmEtlCDXJ1OUzFT50uSRZ6IFsuFq2UVErCz4I6tq/K1SZFPTOtr/KBHBeksoGMGw==;BlobEndpoint=http://172.17.0.1:10000/devstoreaccount1;QueueEndpoint=http://172.17.0.1:10001/devstoreaccount1;';
                $azureBlobService = \MicrosoftAzure\Storage\Blob\BlobRestProxy::createBlobService($connectionString);
                $existingContainers = $azureBlobService->listContainers();
                $containerExists = false;
                // Container names can contain only lowercase letters, numbers, and hyphens, and must begin and end with a letter or a number. The name can't contain two consecutive hyphens.
                foreach ($existingContainers->getContainers() as $container) {
                    if ($container->getName() == 'redcap') {
                        $containerExists = true;
                        break;
                    }
                }
                if (!$containerExists) {
                    $azureBlobService->createContainer('redcap');
                }
                $path       = $module->buildUploadPath('redcap', $fieldName, $fileName, $recordId, $eventId, $instanceId);
                $authScheme = new SharedKeyAuthScheme('devstoreaccount1', 'Eby8vdM02xNOcqFlqUwJPLlmEtlCDXJ1OUzFT50uSRZ6IFsuFq2UVErCz4I6tq/K1SZFPTOtr/KBHBeksoGMGw==');
                $response   = $module->getAzureStorageSAS($path, $contentType);

                // Required headers
                // TODO: Add x-ms-client-request-id header to correlate client requests with server-side activities
                $httpHeaders['X-Ms-Date'] = (new \DateTime())->format(\DateTime::ISO8601);
                $httpHeaders['X-Ms-Version']  = BlobResources::STORAGE_API_LATEST_VERSION;
                $httpHeaders['X-Ms-Blob-Type'] = \MicrosoftAzure\Storage\Blob\Models\BlobType::BLOCK_BLOB;
                $httpHeaders['Authorization'] = $authScheme->getAuthorizationHeader( $httpHeaders, $response, array(), 'PUT');

                break;
            case GoogleStorage::PLATFORM_GOOGLE:
            default:
                $path        = $module->buildUploadPath($prefix, $fieldName, $fileName, $recordId, $eventId, $instanceId);
                $bucket = $module->getBucket($fieldName);
                $response = $module->getGoogleStorageSignedUploadUrl($bucket, $path, $contentType);
                break;
        }
        \REDCap::logEvent(USERID . " generated Upload signed URL for $fileName ", '', null, null);
        echo json_encode(array('status' => 'success', 'url' => $response, 'path' => $path, 'platform' => $module->getPlatform($fieldName), 'headers' => $httpHeaders));
    } elseif (isset($_GET['action']) && $_GET['action'] == 'download') {
        $fileName = filter_var($_GET['file_name'], \FILTER_SANITIZE_STRING);
        $fieldName = filter_var($_GET['field_name'], \FILTER_SANITIZE_STRING);
        $platform = $module->getPlatform($fieldName);
        if($platform == 'AZURE') {
            $link = $module->getAzureStorageDownloadURL($fileName);
        } elseif($platform == 'GOOGLE') {
            $bucket = $module->getBucket($fieldName);
            $link = $module->getGoogleStorageSignedUrl($bucket, trim($fileName));
        }
        \REDCap::logEvent(USERID . " generated Download signed URL for $fileName ", '', null, null);

        echo json_encode(array('status' => 'success', 'link' => $link));
    } else {
        throw new \Exception("cant find required action");
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