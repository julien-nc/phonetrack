<?php

/**
 * Nextcloud - phonetrack
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @copyright Julien Veyssier 2019
 */

namespace OCA\PhoneTrack\Service;

use OCP\IL10N;
use OCP\ILogger;
use OCP\DB\QueryBuilder\IQueryBuilder;

use OCA\PhoneTrack\Db\SessionMapper;
use OCP\IUserManager;
use OCP\IGroupManager;

use OCP\IConfig;

class SessionService {

    private $l10n;
    private $logger;
    private $qb;
    private $dbconnection;

    public function __construct (
        ILogger $logger,
        IL10N $l10n,
        SessionMapper $sessionMapper,
        IUserManager $userManager,
        IGroupManager $groupManager,
        IConfig $config
    ) {
        $this->l10n = $l10n;
        $this->logger = $logger;
        $this->qb = \OC::$server->getDatabaseConnection()->getQueryBuilder();
        $this->dbconnection = \OC::$server->getDatabaseConnection();
        $this->sessionMapper = $sessionMapper;
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
        $this->config = $config;

        $this->dbtype = $config->getSystemValue('dbtype');
        if ($this->dbtype === 'pgsql'){
            $this->dbdblquotes = '"';
        }
        else{
            $this->dbdblquotes = '';
        }
        $this->appVersion = $config->getAppValue('phonetrack', 'installed_version');
    }

    private function db_quote_escape_string($str){
        return $this->dbconnection->quote($str);
    }

