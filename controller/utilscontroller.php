<?php
/**
 * ownCloud/Nextcloud - phonetrack
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
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

class UtilsController extends Controller {


    private $userId;
    private $config;
    private $dbconnection;
    private $dbtype;
    private $appPath;

    public function __construct($AppName, IRequest $request, $UserId,
        $userfolder, $config, IAppManager $appManager){
        parent::__construct($AppName, $request);
        // just to keep Owncloud compatibility
        // the first case : Nextcloud
        // else : Owncloud
        if (method_exists($appManager, 'getAppPath')){
            $this->appPath = $appManager->getAppPath('phonetrack');
        }
        $this->userId = $UserId;
        $this->dbtype = $config->getSystemValue('dbtype');
        if ($this->dbtype === 'pgsql'){
            $this->dbdblquotes = '"';
        }
        else{
            $this->dbdblquotes = '';
        }
        // IConfig object
        $this->config = $config;
        $this->dbconnection = \OC::$server->getDatabaseConnection();
    }

    /*
     * quote and choose string escape function depending on database used
     */
    private function db_quote_escape_string($str){
        return $this->dbconnection->quote($str);
    }

    /**
     * set global point quota
     */
    public function setPointQuota($quota) {
        $this->config->setAppValue('phonetrack', 'pointQuota', $quota);
        $response = new DataResponse(
            [
                'done'=>'1'
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
     * Add one tile server to the DB for current user
     * @NoAdminRequired
     */
    public function addTileServer($servername, $serverurl, $type,
                    $layers, $version, $tformat, $opacity, $transparent,
                    $minzoom, $maxzoom, $attribution) {
        // first we check it does not already exist
        $sqlts = '
            SELECT servername
            FROM *PREFIX*phonetrack_tileserver
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                  AND servername='.$this->db_quote_escape_string($servername).'
                  AND type='.$this->db_quote_escape_string($type).' ;';
        $req = $this->dbconnection->prepare($sqlts);
        $req->execute();
        $ts = null;
        while ($row = $req->fetch()){
            $ts = $row['servername'];
            break;
        }
        $req->closeCursor();

        // then if not, we insert it
        if ($ts === null){
            $sql = '
                INSERT INTO *PREFIX*phonetrack_tileserver
                ('.$this->dbdblquotes.'user'.$this->dbdblquotes.', type, servername, url, layers, version, format, opacity, transparent, minzoom, maxzoom, attribution)
                VALUES ('.
                    $this->db_quote_escape_string($this->userId).','.
                    $this->db_quote_escape_string($type).','.
                    $this->db_quote_escape_string($servername).','.
                    $this->db_quote_escape_string($serverurl).','.
                    $this->db_quote_escape_string($layers).','.
                    $this->db_quote_escape_string($version).','.
                    $this->db_quote_escape_string($tformat).','.
                    $this->db_quote_escape_string($opacity).','.
                    $this->db_quote_escape_string($transparent).','.
                    $this->db_quote_escape_string($minzoom).','.
                    $this->db_quote_escape_string($maxzoom).','.
                    $this->db_quote_escape_string($attribution).'
                ) ;';
            $req = $this->dbconnection->prepare($sql);
            $req->execute();
            $req->closeCursor();
            $ok = 1;
        }
        else{
            $ok = 0;
        }

        $response = new DataResponse(
            [
                'done'=>$ok
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
     * Delete one tile server entry from DB for current user
     * @NoAdminRequired
     */
    public function deleteTileServer($servername, $type) {
        $sqldel = '
            DELETE FROM *PREFIX*phonetrack_tileserver
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                  AND servername='.$this->db_quote_escape_string($servername).'
                  AND type='.$this->db_quote_escape_string($type).' ;';
        $req = $this->dbconnection->prepare($sqldel);
        $req->execute();
        $req->closeCursor();

        $response = new DataResponse(
            [
                'done'=>1
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
     * Delete user options
     * @NoAdminRequired
     */
    public function deleteOptionsValues() {
        $sqldel = '
            DELETE FROM *PREFIX*phonetrack_options
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ;';
        $req = $this->dbconnection->prepare($sqldel);
        $req->execute();
        $req->closeCursor();

        $response = new DataResponse(
            [
                'done'=>1
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
     * Save options values to the DB for current user
     * @NoAdminRequired
     */
    public function saveOptionsValues($optionsValues) {
        // first we check if user already has options values in DB
        $sqlts = '
            SELECT jsonvalues
            FROM *PREFIX*phonetrack_options
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ;';
        $req = $this->dbconnection->prepare($sqlts);
        $req->execute();
        $check = null;
        while ($row = $req->fetch()){
            $check = $row['jsonvalues'];
            break;
        }
        $req->closeCursor();

        // if nothing is there, we insert
        if ($check === null){
            $sql = '
                INSERT INTO *PREFIX*phonetrack_options
                ('.$this->dbdblquotes.'user'.$this->dbdblquotes.', jsonvalues)
                VALUES ('.
                    $this->db_quote_escape_string($this->userId).','.
                    $this->db_quote_escape_string($optionsValues).') ;';
            $req = $this->dbconnection->prepare($sql);
            $req->execute();
            $req->closeCursor();
        }
        // else we update the values
        else{
            $sqlupd = '
                UPDATE *PREFIX*phonetrack_options
                SET jsonvalues='.$this->db_quote_escape_string($optionsValues).'
                WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ;';
            $req = $this->dbconnection->prepare($sqlupd);
            $req->execute();
            $req->closeCursor();
        }

        $response = new DataResponse(
            [
                'done'=>true
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
     * get options values to the DB for current user
     * @NoAdminRequired
     */
    public function getOptionsValues() {
        $sqlov = '
            SELECT jsonvalues
            FROM *PREFIX*phonetrack_options
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ;';
        $req = $this->dbconnection->prepare($sqlov);
        $req->execute();
        $ov = '{}';
        while ($row = $req->fetch()){
            $ov = $row['jsonvalues'];
        }
        $req->closeCursor();

        $response = new DataResponse(
            [
                'values'=>$ov
            ]
        );
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

}
