<?php
/**
 * Nextcloud - PhoneTrack
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 */

namespace OCA\PhoneTrack\Cron;

use \OCA\PhoneTrack\AppInfo\Application;

#class AutoExport extends \OC\BackgroundJob\TimedJob {
class AutoExport extends \OC\BackgroundJob\Job {

    #public function __construct() {
    #    // Run each day
    #    $this->setInterval(24 * 60 * 60);
    #}

    protected function run($argument) {
        (new Application())->getContainer()->query('PageController')->cronAutoExport();
    }

}
