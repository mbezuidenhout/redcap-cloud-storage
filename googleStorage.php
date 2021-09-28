<?php
/**
 * You can test against a local sandbox by running the follow docker commands
 *
 * docker run -ti --name gcloud-config gcr.io/google.com/cloudsdktool/cloud-sdk gcloud auth login
 * docker run --rm -ti --volumes-from gcloud-config -p 8081:8081 gcr.io/google.com/cloudsdktool/cloud-sdk gcloud beta \
 *  emulators datastore start --host-port=0.0.0.0:8081 --no-store-on-disk
 * docker run --rm -ti --volumes-from gcloud-config gcr.io/google.com/cloudsdktool/cloud-sdk gcloud beta emulators datastore env-init
 *
 */

namespace Stanford\CloudStorage;

require_once "cloudStoragePlatform.php";

use Google\Cloud\Storage\StorageClient;
use Google\Cloud\Storage\Bucket;
use Stanford\CloudStorage\CloudUpload;

class Google extends CloudStoragePlatform {

    /**
     * @var string
     */
    private $projectId;

    /**
     * @var string
     */
    private $apiToken;

    /**
     * @var \Google\Cloud\Storage\StorageClient
     */
    private $restProxy;

    /**
     * @var \Google\Cloud\Storage\Bucket[]
     */
    private $buckets;

    public function __construct($projectId, $apiToken, $isSandbox = false, $serverEndpoint = '', $browserEndpoint = '')
    {
        //configure google storage object
        $this->projectId = $projectId;
        $this->apiToken = $apiToken;
        $this->isSandbox = $isSandbox;
        $storageClientConfig = array(
            'projectId' => $projectId,
            'keyFile'   => \json_decode($apiToken, true)
        );
        if($isSandbox) {
            $storageClientConfig['projectId']   = 'emulator-project';
            if(!empty($serverEndpoint)) {
                // TODO: Do a sanity check here to make sure $serverEndpoint is in the valid format: (http(s)?://)?(\d{1,3}\.){4}(:\d+)? ie. http(s)://host:port
                $this->serverEndpoint = $serverEndpoint;
                $storageClientConfig['apiEndpoint'] = $this->serverEndpoint;
            }
        }
        $this->restProxy = new StorageClient($storageClientConfig);
    }

    public function addBucket($bucketName, $bucketPrefix)
    {
        $this->buckets[$bucketName] = $bucketPrefix;
    }

    public function testConnection()
    {
        // Try to get a list of buckets
        $buckets = $this->restProxy->buckets(); // User credentials might not support bucket list access
        try {
            foreach ($buckets as $bucket) {
                $test = 1;
            }
        } catch(\Google\Cloud\Core\Exception\ServiceException $e) {
            // Error message says that user does not have the necessary privileges but did connect
            if($e->getCode() == 403) {
                return true;
            } else {
                throw $e;
            }
        }
        return true;
    }

    public function getSignedUploadUrl($bucketOrContainer, $path, $createdDate, $fileType) {
        $duration = 3600;
        $bucket = $this->restProxy->bucket($bucketOrContainer);
        $url = $bucket->object($path)->signedUrl(new \DateTime('+ ' . $duration . ' seconds'),
            [
                'method' => 'PUT',
                'contentType' => $fileType,
                'version' => 'v4',
            ]);
        return $url;
    }

    public function createUpload($bucketOrContainer, $path, $fileType)
    {
        $createdDate = new \DateTime;
        $url = $this->getSignedUploadUrl($bucketOrContainer, $path, $createdDate, $fileType);
        $httpHeaders = [
            "Access-Control-Allow-Origin" => "*",
            "Content-Type"                => $fileType
        ];

        $upload = new CloudUpload($url, $httpHeaders);
        return $upload;
    }

    public function getBucketOrContainerPrefix($bucketOrContainerName)
    {
        if(\is_array($this->buckets) && \in_array($bucketOrContainerName, $this->buckets)) {
            return $this->buckets[$bucketOrContainerName];
        } else {
            return '';
        }
    }

    // getDownloadLink, getSignedUrl and createUpload does similar things. Check if they are all necessary and remove.
    public function getDownloadLink($bucketOrContainerName, $path)
    {
        $this->getSignedUrl($bucketOrContainerName, $path, new \DateTime());
    }

    public function getSignedUrl($bucketOrContainerName, $path, $createdDate, $duration = 'PT6H')
    {
        $duration = 3600;
        $bucket = $this->restProxy->bucket($bucketOrContainerName);
        $url = $bucket->object($path)->signedUrl(new \DateTime('+ ' . $duration . ' seconds'),
            [
                'version' => 'v4',
            ]);
        return $url;
    }
}