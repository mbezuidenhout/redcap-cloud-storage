<?php

namespace Stanford\CloudStorage;

require_once "emLoggerTrait.php";
require_once "googleStorage.php";
require_once "azureStorage.php";
require __DIR__ . '/vendor/autoload.php';

/**
 * Class GoogleStorage
 *
 * Used to for projects to interface with Cloud storage services. Supports Google and Azure. Future
 * expansion to include Amazon AWS.
 *
 * @package  Stanford\CloudStorage
 * @version  0.0.1
 */
class CloudStorage extends \ExternalModules\AbstractExternalModule
{

    use emLoggerTrait;

    public const PLATFORM_GOOGLE = 'GOOGLE';
    public const PLATFORM_AZURE  = 'AZURE';

    /**
     * @var \Project
     */
    private $project;

    /**
     * @var string
     */
    private $recordId;

    /**
     * @var int
     */
    private $eventId;

    /**
     * @var $instanceId
     */
    private $instanceId;

    /**
     * @var array
     */
    private $record;

    /**
     * @var array
     */
    private $downloadLinks;

    /**
     * @var array
     */
    private $bucketPrefix;

    /**
     * @var array
     */
    private $filesPath;

    /**
     * @var bool
     */
    protected $linksDisabled;

    /**
     * @var bool
     */
    protected $isSurvey;

    /**
     * @var bool
     */
    protected $autoSaveDisabled;

    /**
     * Array of CloudStoragePlatform instances.
     *
     * @var CloudStoragePlatform[]
     */
    protected $storagePlatforms;

    /**
     * Array of platforms and their fields associated with them.
     */
    private $platformFields;

    public function __construct()
    {
        try {
            parent::__construct();
            if (isset($_GET['pid']) && $this->getProjectSetting('azure-enabled') || $this->getProjectSetting('google-enabled')) {
                global $Proj;

                $this->project = $Proj;

                if ($this->getProjectSetting('azure-enabled')) {
                    $this->storagePlatforms[self::PLATFORM_AZURE] = new Azure(
                        $this->getProjectSetting('azure-account-name'),
                        $this->getProjectSetting('azure-account-key'),
                        $this->getProjectSetting('azure-sandbox'),
                        $this->getProjectSetting('azure-sandbox-endpoint'),
                        $this->getProjectSetting('azure-browser-endpoint')
                    );
                    $fields = $this->setStorageFields($this->storagePlatforms[self::PLATFORM_AZURE], self::PLATFORM_AZURE . "-STORAGE");
                    $this->platformFields[self::PLATFORM_AZURE] = $fields;

                }

                if ($this->getProjectSetting('google-enabled')) {
                    if (!empty($this->getProjectSetting('google-project-id'))) {
                        $this->storagePlatforms[self::PLATFORM_GOOGLE] = new Google(
                            $this->getProjectSetting('google-project-id'),
                            $this->getProjectSetting('google-api-token')
                        );
                        $fields = $this->setStorageFields($this->storagePlatforms[self::PLATFORM_GOOGLE], self::PLATFORM_GOOGLE . "-STORAGE");
                        $this->platformFields[self::PLATFORM_GOOGLE] = $fields;
                    }
                }

                // set flag to display uploaded file download links
                if (!is_null($this->getProjectSetting('disable-file-link'))) {
                    $this->setLinksDisabled($this->getProjectSetting('disable-file-link'));

                } else {
                    $this->setLinksDisabled(false);
                }

                // set if we want auto save when file is uploaded.
                if (!is_null($this->getProjectSetting('disable-auto-save'))) {
                    $this->setAutoSaveDisabled($this->getProjectSetting('disable-auto-save'));
                } else {
                    $this->setAutoSaveDisabled(false);
                }
            }
        } catch (\Exception $e) {
            #echo $e->getMessage();
        }
    }

    /**
     * Tests connection to platform and returns true on success.
     *
     * @param string $platform Platform to test
     *
     * @return bool
     */
    public function testConnection($platform)
    {
        return $this->storagePlatforms[$platform]->testConnection();
    }

    /**
     * Get the $platform instance.
     *
     * @param $platform
     * @return CloudStoragePlatform
     */
    public function getPlatform($platform)
    {
        return $this->storagePlatforms[$platform];
    }

    /**
     * Get CloudStoragePlatform instance from $fielName
     *
     * @param string $fieldName
     * @return CloudStoragePlatform
     */
    public function getPlatformByFieldName($fieldName)
    {
        return $this->storagePlatforms[$this->getPlatformNameByFieldName($fieldName)];
    }

    /**
     * Get the bucket or container to use for $fieldName
     *
     * @param string $fieldName
     * @return string
     */
    public function getBucketOrContainerNameByFieldName($fieldName)
    {
        $platform = $this->getPlatformNameByFieldName($fieldName);
        $bucketOrContainerName = $this->platformFields[$platform][$fieldName];
        return $bucketOrContainerName;
    }

    /**
     * @param $fieldName
     * @return false|CloudStoragePlatform
     */
    public function getPlatformNameByFieldName($fieldName)
    {
        foreach($this->platformFields as $platform => $fields) {
            if (\in_array($fieldName, array_keys($fields))) {
                return $platform;
            }
        }
        return false;
    }

