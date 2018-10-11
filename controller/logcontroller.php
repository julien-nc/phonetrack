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
use \OCP\ILogger;

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

function distance($lat1, $long1, $lat2, $long2){

    if ($lat1 === $lat2 and $long1 === $long2){
        return 0;
    }

    // Convert latitude and longitude to
    // spherical coordinates in radians.
    $degrees_to_radians = pi()/180.0;

    // phi = 90 - latitude
    $phi1 = (90.0 - $lat1)*$degrees_to_radians;
    $phi2 = (90.0 - $lat2)*$degrees_to_radians;

    // theta = longitude
    $theta1 = $long1*$degrees_to_radians;
    $theta2 = $long2*$degrees_to_radians;

    $cos = (sin($phi1)*sin($phi2)*cos($theta1 - $theta2) +
           cos($phi1)*cos($phi2));
    // why some cosinus are > than 1 ?
    if ($cos > 1.0){
        $cos = 1.0;
    }
    $arc = acos($cos);

    // Remember to multiply arc by the radius of the earth
    // in your favorite set of units to get length.
    return $arc*6371000;
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
    private $ncLogger;

    const LOG_OWNTRACKS = 'Owntracks';

    public function __construct($AppName, IRequest $request, $UserId,
                                $userfolder, $config, $shareManager,
                                IAppManager $appManager, $userManager, IL10N $trans, ILogger $ncLogger){
        parent::__construct($AppName, $request);
        $this->appVersion = $config->getAppValue('phonetrack', 'installed_version');
        // just to keep Owncloud compatibility
        // the first case : Nextcloud
        // else : Owncloud
        if (method_exists($appManager, 'getAppPath')){
            $this->appPath = $appManager->getAppPath('phonetrack');
        }
        $this->userId = $UserId;
        $this->trans = $trans;
        $this->ncLogger = $ncLogger;
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
        $sqlget = 'SELECT lat, lon, timestamp, batterylevel, satellites, accuracy, altitude, speed, bearing ';
        $sqlget .= 'FROM *PREFIX*phonetrack_points ';
        $sqlget .= 'WHERE deviceid='.$this->db_quote_escape_string($devid).' ';
        $sqlget .= 'ORDER BY timestamp DESC LIMIT 1 ;';
        $req = $this->dbconnection->prepare($sqlget);
        $req->execute();
        while ($row = $req->fetch()){
            $therow = $row;
        }
        $req->closeCursor();
        return $therow;
    }

    private function getDeviceProxims($devid) {
        $proxims = array();
        $sqlget = 'SELECT deviceid1, deviceid2, highlimit, lowlimit, urlclose, urlfar, urlclosepost, urlfarpost, sendemail ';
        $sqlget .= 'FROM *PREFIX*phonetrack_proxims ';
        $sqlget .= 'WHERE deviceid1='.$this->db_quote_escape_string($devid).' OR deviceid2='.$this->db_quote_escape_string($devid).' ;';
        $req = $this->dbconnection->prepare($sqlget);
        $req->execute();
        while ($row = $req->fetch()){
            array_push($proxims, $row);
        }
        $req->closeCursor();
        return $proxims;
    }

    private function checkProxims($lat, $lon, $devid, $userid, $devicename, $sessionname) {
        $lastPoint = $this->getLastDevicePoint($devid);
        $proxims = $this->getDeviceProxims($devid);
        foreach ($proxims as $proxim) {
            $this->checkProxim($lat, $lon, $devid, $proxim, $userid, $lastPoint, $devicename);
        }
    }

    private function getSessionOwnerOfDevice($devid) {
        $owner = null;
        $sqlget = '
        SELECT '.$this->dbdblquotes.'user'.$this->dbdblquotes.'
        FROM *PREFIX*phonetrack_devices
        INNER JOIN *PREFIX*phonetrack_sessions ON *PREFIX*phonetrack_devices.sessionid=*PREFIX*phonetrack_sessions.token
        WHERE *PREFIX*phonetrack_devices.id='.$this->db_quote_escape_string($devid).' ;';
        $req = $this->dbconnection->prepare($sqlget);
        $req->execute();
        while ($row = $req->fetch()){
            $owner = $row['user'];
        }
        $req->closeCursor();
        return $owner;
    }

    private function getDeviceName($devid) {
        $dbname = null;
        $sqlget = 'SELECT name FROM *PREFIX*phonetrack_devices ';
        $sqlget .= 'WHERE id='.$this->db_quote_escape_string($devid).';';
        $req = $this->dbconnection->prepare($sqlget);
        $req->execute();
        while ($row = $req->fetch()){
            $dbname = $row['name'];
        }
        $req->closeCursor();

        return $dbname;
    }

    private function checkProxim($newLat, $newLon, $movingDevid, $proxim, $userid, $lastPoint, $movingDeviceName) {
        $highlimit = intval($proxim['highlimit']);
        $lowlimit = intval($proxim['lowlimit']);
        $urlclose = $proxim['urlclose'];
        $urlfar = $proxim['urlfar'];
        $urlclosepost = intval($proxim['urlclosepost']);
        $urlfarpost = intval($proxim['urlfarpost']);
        $sendemail = intval($proxim['sendemail']);

        $otherDeviceId = null;
        // get the deviceid of other device
        if (intval($movingDevid) === intval($proxim['deviceid1'])) {
            $otherDeviceId = intval($proxim['deviceid2']);
        }
        else {
            $otherDeviceId = intval($proxim['deviceid1']);
        }

        // get coords of other device
        $lastOtherPoint = $this->getLastDevicePoint($otherDeviceId);
        $latOther = floatval($lastOtherPoint['lat']);
        $lonOther = floatval($lastOtherPoint['lon']);

        if ($lastPoint !== null) {
            // previous coords of observed device
            $prevLat = floatval($lastPoint['lat']);
            $prevLon = floatval($lastPoint['lon']);

            $prevDist = distance($prevLat, $prevLon, $latOther, $lonOther);
            $currDist = distance($newLat, $newLon, $latOther, $lonOther);

            // if distance was not close and is now close
            if ($lowlimit !== 0 and $prevDist >= $lowlimit and $currDist < $lowlimit) {
                // devices are now close !
                if ($sendemail !== 0) {
                    // if the observed device is 'deviceid2', then we might have the wrong userId
                    if (intval($movingDevid) === intval($proxim['deviceid2'])) {
                        $userid = $this->getSessionOwnerOfDevice($proxim['deviceid1']);
                    }
                    $dev1name = $movingDeviceName;
                    $dev2name = $this->getDeviceName($otherDeviceId);

                    $user = $this->userManager->get($userid);
                    $userEmail = $user->getEMailAddress();
                    $mailFromA = $this->config->getSystemValue('mail_from_address');
                    $mailFromD = $this->config->getSystemValue('mail_domain');

                    if (!empty($mailFromA) and !empty($mailFromD) and !empty($userEmail)) {
                        $mailfrom = $mailFromA.'@'.$mailFromD;

                        try {
                            $mailer = \OC::$server->getMailer();
                            $message = $mailer->createMessage();
                            $message->setSubject($this->trans->t('PhoneTrack proximity alert (%s and %s)', array($dev1name, $dev2name)));
                            $message->setFrom([$mailfrom => 'PhoneTrack']);
                            $message->setTo([$userEmail => $userid]);
                            $message->setPlainBody($this->trans->t('Device "%s" is now closer than %sm to "%s".', array($dev1name, $lowlimit, $dev2name)));
                            $mailer->send($message);
                        }
                        catch (\Exception $e) {
                            $this->ncLogger->warning('Error during PhoneTrack mail sending : '.$e, array('app' => $this->appName));
                        }
                    }
                }
                if ($urlclose !== '' and startsWith($urlclose, 'http')) {
                    // GET
                    if ($urlenterpost === 0) {
                        $xml = file_get_contents($urlclose);
                    }
                    // POST
                    else {
                        $parts = parse_url($urlclose);
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
            else if ($highlimit !== 0 and $prevDist <= $highlimit and $currDist > $highlimit) {
                // devices are now far !
                if ($sendemail !== 0) {
                    // if the observed device is 'deviceid2', then we might have the wrong userId
                    if (intval($movingDevid) === intval($proxim['deviceid2'])) {
                        $userid = $this->getSessionOwnerOfDevice($proxim['deviceid1']);
                    }
                    $dev1name = $movingDeviceName;
                    $dev2name = $this->getDeviceName($otherDeviceId);

                    $user = $this->userManager->get($userid);
                    $userEmail = $user->getEMailAddress();
                    $mailFromA = $this->config->getSystemValue('mail_from_address');
                    $mailFromD = $this->config->getSystemValue('mail_domain');

                    if (!empty($mailFromA) and !empty($mailFromD) and !empty($userEmail)) {
                        $mailfrom = $mailFromA.'@'.$mailFromD;

                        try {
                            $mailer = \OC::$server->getMailer();
                            $message = $mailer->createMessage();
                            $message->setSubject($this->trans->t('PhoneTrack proximity alert (%s and %s)', array($dev1name, $dev2name)));
                            $message->setFrom([$mailfrom => 'PhoneTrack']);
                            $message->setTo([$userEmail => $userid]);
                            $message->setPlainBody($this->trans->t('Device "%s" is now farther than %sm from "%s".', array($dev1name, $highlimit, $dev2name)));
                            $mailer->send($message);
                        }
                        catch (\Exception $e) {
                            $this->ncLogger->warning('Error during PhoneTrack mail sending : '.$e, array('app' => $this->appName));
                        }
                    }
                }
                if ($urlfar !== '' and startsWith($urlfar, 'http')) {
                    // GET
                    if ($urlenterpost === 0) {
                        $xml = file_get_contents($urlfar);
                    }
                    // POST
                    else {
                        $parts = parse_url($urlfar);
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

    private function getDeviceFences($devid) {
        $fences = array();
        $sqlget = 'SELECT latmin, lonmin, latmax, lonmax, name, urlenter, urlleave, urlenterpost, urlleavepost, sendemail ';
        $sqlget .= 'FROM *PREFIX*phonetrack_geofences ';
        $sqlget .= 'WHERE deviceid='.$this->db_quote_escape_string($devid).' ;';
        $req = $this->dbconnection->prepare($sqlget);
        $req->execute();
        while ($row = $req->fetch()){
            array_push($fences, $row);
        }
        $req->closeCursor();
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

                        try {
                            $mailer = \OC::$server->getMailer();
                            $message = $mailer->createMessage();
                            $message->setSubject($this->trans->t('Geofencing alert'));
                            $message->setFrom([$mailfrom => 'PhoneTrack']);
                            $message->setTo([$userEmail => $this->userId]);
                            $message->setPlainBody($this->trans->t('In session "%s", device "%s" entered geofencing zone "%s".', array($sessionname, $devicename, $fencename)));
                            $mailer->send($message);
                        }
                        catch (\Exception $e) {
                            $this->ncLogger->warning('Error during PhoneTrack mail sending : '.$e, array('app' => $this->appName));
                        }
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

                        try {
                            $mailer = \OC::$server->getMailer();
                            $message = $mailer->createMessage();
                            $message->setSubject($this->trans->t('Geofencing alert'));
                            $message->setFrom([$mailfrom => 'PhoneTrack']);
                            $message->setTo([$userEmail => $this->userId]);
                            $message->setPlainBody($this->trans->t('In session "%s", device "%s" exited geofencing zone "%s".', array($sessionname, $devicename, $fencename)));
                            $mailer->send($message);
                        }
                        catch (\Exception $e) {
                            $this->ncLogger->warning('Error during PhoneTrack mail sending : '.$e, array('app' => $this->appName));
                        }
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
     */
    public function addPoint($token, $devicename, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat, $useragent, $speed, $bearing) {
        $done = 0;
        $dbid = null;
        $dbdevid = null;
        if ($token !== '' and $devicename !== '') {
            $logres = $this->logPost($token, $devicename, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat, $useragent, $speed, $bearing);
            if ($logres['done'] === 1) {
                $sqlchk = 'SELECT id FROM *PREFIX*phonetrack_devices ';
                $sqlchk .= 'WHERE sessionid='.$this->db_quote_escape_string($token).' ';
                $sqlchk .= 'AND name='.$this->db_quote_escape_string($devicename).' ;';
                $req = $this->dbconnection->prepare($sqlchk);
                $req->execute();
                while ($row = $req->fetch()){
                    $dbdevid = $row['id'];
                    break;
                }
                $req->closeCursor();

                // if it's reserved and a device token was given
                if ($dbdevid === null) {
                    $sqlchk = 'SELECT id FROM *PREFIX*phonetrack_devices ';
                    $sqlchk .= 'WHERE sessionid='.$this->db_quote_escape_string($token).' ';
                    $sqlchk .= 'AND nametoken='.$this->db_quote_escape_string($devicename).' ;';
                    $req = $this->dbconnection->prepare($sqlchk);
                    $req->execute();
                    while ($row = $req->fetch()){
                        $dbdevid = $row['id'];
                        break;
                    }
                    $req->closeCursor();
                }

                if ($dbdevid !== null) {
                    $sqlchk = 'SELECT MAX(id) as maxid FROM *PREFIX*phonetrack_points ';
                    $sqlchk .= 'WHERE deviceid='.$this->db_quote_escape_string($dbdevid).' ';
                    $sqlchk .= 'AND lat='.$this->db_quote_escape_string($lat).' ';
                    $sqlchk .= 'AND lon='.$this->db_quote_escape_string($lon).' ';
                    $sqlchk .= 'AND timestamp='.$this->db_quote_escape_string($timestamp).' ';
                    $req = $this->dbconnection->prepare($sqlchk);
                    $req->execute();
                    while ($row = $req->fetch()){
                        $dbid = $row['maxid'];
                        break;
                    }
                    $req->closeCursor();
                    $done = 1;
                }
                else {
                    $done = 4;
                }
            }
            else {
                $done = 3;
            }
        }
        else {
            $done = 2;
        }

        $response = new DataResponse(
            [
                'done'=>$done,
                'pointid'=>$dbid,
                'deviceid'=>$dbdevid
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
     *
     * @return array;
     *
     **/
    public function logPost($token, $devicename, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat, $useragent, $speed=null, $bearing=null) {
        $res = ['done'=>0, 'friends'=>[]];
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
                    // this device id reserved => logging refused if the request does not come from correct user
                    if ($dbdevicenametoken !== null and $dbdevicenametoken !== '') {
                        // here, we check if we're logged in as the session owner
                        if ($this->userId !== '' and $this->userId !== null and $userid === $this->userId) {
                            // if so, accept to (manually) log with name and not nametoken
                            $deviceidToInsert = $dbdeviceid;
                        }
                        else {
                            return;
                        }
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
                $this->checkProxims(floatval($lat), floatval($lon), $deviceidToInsert, $userid, $devicename, $dbname);

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

                $res['done'] = 1;

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
                    $res['friends'] = $result;
                }
            }
        }
        return $res;
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
        $res = $this->logPost($token, $dname, $lat, $lon, $alt, $tst, $acc, $batt, null, self::LOG_OWNTRACKS);
        return $res['friends'];
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
            if ($speed !== null){
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
