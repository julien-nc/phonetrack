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

function startsWith($haystack, $needle) {
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
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

    const LOG_OWNTRACKS = 'Owntracks';

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
        $sqlget = 'SELECT lat, lon, timestamp, batterylevel, satellites, accuracy, altitude, speed, bearing';
        $sqlget .= ' FROM *PREFIX*phonetrack_points ';
        $sqlget .= 'WHERE deviceid='.$this->db_quote_escape_string($devid).' ';
        $sqlget .= 'ORDER BY timestamp DESC LIMIT 1 ;';
        $req = $this->dbconnection->prepare($sqlget);
        $req->execute();
        while ($row = $req->fetch()){
            $therow = $row;
        }
        return $therow;
    }

    private function getDeviceFences($devid) {
        $fences = array();
        $sqlget = 'SELECT latmin, lonmin, latmax, lonmax, name, urlenter, urlleave, urlenterpost, urlleavepost, sendemail';
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
        $urlenter = $fence['urlenter'];
        $urlleave = $fence['urlleave'];
        $urlenterpost = intval($fence['urlenterpost']);
        $urlleavepost = intval($fence['urlleavepost']);
        $sendemail = intval($fence['sendemail']);
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

                    if (!empty($mailFromA) and !empty($mailFromD) and !empty($userEmail) and $sendemail !== 0) {
                        $mailfrom = $mailFromA.'@'.$mailFromD;

                        $mailer = \OC::$server->getMailer();
                        $message = $mailer->createMessage();
                        $message->setSubject($this->trans->t('Geofencing alert'));
                        $message->setFrom([$mailfrom => 'PhoneTrack']);
                        $message->setTo([$userEmail => $this->userId]);
                        $message->setPlainBody($this->trans->t('In session "%s", device "%s" entered geofencing zone "%s".', array($sessionname, $devicename, $fencename)));
                        $mailer->send($message);
                    }
                    if ($urlenter !== '' and startsWith($urlenter, 'http')) {
                        // GET
                        if ($urlenterpost === 0) {
                            $xml = file_get_contents($urlenter);
                        }
                        // POST
                        else {
                            $parts = parse_url($urlenter);
                            parse_str($parts['query'], $data);

                            $url = $parts['scheme'].'://'.$parts['host'].$parts['path'];

                            $options = array(
                                'http' => array(
                                    'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                                    'method'  => 'POST',
                                    'content' => http_build_query($data)
                                )
                            );
                            $context  = stream_context_create($options);
                            $result = file_get_contents($url, false, $context);
                        }
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

                    if (!empty($mailFromA) and !empty($mailFromD) and !empty($userEmail) and $sendemail !== 0) {
                        $mailfrom = $mailFromA.'@'.$mailFromD;

                        $mailer = \OC::$server->getMailer();
                        $message = $mailer->createMessage();
                        $message->setSubject($this->trans->t('Geofencing alert'));
                        $message->setFrom([$mailfrom => 'PhoneTrack']);
                        $message->setTo([$userEmail => $this->userId]);
                        $message->setPlainBody($this->trans->t('In session "%s", device "%s" exited geofencing zone "%s".', array($sessionname, $devicename, $fencename)));
                        $mailer->send($message);
                    }
                    if ($urlleave !== '' and startsWith($urlleave, 'http')) {
                        // GET
                        if ($urlleavepost === 0) {
                            $xml = file_get_contents($urlleave);
                        }
                        // POST
                        else {
                            $parts = parse_url($urlleave);
                            parse_str($parts['query'], $data);

                            $url = $parts['scheme'].'://'.$parts['host'].$parts['path'];

                            $options = array(
                                'http' => array(
                                    'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                                    'method'  => 'POST',
                                    'content' => http_build_query($data)
                                )
                            );
                            $context  = stream_context_create($options);
                            $result = file_get_contents($url, false, $context);
                        }
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
     * @return array;
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
            // check if session exists
            $sqlchk = '
                SELECT `name`, `user`, `public`
                FROM `*PREFIX*phonetrack_sessions` 
                WHERE `token`=?
            ';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute([$token]);
            $dbname = null;
            $userid = null;
            $isPublicSession = null;
            while ($row = $req->fetch()){
                $dbname = $row['name'];
                $userid = $row['user'];
                $isPublicSession = (bool)$row['public'];
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
                if (is_numeric($time)) {
                    $time = floatval($time);
                    if ($time > 10000000000.0) {
                        $time = $time / 1000;
                    }
                }

                if (is_numeric($acc)) {
                    $acc = sprintf('%.2f', (float)$acc);
                }

                $this->checkGeoFences(floatval($lat), floatval($lon), $deviceidToInsert, $userid, $devicename, $dbname);

                $sql = 'INSERT INTO *PREFIX*phonetrack_points';
                $sql .= ' (deviceid, lat, lon, timestamp, accuracy, satellites, altitude, batterylevel, useragent, speed, bearing) ';
                $sql .= 'VALUES (';
                $sql .= $this->db_quote_escape_string($deviceidToInsert).',';
                $sql .= $this->db_quote_escape_string(floatval($lat)).',';
                $sql .= $this->db_quote_escape_string(floatval($lon)).',';
                $sql .= $this->db_quote_escape_string(intval($time)).',';
                $sql .= (is_numeric($acc) ? $this->db_quote_escape_string(floatval($acc)) : 'NULL').',';
                $sql .= (is_numeric($sat) ? $this->db_quote_escape_string(intval($sat)) : 'NULL').',';
                $sql .= (is_numeric($alt) ? $this->db_quote_escape_string(floatval($alt)) : 'NULL').',';
                $sql .= (is_numeric($bat) ? $this->db_quote_escape_string(floatval($bat)) : 'NULL').',';
                $sql .= $this->db_quote_escape_string($useragent).',';
                $sql .= (is_numeric($speed) ? $this->db_quote_escape_string(floatval($speed)) : 'NULL').',';
                $sql .= (is_numeric($bearing) ? $this->db_quote_escape_string(floatval($bearing)) : 'NULL').');';
                $req = $this->dbconnection->prepare($sql);
                $req->execute();
                $req->closeCursor();

                if ($isPublicSession && $useragent === self::LOG_OWNTRACKS) {
                    $friendSQL  = '
                        SELECT p.`deviceid`, `nametoken`, `name`, `lat`, `lon`,
                            `speed`, `altitude`, `batterylevel`, `accuracy`,
                            `timestamp`
                        FROM `*PREFIX*phonetrack_points` p 
                        JOIN (
                            SELECT `deviceid`, `nametoken`, `name`,
                                MAX(`timestamp`) `lastupdate`
                            FROM `*PREFIX*phonetrack_points` po
                            JOIN `*PREFIX*phonetrack_devices` d ON po.`deviceid` = d.`id`
                            WHERE `sessionid` = ?
                            GROUP BY `deviceid`, `nametoken`, `name`
                        ) l ON p.`deviceid` = l.`deviceid`
                        AND p.`timestamp` = l.`lastupdate`
                    ';
                    $friendReq = $this->dbconnection->prepare($friendSQL);
                    $friendReq->execute([$token]);
                    $result = [];
                    while ($row = $friendReq->fetch()){
                        // we don't store the tid, so we fall back to the last
                        // two chars of the nametoken
                        // TODO feels far from unique, currently 32 ids max
                        $tid = substr($row['nametoken'],-2);
                        $location = [
                            '_type'=>'location',

                            // Tracker ID used to display the initials of a user (iOS,Android/string/optional) required for http mode
                            'tid' => $tid,

                            // latitude (iOS,Android/float/meters/required)
                            'lat' => (float)$row['lat'],

                            // longitude (iOS,Android/float/meters/required)
                            'lon' => (float)$row['lon'],

                            // UNIX epoch timestamp in seconds of the location fix (iOS,Android/integer/epoch/required)
                            'tst' => (int)$row['timestamp'],
                        ];

                        if (isset($row['speed'])) {
                            // velocity (iOS/integer/kmh/optional)
                            $location['vel'] = (int)$row['speed'];
                        }
                        if (isset($row['altitude'])) {
                            // Altitude measured above sea level (iOS/integer/meters/optional)
                            $location['alt'] = (int)$row['altitude'];
                        }
                        if (isset($row['batterylevel'])) {
                            // Device battery level (iOS,Android/integer/percent/optional)
                            $location['batt'] = (int)$row['batterylevel'];
                        }
                        if (isset($row['accuracy'])) {
                            // Accuracy of the reported location in meters without unit (iOS/integer/meters/optional)
                            $location['acc'] = (int)$row['accuracy'];
                        }
                        $result[] = $location;
                        $result[] = [
                            '_type'=>'card',
                            'tid' => $tid,
                            //'face'=>'/9j/4AAQSkZJR...', // TODO lookup avatar?
                            'name' => $row['name'],
                        ];
                    }
                    return $result;
                }
            }
        }
        return [];
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     *
     **/
    public function logGet($token, $devicename, $lat, $lon, $timestamp, $bat, $sat, $acc, $alt, $speed=null, $bearing=null) {
        $dname = $this->chooseDeviceName($devicename, null);
        $this->logPost($token, $dname, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat, 'unknown GET logger', $speed, $bearing);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     *
     **/
    public function logOsmand($token, $devicename, $lat, $lon, $timestamp, $bat, $sat, $acc, $alt, $speed=null, $bearing=null) {
        $dname = $this->chooseDeviceName($devicename, null);
        $this->logPost($token, $dname, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat, 'OsmAnd', $speed, $bearing);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     *
     **/
    public function logGpsloggerGet($token, $devicename, $lat, $lon, $timestamp, $bat, $sat, $acc, $alt, $speed=null, $bearing=null) {
        $dname = $this->chooseDeviceName($devicename, null);
        $this->logPost($token, $dname, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat, 'GpsLogger GET', $speed, $bearing);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     *
     **/
    public function logGpsloggerPost($token, $devicename, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat, $speed=null, $bearing=null) {
        $dname = $this->chooseDeviceName($devicename, null);
        $this->logPost($token, $dname, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat, 'GpsLogger POST', $speed, $bearing);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     *
     * Owntracks IOS
     *
     * @param string $token
     * @param string $devicename
     * @param string $tid
     * @param float $lat
     * @param float $lon
     * @param float $alt
     * @param int $tst
     * @param float $acc
     * @param float $batt
     *
     * @return array;
     **/
    public function logOwntracks($token, $devicename='', $tid, $lat, $lon, $alt, $tst, $acc, $batt) {
        $dname = $this->chooseDeviceName($devicename, $tid);
        return $this->logPost($token, $dname, $lat, $lon, $alt, $tst, $acc, $batt, null, self::LOG_OWNTRACKS);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     *
     * Ulogger Android
     **/
    public function logUlogger($token, $devicename, $trackid, $lat, $lon, $time, $accuracy, $altitude,
                               $pass, $user, $action, $speed=null, $bearing=null) {
        if ($action === 'addpos') {
            $dname = $this->chooseDeviceName($devicename, null);
            if ($speed != null){
                $speed = 3.6 * $speed ;
            }
            $this->logPost($token, $dname, $lat, $lon, $altitude, $time, $accuracy, null, null,'Ulogger', $speed, $bearing);
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
        $speedp = $speed;
        if (is_numeric($speed)) {
            // according to traccar sources, speed is converted in knots...
            // convert back to meter/s
            $speedp = floatval($speed) / 1.943844;
        }
        $this->logPost($token, $dname, $lat, $lon, $altitude, $timestamp, $accuracy, $batt, null, 'Traccar', $speedp, $bearing);
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
        $this->logPost($token, $dname, $lat, $lon, $alt, $timestamp, null, $batt, null, 'OpenGTS client');
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
