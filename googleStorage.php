<?php

namespace Stanford\CloudStorage;

require_once "cloudStoragePlatform.php";

use Google\Cloud\Storage\StorageClient;
use Google\Cloud\Storage\Bucket;
use Stanford\CloudStorage\CloudUpload;

class Google extends CloudStoragePlatform {
    /**
     * @var \Google\Cloud\Storage\StorageClient
     */
    private $client;

    /**
     * @var \Google\Cloud\Storage\Bucket[]
     */
    private $buckets;

    public function __construct($accountName, $accountKey)
    {
        //configure google storage object
        $this->client = new StorageClient(['keyFile' => json_decode($this->getProjectSetting('google-api-token'), true), 'projectId' => $this->getProjectSetting('google-project-id')]);
    }

    public function addBucket($bucketName, $bucketPrefix)
    {
        $this->buckets[$bucketName] = $bucketPrefix;
    }

    public function testConnection()
    {
        // TODO: Implement testConnection() method.
    }

    public function createUpload($bucketOrContainer, $path, $fileType)
    {
        // TODO: Implement createUpload() method.
    }

    public function getBucketOrContainerPrefix($bucketOrContainerName)
    {
        if(\is_array($this->buckets) && \in_array($bucketOrContainerName, $this->buckets)) {
            return $this->buckets[$bucketOrContainerName];
        } else {
            return '';
        }
    }

    public function getDownloadLink($bucketOrContainerName, $path)
    {
        // TODO: Implement getDownloadLink() method.
    }

    public function getSignedUrl($bucketOrContainerName, $path, $createdDate, $duration = 'PT6H')
    {
        // TODO: Implement getSignedDownloadLink() method.
    }
}