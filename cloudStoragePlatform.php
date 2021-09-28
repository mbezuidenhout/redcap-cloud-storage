<?php

namespace Stanford\CloudStorage;

require_once "cloudUpload.php";

abstract class CloudStoragePlatform
{
    /**
     * An array of fields and their associated bucket or container.
     *
     * @var array
     */
    private $fields;

    /**
     * @var string
     */
    protected $serverEndpoint;

    /**
     * Run in sandbox mode
     *
     * @var string
     */
    protected $isSandbox;

    /**
     * Test the connection to the cloud storage platform and returns true on success or error message on failure.
     *
     * @return bool|string
     */
    abstract public function testConnection();

    /**
     * Create and return an instance of CloudUpload
     *
     * @return CloudUpload
     */
    abstract public function createUpload($bucketOrContainer, $path, $contentType);

    /**
     * Get the download url for specified file
     *
     * @param string $bucketOrContainerName
     * @param string $path Path of file
     * @return string
     */
    abstract public function getDownloadLink($bucketOrContainerName, $path);

    /**
     * Get a signed url for specified file
     *
     * @param string $bucketOrContainerName
     * @param string $path
     * @param \DateTime $createdDate
     * @param string $duration
     * @return string
     */
    abstract public function getSignedUrl($bucketOrContainerName, $path, $createdDate, $duration = 'PT6H');

    /**
     * Get the upload prefix for $bucketOrContainerName
     *
     * @param string $bucketOrContainerName
     * @return string Upload prefix
     */
    abstract public function getBucketOrContainerPrefix($bucketOrContainerName);

    public function setFields($fields) {
        $this->fields = $fields;
    }

    public function getEndpoint()
    {
        return $this->serverEndpoint;
    }

    public function addField($fieldName, $storageBucketOrContainer) {
        $this->fields[$fieldName] = $storageBucketOrContainer;
    }
}