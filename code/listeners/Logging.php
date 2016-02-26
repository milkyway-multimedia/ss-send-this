<?php namespace Milkyway\SS\SendThis\Listeners;

/**
 * Milkyway Multimedia
 * Logging.php
 *
 * @package milkyway-multimedia/ss-send-this
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

use Milkyway\SS\SendThis\Contracts\Event;
use Member;
use SS_Datetime;
use Object;
use SendThis_Bounce as Bounce;
use SendThis_Blacklist as Blacklist;
use SendThis_Log as Log;

class Logging
{
    public function up(Event $e, $messageId, $email, $params, $response, $log, $headers)
    {
        if (!$this->allowed($e->mailer())) {
            return;
        }

        if ($log) {
            $log->To = $params['to'];
            $log->From = $params['from'];
            $log->Subject = $params['subject'];

            $headers = (array)$headers;

            $this->Cc = isset($headers['Cc']) ? $headers['Cc'] : null;
            $this->Bcc = isset($headers['Bcc']) ? $headers['Bcc'] : null;
            $this->ReplyTo = isset($headers['Reply-To']) ? $headers['Reply-To'] : null;

            $attachments = [];
            $count = 1;

            if (is_array($params['attachedFiles']) && !empty($params['attachedFiles'])) {
                foreach ($params['attachedFiles'] as $attached) {
                    $file = '';
                    if (isset($attached['filename'])) {
                        $file .= $attached['filename'];
                    }
                    if (isset($attached['mimetype'])) {
                        $file .= ' <' . $attached['mimetype'] . '>';
                    }

                    if (!trim($file)) {
                        $attachments[] = $count . '. File has no info';
                    } else {
                        $attachments[] = $count . '. ' . $file;
                    }

                    $count++;
                }
            }

            if (!empty($attachments)) {
                $log->Attachments = count($attachments) . ' files attached: ' . "\n" . implode("\n", $attachments);
            }

            if ($member = Member::currentUser()) {
                $log->SentByID = $member->ID;
            }

            $log->write();
        }
    }

    public function sent(Event $e, $messageId = '', $email = '', $params = [], $response = [], $log = null)
    {
        if (!$log && $messageId) {
            $params['MessageID'] = $messageId;
            $params['success'] = true;
            $params['Sent'] = SS_Datetime::now()->Rfc2822();

            $this->updateLogsForMessageId($messageId, $params);
        }
        else if ($log) {
            $log->Sent = SS_Datetime::now()->Rfc2822();

            $params['MessageID'] = $messageId;
            $params['success'] = true;
            $this->updateLog($log, $params);
        }
    }

    public function failed(Event $e, $messageId = '', $email = '', $params = [], $response = [], $log = null)
    {
        if (!$log && $messageId) {
            $params['MessageID'] = $messageId;
            $params['success'] = false;

            $this->updateLogsForMessageId($messageId, $params);
        }
        else if ($log) {
            $params['MessageID'] = $messageId;
            $params['success'] = false;
            $this->updateLog($log, $params);
        }
    }

    public function delivered(Event $e, $messageId = '', $email = '', $params = [], $response = [], $log = null)
    {
        if (!$log && $messageId) {
            $params['MessageID'] = $messageId;
            $params['success'] = true;
            $params['Delivered'] = SS_Datetime::now()->Rfc2822();

            $this->updateLogsForMessageId($messageId, $params);
        }
        else if ($log) {
            $log->Delivered = SS_Datetime::now()->Rfc2822();

            $params['MessageID'] = $messageId;
            $params['success'] = true;
            $this->updateLog($log, $params);
        }
    }

    public function bounced(Event $e, $messageId = '', $email = '', $params = [], $response = [])
    {
        if (!$this->allowed($e->mailer())) {
            return;
        }

        $base = '@' . trim(singleton('director')->baseWebsiteURL(), ' /');
        $blacklistAfter = $e->mailer()->config()->blacklist_after_bounced ?: 2;
        $permanent = isset($params['permanent']) && $params['permanent'];

        $bounce = null;

        if (!isset($params['message'])) {
            $params['message'] = 'Bounced.';
        }

        $params['message'] .= "\n\n" . print_r($response, true);

        if ((substr($email, -strlen($base)) !== $base)) {
            $bounce = Bounce::create();
            $bounce->Email = $email;
            $bounce->Message = $params['message'];

            if ((Bounce::get()->filter('Email', $email)->count() + 1) >= $blacklistAfter || $permanent) {
                $message = "\n\n" . print_r($response, true);
                $message = $permanent ? 'Permanent Bounce. ' . $message : 'Bounced too many times. ' . $message;

                $e->mailer()->eventful()->fire(
                    singleton('sendthis-event')->named('sendthis:spam', $e->mailer()),
                    $messageId,
                    $email,
                    array_merge($params, ['message' => $message, 'valid_email' => false]),
                    $response
                );

                $bounce->Message = $message;
                $bounce->write();

                return;
            }
        }

        if ($messageId) {
            $this->updateLogsForMessageId($messageId, $params, $bounce);
        }

        if ($bounce) {
            $bounce->write();
        }
    }

    public function spam(Event $e, $messageId = '', $email = '', $params = [], $response = [])
    {
        if (!isset($params['message'])) {
            $params['message'] = 'The email address marked this email as spam';
        }

        if (!array_key_exists('valid_email', $params)) {
            $params['valid_email'] = true;
        }

        $this->updateBadEmail($messageId, $email, $params);
    }

    public function rejected(Event $e, $messageId = '', $email = '', $params = [], $response = [])
    {
        if (!isset($params['message'])) {
            $params['message'] = 'The end point has rejected this email for some reason. Usually this is because the recent bounce for this recipient, the recipient has registered a spam complaint, the recipient is unsubscribed from emails from your application, or the recipient has been blacklisted.';
        }

        $this->updateBadEmail($messageId, $email, $params);
    }

    public function blacklisted(Event $e, $messageId = '', $email = '', $params = [], $response = [])
    {
        if (!isset($params['message'])) {
            $params['message'] = 'The user of this email has requested to be blacklisted from this application';
        }

        if (!array_key_exists('valid_email', $params)) {
            $params['valid_email'] = true;
        }

        $this->updateBadEmail($messageId, $email, $params);
    }

    public function unsubscribed(Event $e, $messageId = '', $email = '', $params = [], $response = [])
    {
        if (!isset($params['message'])) {
            $params['message'] = 'The user of this email has requested to be unsubscribed from this application';
        }

        $params['valid_email'] = true;

        $this->updateBadEmail('', $email, $params);
    }

    public function whitelisted(Event $e, $messageId = '', $email = '', $params = [], $response = [])
    {
        if (!$email) {
            return;
        }

//        if(!isset($params['message']))
//            $params['message'] = 'The user of this email has requested to be whitelisted for this application';

        $blocked = Blacklist::get()->filter(['Email' => $email, 'Valid' => true]);

        if ($blocked->exists()) {
            foreach ($blocked as $block) {
                $block->delete();
                $block->destroy();
            }
        }
    }

    protected function updateBadEmail($messageId = '', $email = '', $params = [])
    {
        if ($email) {
            $message = isset($params['message']) ? $params['message'] : 'Unknown';
            $this->blacklistEmail($email, $message, (isset($params['valid_email']) && $params['valid_email']));
        }

        if ($messageId) {
            $this->updateLogsForMessageId($messageId, $params);
        }
    }

    protected function blacklistEmail($email, $message = '', $valid = false)
    {
        Blacklist::log_invalid($email, $message, $valid);
    }

    protected function updateLogsForMessageId($messageId, $params, $bounce = null)
    {
        $logs = Log::get()->filter('MessageID', $messageId)->sort('Created', 'ASC');

        if ($logs->exists()) {
            foreach ($logs as $log) {
                $this->updateLog($log, $params, $bounce);
            }
        }
    }

    protected function updateLog($log, $params = [], $bounce = null)
    {
        $log->Success = isset($params['success']) ? $params['success'] : false;

        if (isset($params['MessageID'])) {
            $log->MessageID = $params['MessageID'];
        }

        if (isset($params['message'])) {
            $log->Notes = $params['message'];
        }

        if ($bounce) {
            $bounce->LogID = $log->ID;
        }

        $log->write();
    }

    protected function allowed(Object $mailer)
    {
        return $mailer->config()->logging || $mailer->config()->api_tracking;
    }
}
