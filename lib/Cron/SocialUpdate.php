<?php
namespace OCA\Contacts\Cron;

use \OCA\Contacts\AppInfo\Application;
use \OCA\Contacts\Controller\SocialApiController;

class SocialUpdate extends \OC\BackgroundJob\TimedJob {

    /** @var SocialApiController */
    private $social;

    public function __construct(SocialApiController $social) {
        $this->social = $social;

        // Run once a week
        // parent::setInterval(7 * 24 * 60 * 60);

        // FIXME: for testing -- Run every x seconds
        parent::setInterval(10);
    }

    protected function run($arguments) {
        $this->social->cronUpdate();
    }

}

