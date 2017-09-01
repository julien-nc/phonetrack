<?php
/**
 * ownCloud - phonetrack
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@gmx.fr>
 * @copyright Julien Veyssier 2017
 */

namespace OCA\PhoneTrack\Controller;

use OCP\App\IAppManager;

use OCP\IURLGenerator;
use OCP\IConfig;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

function delTree($dir) {
    $files = array_diff(scandir($dir), array('.','..'));
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file");
    }
    return rmdir($dir);
}

/**
 * Recursive find files from name pattern
 */
function globRecursive($path, $find, $recursive=True) {
    $result = Array();
    $dh = opendir($path);
    while (($file = readdir($dh)) !== false) {
        if (substr($file, 0, 1) === '.') continue;
        $rfile = "{$path}/{$file}";
        if (is_dir($rfile) and $recursive) {
            foreach (globRecursive($rfile, $find) as $ret) {
                array_push($result, $ret);
            }
        } else {
            if (fnmatch($find, $file)){
                array_push($result, $rfile);
            }
        }
    }
    closedir($dh);
    return $result;
}

function endswith($string, $test) {
    $strlen = strlen($string);
    $testlen = strlen($test);
    if ($testlen > $strlen) return false;
    return substr_compare($string, $test, $strlen - $testlen, $testlen) === 0;
}

class PageController extends Controller {

    private $userId;
    private $userfolder;
    private $config;
    private $appVersion;
    private $userAbsoluteDataPath;
    private $shareManager;
    private $dbconnection;
    private $dbtype;
    private $dbdblquotes;
    private $appPath;

    public function __construct($AppName, IRequest $request, $UserId,
                                $userfolder, $config, $shareManager, IAppManager $appManager){
        parent::__construct($AppName, $request);
        $this->appVersion = $config->getAppValue('phonetrack', 'installed_version');
        // just to keep Owncloud compatibility
        // the first case : Nextcloud
        // else : Owncloud
        if (method_exists($appManager, 'getAppPath')){
            $this->appPath = $appManager->getAppPath('phonetrack');
        }
        else {
            $this->appPath = \OC_App::getAppPath('phonetrack');
            // even dirtier
            //$this->appPath = getcwd().'/apps/phonetrack';
        }
        $this->userId = $UserId;
        $this->dbtype = $config->getSystemValue('dbtype');
        // IConfig object
        $this->config = $config;

        if ($this->dbtype === 'pgsql'){
            $this->dbdblquotes = '"';
        }
        else{
            $this->dbdblquotes = '';
        }
        $this->dbconnection = \OC::$server->getDatabaseConnection();
        if ($UserId !== '' and $userfolder !== null){
            // path of user files folder relative to DATA folder
            $this->userfolder = $userfolder;
            // absolute path to user files folder
            $this->userAbsoluteDataPath =
                $this->config->getSystemValue('datadirectory').
                rtrim($this->userfolder->getFullPath(''), '/');
        }
        //$this->shareManager = \OC::$server->getShareManager();
        $this->shareManager = $shareManager;
    }

    /*
     * quote and choose string escape function depending on database used
     */
    private function db_quote_escape_string($str){
        return $this->dbconnection->quote($str);
    }

    private function getUserTileServers($type){
        // custom tile servers management
        $sqlts = 'SELECT servername, type, url, layers, version, format, opacity, transparent, minzoom, maxzoom, attribution FROM *PREFIX*phonetrack_tileserver ';
        $sqlts .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ';
        $sqlts .= 'AND type='.$this->db_quote_escape_string($type).';';
        $req = $this->dbconnection->prepare($sqlts);
        $req->execute();
        $tss = Array();
        while ($row = $req->fetch()){
            $tss[$row["servername"]] = Array();
            foreach (Array('servername', 'type', 'url', 'layers', 'version', 'format', 'opacity', 'transparent', 'minzoom', 'maxzoom', 'attribution') as $field) {
                $tss[$row['servername']][$field] = $row[$field];
            }
        }
        $req->closeCursor();
        return $tss;
    }

