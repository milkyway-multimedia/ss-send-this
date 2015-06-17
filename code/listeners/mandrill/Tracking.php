<?php namespace Milkyway\SS\SendThis\Listeners\Mandrill;
use Milkyway\SS\SendThis\Events\Event;
use Milkyway\SS\SendThis\Mailer;
use \Milkyway\SS\SendThis\Listeners\Tracking as Tracking_Default;
use SendThis_Log;
use SendThis_Link;
use Cookie;

/**
 * Milkyway Multimedia
 * Tracking.php
 *
 * @package reggardocolaianni.com
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

class Tracking extends Tracking_Default {
    public function opened(Event $e, $messageId = '', $email = '', $params = [], $response = [], $log = null) {
        if (!$this->allowed($e->mailer()) || !$messageId) return;

        $logs = SendThis_Log::get()->filter('MessageID', $messageId)->sort('Created', 'ASC');

        if(!$logs->exists())
            return;

        $tracked = $this->getTrackerData($response);
        $client = isset($tracked['Client']) ? $tracked['Client'] : _t('SendThis_Log.UNKNOWN', 'Unknown');
        $date = isset($response['ts']) ? date('Y-m-d H:i:s', $response['ts']) : date('Y-m-d H:i:s');

        foreach($logs as $log) {
            if(!$log->Opened) {
                $log->Tracker = $tracked;
                $log->Track_Client = $client;
                $log->Opened = $date;
                $log->write();
            }

            $log->Opens++;
            $log->write();
        }
    }

    public function clicked(Event $e, $messageId = '', $email = '', $params = [], $response = [], $link = null) {
        if (!$this->allowed($e->mailer()) || !$messageId || !isset($response['url'])) return;

        $logs = SendThis_Log::get()->filter('MessageID', $messageId)->sort('Created', 'ASC');

        if(!$logs->exists())
            return;

        foreach($logs as $log) {
            $link = $log->Links()->filter('Original', $response['url'])->first();
            if($link) break;
        }

        if (!$link) {
            $link           = SendThis_Link::create();
            $link->Original = $response['url'];
            $link->LogID    = $logs->first()->ID;
        }

        if($link) {
            if (! Cookie::get('tracking-email-link-' . $link->Slug)) {
                $link->Visits++;
                Cookie::set('tracking-email-link-' . $link->Slug, true);
            }

            if (!$link->Clicked) {
                $link->Clicked = date('Y-m-d H:i:s');
            }

            $link->Clicks ++;
            $link->write();
        }
    }

    function getTrackerData($response)
    {
        $tracked = [];

        if(isset($response['user_agent']))
            $tracked['UserAgentString'] = $response['user_agent'];

        if (isset($response['user_agent_parsed']))
        {
            $tracked = array_merge($tracked, [
                'Type' => $this->checkIfKeyExistsAndReturnValue('type', $response['user_agent_parsed']),
                'Mobile' => $this->checkIfKeyExistsAndReturnValue('mobile', $response['user_agent_parsed']),

                'ClientBrand' => $this->checkIfKeyExistsAndReturnValue('ua_family', $response['user_agent_parsed']),
                'Client' => $this->checkIfKeyExistsAndReturnValue('ua_name', $response['user_agent_parsed']),
                'ClientVersion' => $this->checkIfKeyExistsAndReturnValue('ua_version', $response['user_agent_parsed']),
                'ClientLink' => $this->checkIfKeyExistsAndReturnValue('ua_url', $response['user_agent_parsed']),
                'ClientCompany' => $this->checkIfKeyExistsAndReturnValue('ua_company', $response['user_agent_parsed']),
                'ClientCompanyLink' => $this->checkIfKeyExistsAndReturnValue('ua_company_url', $response['user_agent_parsed']),
                'Icon' => $this->checkIfKeyExistsAndReturnValue('ua_icon', $response['user_agent_parsed']),

                'OperatingSystemBrand' => $this->checkIfKeyExistsAndReturnValue('os_family', $response['user_agent_parsed']),
                'OperatingSystem' => $this->checkIfKeyExistsAndReturnValue('os_name', $response['user_agent_parsed']),
                'OperatingSystemLink' => $this->checkIfKeyExistsAndReturnValue('os_url', $response['user_agent_parsed']),
                'OperatingSystemCompany' => $this->checkIfKeyExistsAndReturnValue('os_company', $response['user_agent_parsed']),
                'OperatingSystemCompanyLink' => $this->checkIfKeyExistsAndReturnValue('os_company_url', $response['user_agent_parsed']),
                'OperatingSystemIcon' => $this->checkIfKeyExistsAndReturnValue('os_icon', $response['user_agent_parsed']),
            ]);
        }

        if (isset($response['location']))
        {
            $tracked = array_merge($tracked, [
                'CountryCode' => $this->checkIfKeyExistsAndReturnValue('country_short', $response['location']),
                'Country' => $this->checkIfKeyExistsAndReturnValue('country', $response['location']),
                'Region' => $this->checkIfKeyExistsAndReturnValue('region', $response['location']),
                'City' => $this->checkIfKeyExistsAndReturnValue('city', $response['location']),
                'PostalCode' => $this->checkIfKeyExistsAndReturnValue('postal_code', $response['location']),

                'Latitude' => $this->checkIfKeyExistsAndReturnValue('latitude', $response['location']),
                'Longitude' => $this->checkIfKeyExistsAndReturnValue('longitude', $response['location']),

                'Timezone' => $this->checkIfKeyExistsAndReturnValue('timezone', $response['location']),
            ]);
        }

        if(isset($response['ip']))
            $tracked['IP'] = $response['ip'];

        return $tracked;
    }

	protected function allowed(\Object $mailer) {
		return $mailer->config()->api_tracking && ($mailer instanceof Mailer);
	}

    protected function checkIfKeyExistsAndReturnValue($key, $data, $default = null) {
        return isset($data[$key]) ? $data[$key] : $default;
    }
} 