    public function findUsers($id) {
        $userIds = [];
        // get owner with mapper
        $session = $this->sessionMapper->find($id);
        array_push($userIds, $session->getUser());

        // get user shares from session token
        $token = $session->getToken();
        $qb = $this->dbconnection->getQueryBuilder();
        $qb->select('username')
            ->from('phonetrack_shares', 's')
            ->where(
                $qb->expr()->eq('sessionid', $qb->createNamedParameter($token, IQueryBuilder::PARAM_STR))
            );
        $req = $qb->execute();
        while ($row = $req->fetch()) {
            if (!in_array($row['username'], $userIds)) {
                array_push($userIds, $row['username']);
            }
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();

        return $userIds;
    }

    private function getOrCreateExportDir($userId) {
        $dir = null;
        $userFolder = \OC::$server->getUserFolder($userId);

        $dirpath = $this->config->getUserValue($userId, 'phonetrack', 'autoexportpath', '/PhoneTrack_export');

        if ($userFolder->nodeExists($dirpath)){
            $tmp = $userFolder->get($dirpath);
            if ($tmp->getType() === \OCP\Files\FileInfo::TYPE_FOLDER and
                $tmp->isCreatable()){
                $dir = $tmp;
            }
        }
        else {
            $userFolder->newFolder($dirpath);
            $dir = $userFolder->get($dirpath);
        }
        return $dir;
    }

    private function cronAutoPurge() {
        date_default_timezone_set('UTC');
        foreach (array('day'=>'1', 'week'=>'7', 'month'=>'31') as $s => $nbDays) {
            $now = new \DateTime();
            $now->modify('-'.$nbDays.' day');
            $ts = $now->getTimestamp();

            // get all sessions with this auto purge value
            $sessions = array();
            $sqlget = '
                SELECT token FROM *PREFIX*phonetrack_sessions
                WHERE autopurge='.$this->db_quote_escape_string($s).' ;';
            $req = $this->dbconnection->prepare($sqlget);
            $req->execute();
            while ($row = $req->fetch()){
                array_push($sessions, $row['token']);
            }
            $req->closeCursor();

            $devices = array();
            foreach ($sessions as $token) {
                $sqlget = '
                    SELECT id
                    FROM *PREFIX*phonetrack_devices
                    WHERE sessionid='.$this->db_quote_escape_string($token).' ;';
                $req = $this->dbconnection->prepare($sqlget);
                $req->execute();
                while ($row = $req->fetch()){
                    array_push($devices, $row['id']);
                }
                $req->closeCursor();
            }

            foreach ($devices as $did) {
                $sqldel = '
                    DELETE FROM *PREFIX*phonetrack_points
                    WHERE deviceid='.$this->db_quote_escape_string($did).'
                          AND timestamp<'.$this->db_quote_escape_string($ts).' ;';
                $req = $this->dbconnection->prepare($sqldel);
                $req->execute();
                $req->closeCursor();
            }
        }
    }

    /**
     * auto export
     * triggered by NC cron job
     *
     * export sessions
     */
    public function cronAutoExport() {
        $dtz = ini_get('date.timezone');
        if ($dtz === '') {
            $dtz = 'UTC';
        }
        date_default_timezone_set($dtz);
        $userNames = [];

        // last day
        $now = new \DateTime();
        $y = $now->format('Y');
        $m = $now->format('m');
        $d = $now->format('d');
        $timestamp = $now->getTimestamp();

        // get begining of today
        $dateMaxDay = new \DateTime($y.'-'.$m.'-'.$d);
        $maxDayTimestamp = $dateMaxDay->getTimestamp();
        $minDayTimestamp = $maxDayTimestamp - 24*60*60;

        $dateMaxDay->modify('-1 day');
        $dailySuffix = '_daily_'.$dateMaxDay->format('Y-m-d');
        //$dailySuffix = '_daily_'.$y.'-'.sprintf('%02d', intval($m)).'-'.sprintf('%02d', intval($d)-1);

        // last week
        $now = new \DateTime();
        while (intval($now->format('N')) !== 1) {
            $now->modify('-1 day');
        }
        $y = $now->format('Y');
        $m = $now->format('m');
        $d = $now->format('d');
        $dateWeekMax = new \DateTime($y.'-'.$m.'-'.$d);
        $maxWeekTimestamp = $dateWeekMax->getTimestamp();
        $minWeekTimestamp = $maxWeekTimestamp - 7*24*60*60;
        $dateWeekMin = new \DateTime($y.'-'.$m.'-'.$d);
        $dateWeekMin->modify('-7 day');
        $weeklySuffix = '_weekly_'.$dateWeekMin->format('Y-m-d');

        // last month
        $now = new \DateTime();
        while (intval($now->format('d')) !== 1) {
            $now->modify('-1 day');
        }
        $y = $now->format('Y');
        $m = $now->format('m');
        $d = $now->format('d');
        $dateMonthMax = new \DateTime($y.'-'.$m.'-'.$d);
        $maxMonthTimestamp = $dateMonthMax->getTimestamp();
        $now->modify('-1 day');
        while (intval($now->format('d')) !== 1) {
            $now->modify('-1 day');
        }
        $y = intval($now->format('Y'));
        $m = intval($now->format('m'));
        $d = intval($now->format('d'));
        $dateMonthMin = new \DateTime($y.'-'.$m.'-'.$d);
        $minMonthTimestamp = $dateMonthMin->getTimestamp();
        $monthlySuffix = '_monthly_'.$dateMonthMin->format('Y-m');

        $weekFilterArray = array();
        $weekFilterArray['tsmin'] = $minWeekTimestamp;
        $weekFilterArray['tsmax'] = $maxWeekTimestamp;
        $dayFilterArray = array();
        $dayFilterArray['tsmin'] = $minDayTimestamp;
        $dayFilterArray['tsmax'] = $maxDayTimestamp;
        $monthFilterArray = array();
        $monthFilterArray['tsmin'] = $minMonthTimestamp;
        $monthFilterArray['tsmax'] = $maxMonthTimestamp;

        date_default_timezone_set('UTC');

        foreach($this->userManager->search('') as $u) {
            $userName = $u->getUID();

            $sqlget = '
                SELECT name, token, autoexport
                FROM *PREFIX*phonetrack_sessions
                WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($userName).' ;';
            $req = $this->dbconnection->prepare($sqlget);
            $req->execute();
            while ($row = $req->fetch()){
                $dbname = $row['name'];
                $dbtoken = $row['token'];
                $dbexportType = $row['autoexport'];
                // export if autoexport is set
                if ($dbexportType !== 'no') {
                    $suffix = $dailySuffix;
                    $filterArray = $dayFilterArray;
                    if ($dbexportType === 'weekly') {
                        $suffix = $weeklySuffix;
                        $filterArray = $weekFilterArray;
                    }
                    else if ($dbexportType === 'monthly') {
                        $suffix = $monthlySuffix;
                        $filterArray = $monthFilterArray;
                    }
                    $dir = $this->getOrCreateExportDir($userName);
                    // check if file already exists
                    $exportName = $dbname.$suffix.'.gpx';

                    $rel_path = str_replace(\OC::$server->getUserFolder($userName)->getPath(), '', $dir->getPath());
                    $exportPath = $rel_path.'/'.$exportName;
                    if (! $dir->nodeExists($exportName)) {
                        $this->export($dbname, $dbtoken, $exportPath, $userName, $filterArray);
                    }
                }
            }
        }
        // we run the auto purge method AFTER the auto export
        // to avoid deleting data before it has been eventually exported
        $this->cronAutoPurge();
    }

    public function export($name, $token, $target, $username='', $filterArray=null) {
        date_default_timezone_set('UTC');
        $done = false;
        $warning = 0;
        $userFolder = null;
        if ($username !== ''){
            $userFolder = \OC::$server->getUserFolder($username);
            $userId = $username;
        } else {
                return [false, 0];
        }
        // get options to know if we should export one file per device
        $ofpd = $this->config->getUserValue($userId, 'phonetrack', 'exportoneperdev', 'false');
        $oneFilePerDevice = ($ofpd === 'true');

        $path = $target;
        $cleanpath = str_replace(array('../', '..\\'), '',  $path);

        if ($userFolder !== null) {
            $file = null;
            $filePossible = false;
            $dirpath = dirname($cleanpath);
            $newFileName = basename($cleanpath);
            if ($oneFilePerDevice) {
                if ($userFolder->nodeExists($dirpath)){
                    $dir = $userFolder->get($dirpath);
                    if ($dir->getType() === \OCP\Files\FileInfo::TYPE_FOLDER and
                        $dir->isCreatable()){
                        $filePossible = true;
                    }
                }
            }
            else {
                if ($userFolder->nodeExists($cleanpath)){
                    $dir = $userFolder->get($dirpath);
                    $file = $userFolder->get($cleanpath);
                    if ($file->getType() === \OCP\Files\FileInfo::TYPE_FILE and
                        $file->isUpdateable()){
                        $filePossible = true;
                    }
                }
                else{
                    if ($userFolder->nodeExists($dirpath)){
                        $dir = $userFolder->get($dirpath);
                        if ($dir->getType() === \OCP\Files\FileInfo::TYPE_FOLDER and
                            $dir->isCreatable()){
                            $filePossible = true;
                        }
                    }
                }
            }

            if ($filePossible) {
                // check if session exists
                $dbtoken = null;
                $sqlget = '
                    SELECT token
                    FROM *PREFIX*phonetrack_sessions
                    WHERE name='.$this->db_quote_escape_string($name).'
                          AND token='.$this->db_quote_escape_string($token).' ;';
                $req = $this->dbconnection->prepare($sqlget);
                $req->execute();
                while ($row = $req->fetch()){
                    $dbtoken = $row['token'];
                }
                $req->closeCursor();

                // if not, check it is a shared session
                if ($dbtoken === null) {
                    $sqlget = '
                        SELECT sessionid
                        FROM *PREFIX*phonetrack_shares
                        WHERE sharetoken='.$this->db_quote_escape_string($token).'
                              AND username='.$this->db_quote_escape_string($userId).' ;';
                    $req = $this->dbconnection->prepare($sqlget);
                    $req->execute();
                    while ($row = $req->fetch()){
                        $dbtoken = $row['sessionid'];
                    }
                    $req->closeCursor();
                }

                // session exists
                if ($dbtoken !== null) {
                    // indexed by track name
                    $coords = array();
                    // get list of all devices which have points in this session (without filters)
                    $devices = array();
                    $sqldev = '
                        SELECT dev.id AS id, dev.name AS name
                        FROM *PREFIX*phonetrack_devices AS dev, *PREFIX*phonetrack_points AS po
                        WHERE dev.sessionid='.$this->db_quote_escape_string($dbtoken).' AND dev.id=po.deviceid GROUP BY dev.id;';
                    $req = $this->dbconnection->prepare($sqldev);
                    $req->execute();
                    while ($row = $req->fetch()){
                        array_push($devices, array($row['id'], $row['name']));
                    }
                    $req->closeCursor();

                    // get the coords for each device
                    $result[$name] = array();

                    // get filters
                    if ($filterArray === null) {
                        $filterArray = $this->getCurrentFilters($userId);
                    }
                    $filterSql = $this->getSqlFilter($filterArray);

                    // check if there are points in this session (with filters)
                    if ($this->countPointsPerSession($dbtoken, $filterSql) > 0) {
                        // check if all devices of this session (not filtered) have points
                        if ($this->countDevicesPerSession($dbtoken) > count($devices)) {
                            $warning = 2;
                        }
                        // one file for the whole session
                        if (!$oneFilePerDevice) {
                            $gpxHeader = $this->generateGpxHeader($name, count($devices));
                            if (! $dir->nodeExists($newFileName)) {
                                $dir->newFile($newFileName);
                            }
                            $file = $dir->get($newFileName);
                            $fd = $file->fopen('w');
                            fwrite($fd, $gpxHeader);
                        }
                        foreach ($devices as $d) {
                            $devid = $d[0];
                            $devname = $d[1];

                            // check if there are coords for this device (with filters)
                            $nbPoints = $this->countPointsPerDevice($devid, $filterSql);
                            if ($nbPoints > 0) {
                                // generate a file for this device if needed
                                if ($oneFilePerDevice) {
                                    $gpxHeader = $this->generateGpxHeader($name);
                                    // generate file name for this device
                                    $devFileName = str_replace(array('.gpx', '.GPX'), '_'.$devname.'.gpx',  $newFileName);
                                    if (! $dir->nodeExists($devFileName)) {
                                        $dir->newFile($devFileName);
                                    }
                                    $file = $dir->get($devFileName);
                                    $fd = $file->fopen('w');
                                    fwrite($fd, $gpxHeader);
                                }

                                $this->getAndWriteDevicePoints($devid, $devname, $filterSql, $fd, $nbPoints);

                                if ($oneFilePerDevice) {
                                    fwrite($fd, '</gpx>');
                                    fclose($fd);
                                    $file->touch();
                                }
                            }
                            else {
                                $warning = 2;
                            }
                        }
                        if (!$oneFilePerDevice) {
                            fwrite($fd, '</gpx>');
                            fclose($fd);
                            $file->touch();
                        }
                    }
                    else {
                        $warning = 1;
                    }
                    $done = true;
                }
            }
        }

    return [$done, $warning];
    }

    public function getCurrentFilters($userId) {
        $fArray = null;
        $f = array();
        $keys = $this->config->getUserKeys($userId, 'phonetrack');
        foreach ($keys as $key) {
            $value = $this->config->getUserValue($userId, 'phonetrack', $key);
            $f[$key] = $value;
        }
        if (array_key_exists('applyfilters', $f) and $f['applyfilters'] === 'true') {
            $fArray = array();
            if (array_key_exists('datemin', $f) and $f['datemin'] !== '') {
                $hourmin =   (array_key_exists('hourmin', $f)   and $f['hourmin']   !== '') ? intval($f['hourmin']) : 0;
                $minutemin = (array_key_exists('minutemin', $f) and $f['minutemin'] !== '') ? intval($f['minutemin']) : 0;
                $secondmin = (array_key_exists('secondmin', $f) and $f['secondmin'] !== '') ? intval($f['secondmin']) : 0;
                $fArray['tsmin'] = intval($f['datemin']) + 3600*$hourmin + 60*$minutemin + $secondmin;
            }
            else {
                if (    array_key_exists('hourmin', $f)   and $f['hourmin'] !== ''
                    and array_key_exists('minutemin', $f) and $f['minutemin'] !== ''
                    and array_key_exists('secondmin', $f) and $f['secondmin'] !== ''
                ) {
                    $dtz = ini_get('date.timezone');
                    if ($dtz === '') {
                        $dtz = 'UTC';
                    }
                    date_default_timezone_set($dtz);
                    $now = new \DateTime();
                    $y = $now->format('Y');
                    $m = $now->format('m');
                    $d = $now->format('d');
                    $h = intval($f['hourmin']);
                    $mi = intval($f['minutemin']);
                    $s = intval($f['secondmin']);
                    $dmin = new \DateTime($y.'-'.$m.'-'.$d.' '.$h.':'.$mi.':'.$s);
                    $fArray['tsmin'] = $dmin->getTimestamp();
                }
            }
            if (array_key_exists('datemax', $f) and $f['datemax'] !== '') {
                $hourmax =   (array_key_exists('hourmax', $f)   and $f['hourmax'] !== '')   ? intval($f['hourmax']) : 23;
                $minutemax = (array_key_exists('minutemax', $f) and $f['minutemax'] !== '') ? intval($f['minutemax']) : 59;
                $secondmax = (array_key_exists('secondmax', $f) and $f['secondmax'] !== '') ? intval($f['secondmax']) : 59;
                $fArray['tsmax'] = intval($f['datemax']) + 3600*$hourmax + 60*$minutemax + $secondmax;
            }
            else {
                if (    array_key_exists('hourmax', $f)   and $f['hourmax'] !== ''
                    and array_key_exists('minutemax', $f) and $f['minutemax'] !== ''
                    and array_key_exists('secondmax', $f) and $f['secondmax'] !== ''
                ) {
                    $dtz = ini_get('date.timezone');
                    if ($dtz === '') {
                        $dtz = 'UTC';
                    }
                    date_default_timezone_set($dtz);
                    $now = new \DateTime();
                    $y = $now->format('Y');
                    $m = $now->format('m');
                    $d = $now->format('d');
                    $h = intval($f['hourmax']);
                    $mi = intval($f['minutemax']);
                    $s = intval($f['secondmax']);
                    $dmax = new \DateTime($y.'-'.$m.'-'.$d.' '.$h.':'.$mi.':'.$s);
                    $fArray['tsmax'] = $dmax->getTimestamp();
                }
            }
            date_default_timezone_set('UTC');
            $lastTS = new \DateTime();
            $lastTS = $lastTS->getTimestamp();
            $lastTSset = false;
            if (array_key_exists('lastdays', $f) and $f['lastdays'] !== '') {
                $lastTS = $lastTS - 24*3600*intval($f['lastdays']);
                $lastTSset = true;
            }
            if (array_key_exists('lasthours', $f) and $f['lasthours'] !== '') {
                $lastTS = $lastTS - 3600*intval($f['lasthours']);
                $lastTSset = true;
            }
            if (array_key_exists('lastmins', $f) and $f['lastmins'] !== '') {
                $lastTS = $lastTS - 60*intval($f['lastmins']);
                $lastTSset = true;
            }
            if ($lastTSset and (!array_key_exists('tsmin', $fArray) or $lastTS > $fArray['tsmin'])) {
                $fArray['tsmin'] = $lastTS;
            }
            foreach (['elevationmin', 'elevationmax', 'accuracymin', 'accuracymax', 'satellitesmin', 'satellitesmax', 'batterymin', 'batterymax', 'speedmax', 'speedmin', 'bearingmax', 'bearingmin', 'lastdays', 'lasthours', 'lastmins'] as $k) {
                if (array_key_exists($k, $f) and $f[$k] !== '') {
                    $fArray[$k] = intval($f[$k]);
                }
            }
        }

        return $fArray;
    }

    private function getSqlFilter($fArray) {
        $sql = '';
        if ($fArray !== null) {
            $cond = array();
            if (array_key_exists('tsmin', $fArray)) { array_push($cond, 'timestamp >= '.$this->db_quote_escape_string($fArray['tsmin'])); }
            if (array_key_exists('tsmax', $fArray)) { array_push($cond, 'timestamp <= '.$this->db_quote_escape_string($fArray['tsmax'])); }
            if (array_key_exists('elevationmax', $fArray)) { array_push($cond, 'altitude <= '.$this->db_quote_escape_string($fArray['elevationmax'])); }
            if (array_key_exists('elevationmin', $fArray)) { array_push($cond, 'altitude >= '.$this->db_quote_escape_string($fArray['elvationmin'])); }
            if (array_key_exists('accuracymax', $fArray)) { array_push($cond, 'accuracy <= '.$this->db_quote_escape_string($fArray['accuracymax'])); }
            if (array_key_exists('accuracymin', $fArray)) { array_push($cond, 'accuracy >= '.$this->db_quote_escape_string($fArray['accuracymin'])); }
            if (array_key_exists('satellitesmax', $fArray)) { array_push($cond, 'satellites <= '.$this->db_quote_escape_string($fArray['satellitesmax'])); }
            if (array_key_exists('satellitesmin', $fArray)) { array_push($cond, 'satellites >= '.$this->db_quote_escape_string($fArray['satellitesmin'])); }
            if (array_key_exists('batterymax', $fArray)) { array_push($cond, 'batterylevel <= '.$this->db_quote_escape_string($fArray['batterymax'])); }
            if (array_key_exists('batterymin', $fArray)) { array_push($cond, 'batterylevel >= '.$this->db_quote_escape_string($fArray['batterymin'])); }
            if (array_key_exists('speedmax', $fArray)) { array_push($cond, 'speed <= '.$this->db_quote_escape_string($fArray['speedmax'])); }
            if (array_key_exists('speedmin', $fArray)) { array_push($cond, 'speed >= '.$this->db_quote_escape_string($fArray['speedmin'])); }
            if (array_key_exists('bearingmax', $fArray)) { array_push($cond, 'bearing <= '.$this->db_quote_escape_string($fArray['bearingmax'])); }
            if (array_key_exists('bearingmin', $fArray)) { array_push($cond, 'bearing >= '.$this->db_quote_escape_string($fArray['bearingmin'])); }
            $sql = implode(' AND ', $cond);
        }
        return $sql;
    }

    private function countPointsPerSession($dbtoken, $filterSql) {
        $sqlget = '
            SELECT count(*) AS co
            FROM *PREFIX*phonetrack_devices AS dev, *PREFIX*phonetrack_points AS po
            WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' AND dev.id=po.deviceid ';
        if ($filterSql !== '') {
            $sqlget .= 'AND '.$filterSql;
        }
        $sqlget .= ' ;';
        $req = $this->dbconnection->prepare($sqlget);
        $req->execute();
        $nbPoints = 0;
        while ($row = $req->fetch()) {
            $nbPoints = intval($row['co']);
        }
        return $nbPoints;
    }

    private function countDevicesPerSession($dbtoken) {
        $sqlget = '
            SELECT count(*) AS co
            FROM *PREFIX*phonetrack_devices
            WHERE sessionid='.$this->db_quote_escape_string($dbtoken).';';
        $req = $this->dbconnection->prepare($sqlget);
        $req->execute();
        $nbDevices = 0;
        while ($row = $req->fetch()) {
            $nbDevices = intval($row['co']);
        }
        return $nbDevices;
    }

    private function generateGpxHeader($name, $nbdev=0) {
        date_default_timezone_set('UTC');
        $dt = new \DateTime();
        $date = $dt->format('Y-m-d\TH:i:s\Z');
        $gpxText = '<?xml version="1.0" encoding="UTF-8" standalone="no" ?>' . "\n";
        $gpxText .= '<gpx xmlns="http://www.topografix.com/GPX/1/1"' .
            ' xmlns:gpxx="http://www.garmin.com/xmlschemas/GpxExtensions/v3"' .
            ' xmlns:wptx1="http://www.garmin.com/xmlschemas/WaypointExtension/v1"' .
            ' xmlns:gpxtpx="http://www.garmin.com/xmlschemas/TrackPointExtension/v1"' .
            ' creator="PhoneTrack Nextcloud app ' .
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
        if ($nbdev > 0) {
            $gpxText .= ' <desc>' . $nbdev . ' device'.($nbdev > 1 ? 's' : '').'</desc>' . "\n";
        }
        $gpxText .= '</metadata>' . "\n";
        return $gpxText;
    }

    private function countPointsPerDevice($devid, $filterSql) {
        $sqlget = '
            SELECT count(*) AS co
            FROM *PREFIX*phonetrack_points
            WHERE deviceid='.$this->db_quote_escape_string($devid).' ';
        if ($filterSql !== '') {
            $sqlget .= 'AND '.$filterSql;
        }
        $sqlget .= ' ;';
        $req = $this->dbconnection->prepare($sqlget);
        $req->execute();
        $nbPoints = 0;
        while ($row = $req->fetch()) {
            $nbPoints = intval($row['co']);
        }
        return $nbPoints;
    }

    private function getAndWriteDevicePoints($devid, $devname, $filterSql, $fd, $nbPoints) {
        $done = 0;

        $gpxText  = '<trk>' . "\n" . ' <name>' . $devname . '</name>' . "\n";
        $gpxText .= ' <trkseg>' . "\n";
        fwrite($fd, $gpxText);

        $chunkSize = 10000;
        $pointIndex = 0;

        while ($pointIndex < $nbPoints) {
            $gpxText = '';
            $sqlget = '
                SELECT *
                FROM *PREFIX*phonetrack_points
                WHERE deviceid='.$this->db_quote_escape_string($devid).' ';
            if ($filterSql !== '') {
                $sqlget .= 'AND '.$filterSql;
            }
            $sqlget .= ' ORDER BY timestamp ASC LIMIT '.$chunkSize.' OFFSET '.$pointIndex.' ;';
            $req = $this->dbconnection->prepare($sqlget);
            $req->execute();
            while ($row = $req->fetch()) {
                $epoch = $row['timestamp'];
                $date = '';
                if (is_numeric($epoch)) {
                    $epoch = intval($epoch);
                    $dt = new \DateTime("@$epoch");
                    $date = $dt->format('Y-m-d\TH:i:s\Z');
                }
                $lat = $row['lat'];
                $lon = $row['lon'];
                $alt = $row['altitude'];
                $acc = $row['accuracy'];
                $bat = $row['batterylevel'];
                $ua  = $row['useragent'];
                $sat = $row['satellites'];
                $speed = $row['speed'];
                $bearing = $row['bearing'];

                $gpxExtension = '';
                $gpxText .= '  <trkpt lat="'.$lat.'" lon="'.$lon.'">' . "\n";
                $gpxText .= '   <time>' . $date . '</time>' . "\n";
                if (is_numeric($alt)) {
                    $gpxText .= '   <ele>' . sprintf('%.2f', floatval($alt)) . '</ele>' . "\n";
                }
                if (is_numeric($speed) && floatval($speed) >= 0) {
                    $gpxExtension .= '     <speed>' . sprintf('%.3f', floatval($speed)) . '</speed>' . "\n";
                }
                if (is_numeric($bearing) && floatval($bearing) >= 0 && floatval($bearing) <= 360) {
                    $gpxExtension .= '     <course>' . sprintf('%.3f', floatval($bearing)) . '</course>' . "\n";
                }
                if (is_numeric($sat) && intval($sat) >= 0) {
                    $gpxText .= '   <sat>' . intval($sat) . '</sat>' . "\n";
                }
                if (is_numeric($acc) && intval($acc) >= 0) {
                    $gpxExtension .= '     <accuracy>' . sprintf('%.2f', floatval($acc)) . '</accuracy>' . "\n";
                }
                if (is_numeric($bat) && intval($bat) >= 0) {
                    $gpxExtension .= '     <batterylevel>' . sprintf('%.2f', floatval($bat)) . '</batterylevel>' . "\n";
                }
                if ($ua !== '') {
                    $gpxExtension .= '     <useragent>' . $ua . '</useragent>' . "\n";
                }
                if ($gpxExtension !== '') {
                    $gpxText .= '   <extensions>'. "\n" . $gpxExtension;
                    $gpxText .= '   </extensions>' . "\n";
                }
                $gpxText .= '  </trkpt>' . "\n";
            }
            // write the chunk !
            fwrite($fd, $gpxText);
            $pointIndex = $pointIndex + $chunkSize;
        }
        $gpxText  = ' </trkseg>' . "\n";
        $gpxText .= '</trk>' . "\n";
        fwrite($fd, $gpxText);

        return $done;
    }

}
