#!/opt/homebrew/bin/php
<?php

/*
 * Run deploy in the directory of your redcap installation.
 * This script will attempt to locate your redcap installation in either the parent
 * directories or in the child directories.
 *
 * It then scans the config.json files to locate a previous version of the plugin.
 * You can then select the new version number.
 */

class Module {
    /** @var $name string Name of the module as it appears in config.json */
    public $name;

    /** @var $version array Array of version numbers */
    public $versions;

    /** @var $dstpath string Module directory name base. No version appendix */
    protected $pathBase;

    /** @var $modulesPath string Path where modules are installed  */
    protected $modulesPath;

    const MODULE_PATTERN = '/(.*)_v(\d+)(\.\d+)?(\.\d+)?/';

    /**
     * Supply the full path to the module
     */
    public function __construct( $path ) {
        $this->modulesPath = dirname($path);
        $this->pathBase = self::getModulePathBase( $path );
        $moduleConfig = json_decode( file_get_contents( $path . DIRECTORY_SEPARATOR . 'config.json' ) );
        $this->name = $moduleConfig->name;
        $this->addVersion( $path );
    }

    public function getModulesPath() {
        return $this->modulesPath;
    }

    public function getBaseName() {
        return $this->pathBase;
    }

    public function addVersion( $path ) {
        if( preg_match( self::MODULE_PATTERN, basename($path), $matches ) ) {
            $versionString = '';
            for( $i = 2; $i < count($matches); $i++ ) {
                $versionString .= $matches[$i];
            }
            $this->versions[] = $versionString;
        }
        if( count($this->versions) > 1 ) {
            $this->versions = array_unique( $this->versions );
            usort( $this->versions, "version_compare" );
        }
    }

    public function getLatestVersionNr() {
        return $this->versions[count($this->versions)-1];
    }

    /**
     * Increase the highest version number by one
     *
     * @param $part Number between 1 and 3
     * @return string
     */
    public function getNextVersionNr( $part ) {
        $versionNrParts = explode( '.', $this->getLatestVersionNr() );
        switch($part) {
            case 1:
                return intval($versionNrParts[0]) + 1;
                break;
            case 2:
                if(count($versionNrParts) < 2)
                    $versionNrParts[1] = -1;
                return $versionNrParts[0] . '.' . intval($versionNrParts[1]) + 1;
                break;
            case 3:
            default:
                if(count($versionNrParts) < 2) {
                    $versionNrParts[1] = 0;
                    $versionNrParts[2] = -1;
                }
                return $versionNrParts[0] . '.' . $versionNrParts[1] . '.' . intval($versionNrParts[2]) + 1;
                break;
        }
    }

    /**
     * Check the directory name structure for valid module name and version and existence of a config.json file
     *
     * @param $dirname string Directory name base
     * @return bool
     */
    public static function isModuleDirName( $dirname ) {
        $moduleConfigFile = $dirname . DIRECTORY_SEPARATOR . 'config.json';
        if( is_dir( $dirname ) && preg_match( self::MODULE_PATTERN, basename($dirname) ) && is_file( $moduleConfigFile ) ) {
            return true;
        }
        return false;
    }

    public static function getModulePathBase( $dirname ) {
        $matches = array();
        if( preg_match( self::MODULE_PATTERN, $dirname, $matches ) ) {
            return $matches[1];
        }
        return false;
    }
}

$redcapPath = '';
// Search for redcap installation in current directory
if( isRedcapDir( getcwd() ) ) {
    $redcapPath = getcwd();
} else {
// Search for redcap installation in subdirectories
    $dirEntries = array_diff( scandir( getcwd() ), array('..', '.') );
    foreach( $dirEntries as $dirEntry ) {
        $fullpath = getcwd() . DIRECTORY_SEPARATOR . $dirEntry;
        if( is_dir( $fullpath ) && isRedcapDir( $fullpath )) {
            $redcapPath = $fullpath;
            break;
        }
    }
}
// Search for redcap in parent directories
if(empty($redcapPath)) {
    $fullpath = dirname( getcwd() );
    while( is_dir( $fullpath ) ) {
        if( isRedcapDir( $fullpath ) ) {
            $redcapPath = $fullpath;
            break;
        }
        $fullpath = dirname($fullpath);
    }
}

if(empty($redcapPath)) {
    die( "Redcap path not found" );
}

echo "Redcap installation found at: " . $redcapPath . "\n";

if (!in_array(
    readchar('Continue? [Y/n] '), ["\n", 'y', 'Y']
// enter/return key ("\n") for default 'Y'
)) die("Good Bye\n");
echo "\n";

