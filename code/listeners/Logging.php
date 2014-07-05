<?php namespace Milkyway\SendThis\Listeners;
/**
 * Milkyway Multimedia
 * Logging.php
 *
 * Updates the logs of the
 *
 * @package reggardocolaianni.com
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */
class Logging {
    public function bounced($email = '', $messageId = '', $params = array(), $response = array()) {
        $base = '@' . trim(str_replace(array('http://', 'https://', 'www.'), '', self::protocolAndHost()), ' /');
        $blacklistAfter = SendThis::config()->blacklist_after_bounced ? SendThis::config()->blacklist_after_bounced : 2;
        $permanent = isset($params['blacklist']);

        $bounce = null;

        if(!(substr($email, -strlen($base)) === $base)) {
            $bounce = SendThis_Bounce::create();
            $bounce->Email = $email;
            $bounce->Message = print_r($info, true);

            if (SendThis_Bounce::get()->filter('Email', $email)->count() >= $blacklistAfter || $permanent) {
                $message = "\n\n" . print_r($info, true);
                \SendThis_Blacklist::log_invalid($email, $permanent ? 'Permanent Bounce' . $message : 'Bounced too many times' . $message);
            }
        }

        if($messageId) {
            $logs = SendThis_Log::get()->filter('MessageID', $messageId);

            if($logs->exists()) {
                foreach($logs as $log) {
                    $this->updateLog($log, false, $params, $bounce);
                }
            }
        }

        if($bounce)
            $bounce->write();
    }

    public function spam($email, $messageId, $info, $params) {
        $message = sprintf('%s has logged a complaint, and has been blocked from receiving emails from this domain%s',
            $email,
            isset($params['complaintFeedbackType']) ? '. Reason: ' . $params['complaintFeedbackType'] : ''
        );

        \SendThis_Blacklist::log_invalid($email, $message);

        if($messageId) {
            $logs = SendThis_Log::get()->filter('MessageID', $messageId);

            if($logs->exists()) {
                foreach($logs as $log)
                    $this->updateLog($log, false, ['message' => $message]);
            }
        }
    }

    protected function updateLog($log, $success = true, $params = array(), $bounce = null) {
        $log->Success = $success;

        if(isset($params['message']))
            $log->Notes = $params['message'];
        else {
            $message = array();

            if(isset($params['details'])) {
                if(isset($params['details']['status']))
                    $message[] = 'Status: ' . $params['details']['status'];
                if(isset($params['details']['action']))
                    $message[] = 'Action: ' . $params['details']['action'];
                if(isset($params['details']['diagnosticCode']))
                    $message[] = 'Diagnostic Code: ' . $params['details']['diagnosticCode'];
            }

            if(count($message))
                $log->Notes = $log->Notes . "\n\nBounce Details:\n" . implode("\n", $message);
            elseif(!$log->Notes)
                $log->Notes = 'Bounced';
        }

        if($bounce)
            $bounce->LogID = $log->ID;

        $log->write();
    }
} 