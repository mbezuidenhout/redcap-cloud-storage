<?php
/**
 * You can test against a local sandbox by running a docker container as follows
 *
 * docker run -p 10000:10000 mcr.microsoft.com/azure-storage/azurite azurite-blob --blobHost 0.0.0.0 --blobPort 10000
 *
 * and enabling sandbox mode in this module's configuration inside a REDCap project.
 */

namespace Stanford\CloudStorage;

require_once "cloudStoragePlatform.php";

use MicrosoftAzure\Storage\Blob\BlobSharedAccessSignatureHelper;
use MicrosoftAzure\Storage\Blob\Internal\BlobResources;
use MicrosoftAzure\Storage\Common\Internal\Authentication\SharedKeyAuthScheme;
use MicrosoftAzure\Storage\Common\Internal\Resources as AzureResources;
use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Common\Internal\Resources;

class Azure extends CloudStoragePlatform {

    /**
     * @var string
     */
    private $accountName;

    /**
     * @var string
     */
    private $accountKey;

    /**
     * @var string[]
     */
    private $containers;

    /**
     * @var string
     */
    private $browserEndpoint;

    /**
     * @var \MicrosoftAzure\Storage\Blob\BlobRestProxy
     */
    protected $restProxy;

    /**
     * @param string $accountName Azure account name.
     * @param string $accountKey Azure account key.
     * @param bool $isSandbox Test against Azurite test platform.
     */
    public function __construct($accountName, $accountKey, $isSandbox = false, $serverEndpoint = '', $browserEndpoint = '')
    {
        $this->accountName = $accountName;
        $this->accountKey = $accountKey;
        $this->isSandbox = $isSandbox;
        if($isSandbox) {
            $this->serverEndpoint = $serverEndpoint;
            $this->browserEndpoint = $browserEndpoint;
            if($accountName == '') {
                $this->accountName = AzureResources::DEV_STORE_NAME;
            }
            if($accountKey == '') {
                $this->accountKey = AzureResources::DEV_STORE_KEY;
            }
            if($serverEndpoint == '') {
                $this->serverEndpoint = AzureResources::EMULATOR_BLOB_URI;
            }
        }
        $this->restProxy = BlobRestProxy::createBlobService(self::createConnectionString($this->accountName, $this->accountKey, $isSandbox, $this->serverEndpoint));
    }

    public function addContainer($containerName, $containerPrefix = '') {
        $this->containers[$containerName] = $containerPrefix;
    }

    public static function getBlobEndpointString($accountName, $isSandbox = false, $serverEndpoint = '')
    {
        $protocol = 'https';
        if($isSandbox) {
            $protocol = 'http';
        }
        $endpoint = 'https://' . $accountName . "." . AzureResources::BLOB_BASE_DNS_NAME . "/";
        if($isSandbox) {
            $endpoint = $serverEndpoint . ($serverEndpoint[\strlen($serverEndpoint)] == '/' ? '' : '/') . "${accountName}";
        }
        return $endpoint;
    }

    public static function createConnectionString($accountName, $accountKey, $isSandbox = false, $serverEndpoint = '')
    {
        // $connectionString = 'DefaultEndpointsProtocol=http;AccountName=devstoreaccount1;AccountKey=Eby8vdM02xNOcqFlqUwJPLlmEtlCDXJ1OUzFT50uSRZ6IFsuFq2UVErCz4I6tq/K1SZFPTOtr/KBHBeksoGMGw==;BlobEndpoint=http://172.17.0.1:10000/devstoreaccount1;QueueEndpoint=http://172.17.0.1:10001/devstoreaccount1;';
        $format = "DefaultEndpointsProtocol=%s;AccountName=%s;AccountKey=%s;BlobEndpoint=%s;";
        $protocol = 'https';
        if($isSandbox) {
            $protocol = 'http';
        }
        return sprintf($format, $protocol, $accountName, $accountKey, self::getBlobEndpointString($accountName, $isSandbox, $serverEndpoint));
    }

    public function testConnection()
    {
        $result = $this->restProxy->listContainers();
        return true;
    }

    public function getEndpoint()
    {
        return $this->serverEndpoint;
    }