    /**
     * Welcome page.
     * Get session list
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index() {
        $userFolder = \OC::$server->getUserFolder();
        $userfolder_path = $userFolder->getPath();

        $tss = $this->getUserTileServers('tile');
        $oss = $this->getUserTileServers('overlay');
        $tssw = $this->getUserTileServers('tilewms');
        $ossw = $this->getUserTileServers('overlaywms');

        // PARAMS to view

        require_once('tileservers.php');
        $params = [
            'username'=>$this->userId,
			'basetileservers'=>$baseTileServers,
			'usertileservers'=>$tss,
			'useroverlayservers'=>$oss,
			'usertileserverswms'=>$tssw,
			'useroverlayserverswms'=>$ossw,
            'phonetrack_version'=>$this->appVersion
        ];
        $response = new TemplateResponse('phonetrack', 'main', $params);
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedChildSrcDomain('*')
            ->addAllowedObjectDomain('*')
            ->addAllowedScriptDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    /**
     * @NoAdminRequired
     */
    public function getSessions($name) {
        $sessions = array();
        // check if session name is not already used
        $sqlget = 'SELECT name, token FROM *PREFIX*phonetrack_sessions ';
        $sqlget .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'=\''.$this->userId.'\' ';
        $req = $this->dbconnection->prepare($sqlget);
        $req->execute();
        while ($row = $req->fetch()){
            $dbname = $row['name'];
            $dbtoken = $row['token'];
            array_push($sessions, array($dbname, $dbtoken));
        }
        $req->closeCursor();

        $response = new DataResponse(
            [
                'sessions'=>$sessions
            ]
        );
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    /**
     * @NoAdminRequired
     */
    public function createSession($name) {
        $token = '';
        // check if session name is not already used
        $sqlchk = 'SELECT name FROM *PREFIX*phonetrack_sessions ';
        $sqlchk .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'=\''.$this->userId.'\' ';
        $sqlchk .= 'AND name='.$this->db_quote_escape_string($name).' ';
        $req = $this->dbconnection->prepare($sqlchk);
        $req->execute();
        $dbname = null;
        while ($row = $req->fetch()){
            $dbname = $row['name'];
            break;
        }
        $req->closeCursor();

        if ($dbname === null) {
            // determine token
            $token = md5($this->userId.$name);

            // insert
            $sql = 'INSERT INTO *PREFIX*phonetrack_sessions';
            $sql .= ' ('.$this->dbdblquotes.'user'.$this->dbdblquotes.', name, token) ';
            $sql .= 'VALUES (\''.$this->userId.'\',';
            $sql .= $this->db_quote_escape_string($name).',';
            $sql .= $this->db_quote_escape_string($token).');';
            $req = $this->dbconnection->prepare($sql);
            $req->execute();
            $req->closeCursor();

            $ok = 1;
        }
        else {
            $ok = 2;
        }

        $response = new DataResponse(
            [
                'done'=>$ok,
                'token'=>$token
            ]
        );
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    /**
     * @NoAdminRequired
     */
    public function deleteSession($name, $token) {
        // check if session exists
        $sqlchk = 'SELECT name FROM *PREFIX*phonetrack_sessions ';
        $sqlchk .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'=\''.$this->userId.'\' ';
        $sqlchk .= 'AND name='.$this->db_quote_escape_string($name).' ';
        $sqlchk .= 'AND token='.$this->db_quote_escape_string($token).' ';
        $req = $this->dbconnection->prepare($sqlchk);
        $req->execute();
        $dbname = null;
        while ($row = $req->fetch()){
            $dbname = $row['name'];
            break;
        }
        $req->closeCursor();

        if ($dbname !== null) {
            $sqldel = 'DELETE FROM *PREFIX*phonetrack_sessions ';
            $sqldel .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' AND name=';
            $sqldel .= $this->db_quote_escape_string($name).' AND token='.$this->db_quote_escape_string($token).';';
            $req = $this->dbconnection->prepare($sqldel);
            $req->execute();
            $req->closeCursor();

            $sqldel = 'DELETE FROM *PREFIX*phonetrack_points ';
            $sqldel .= 'WHERE sessionid='.$this->db_quote_escape_string($token).';';
            $req = $this->dbconnection->prepare($sqldel);
            $req->execute();
            $req->closeCursor();

            $ok = 1;
        }
        else {
            $ok = 2;
        }

        $response = new DataResponse(
            [
                'done'=>$ok,
            ]
        );
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    /**
     * @NoAdminRequired
     */
    public function deleteDevice($session, $device) {
        // check if session exists
        $sqlchk = 'SELECT name, token FROM *PREFIX*phonetrack_sessions ';
        $sqlchk .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'=\''.$this->userId.'\' ';
        $sqlchk .= 'AND name='.$this->db_quote_escape_string($session).' ';
        $req = $this->dbconnection->prepare($sqlchk);
        $req->execute();
        $dbname = null;
        $token = null;
        while ($row = $req->fetch()){
            $dbname = $row['name'];
            $token = $row['token'];
            break;
        }
        $req->closeCursor();

        if ($dbname !== null) {
            // check if device exists
            $sqlchk = 'SELECT count(*) as c FROM *PREFIX*phonetrack_points ';
            $sqlchk .= 'WHERE sessionid='.$this->db_quote_escape_string($token).' ';
            $sqlchk .= 'AND deviceid='.$this->db_quote_escape_string($device).' ';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            $c = 0;
            while ($row = $req->fetch()){
                $c = (int)$row['c'];
                break;
            }
            $req->closeCursor();

            if ($c > 0) {
                $sqldel = 'DELETE FROM *PREFIX*phonetrack_points ';
                $sqldel .= 'WHERE sessionid='.$this->db_quote_escape_string($token).' ';
                $sqldel .= 'AND deviceid='.$this->db_quote_escape_string($device).' ';
                $req = $this->dbconnection->prepare($sqldel);
                $req->execute();
                $req->closeCursor();
            }

            $ok = 1;
        }
        else {
            $ok = 2;
        }

        $response = new DataResponse(
            [
                'done'=>$ok,
            ]
        );
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     **/
    public function logpost($deviceid, $token, $lat, $lon, $alt, $timestamp, $acc, $batt, $sat) {
        if ($deviceid !== '' and
            $token !== '' and
            $lat !== '' and
            $lon !== '' and
            $timestamp !== ''
        ) {
            // check if session exists
            $sqlchk = 'SELECT name FROM *PREFIX*phonetrack_sessions ';
            $sqlchk .= 'WHERE token='.$this->db_quote_escape_string($token).' ';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            $dbname = null;
            while ($row = $req->fetch()){
                $dbname = $row['name'];
                break;
            }
            $req->closeCursor();

            if ($dbname !== null) {
                // correct timestamp if needed
                $time = $timestamp;
                if (is_numeric($time) and (int)$time > 10000000000) {
                    $time = (int)((int)$time / 1000);
                }

                $sql = 'INSERT INTO *PREFIX*phonetrack_points';
                $sql .= ' (sessionid, deviceid, lat, lon, timestamp, accuracy, satellites, altitude, batterylevel) ';
                $sql .= 'VALUES (';
                $sql .= $this->db_quote_escape_string($token).',';
                $sql .= $this->db_quote_escape_string($deviceid).',';
                $sql .= $this->db_quote_escape_string($lat).',';
                $sql .= $this->db_quote_escape_string($lon).',';
                $sql .= $this->db_quote_escape_string($time).',';
                $sql .= $this->db_quote_escape_string($acc).',';
                $sql .= $this->db_quote_escape_string($sat).',';
                $sql .= $this->db_quote_escape_string($alt).',';
                $sql .= $this->db_quote_escape_string($batt).');';
                $req = $this->dbconnection->prepare($sql);
                $req->execute();
                $req->closeCursor();
            }
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     **/
    public function log() {
        if (isset($_GET['token']) &&
            isset($_GET['deviceid']) &&
            isset($_GET['lat']) &&
            isset($_GET['lon']) &&
            isset($_GET['timestamp'])
        ) {
            // check if session exists
            $sqlchk = 'SELECT name FROM *PREFIX*phonetrack_sessions ';
            $sqlchk .= 'WHERE token='.$this->db_quote_escape_string($_GET['token']).' ';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            $dbname = null;
            while ($row = $req->fetch()){
                $dbname = $row['name'];
                break;
            }
            $req->closeCursor();

            if ($dbname !== null) {
                // correct timestamp if needed
                $time = $_GET['timestamp'];
                if (is_numeric($time) and (int)$time > 10000000000) {
                    $time = (int)((int)$time / 1000);
                }

                $bat = '-1';
                if (isset($_GET['bat'])) {
                    $bat = $_GET['bat'];
                }
                $sat = '-1';
                if (isset($_GET['sat'])) {
                    $sat = $_GET['sat'];
                }
                $acc = '-1';
                if (isset($_GET['acc'])) {
                    $acc = $_GET['acc'];
                }
                $alt = '-1';
                if (isset($_GET['alt'])) {
                    $alt = $_GET['alt'];
                }

                $sql = 'INSERT INTO *PREFIX*phonetrack_points';
                $sql .= ' (sessionid, deviceid, lat, lon, timestamp, accuracy, satellites, altitude, batterylevel) ';
                $sql .= 'VALUES (';
                $sql .= $this->db_quote_escape_string($_GET['token']).',';
                $sql .= $this->db_quote_escape_string($_GET['deviceid']).',';
                $sql .= $this->db_quote_escape_string($_GET['lat']).',';
                $sql .= $this->db_quote_escape_string($_GET['lon']).',';
                $sql .= $this->db_quote_escape_string($time).',';
                $sql .= $this->db_quote_escape_string($acc).',';
                $sql .= $this->db_quote_escape_string($sat).',';
                $sql .= $this->db_quote_escape_string($alt).',';
                $sql .= $this->db_quote_escape_string($bat).');';
                $req = $this->dbconnection->prepare($sql);
                $req->execute();
                $req->closeCursor();
            }
        }
    }

    /**
     * @NoAdminRequired
     * @PublicPage
     */
    public function track($sessions) {
        $result = array();
        foreach ($sessions as $session) {
            $name = $session[1];
            $token = $session[0];
            $lastTime = $session[2];

            // check if session exists
            $dbtoken = null;
            $sqlget = 'SELECT token FROM *PREFIX*phonetrack_sessions ';
            $sqlget .= 'WHERE name='.$this->db_quote_escape_string($name).' ';
            $sqlget .= 'AND token='.$this->db_quote_escape_string($token).' ';
            $req = $this->dbconnection->prepare($sqlget);
            $req->execute();
            while ($row = $req->fetch()){
                $dbtoken = $row['token'];
            }
            $req->closeCursor();

            // session exists
            if ($dbtoken !== null) {
                // get list of devices
                $devices = array();
                $sqldev = 'SELECT deviceid FROM *PREFIX*phonetrack_points ';
                $sqldev .= 'WHERE sessionid='.$this->db_quote_escape_string($token).' ';
                $sqldev .= 'GROUP BY deviceid;';
                $req = $this->dbconnection->prepare($sqldev);
                $req->execute();
                while ($row = $req->fetch()){
                    array_push($devices, $row['deviceid']);
                }
                $req->closeCursor();

                // get the coords for each device
                $result[$name] = array();

                foreach ($devices as $devname) {
                    $resultDevArray = array();
                    $lastDeviceTime = 0;
                    if (is_array($lastTime) && array_key_exists('d'.$devname, $lastTime)) {
                        $lastDeviceTime = $lastTime['d'.$devname];
                    }

                    $sqlget = 'SELECT * FROM *PREFIX*phonetrack_points ';
                    $sqlget .= 'WHERE sessionid='.$this->db_quote_escape_string($token).' ';
                    $sqlget .= 'AND deviceid='.$this->db_quote_escape_string($devname).' ';
                    $sqlget .= 'AND timestamp>'.$this->db_quote_escape_string($lastDeviceTime).' ';
                    $req = $this->dbconnection->prepare($sqlget);
                    $req->execute();
                    while ($row = $req->fetch()){
                        $entry = array();
                        foreach ($row as $k => $v) {
                            $entry[$k] = $v;
                        }
                        array_push($resultDevArray, $entry);
                    }
                    $req->closeCursor();
                    if (count($resultDevArray) > 0) {
                        $result[$name][$devname] = $resultDevArray;
                    }
                }
            }
        }

        $response = new DataResponse(
            [
                'sessions'=>$result
            ]
        );
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     **/
    public function publicSession() {
        if (isset($_GET['sessionid'])) {
            $sessionid = $_GET['sessionid'];
            // check if session exists
            $sqlchk = 'SELECT name FROM *PREFIX*phonetrack_sessions ';
            $sqlchk .= 'WHERE token='.$this->db_quote_escape_string($_GET['sessionid']).' ';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            $dbname = null;
            while ($row = $req->fetch()){
                $dbname = $row['name'];
                break;
            }
            $req->closeCursor();

            if ($dbname !== null) {
            }
            else {
                return 'There is no such session';
            }
        }
        else {
            return 'There is no such session';
        }

        require_once('tileservers.php');
        $params = [
            'username'=>'',
			'basetileservers'=>$baseTileServers,
			'usertileservers'=>'',
			'useroverlayservers'=>'',
			'usertileserverswms'=>'',
			'useroverlayserverswms'=>'',
            'phonetrack_version'=>$this->appVersion
        ];
        $response = new TemplateResponse('phonetrack', 'main', $params);
        $response->setHeaders(Array('X-Frame-Options'=>''));
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedChildSrcDomain('*')
            ->addAllowedObjectDomain('*')
            ->addAllowedScriptDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    /**
     * @NoAdminRequired
     */
    public function export($name, $token, $target) {
        date_default_timezone_set('UTC');
        $done = false;
        $userFolder = \OC::$server->getUserFolder();
        $path = $target.'/'.$name.'.gpx';
        $cleanpath = str_replace(array('../', '..\\'), '',  $path);

        $file = null;
        $filePossible = false;
        if ($userFolder->nodeExists($cleanpath)){
            $file = $userFolder->get($cleanpath);
            if ($file->getType() === \OCP\Files\FileInfo::TYPE_FILE and
                $file->isUpdateable()){
                $filePossible = true;
            }
            else{
                $filePossible = false;
            }
        }
        else{
            $dirpath = dirname($cleanpath);
            $newFileName = basename($cleanpath);
            if ($userFolder->nodeExists($dirpath)){
                $dir = $userFolder->get($dirpath);
                if ($dir->getType() === \OCP\Files\FileInfo::TYPE_FOLDER and
                    $dir->isCreatable()){
                    $dir->newFile($newFileName);
                    $file = $dir->get($newFileName);
                    $filePossible = true;
                }
                else{
                    $filePossible = false;
                }
            }
            else{
                $filePossible = false;
            }
        }

        if ($filePossible) {
            // check if session exists
            $dbtoken = null;
            $sqlget = 'SELECT token FROM *PREFIX*phonetrack_sessions ';
            $sqlget .= 'WHERE name='.$this->db_quote_escape_string($name).' ';
            $sqlget .= 'AND token='.$this->db_quote_escape_string($token).' ';
            $req = $this->dbconnection->prepare($sqlget);
            $req->execute();
            while ($row = $req->fetch()){
                $dbtoken = $row['token'];
            }
            $req->closeCursor();

            // session exists
            if ($dbtoken !== null) {
                // indexed by track name
                $coords = array();
                // get list of devices
                $devices = array();
                $sqldev = 'SELECT deviceid FROM *PREFIX*phonetrack_points ';
                $sqldev .= 'WHERE sessionid='.$this->db_quote_escape_string($token).' ';
                $sqldev .= 'GROUP BY deviceid;';
                $req = $this->dbconnection->prepare($sqldev);
                $req->execute();
                while ($row = $req->fetch()){
                    array_push($devices, $row['deviceid']);
                }
                $req->closeCursor();

                // get the coords for each device
                $result[$name] = array();

                foreach ($devices as $devname) {
                    $coords[$devname] = array();
                    $sqlget = 'SELECT * FROM *PREFIX*phonetrack_points ';
                    $sqlget .= 'WHERE sessionid='.$this->db_quote_escape_string($token).' ';
                    $sqlget .= 'AND deviceid='.$this->db_quote_escape_string($devname).' ';
                    $req = $this->dbconnection->prepare($sqlget);
                    $req->execute();
                    while ($row = $req->fetch()){
                        $epoch = $row['timestamp'];
                        $date = '';
                        if (is_numeric($epoch)) {
                            $epoch = (int)$epoch;
                            $dt = new \DateTime("@$epoch");
                            $date = $dt->format('Y-m-d\TH:i:s\Z');
                        }
                        $lat = $row['lat'];
                        $lon = $row['lon'];
                        $alt = $row['altitude'];

                        $point = array($lat, $lon, $date, $alt);
                        array_push($coords[$devname], $point);
                    }
                    $req->closeCursor();
                }
                $gpxContent = $this->generateGpx($name, $coords);
                $file->putContent($gpxContent);
                $done = true;
            }
        }

        $response = new DataResponse(
            [
                'done'=>$done
            ]
        );
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    private function generateGpx($name, $coords) {
        $dt = new \DateTime();
        $date = $dt->format('Y-m-d\TH:i:s\Z');
        $gpxText = '<?xml version="1.0" encoding="UTF-8" standalone="no" ?>' . "\n";
        $gpxText .= '<gpx xmlns="http://www.topografix.com/GPX/1/1"' .
            ' xmlns:gpxx="http://www.garmin.com/xmlschemas/GpxExtensions/v3"' .
            ' xmlns:wptx1="http://www.garmin.com/xmlschemas/WaypointExtension/v1"' .
            ' xmlns:gpxtpx="http://www.garmin.com/xmlschemas/TrackPointExtension/v1"' .
            ' creator="PhoneTrack Owncloud/Nextcloud app ' .
            $this->appVersion. '" version="1.1"' .
            ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' .
            ' xsi:schemaLocation="http://www.topografix.com/GPX/1/1' .
            ' http://www.topografix.com/GPX/1/1/gpx.xsd' .
            ' http://www.garmin.com/xmlschemas/GpxExtensions/v3' .
            ' http://www8.garmin.com/xmlschemas/GpxExtensionsv3.xsd' .
            ' http://www.garmin.com/xmlschemas/WaypointExtension/v1' .
            ' http://www8.garmin.com/xmlschemas/WaypointExtensionv1.xsd' .
            ' http://www.garmin.com/xmlschemas/TrackPointExtension/v1' .
            ' http://www.garmin.com/xmlschemas/TrackPointExtensionv1.xsd">' . "\n";
        $gpxText .= '<metadata>' . "\n" . ' <time>' . $date . '</time>' . "\n";
        $gpxText .= ' <name>' . $name . '</name>' . "\n";
        $gpxText .= ' <desc>' . count($coords) . ' devices</desc>' . "\n";
        $gpxText .= '</metadata>' . "\n";
        foreach ($coords as $device => $points) {
            $gpxText .= '<trk>' . "\n" . ' <name>' . $device . '</name>' . "\n";
            $gpxText .= ' <trkseg>' . "\n";
            foreach ($points as $point) {
                $gpxText .= '  <trkpt lat="'.$point[0].'" lon="'.$point[1].'">' . "\n";
                $gpxText .= '   <time>' . $point[2] . '</time>' . "\n";
                if ($point[3] !== '') {
                    $gpxText .= '   <ele>' . $point[3] . '</ele>' . "\n";
                }
                $gpxText .= '  </trkpt>' . "\n";
            }
            $gpxText .= ' </trkseg>' . "\n";
            $gpxText .= '</trk>' . "\n";
        }
        $gpxText .= '</gpx>';
        return $gpxText;
    }

}
