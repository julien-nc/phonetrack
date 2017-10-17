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

function DMStoDEC($dms, $longlat) {
    if ($longlat === 'latitude') {
        $deg = substr($dms, 0, 2);
        $min = substr($dms, 2, 8);
        $sec = '';
    }
    if ($longlat === 'longitude') {
        $deg = substr($dms, 0, 3);
        $min = substr($dms, 3, 8);
        $sec = '';
    }
    return $deg + ((($min * 60) + ($sec)) / 3600);
}

function getBrowser() {
    $u_agent = $_SERVER['HTTP_USER_AGENT'];
    $bname = 'Unknown';
    $platform = 'Unknown';
    $version = '';

    //First get the platform?
    if (preg_match('/linux/i', $u_agent)) {
        $platform = 'Linux';
    }
    elseif (preg_match('/macintosh|mac os x/i', $u_agent)) {
        $platform = 'Mac';
    }
    elseif (preg_match('/windows|win32/i', $u_agent)) {
        $platform = 'Windows';
    }

    // Next get the name of the useragent yes seperately and for good reason
    if(preg_match('/MSIE/i',$u_agent) && !preg_match('/Opera/i',$u_agent))
    {
        $bname = 'Internet Explorer';
        $ub = "MSIE";
    }
    elseif(preg_match('/Trident/i',$u_agent))
    { // this condition is for IE11
        $bname = 'Internet Explorer';
        $ub = "rv";
    }
    elseif(preg_match('/Firefox/i',$u_agent))
    {
        $bname = 'Mozilla Firefox';
        $ub = "Firefox";
    }
    elseif(preg_match('/Chrome/i',$u_agent))
    {
        $bname = 'Google Chrome';
        $ub = "Chrome";
    }
    elseif(preg_match('/Safari/i',$u_agent))
    {
        $bname = 'Apple Safari';
        $ub = "Safari";
    }
    elseif(preg_match('/Opera/i',$u_agent))
    {
        $bname = 'Opera';
        $ub = "Opera";
    }
    elseif(preg_match('/Netscape/i',$u_agent))
    {
        $bname = 'Netscape';
        $ub = "Netscape";
    }

    // finally get the correct version number
    // Added "|:"
    $known = array('Version', $ub, 'other');
    $pattern = '#(?<browser>' . join('|', $known) .
        ')[/|: ]+(?<version>[0-9.|a-zA-Z.]*)#';
    if (!preg_match_all($pattern, $u_agent, $matches)) {
        // we have no matching number just continue
    }

    // see how many we have
    $i = count($matches['browser']);
    if ($i !== 1) {
        //we will have two since we are not using 'other' argument yet
        //see if version is before or after the name
        if (strripos($u_agent,"Version") < strripos($u_agent,$ub)){
            $version= $matches['version'][0];
        }
        else {
            $version= $matches['version'][1];
        }
    }
    else {
        $version= $matches['version'][0];
    }

    // check if we have a number
    if ($version === null || $version === "") {
        $version = "?";
    }

    return array(
        'userAgent' => $u_agent,
        'name'      => $bname,
        'version'   => $version,
        'platform'  => $platform,
        'pattern'   => $pattern
    );
}