    /**
     * Create and return an instance of CloudUpload
     *
     * @return CloudUpload
     */
    public function createUpload($bucketOrContainer, $path, $fileType) {
        // Container names can contain only lowercase letters, numbers, and hyphens, and must begin and end with a letter or a number. The name can't contain two consecutive hyphens.
        $bucketOrContainer = \strtolower($bucketOrContainer);

        // A user might not have the list containers privilege so this code might have to be removed from here to the ->createContainer statement.
        $existingContainers = $this->restProxy->listContainers();
        $containerExists = false;
        // Container names can contain only lowercase letters, numbers, and hyphens, and must begin and end with a letter or a number. The name can't contain two consecutive hyphens.
        foreach ($existingContainers->getContainers() as $container) {
            if ($container->getName() == $bucketOrContainer) {
                $containerExists = true;
                break;
            }
        }
        if (!$containerExists) {
            $this->restProxy->createContainer($bucketOrContainer);
        }

        $createdDate = new \DateTime();
        $url = $this->getSignedUrl($bucketOrContainer, $path, $createdDate, $fileType);

        $authScheme = new SharedKeyAuthScheme($this->accountName, $this->accountKey);
        // Required headers
        // TODO: Add x-ms-client-request-id header to correlate client requests with server-side activities
        $httpHeaders = array();
        $httpHeaders['X-Ms-Date'] = $createdDate->format(\DateTimeInterface::RFC7231);
        $httpHeaders['X-Ms-Version']  = BlobResources::STORAGE_API_LATEST_VERSION;
        $httpHeaders['X-Ms-Blob-Type'] = \MicrosoftAzure\Storage\Blob\Models\BlobType::BLOCK_BLOB;
        $httpHeaders['Authorization'] = $authScheme->getAuthorizationHeader( $httpHeaders, $url, array(), 'PUT');

        $upload = new CloudUpload($url, $httpHeaders);

        return $upload;
    }

    /**
     * Get Azure Shared Access Signature
     * @link https://docs.microsoft.com/en-us/rest/api/storageservices/authorize-with-shared-key
     *
     * @param string $bucketOrContainerName
     * @param string $path
     * @param \DateTime $createdDate Instance of DateTime with current date and time
     * @param int $duration
     * @return string
     */
    public function getSignedUrl($bucketOrContainerName, $path, $createdDate, $duration = 'PT6H')
    {
        $sasExpiry = new \DateTime();
        $sasExpiry->add(new \DateInterval($duration)); // Add 6 hours to current time.
        $helper = new BlobSharedAccessSignatureHelper($this->accountName, $this->accountKey);
        // Note validateAndSanitizeStringWithArray needs fixing does strlen($input) == '' instead of strlen($input) == 0
        $azureSasToken = $helper->generateBlobServiceSharedAccessSignatureToken(
            Resources::RESOURCE_TYPE_BLOB,
            $bucketOrContainerName . '/' . ltrim($path, '/'),
            'racwd', // See: https://docs.microsoft.com/en-us/rest/api/storageservices/create-user-delegation-sas
            $sasExpiry,
            $createdDate,
            '',
            'https,http');

        $signedUrlFormat = '%s/%s/%s?%s'; // 1: Endpoint, 2: Container, 3: Path, 4: SAS token

        $signedUrl = sprintf($signedUrlFormat, self::getBlobEndpointString($this->accountName, $this->isSandbox, $this->browserEndpoint), $bucketOrContainerName, $path, $azureSasToken);
        return $signedUrl;
    }

    public function getBucketOrContainerPrefix($bucketOrContainerName)
    {
        if(\is_array($this->containers) && \in_array($bucketOrContainerName, $this->containers)) {
            return $this->containers[$bucketOrContainerName];
        } else {
            return '';
        }
    }

    public function getDownloadLink($bucketOrContainerName, $path)
    {
        if(\str_starts_with($path, $bucketOrContainerName)) {
            $path = \substr($path, strlen($bucketOrContainerName));
        }
        $path = ltrim($path, '/');
        $url = $this->restProxy->getBlobUrl($bucketOrContainerName, $path);
        if(!empty($this->serverEndpoint) && !empty($this->browserEndpoint)) {
            $url = \str_replace($this->serverEndpoint, $this->browserEndpoint, $url);
        }
        return $url;
    }

}