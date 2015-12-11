<?php

/**
 * Milkyway Multimedia
 * SendThis_Link.php
 *
 * @package milkyway-multimedia/ss-send-this
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

use Milkyway\SS\SendThis\Controllers\Tracker;

class SendThis_Link extends DataObject
{
    private static $db = [
        'Original' => 'Varchar(255)',
        'Visits'   => 'Int',
        'Clicks'   => 'Int',
        'Clicked'  => 'Datetime',
    ];

    private static $extensions = [
        'Milkyway\\SS\\Behaviours\\Extensions\\Sluggable',
    ];

    private static $has_one = [
        'Log' => 'SendThis_Log',
    ];

    private static $summary_fields = [
        'Original',
        'Visits',
        'Clicks',
    ];

    public function getURL()
    {
        if (!$this->Slug) {
            $this->write();
        }

        return Director::absoluteURL(Controller::join_links(Tracker::config()->slug, 'links', urlencode($this->Slug)));
    }

    public function Link()
    {
        return $this->Original;
    }

    public function forTemplate()
    {
        return $this->URL;
    }

    public function canView($member = null)
    {
        $method = __FUNCTION__;

        $this->beforeExtending(__FUNCTION__, function ($member) use ($method) {
            if (Permission::check('CAN_VIEW_SEND_LOGS', 'any', $member)) {
                return true;
            }
        });

        return parent::canView($member);
    }
} 