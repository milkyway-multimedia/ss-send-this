<?php /**
 * Milkyway Multimedia
 * SendThis_Bounce.php
 *
 * @package milkyway-multimedia/silverstripe-send-this
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */
class SendThis_Bounce extends DataObject {
    private static $db = array(
        'Email'         => 'Text',
        'Message'       => 'Text',
    );

    private static $has_one = array(
        'Log'           => 'SendThis_Log',
    );

    private static $singular_name = 'Bounced Email';

    public function getTitle() {
        return $this->Email;
    }

    function canView($member = null) {
        return Permission::check('CAN_VIEW_SEND_LOGS');
    }
} 