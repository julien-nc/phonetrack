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
use \OCP\IL10N;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

function DMStoDEC($dms, $longlat) {
    if ($longlat === 'latitude') {
        $deg = intval(substr($dms, 0, 3));
        $min = floatval(substr($dms, 3, 8));
        $sec = 0;
    }
    if ($longlat === 'longitude') {
        $deg = intval(substr($dms, 0, 3));
        $min = floatval(substr($dms, 3, 8));
        $sec = 0;
    }
    return $deg + ((($min * 60) + ($sec)) / 3600);
}

function getBrowser() {
    if (isset($_SERVER['HTTP_USER_AGENT'])) {
        $u_agent = $_SERVER['HTTP_USER_AGENT'];
    }
    else {
        $u_agent = 'Yeyeah/5.0 (X11; Linux x86_64; rv:0.1) Gecko/20100101 UnknownBrowser/0.1';
    }
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
    else
    {
        $bname = 'NoBrowser';
        $ub = "NonoBrowser";
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
    if ($i === 0) {
        $version = '0.1';
    }
    else if ($i !== 1) {
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
    private $defaultDeviceName;
    private $trans;
    private $userManager;

    public function __construct($AppName, IRequest $request, $UserId,
                                $userfolder, $config, $shareManager, IAppManager $appManager, $userManager, IL10N $trans){
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
        $this->trans = $trans;
        $this->userManager = $userManager;
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
        $this->defaultDeviceName = ['yourname', 'devicename', 'name'];
    }

    /*
     * quote and choose string escape function depending on database used
     */
    private function db_quote_escape_string($str){
        return $this->dbconnection->quote($str);
    }

    /**
     * if devicename is not set to default value, we take it
     * else
     */
    private function chooseDeviceName($devicename, $tid) {
        if ( (!in_array($devicename, $this->defaultDeviceName)) and
             $devicename !== '' and
             (!is_null($devicename))
        ) {
            $dname = $devicename;
        }
        else if ($tid !== '' and !is_null($tid)){
            $dname = $tid;
        }
        else {
            $dname = 'unknown';
        }
        return $dname;
    }

    private function getLastDevicePoint($devid) {
        $therow = null;
        $sqlget = 'SELECT lat, lon, timestamp, batterylevel, satellites, accuracy, altitude';
        $sqlget .= ' FROM *PREFIX*phonetrack_points ';
        $sqlget .= 'WHERE deviceid='.$this->db_quote_escape_string($devid).' ';
        $sqlget .= 'ORDER BY timestamp DESC LIMIT 1 ';
        $req = $this->dbconnection->prepare($sqlget);
        $req->execute();
        while ($row = $req->fetch()){
            $therow = $row;
        }
        return $therow;
    }

    private function getDeviceFences($devid) {
        $fences = array();
        $sqlget = 'SELECT latmin, lonmin, latmax, lonmax, name';
        $sqlget .= ' FROM *PREFIX*phonetrack_geofences ';
        $sqlget .= 'WHERE deviceid='.$this->db_quote_escape_string($devid).' ;';
        $req = $this->dbconnection->prepare($sqlget);
        $req->execute();
        while ($row = $req->fetch()){
            array_push($fences, $row);
        }
        return $fences;
    }

    private function checkGeoFences($lat, $lon, $devid, $userid, $devicename, $sessionname) {
        $lastPoint = $this->getLastDevicePoint($devid);
        $fences = $this->getDeviceFences($devid);
        foreach ($fences as $fence) {
            $this->checkGeoGence($lat, $lon, $lastPoint, $devid, $fence, $userid, $devicename, $sessionname);
        }
    }

    private function checkGeoGence($lat, $lon, $lastPoint, $devid, $fence, $userid, $devicename, $sessionname) {
        $latmin = floatval($fence['latmin']);
        $latmax = floatval($fence['latmax']);
        $lonmin = floatval($fence['lonmin']);
        $lonmax = floatval($fence['lonmax']);
        $fencename = $fence['name'];

        // first point of this device
        if ($lastPoint === null) {
            if (    $lat > $latmin
                and $lat < $latmax
                and $lon > $lonmin
                and $lon < $lonmax
            ) {
            }
        }
        // not the first point
        else {
            $lastLat = floatval($lastPoint['lat']);
            $lastLon = floatval($lastPoint['lon']);

            // if previous point not in fence
            if (!($lastLat > $latmin and $lastLat < $latmax and $lastLon > $lonmin and $lastLon < $lonmax)) {
                // and new point in fence
                if ($lat > $latmin and $lat < $latmax and $lon > $lonmin and $lon < $lonmax) {
                    // device ENTERED the fence !
                    $user = $this->userManager->get($userid);
                    $userEmail = $user->getEMailAddress();
                    $mailFromA = $this->config->getSystemValue('mail_from_address');
                    $mailFromD = $this->config->getSystemValue('mail_domain');

                    if (!empty($mailFromA) and !empty($mailFromD) and !empty($userEmail)) {
                        $mailfrom = $mailFromA.'@'.$mailFromD;

                        $mailer = \OC::$server->getMailer();
                        $message = $mailer->createMessage();
                        $message->setSubject($this->trans->t('Geofence alert'));
                        $message->setFrom([$mailfrom => 'PhoneTrack']);
                        $message->setTo([$userEmail => $this->userId]);
                        $message->setPlainBody($this->trans->t('In session "%s", device "%s" entered geofence "%s".', array($sessionname, $devicename, $fencename)));
                        $mailer->send($message);
                    }
                }
            }
            // previous point in fence
            else {
                // if new point NOT in fence
                if (!($lat > $latmin and $lat < $latmax and $lon > $lonmin and $lon < $lonmax)) {
                    // device EXITED the fence !
                    $user = $this->userManager->get($userid);
                    $userEmail = $user->getEMailAddress();
                    $mailFromA = $this->config->getSystemValue('mail_from_address');
                    $mailFromD = $this->config->getSystemValue('mail_domain');

                    if (!empty($mailFromA) and !empty($mailFromD) and !empty($userEmail)) {
                        $mailfrom = $mailFromA.'@'.$mailFromD;

                        $mailer = \OC::$server->getMailer();
                        $message = $mailer->createMessage();
                        $message->setSubject($this->trans->t('Geofence alert'));
                        $message->setFrom([$mailfrom => 'PhoneTrack']);
                        $message->setTo([$userEmail => $this->userId]);
                        $message->setPlainBody($this->trans->t('In session "%s", device "%s" exited geofence "%s".', array($sessionname, $devicename, $fencename)));
                        $mailer->send($message);
                    }
                }
            }
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     *
     **/
    public function logPost($token, $devicename, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat, $useragent, $speed=null, $bearing=null) {
        // TODO insert speed and bearing in m/s and degrees
        if (!is_null($devicename) and $devicename !== '' and
            !is_null($token) and $token !== '' and
            !is_null($lat) and $lat !== '' and is_numeric($lat) and
            !is_null($lon) and $lon !== '' and is_numeric($lon) and
            !is_null($timestamp) and $timestamp !== '' and is_numeric($timestamp)
        ) {
            $userid = null;
            // check if session exists
            $sqlchk = 'SELECT name, user FROM *PREFIX*phonetrack_sessions ';
            $sqlchk .= 'WHERE token='.$this->db_quote_escape_string($token).' ';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            $dbname = null;
            while ($row = $req->fetch()){
                $dbname = $row['name'];
                $userid = $row['user'];
                break;
            }
            $req->closeCursor();

            if ($dbname !== null) {
                // check if this devicename is reserved or exists
                $dbdevicename = null;
                $dbdevicenametoken = null;
                $deviceidToInsert = null;
                $sqlgetres = 'SELECT id, name, nametoken FROM *PREFIX*phonetrack_devices ';
                $sqlgetres .= 'WHERE sessionid='.$this->db_quote_escape_string($token).' ';
                $sqlgetres .= 'AND name='.$this->db_quote_escape_string($devicename).' ;';
                $req = $this->dbconnection->prepare($sqlgetres);
                $req->execute();
                while ($row = $req->fetch()){
                    $dbdeviceid = $row['id'];
                    $dbdevicename = $row['name'];
                    $dbdevicenametoken = $row['nametoken'];
                }
                $req->closeCursor();

                // the device exists
                if ($dbdevicename !== null) {
                    // this device id reserved => logging refused
                    if ($dbdevicenametoken !== null and $dbdevicenametoken !== '') {
                        return;
                    }
                    else {
                        $deviceidToInsert = $dbdeviceid;
                    }
                }
                // the device with this device name does not exist
                else {
                    // check if the device name corresponds to a nametoken
                    $dbdevicenametoken = null;
                    $dbdevicename = null;
                    $sqlgetres = 'SELECT id, name, nametoken FROM *PREFIX*phonetrack_devices ';
                    $sqlgetres .= 'WHERE sessionid='.$this->db_quote_escape_string($token).' ';
                    $sqlgetres .= 'AND nametoken='.$this->db_quote_escape_string($devicename).' ;';
                    $req = $this->dbconnection->prepare($sqlgetres);
                    $req->execute();
                    while ($row = $req->fetch()){
                        $dbdeviceid = $row['id'];
                        $dbdevicename = $row['name'];
                        $dbdevicenametoken = $row['nametoken'];
                    }
                    $req->closeCursor();

                    // there is a device which has this nametoken => we log for this device
                    if ($dbdevicenametoken !== null and $dbdevicenametoken !== '') {
                        $deviceidToInsert = $dbdeviceid;
                    }
                    else {
                        // device does not exist and there is no reservation corresponding
                        // => we create it
                        $sql = 'INSERT INTO *PREFIX*phonetrack_devices';
                        $sql .= ' (name, sessionid) ';
                        $sql .= 'VALUES (';
                        $sql .= $this->db_quote_escape_string($devicename).',';
                        $sql .= $this->db_quote_escape_string($token);
                        $sql .= ');';
                        $req = $this->dbconnection->prepare($sql);
                        $req->execute();
                        $req->closeCursor();

                        // get the newly created device id
                        $sqlgetdid = 'SELECT id FROM *PREFIX*phonetrack_devices ';
                        $sqlgetdid .= 'WHERE sessionid='.$this->db_quote_escape_string($token).' ';
                        $sqlgetdid .= 'AND name='.$this->db_quote_escape_string($devicename).' ;';
                        $req = $this->dbconnection->prepare($sqlgetdid);
                        $req->execute();
                        while ($row = $req->fetch()){
                            $deviceidToInsert = $row['id'];
                        }
                        $req->closeCursor();
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

                $this->checkGeoFences(floatval($lat), floatval($lon), $deviceidToInsert, $userid, $devicename, $dbname);

                $sql = 'INSERT INTO *PREFIX*phonetrack_points';
                $sql .= ' (deviceid, lat, lon, timestamp, accuracy, satellites, altitude, batterylevel, useragent) ';
                $sql .= 'VALUES (';
                $sql .= $this->db_quote_escape_string($deviceidToInsert).',';
                $sql .= $this->db_quote_escape_string(floatval($lat)).',';
                $sql .= $this->db_quote_escape_string(floatval($lon)).',';
                $sql .= $this->db_quote_escape_string(intval($time)).',';
                $sql .= $this->db_quote_escape_string(floatval($acc)).',';
                $sql .= $this->db_quote_escape_string(intval($sat)).',';
                $sql .= $this->db_quote_escape_string(floatval($alt)).',';
                $sql .= $this->db_quote_escape_string(floatval($bat)).',';
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
    public function logGet($token, $devicename, $lat, $lon, $timestamp, $bat, $sat, $acc, $alt) {
        $dname = $this->chooseDeviceName($devicename, null);
        $this->logPost($token, $dname, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat, 'unknown GET logger');
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     *
     **/
    public function logOsmand($token, $devicename, $lat, $lon, $timestamp, $bat, $sat, $acc, $alt) {
        $dname = $this->chooseDeviceName($devicename, null);
        $this->logPost($token, $dname, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat, 'OsmAnd');
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     *
     **/
    public function logGpsloggerGet($token, $devicename, $lat, $lon, $timestamp, $bat, $sat, $acc, $alt) {
        $dname = $this->chooseDeviceName($devicename, null);
        $this->logPost($token, $dname, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat, 'GpsLogger GET');
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     *
     **/
    public function logGpsloggerPost($token, $devicename, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat) {
        $dname = $this->chooseDeviceName($devicename, null);
        $this->logPost($token, $dname, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat, 'GpsLogger POST');
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     *
     * Owntracks IOS
     **/
    public function logOwntracks($token, $devicename='', $tid, $lat, $lon, $alt, $tst, $acc, $batt) {
        $dname = $this->chooseDeviceName($devicename, $tid);
        $this->logPost($token, $dname, $lat, $lon, $alt, $tst, $acc, $batt, -1, 'Owntracks');
        return array();
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     *
     * Ulogger Android
     **/
    public function logUlogger($token, $devicename, $trackid, $lat, $lon, $time, $accuracy, $altitude, $pass, $user, $action) {
        if ($action === 'addpos') {
            $dname = $this->chooseDeviceName($devicename, null);
            $this->logPost($token, $dname, $lat, $lon, $altitude, $time, $accuracy, -1, -1, 'Ulogger');
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
    public function logTraccar($token, $devicename='', $id, $lat, $lon, $timestamp, $accuracy, $altitude, $batt, $speed, $bearing) {
        $dname = $this->chooseDeviceName($devicename, $id);
        $this->logPost($token, $dname, $lat, $lon, $altitude, $timestamp, $accuracy, $batt, -1, 'Traccar', $speed, $bearing);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     *
     * any Opengts-compliant app
     **/
    public function logOpengts($token, $devicename='', $id, $dev, $acct, $alt, $batt, $gprmc) {
        $dname = $this->chooseDeviceName($devicename, $id);
        $gprmca = explode(',', $gprmc);
        $time = sprintf("%06d", (int)$gprmca[1]);
        $date = sprintf("%06d", (int)$gprmca[9]);
        $datetime = \DateTime::createFromFormat('dmy His', $date.' '.$time);
        $timestamp = $datetime->getTimestamp();
        $lat = DMStoDEC(sprintf('%010.4f', (float)$gprmca[3]), 'latitude');
        if ($gprmca[4] === 'S') {
            $lat = - $lat;
        }
        $lon = DMStoDEC(sprintf('%010.4f', (float)$gprmca[5]), 'longitude');
        if ($gprmca[6] === 'W') {
            $lon = - $lon;
        }
        $this->logPost($token, $dname, $lat, $lon, $alt, $timestamp, -1, $batt, -1, 'OpenGTS client');
        return true;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     *
     * in case there is a POST request (like celltrackGTS does)
     **/
    public function logOpengtsPost($token, $devicename, $id, $dev, $acct, $alt, $batt, $gprmc) {
        return [];
    }

}
