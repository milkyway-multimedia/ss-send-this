<?php namespace Milkyway\SendThis\Listeners\Mandrill;
/**
 * Milkyway Multimedia
 * Tracking.php
 *
 * @package reggardocolaianni.com
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

class Tracking extends \Milkyway\SendThis\Listeners\Tracking {
    public function opened($messageId = '', $email = '', $params = [], $response = [], $log = null) {
        if (!SendThis::config()->api_tracking) return;
        $logs = null;

        if($messageId)
            $logs = SendThis_Log::get()->filter('MessageID', $messageId)->sort('Created', 'ASC');

        if(!$logs || !$logs->exists())
            return;

        $tracked = $this->getTrackerData($response);
        $client = isset($tracked['Client']) ? $tracked['Client'] : _t('SendThis_Log.UNKNOWN', 'Unknown');
        $date = date('Y-m-d H:i:s');

        foreach($logs as $log) {
            if(!$log->Track_Open) {
                $log->Tracker = $tracked;
                $log->Track_Client = $client;
                $log->Opened = $date;
                $log->write();
            }

            $log->Opens++;
            $log->write();
        }
    }

    public function clicked($messageId = '', $email = '', $params = [], $response = [], $link = null) {
        if (!SendThis::config()->api_tracking) return;

        if(isset($response['url']) && $messageId) {
            $logs = SendThis_Log::get()->filter('MessageID', $messageId)->sort('Created', 'DESC');

            if(!$logs || !$logs->exists())
                return;

            foreach($logs as $log) {
                $link = $log->Links()->filter('Original', $response['url'])->first();
                if($link) break;
            }

            if($link) {
                if (! Cookie::get('tracking-email-link-' . $link->Slug))
                {
                    $link->Visits ++;
                    Cookie::set('tracking-email-link-' . $link->Slug, true);
                }

                if (! $link->Clicked)
                {
                    $link->Clicked = date('Y-m-d H:i:s');
                }

                $link->Clicks ++;
                $link->write();
            }
        }
    }

    function getTrackerData($response)
    {
        $tracked = [];

        if(isset($response['user_agent']))
            $tracked['UserAgentString'] = $response['user_agent'];

        if (isset($response['user_agent_parsed']))
        {
            list(
                $Mobile,
                $OperatingSystemCompany,
                $OperatingSystemCompanyLink,
                $OperatingSystemBrand,
                $OperatingSystemIcon,
                $OperatingSystem,
                $OperatingSystemLink,
                $Type,
                $ClientCompany,
                $ClientCompanyLink,
                $ClientBrand,
                $ClientIcon,
                $Client,
                $ClientLink,
                $ClientVersion
            ) = $response['user_agent_parsed'];

            $tracked = array_merge($tracked, compact(
                    $Mobile,
                    $OperatingSystemCompany,
                    $OperatingSystemCompanyLink,
                    $OperatingSystemBrand,
                    $OperatingSystemIcon,
                    $OperatingSystem,
                    $OperatingSystemLink,
                    $Type,
                    $ClientCompany,
                    $ClientCompanyLink,
                    $ClientBrand,
                    $ClientIcon,
                    $Client,
                    $ClientLink,
                    $ClientVersion
                )
            );
        }

        if (isset($response['location']))
        {
            list(
                $CountryCode,
                $Country,
                $Region,
                $City,
                $PostalCode,
                $Timezone,
                $Latitude,
                $Longitude
                ) = $response['location'];

            $tracked = array_merge($tracked, compact(
                    $CountryCode,
                    $Country,
                    $Region,
                    $City,
                    $PostalCode,
                    $Timezone,
                    $Latitude,
                    $Longitude
                )
            );
        }

        return $tracked;
    }
} 