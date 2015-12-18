<?php

/**
 * Milkyway Multimedia
 * SendThis_Bounce.php
 *
 * @package milkyway-multimedia/ss-send-this
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

class SendThis_Bounce extends DataObject
{
    private static $db = [
        'Email'   => 'Text',
        'Message' => 'Text',
    ];

    private static $has_one = [
        'Log' => 'SendThis_Log',
    ];

    private static $singular_name = 'Bounced Email';

    public function getTitle()
    {
        return $this->Email;
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
