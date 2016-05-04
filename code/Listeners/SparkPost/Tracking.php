<?php namespace Milkyway\SS\SendThis\Listeners\SparkPost;

/**
 * Milkyway Multimedia
 * SparkPost/Tracking.php
 *
 * @package milkyway-multimedia/ss-send-this
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

use Milkyway\SS\SendThis\Contracts\Event;
use Milkyway\SS\SendThis\Mailer;
use Milkyway\SS\SendThis\Listeners\Tracking as DefaultTracking;
use SendThis_Log as Log;
use SendThis_Link as Link;
use Cookie;
use SS_Datetime;
use Object;

class Tracking extends DefaultTracking
{
    protected $transportClass = 'Milkyway\SS\SendThis\Transports\SparkPost';

    public function opened(Event $e, $messageId = '', $email = '', $params = [], $response = [], $log = null)
    {
        if (!$this->allowed($e->mailer()) || !$messageId) {
            return;
        }

        $logs = Log::get()->filter('MessageID', $messageId)->sort('Created', 'ASC');

        if (!$logs->exists()) {
            return;
        }

        $date = isset($response['timestamp']) ? date('Y-m-d H:i:s',
            $response['timestamp']) : SS_Datetime::now()->Rfc2822();

        foreach ($logs as $log) {
            if (trim($log->Transport, '\\') != $this->transportClass) {
                continue;
            }

            if (!$log->Opened) {
                $log->Tracker = $this->getTrackerData($params);
                $log->Track_Client = $this->getClientFromTracker($log->Tracker);
                $log->Opened = $date;
                $log->write();
            }

            $log->Opens++;
            $log->write();
        }
    }

    public function clicked(Event $e, $messageId = '', $email = '', $params = [], $response = [], $link = null)
    {
        if (!$this->allowed($e->mailer()) || !$messageId || !isset($response['target_link_url'])) {
            return;
        }

        $logs = Log::get()->filter('MessageID', $messageId)->sort('Created', 'ASC');

        if (!$logs->exists()) {
            return;
        }

        $noOfLogs = 0;

        foreach ($logs as $log) {
            if (trim($log->Transport, '\\') != $this->transportClass) {
                continue;
            }

            $noOfLogs++;

            $link = $log->Links()->filter('Original', $response['target_link_url'])->first();

            if ($link) {
                break;
            }
        }

        if (!$noOfLogs) {
            return;
        }

        if (!$link) {
            $link = Link::create();
            $link->Original = $response['target_link_url'];
            $link->LogID = $logs->first()->ID;
        }

        if ($link) {
            if (!Cookie::get('tracking-email-link-' . $link->Slug)) {
                $link->Visits++;
                Cookie::set('tracking-email-link-' . $link->Slug, true);
            }

            if (!$link->Clicked) {
                $link->Clicked = SS_Datetime::now()->Rfc2822();
            }

            $link->Clicks++;
            $link->write();
        }
    }

    public function getTrackerData($response)
    {
        if (isset($response['user_agent'])) {
            $tracked = parent::getTrackerData([
                'UserAgentString' => $response['user_agent'],
            ]);
        } else {
            $tracked = [];
        }

        $tracked = array_merge($tracked, [
            'UserAgentString' => $this->checkIfKeyExistsAndReturnValue('user_agent', $response),
            'Language'        => $this->checkIfKeyExistsAndReturnValue('accept_language', $response),
            'Delivery Method' => $this->checkIfKeyExistsAndReturnValue('delv_method', $response),
            'Customer ID'     => $this->checkIfKeyExistsAndReturnValue('customer_id', $response),
            'IP'              => $this->checkIfKeyExistsAndReturnValue('ip_address', $response),
            'IP Pool'         => $this->checkIfKeyExistsAndReturnValue('ip_pool', $response),
        ]);

        if(!empty($response['rcpt_tags'])) {
            $tracked['Tags'] = implode(', ', $response['rcpt_tags']);
        }

        if(!empty($response['geo_ip'])) {
            $tracked = array_merge($tracked, [
                'Country' => $this->checkIfKeyExistsAndReturnValue('country', $response['geo_ip']),
                'Region' => $this->checkIfKeyExistsAndReturnValue('region', $response['geo_ip']),
                'City' => $this->checkIfKeyExistsAndReturnValue('city', $response['geo_ip']),
                'Latitude' => $this->checkIfKeyExistsAndReturnValue('latitude', $response['geo_ip']),
                'Longitude' => $this->checkIfKeyExistsAndReturnValue('longitude', $response['geo_ip']),
            ]);
        }

        return array_filter($tracked);
    }

    protected function allowed(Object $mailer)
    {
        return $mailer->config()->api_tracking && ($mailer instanceof Mailer);
    }

    protected function checkIfKeyExistsAndReturnValue($key, $data, $default = null)
    {
        return isset($data[$key]) ? $data[$key] : $default;
    }
}