    /**
     * @param string $path
     */
    public function includeFile($path)
    {
        include_once $path;
    }

    /**
     * Set the list of fields which this $platform is responsible for
     *
     * @param CloudStoragePlatform $storagePlatform Instance of CloudStoragePlatform.
     * @param string $platformString The platform string to search for in the field metadata.
     * @return array An array of matched field names.
     */
    private function setStorageFields($storagePlatform, $platformString)
    {
        $fields = array();
        $re = '/^@' . $platformString . '=(.*)$/m';
        foreach ($this->project->metadata as $name => $field) {
            if( preg_match($re, $field['misc'], $matches) ) {
                $fields[$name] = $matches[1];
            }
        }
        $storagePlatform->setFields($fields);
        return $fields;
    }

    public function getFieldInstrument($field)
    {
        foreach ($this->project->forms as $name => $form) {
            if (array_key_exists($field, $form['fields'])) {
                return $name;
            }
        }
    }

    public function saveRecord()
    {
        if (empty($_POST['record_id'])) {
            $this->setRecordId(\REDCap::reserveNewRecordId($this->getProjectId()));
        } else {
            $this->setRecordId(filter_var($_POST['record_id'], FILTER_SANITIZE_STRING));
        }
        $data[\REDCap::getRecordIdField()] = $this->getRecordId();
        $filesPath = json_decode($_POST['files_path'], true);
        foreach ($filesPath as $field => $item) {
            $data[$field] = $item;
            $form = $this->getFieldInstrument($field);
        }
        $this->setEventId(filter_var($_POST['event_id'], FILTER_SANITIZE_NUMBER_INT));
        $data['redcap_event_name'] = $this->project->getUniqueEventNames($this->getEventId());
        if ($this->project->isRepeatingForm($this->getEventId(), $form)) {
            $data['redcap_repeat_instance'] = filter_var($_POST['instance_id'], FILTER_SANITIZE_NUMBER_INT);
            $data['redcap_repeat_instrument'] = $form;
        }

        $response = \REDCap::saveData($this->getProjectId(), 'json', json_encode(array($data)));
        if (empty($response['errors'])) {
            $this->setRecord();
            $this->prepareDownloadLinks();
            return array('status' => 'success', 'links' => $this->getDownloadLinks());
        } else {
            if (is_array($response['errors'])) {
                throw new \Exception(implode(",", $response['errors']));
            } else {
                throw new \Exception($response['errors']);
            }
        }
    }

