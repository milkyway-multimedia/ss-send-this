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
    public function up($messageId, $email, $params, $response, $log, &$headers) {
        if (!SendThis::config()->logging) return;

        if($log) {
            $log->To      = $params['to'];
            $log->From    = $params['from'];
            $log->Subject = $params['subject'];

            $this->Cc      = isset($headers['Cc']) ? $headers['Cc'] : null;
            $this->Bcc     = isset($headers['Bcc']) ? $headers['Bcc'] : null;
            $this->ReplyTo = isset($headers['Reply-To']) ? $headers['Reply-To'] : null;

            $attachments = array();
            $count       = 1;

            if (is_array($params['attachedFiles']) && count($params['attachedFiles']))
            {
                foreach ($params['attachedFiles'] as $attached)
                {
                    $file = '';
                    if (isset($attached['filename']))
                    {
                        $file .= $attached['filename'];
                    }
                    if (isset($attached['mimetype']))
                    {
                        $file .= ' <' . $attached['mimetype'] . '>';
                    }

                    if (! trim($file))
                    {
                        $attachments[] = $count . '. File has no info';
                    } else
                    {
                        $attachments[] = $count . '. ' . $file;
                    }

                    $count ++;
                }
            }

            if (count($attachments))
            {
                $log->Attachments = count($attachments) . ' files attached: ' . "\n" . implode("\n", $attachments);
            }

            if ($member = Member::currentUser())
            {
                $log->SentByID = $member->ID;
            }

            $log->write();
        }
    }

    public function sent($messageId = '', $email = '', $params = [], $response = [], $log = null) {
        if($log) {
            $log->Sent = date('Y-m-d H:i:s');

            $params['MessageID'] = $messageId;
            $params['success'] = true;
            $this->updateLog($log, $params);
        }
    }

    public function failed($messageId = '', $email = '', $params = [], $response = [], $log = null) {
        if($log) {
            $params['MessageID'] = $messageId;
            $params['success'] = false;
            $this->updateLog($log, $params);
        }
    }

    public function bounced($messageId = '', $email = '', $params = [], $response = []) {
        $base = '@' . trim(str_replace(['http://', 'https://', 'www.'], '', self::protocolAndHost()), ' /');
        $blacklistAfter = SendThis::config()->blacklist_after_bounced ? SendThis::config()->blacklist_after_bounced : 2;
        $permanent = isset($params['permanent']);

        $bounce = null;

        if(!isset($params['message'])) {
            $params['message'] = 'Bounced.';
        }

        $params['message'] .= "\n\n" . print_r($response, true);

        if(!(substr($email, -strlen($base)) === $base)) {
            $bounce = SendThis_Bounce::create();
            $bounce->Email = $email;
            $bounce->Message = $params['message'];

            if ((SendThis_Bounce::get()->filter('Email', $email)->count() + 1) >= $blacklistAfter || $permanent) {
                $message = "\n\n" . print_r($response, true);
                $message = $permanent ? 'Permanent Bounce' . $message : 'Bounced too many times' . $message;
                \SendThis::fire('spam', $messageId, $email, $params + ['message' => $message], $response);
                $bounce->write();
                return;
            }
        }

        if($messageId)
            $this->updateLogsForMessageId($messageId, $params, $bounce);

        if($bounce)
            $bounce->write();
    }

    public function spam($messageId = '', $email = '', $params = [], $response = []) {
        if(!isset($params['message']))
            $params['message'] = 'The email address marked this email as spam';

        $this->updateBadEmail($messageId, $email, $params);
    }

    public function rejected($messageId = '', $email = '', $params = [], $response = []) {
        if(!isset($params['message']))
            $params['message'] = 'The end point has rejected this email for some reason';

        $this->updateBadEmail($messageId, $email, $params);
    }

    public function blacklisted($messageId = '', $email = '', $params = [], $response = []) {
        if(!isset($params['message']))
            $params['message'] = 'The user of this email has requested to be blacklisted from this application';

        $this->updateBadEmail($messageId, $email, $params);
    }

    protected function updateBadEmail($messageId = '', $email = '', $params = array()) {
        if($email) {
            $message = isset($params['message']) ? $params['message'] : 'Unknown';
            $this->blacklistEmail($email, $message);
        }

        if($messageId)
            $this->updateLogsForMessageId($messageId, $params);
    }

    protected function blacklistEmail($email, $message = '') {
        \SendThis_Blacklist::log_invalid($email, $message);
    }

    protected function updateLogsForMessageId($messageId, $params, $bounce = null) {
        $logs = SendThis_Log::get()->filter('MessageID', $messageId)->sort('Created', 'ASC');

        if($logs->exists()) {
            foreach($logs as $log)
                $this->updateLog($log, $params, $bounce);
        }
    }

    protected function updateLog($log, $params = array(), $bounce = null) {
        $log->Success = isset($params['success']);

        if(isset($params['MessageID']))
            $log->MessageID = $params['MessageID'];

        if(isset($params['message']))
            $log->Notes = $params['message'];

        if($bounce)
            $bounce->LogID = $log->ID;

        $log->write();
    }
} 