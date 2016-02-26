<?php namespace Milkyway\SS\SendThis\Listeners\Mailgun;

/**
 * Milkyway Multimedia
 * Mailgun/Tracking.php
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
    protected $transportClass = 'Milkyway\SS\SendThis\Transports\Mailgun';

    public function opened(Event $e, $messageId = '', $email = '', $params = [], $response = [], $log = null)
    {
        if (!$this->allowed($e->mailer()) || !$messageId) {
            return;
        }

        $logs = Log::get()->filter('MessageID', $messageId)->sort('Created', 'ASC');

        if (!$logs->exists()) {
            return;
        }

        $tracked = $this->getTrackerData($response);
        $client = isset($tracked['Client']) ? $tracked['Client'] : _t('SendThis_Log.UNKNOWN', 'Unknown');
        $date = isset($response['timestamp']) ? date('Y-m-d H:i:s',
            $response['timestamp']) : SS_Datetime::now()->Rfc2822();

        foreach ($logs as $log) {
            if (trim($log->Transport, '\\') != $this->transportClass) {
                continue;
            }

            if (!$log->Opened) {
                $log->Tracker = $tracked;
                $log->Track_Client = $client;
                $log->Opened = $date;
                $log->write();
            }

            $log->Opens++;
            $log->write();
        }
    }

    public function clicked(Event $e, $messageId = '', $email = '', $params = [], $response = [], $link = null)
    {
        if (!$this->allowed($e->mailer()) || !$messageId || !isset($response['url'])) {
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

            $link = $log->Links()->filter('Original', $response['url'])->first();

            if ($link) {
                break;
            }
        }

        if (!$noOfLogs) {
            return;
        }

        if (!$link) {
            $link = Link::create();
            $link->Original = $response['url'];
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
        return array_filter([
            'UserAgentString' => $this->checkIfKeyExistsAndReturnValue('user-agent', $response),
            'Type'            => $this->checkIfKeyExistsAndReturnValue('client-type', $response),
            'Device'          => $this->checkIfKeyExistsAndReturnValue('device-type', $response),
            'Client'          => $this->checkIfKeyExistsAndReturnValue('client-name', $response),
            'OperatingSystem' => $this->checkIfKeyExistsAndReturnValue('client-os', $response),
            'Country'         => $this->checkIfKeyExistsAndReturnValue('country', $response),
            'Region'          => $this->checkIfKeyExistsAndReturnValue('region', $response),
            'City'            => $this->checkIfKeyExistsAndReturnValue('city', $response),
            'IP'            => $this->checkIfKeyExistsAndReturnValue('ip', $response),
        ]);
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
