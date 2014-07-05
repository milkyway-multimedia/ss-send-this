<?php
/**
 * Milkyway Multimedia
 * SendThis_Link.php
 *
 * @package milkyway-multimedia/silverstripe-send-this
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

class SendThis_Link extends DataObject {
    private static $db = array(
        'Original'  => 'Varchar(255)',
        'Visits'    => 'Int',
        'Clicks'    => 'Int',
    );

    private static $extensions = array(
        'Sluggable',
    );

    private static $has_one = array(
        'Log'        => 'SendThis_Log',
    );

    private static $summary_fields = array(
        'Original',
        'Visits',
        'Clicks',
    );

    public function getURL() {
        if(!$this->Sluggable) $this->write();
        return Director::absoluteURL(Controller::join_links(SendThis_Controller::config()->slug, 'links', urlencode($this->Slug)));
    }

    public function forTemplate() {
        return $this->URL;
    }

    function track() {
        if(!Cookie::get('tracking-email-link-' . $this->Slug)) {
            $this->Visits++;
            Cookie::set('tracking-email-link-' . $this->Slug, true);
        }

        $this->Clicks++;
        $this->write();

        return $this->Original;
    }

    function canView($member = null) {
        return Permission::check('CAN_VIEW_SEND_LOGS');
    }
} 