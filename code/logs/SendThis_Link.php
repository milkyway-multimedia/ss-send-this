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
        'Clicked'        => 'Datetime',
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

    public function Link() {
        return $this->Original;
    }

    public function forTemplate() {
        return $this->URL;
    }

    function canView($member = null) {
        return Permission::check('CAN_VIEW_SEND_LOGS');
    }

    /**
     * Append array as query string to url, making sure the $url takes preference
     *
     * @param string $url
     * @param array  $data
     *
     * @return String
     */
    public static function add_link_data($url, $data = array())
    {
        if (! count($data))
        {
            return $url;
        }

        // Make sure data in url takes preference over data from email log
        if (strpos($url, '?') !== false)
        {
            list($newURL, $query) = explode('?', $url, 2);

            $url = $newURL;

            if ($query)
            {
                @parse_str($url, $current);

                if ($current && count($current))
                {
                    $data = array_merge($data, $current);
                }
            }
        }

        if (count($data))
        {
            $linkData = array();

            foreach ($data as $name => $value)
            {
                $linkData[$name] = urlencode($value);
            }

            $url = Controller::join_links($url, '?' . http_build_query($linkData));
        }

        return $url;
    }
} 