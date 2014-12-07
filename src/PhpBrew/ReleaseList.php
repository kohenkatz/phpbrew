<?php
namespace PhpBrew;
use CLIFramework\Logger;
use CurlKit\CurlDownloader;
use CurlKit\Progress\ProgressBar;
use GetOptionKit\OptionResult;
use PhpBrew\Config;
use Exception;
use RuntimeException;

class ReleaseList
{

    /**
     * $releases['5.3'] = [ {},... ]
     * $releases['5.4'] = [ {},... ]
     * $releases['5.5'] = [ {},... ]
     */
    public $releases = array();

    public $versions = array();

    public function __construct($releases = array())
    { 
        $this->releases = $releases;
    }

    public function setReleases(array $releases)
    {
        $this->releases = $releases;
        foreach($this->releases as $major => $versionReleases) {
            foreach($versionReleases as $version => $release) {
                $this->versions[ $version ] = $release;
            }
        }
    }

    public function loadJson($json)
    {
        if (!$json) {
            throw new RuntimeException("Can't load releases. Empty JSON given.");
        }
        if ($releases = json_decode($json, true)) {
            $this->setReleases($releases);
            return $releases;
        } else {
            throw new RuntimeException("Can't decode release json, invalid JSON string: " . substr($json,0, 125) );
        }
    }

    public function loadJsonFile($file) 
    {
        $this->loadJson(file_get_contents($file));
    }

    public function getLatestPatchVersion($version) {
        if (isset($this->releases[$version])) {
            reset($this->releases[$version]);
            return current($this->releases[$version]);
        }
    }

    public function getVersion($version)
    {
        if (isset($this->releases[$version])) {
            return $this->getLatestPatchVersion($version);
        } elseif (isset($this->versions[$version])) {
            return $this->versions[$version];
        }
        return FALSE;
    }

    /**
     * Get version by minor version number
     */
    public function getVersions($key)
    {
        if (isset($this->releases[$key])) {
            return $this->releases[$key];
        }
    }

    public function loadLocalReleaseList() {
        $releaseListFile = Config::getPHPReleaseListPath();
        if ($json = file_get_contents($releaseListFile)) {
            return $this->loadJson($json);
        }
        return false;
    }

    public function getRemoteReleaseListUrl($branch)
    {
        if (!extension_loaded('openssl')) {
            throw new Exception('openssl extension not found, to download release json file you need openssl.');
        }
        return "https://raw.githubusercontent.com/phpbrew/phpbrew/$branch/assets/php-releases.json";
    }

    public function fetchRemoteReleaseList($branch = 'master', OptionResult $options = NULL) {
        $json = '';
        $url = $this->getRemoteReleaseListUrl($branch);
        if (extension_loaded('curl')) {
            $downloader = new CurlDownloader;
            $downloader->setProgressHandler(new ProgressBar);

            $console = Console::getInstance();
            if (! $console->options->{'no-progress'}) {
                $downloader->setProgressHandler(new ProgressBar);
            }

            if ($options) {
                if ($proxy = $options->{'http-proxy'}) {
                    $downloader->setProxy($proxy);
                }
                if ($proxyAuth = $options->{'http-proxy-auth'}) {
                    $downloader->setProxyAuth($proxyAuth);
                }
            }
            $json = $downloader->request($url);
        } else {
            $json = file_get_contents($url);
        }
        $localFilepath = Config::getPHPReleaseListPath();
        if (false === file_put_contents($localFilepath, $json)) {
            throw new Exception("Can't store release json file");
        }
        return $this->loadJson($json);
    }

    public function foundLocalReleaseList() {
        $releaseListFile = Config::getPHPReleaseListPath();
        return file_exists($releaseListFile);
    }

    public function getReleases()
    {
        return $this->releases;
    }

    static public function getReadyInstance($branch = 'master', Logger $logger = NULL) {
        static $instance;
        if ($instance) {
            return $instance;
        }
        $instance = new self;
        if ($instance->foundLocalReleaseList()) {
            $instance->loadLocalReleaseList();
        } else {
            $instance->fetchRemoteReleaseList($branch, $logger);
        }
        return $instance;
    }

}