    public function redcap_every_page_top()
    {
        global $public_survey;
        try {
            $isSurveyPage = (isset($_GET['s']) && defined("NOAUTH") && PAGE == 'surveys/index.php');
            $this->setIsSurvey($isSurveyPage || (isset($public_survey) && $public_survey));
            // in case we are loading record homepage load its the record children if existed
            if ((strpos($_SERVER['SCRIPT_NAME'], 'DataEntry/index.php') !== false || $this->isSurvey()) && sizeof($this->platformFields) > 0) {

                if (isset($_GET['event_id'])) {
                    $this->setEventId(filter_var($_GET['event_id'], FILTER_SANITIZE_NUMBER_INT));
                } else {
                    $this->setEventId($this->getFirstEventId());
                }

                if (isset($_GET['instance'])) {
                    $this->setInstanceId(filter_var($_GET['instance'], FILTER_SANITIZE_NUMBER_INT));
                }

                // Do not set the record id for new surveys otherwise user will be shown files for record id 1
                if (isset($_GET['id'])) {
                    global $this_record;
                    if ($this->isSurvey) {
                        $_GET['id'] = $this_record;
                    }
                    $this->setRecordId(filter_var($_GET['id'], FILTER_SANITIZE_STRING));
                    $this->setRecord();
                    $this->prepareDownloadLinks();
                }


                $this->includeFile("src/client.php");
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
        }

    }

    /**
     * get list of all files under specific prefix
     * @param \Google\Cloud\Storage\Bucket $bucket
     * @param string $prefix
     * @return array
     */
    private function getPrefixObjects($bucket, $prefix)
    {
        $files = array();
        $objects = $bucket->objects(array('prefix' => $prefix));
        foreach ($objects as $object) {
            $re = '/[0-9]{4}-[0-9]{2}-[0-9]{2}.log/m';

            preg_match_all($re, $object->name(), $matches, PREG_SET_ORDER, 0);

            if (!empty($matches)) {
                continue;
            }
            $files[] = $object->name();
        }
        return $files;
    }

    public function prepareDownloadLinks()
    {
        $record = $this->getRecord();
        $downloadUrls = array();
        foreach ($this->getFields() as $field => $bucket) {
            if ($record[$this->getRecordId()][$this->getEventId()][$field] != '') {
                $files = explode(",", $record[$this->getRecordId()][$this->getEventId()][$field]);
                $platform = $this->getPlatformByFieldName($field);
                $platformName = $this->getPlatformNameByFieldName($field);

                if (!empty($field) && sizeof($files) > 0 && $platform instanceof CloudStoragePlatform) {

                    foreach($files as $file) {
                        $downloadUrls[$field][$file] = $platform->getDownloadLink($this->platformFields[$platformName][$field], $file);
                    }
                }
            }
        }
        $this->setDownloadLinks($downloadUrls);
    }

    public function buildUploadPath($prefix, $fieldName, $fileName, $recordId, $eventId, $instanceId)
    {
        $prefix = $prefix != '' ? $prefix . '/' : '';

        $fileName = rawurlencode($fileName);

        if (empty($recordId)) {
            $recordId = time();
        }

        if ($this->project->longitudinal) {
            return $prefix . $recordId . '/' . $fieldName . '/' . \REDCap::getEventNames($eventId) . '/' . $instanceId . '/' . $fileName;
        }
        if (!empty($this->project->RepeatingFormsEvents)) {
            return $prefix . $recordId . '/' . $fieldName . '/' . $instanceId . '/' . $fileName;
        }

        return $prefix . $recordId . '/' . $fieldName . '/' . $fileName;
    }

    /**
     * @param string $fieldName
     * @return string
     */
    public function getFieldUploadPrefix($fieldName)
    {
        $storagePlatformName = $this->getPlatformNameByFieldName($fieldName);
        $uploadPrefix = $this->storagePlatforms[$storagePlatformName]->getBucketOrContainerPrefix($this->getFields()[$fieldName]);
        return $uploadPrefix;
    }

    /**
     * @return array
     */
    public function getInstances()
    {
        return $this->instances;
    }

    /**
     */
    public function setInstances()
    {
        $this->instances = $this->getSubSettings('instance', $this->getProjectId());
    }

    /**
     * @return array Array of fields and their associated container or bucket
     */
    public function getFields()
    {
        $allFields = array();
        foreach($this->platformFields as $fields) {
            $allFields = \array_merge($allFields, $fields);
        }
        return $allFields;
    }

    /**
     * @param array $fields
     */
    public function setFields($fields)
    {
        $this->fields = $fields;
    }

    /**
     * @return string
     */
    public function getRecordId()
    {
        return $this->recordId;
    }

    /**
     * @param string $recordId
     */
    public function setRecordId()
    {
        if (\func_num_args() < 1)
            throw new \Exception("Function setRecordId requires at least one argument");
        $recordId = \func_get_arg(0);
        $this->recordId = $recordId;
    }

    /**
     * @return int
     */
    public function getEventId()
    {
        return $this->eventId;
    }

    /**
     * @param int $eventId
     */
    public function setEventId($eventId)
    {
        $this->eventId = $eventId;
    }

    /**
     * @return int
     */
    public function getInstanceId()
    {
        return $this->instanceId;
    }

    /**
     * @param int $instanceId
     */
    public function setInstanceId($instanceId)
    {
        $this->instanceId = $instanceId;
    }

    /**
     * @return array
     */
    public function getRecord()
    {
        return $this->record;
    }

    /**
     * @param array $record
     */
    public function setRecord()
    {
        $param = array(
            'project_id' => $this->getProjectId(),
            'return_format' => 'array',
            'events' => $this->getEventId(),
            'records' => [$this->getRecordId()]
        );
        $data = array();
        $record = \REDCap::getData($param);
        $this->record = $record;
    }

    /**
     * @return array
     */
    public function getDownloadLinks()
    {
        return $this->downloadLinks;
    }

    /**
     * @param array $downloadLinks
     */
    public function setDownloadLinks($downloadLinks)
    {
        $this->downloadLinks = $downloadLinks;
    }

    /**
     * @return array
     */
    public function getFilesPath()
    {
        $filesPath = array();
        foreach($this->downloadLinks as $fieldName => $downloadLinks) {
            $files = array();
            foreach($downloadLinks as $downloadPath => $downloadLink) {
                $files[] = $downloadPath;
            }
            $filesPath[$fieldName] = implode(',', $files);
        }
        return $filesPath;
    }

    /**
     * @param array $filesPath
     */
    public function setFilesPath(array $filesPath): void
    {
        $this->filesPath = $filesPath;
    }

    /**
     * @return bool
     */
    public function isLinksDisabled(): bool
    {
        return $this->linksDisabled;
    }

    /**
     * @param bool $linksDisabled
     */
    public function setLinksDisabled($linksDisabled): void
    {
        $this->linksDisabled = $linksDisabled;
    }

    /**
     * @return bool
     */
    public function isSurvey(): bool
    {
        return $this->isSurvey;
    }

    /**
     * @param bool $isSurvey
     */
    public function setIsSurvey($isSurvey): void
    {
        $this->isSurvey = $isSurvey;
    }

    /**
     * @return bool
     */
    public function isAutoSaveDisabled(): bool
    {
        return $this->autoSaveDisabled;
    }

    /**
     * @param bool $autoSaveDisabled
     */
    public function setAutoSaveDisabled($autoSaveDisabled): void
    {
        $this->autoSaveDisabled = $autoSaveDisabled;
    }
}