class LogController extends Controller {

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
    private $defaultDeviceId;

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
        $this->defaultDeviceId = ['yourname', 'deviceid'];
    }

    /*
     * quote and choose string escape function depending on database used
     */
    private function db_quote_escape_string($str){
        return $this->dbconnection->quote($str);
    }

    /**
     * if deviceid is not set to default value, we take it
     * else
     */
    private function chooseDeviceId($deviceid, $tid) {
        if ( (!in_array($deviceid, $this->defaultDeviceId)) and
             $deviceid !== '' and
             (!is_null($deviceid))
        ) {
            $did = $deviceid;
        }
        else if ($tid !== '' and !is_null($tid)){
            $did = $tid;
        }
        else {
            $did = 'unknown';
        }
        return $did;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     *
     **/
    public function logPost($token, $deviceid, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat, $useragent) {
        if (!is_null($deviceid) and $deviceid !== '' and
            !is_null($token) and $token !== '' and
            !is_null($lat) and $lat !== '' and
            !is_null($lon) and $lon !== '' and
            !is_null($timestamp) and $timestamp !== ''
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
                // check if this deviceid is reserved
                $dbdevicename = null;
                $dbdevicenametoken = null;
                $sqlgetres = 'SELECT name, nametoken FROM *PREFIX*phonetrack_devices ';
                $sqlgetres .= 'WHERE sessionid='.$this->db_quote_escape_string($token).' ';
                $sqlgetres .= 'AND name='.$this->db_quote_escape_string($deviceid).' ;';
                $req = $this->dbconnection->prepare($sqlgetres);
                $req->execute();
                while ($row = $req->fetch()){
                    $dbdevicename = $row['name'];
                    $dbdevicenametoken = $row['nametoken'];
                }
                $req->closeCursor();

                // this device id reserved => logging refused
                if ($dbdevicename !== null) {
                    if ($dbdevicenametoken !== null and $dbdevicenametoken !== '') {
                        return;
                    }
                }
                // check if the deviceid corresponds to a nametoken
                else {
                    $dbdevicenametoken = null;
                    $dbdevicename = null;
                    $sqlgetres = 'SELECT name, nametoken FROM *PREFIX*phonetrack_devices ';
                    $sqlgetres .= 'WHERE sessionid='.$this->db_quote_escape_string($token).' ';
                    $sqlgetres .= 'AND nametoken='.$this->db_quote_escape_string($deviceid).' ;';
                    $req = $this->dbconnection->prepare($sqlgetres);
                    $req->execute();
                    while ($row = $req->fetch()){
                        $dbdevicename = $row['name'];
                        $dbdevicenametoken = $row['nametoken'];
                    }
                    $req->closeCursor();

                    // there is a device which has this nametoken => we log for this device
                    if ($dbdevicenametoken !== null and $dbdevicenametoken !== '') {
                        $deviceid = $dbdevicename;
                    }
                }

                // correct timestamp if needed
                $time = $timestamp;
                if (is_numeric($time) and (int)$time > 10000000000) {
                    $time = (int)((int)$time / 1000);
                }

                if ($bat === '' or is_null($bat)) {
                    $bat = '-1';
                }
                if ($sat === '' or is_null($sat)) {
                    $sat = '-1';
                }
                if ($acc === '' or is_null($acc)) {
                    $acc = '-1';
                }
                else {
                    $acc = sprintf('%.2f', (float)$acc);
                }
                if ($alt === '' or is_null($alt)) {
                    $alt = '-1';
                }
                if ($useragent === '' or is_null($useragent)) {
                    $useragent = '';
                }
                else if ($useragent === 'browser') {
                    $bi = getBrowser();
                    $useragent = '';
                    foreach(['name', 'version', 'platform'] as $k) {
                        if (array_key_exists($k, $bi)) {
                            $useragent .= $bi[$k] . ' ';
                        }
                    }
                    $useragent = rtrim($useragent);
                }

                $sql = 'INSERT INTO *PREFIX*phonetrack_points';
                $sql .= ' (sessionid, deviceid, lat, lon, timestamp, accuracy, satellites, altitude, batterylevel, useragent) ';
                $sql .= 'VALUES (';
                $sql .= $this->db_quote_escape_string($token).',';
                $sql .= $this->db_quote_escape_string($deviceid).',';
                $sql .= $this->db_quote_escape_string($lat).',';
                $sql .= $this->db_quote_escape_string($lon).',';
                $sql .= $this->db_quote_escape_string($time).',';
                $sql .= $this->db_quote_escape_string($acc).',';
                $sql .= $this->db_quote_escape_string($sat).',';
                $sql .= $this->db_quote_escape_string($alt).',';
                $sql .= $this->db_quote_escape_string($bat).',';
                $sql .= $this->db_quote_escape_string($useragent).');';
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
     *
     **/
    public function logGet($token, $deviceid, $lat, $lon, $timestamp, $bat, $sat, $acc, $alt) {
        $did = $this->chooseDeviceId($deviceid, null);
        $this->logPost($token, $did, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat, 'unknown GET logger');
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     *
     **/
    public function logOsmand($token, $deviceid, $lat, $lon, $timestamp, $bat, $sat, $acc, $alt) {
        $did = $this->chooseDeviceId($deviceid, null);
        $this->logPost($token, $did, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat, 'OsmAnd');
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     *
     **/
    public function logGpsloggerGet($token, $deviceid, $lat, $lon, $timestamp, $bat, $sat, $acc, $alt) {
        $did = $this->chooseDeviceId($deviceid, null);
        $this->logPost($token, $did, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat, 'GpsLogger GET');
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     *
     **/
    public function logGpsloggerPost($token, $deviceid, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat) {
        $did = $this->chooseDeviceId($deviceid, null);
        $this->logPost($token, $did, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat, 'GpsLogger POST');
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     *
     * Owntracks IOS
     **/
    public function logOwntracks($token, $deviceid='', $tid, $lat, $lon, $alt, $tst, $acc, $batt) {
        $did = $this->chooseDeviceId($deviceid, $tid);
        $this->logPost($token, $did, $lat, $lon, $alt, $tst, $acc, $batt, -1, 'Owntracks');
        return array();
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     *
     * Ulogger Android
     **/
    public function logUlogger($token, $deviceid, $trackid, $lat, $lon, $time, $accuracy, $altitude, $pass, $user, $action) {
        if ($action === 'addpos') {
            $did = $this->chooseDeviceId($deviceid, null);
            $this->logPost($token, $did, $lat, $lon, $altitude, $time, $accuracy, -1, -1, 'Ulogger');
        }
        return array("error" => false, "trackid" => 1);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     *
     * traccar Android/IOS
     **/
    public function logTraccar($token, $deviceid='', $id, $lat, $lon, $timestamp, $accuracy, $altitude, $batt) {
        $did = $this->chooseDeviceId($deviceid, $id);
        $this->logPost($token, $did, $lat, $lon, $altitude, $timestamp, $accuracy, $batt, -1, 'Traccar');
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     *
     * any Opengts-compliant app
     **/
    public function logOpengts($token, $deviceid='', $id, $dev, $acct, $alt, $batt, $gprmc) {
        $did = $this->chooseDeviceId($deviceid, $id);
        $gprmca = explode(',', $gprmc);
        $time = sprintf("%06d", (int)$gprmca[1]);
        $date = sprintf("%06d", (int)$gprmca[9]);
        $datetime = \DateTime::createFromFormat('dmy His', $date.' '.$time);
        $timestamp = $datetime->getTimestamp();
        $lat = DMStoDEC($gprmca[3], 'latitude');
        $lon = DMStoDEC(sprintf('%010.4f', (float)$gprmca[5]), 'longitude');
        $this->logPost($token, $did, $lat, $lon, $alt, $timestamp, -1, $batt, -1, 'OpenGTS client');
        return true;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     *
     * in case there is a POST request (like celltrackGTS does)
     **/
    public function logOpengtsPost($token, $deviceid, $id, $dev, $acct, $alt, $batt, $gprmc) {
        return [];
    }

}