$existingModules = array();
$modulesPath  = $redcapPath . DIRECTORY_SEPARATOR . 'modules';
$dirEntries = array_diff(scandir($modulesPath), array('..', '.'));
foreach( $dirEntries as $dirEntry ) {
    if( Module::isModuleDirName( $modulesPath . DIRECTORY_SEPARATOR . $dirEntry ) ) {
        $modulePathBaseName = Module::getModulePathBase( $dirEntry );
        if( !isset($existingModules[$modulePathBaseName]) ) {
            $existingModules[$modulePathBaseName] = new Module( $modulesPath . DIRECTORY_SEPARATOR . $dirEntry );
        } else {
            $existingModules[$modulePathBaseName]->addVersion($modulesPath . DIRECTORY_SEPARATOR . $dirEntry);
        }
    }
}

$thisModule = json_decode( file_get_contents( __DIR__ .DIRECTORY_SEPARATOR . 'config.json' ) );
$foundModule = false;
foreach( $existingModules as $existingModule ) {
    if( $existingModule->name == $thisModule->name ) {
        $foundModule = $existingModule;
        break;
    }
}
if ( $foundModule instanceof Module ) {
    echo "An existing version of $thisModule->name was found with version number " . $foundModule->getLatestVersionNr() . "\n";
    echo "What version number to you want to assign to this module?\n";
    echo "1) " . $foundModule->getNextVersionNr(1) . "\n";
    echo "2) " . $foundModule->getNextVersionNr(2) . "\n";
    echo "3) " . $foundModule->getNextVersionNr(3) . "\n";
    echo "0) Enter your own version number\n";
    $option = readchar(' ');

    if (!in_array($option, ["0", "1", '2', '3'])) {
        die("Not a valid option. Good Bye\n");
    }

    $dstpath = $foundModule->getBaseName();
    if (in_array($option, ["1", '2', '3'])) {
        $dstpath .= '_v' . $foundModule->getNextVersionNr($option);
    } else {
        echo "Enter a version number for this module \n";
        $stdin = fopen('php://stdin', 'r');
        $response = fgets($stdin);
        $dstpath .= '_v' . $response;
    }

    echo "Copying module to " . $dstpath;
    copyfolder( __DIR__ . DIRECTORY_SEPARATOR, $dstpath . DIRECTORY_SEPARATOR);
} else {
    // This is a new installation of this module.
    $dstpath = $modulesPath . DIRECTORY_SEPARATOR . fromCamelCase($thisModule->name);
    echo "Enter a version number for this module \n";
    $stdin = fopen('php://stdin', 'r');
    $response = fgets($stdin);
    $dstpath .= '_v' . $response;
    echo "Copying module to " . $dstpath;
    copyfolder( __DIR__ . DIRECTORY_SEPARATOR, $dstpath . DIRECTORY_SEPARATOR);
}

function fromCamelCase($input) {
    $input = preg_replace('/\s+/', '_', $input);
    $pattern = '!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!';
    preg_match_all($pattern, $input, $matches);
    $ret = $matches[0];
    foreach ($ret as &$match) {
        $match = $match == strtoupper($match) ?
            strtolower($match) :
            lcfirst($match);
    }
    return implode('_', $ret);
}

function copyfolder ($from, $to) {
    // (A1) SOURCE FOLDER CHECK
    if (!is_dir($from)) { exit("$from does not exist"); }

    // (A2) CREATE DESTINATION FOLDER
    if (!is_dir($to)) {
        if (!mkdir($to)) { exit("Failed to create $to"); };
        echo "$to created\r\n";
    }

    // (A3) COPY FILES + RECURSIVE INTERNAL FOLDERS
    $dir = opendir($from);
    while (($ff = readdir($dir)) !== false) { if ($ff!="." && $ff!="..") {
        if (is_dir("$from$ff")) {
            copyfolder("$from$ff/", "$to$ff/");
        } else {
            if (!copy("$from$ff", "$to$ff")) { exit("Error copying $from$ff to $to$ff"); }
            echo "$from$ff copied to $to$ff\r\n";
        }
    }}
    closedir($dir);
}

function isRedcapDir( $dir ) {
    if( is_file( $dir . DIRECTORY_SEPARATOR . 'redcap_connect.php') && is_dir($dir . DIRECTORY_SEPARATOR . 'modules' ) ) {
        return true;
    }
}

function readchar( $prompt )
{
    readline_callback_handler_install( $prompt, function() {} );
    $char = stream_get_contents( STDIN, 1 );
    readline_callback_handler_remove();
    return $char;
}