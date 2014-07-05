<?php namespace Milkyway\SendThis\Listeners;
/**
 * Milkyway Multimedia
 * Notifications.php
 *
 * @package reggardocolaianni.com
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */
class Notifications {
    public function bounced($email, $messageId, $info, $params) {
        $this->notifyByMessageId($messageId, $email, $info);
    }

    public function spam($email, $messageId, $info, $params) {
        $this->notifyByMessageId($messageId, $email, $info);
    }

    public function notifyByMessageId($messageId, $email = '', $info = array()) {
        if($messageId) {
            $logs = SendThis_Log::get()->filter('MessageID', $messageId);

            if($logs->exists()) {
                foreach($logs as $log) {
                    $this->sendNotificationToSender($log, $email, $info);
                }
            }
        }
    }

    protected function sendNotificationToSender($log, $email, $info = array(), $write = false) {
        if (($log->Notify_Sender && ($log->From || $log->SentBy()->exists())) || SendThis::config()->notify_on_fail)
        {
            $from = $log->Notify_Sender;
            $notify = SendThis::config()->notify_on_fail;

            if (! Email::is_valid_address($from))
            {
                $from = $log->SentBy()->exists() ? $log->SentBy()->ForEmail : $log->From;
            }

            if($notify) {
                if (! Email::is_valid_address($notify))
                {
                    $from = Email::config()->admin_email;
                }

                if(strpos($notify, '+') === false)
                    $notify = 'bounces+' . $notify;

                if(!$from) {
                    $from = $notify;
                    $notify = null;
                }
            }

            $e = Email::create(
                $from,
                $from,
                _t(
                    'SendThis_Log.SUBJECT-EMAIL_BOUNCED',
                    'Email bounced: {subject}',
                    array('subject' => $this->Subject)
                ),
                _t(
                    'SendThis_Log.EMAIL_BOUNCED',
                    'An email (subject: {subject}) addressed to {to} sent via {application} has bounced. For security reasons, we cannot display its contents. {message}',
                    array(
                        'subject'     => $this->Subject,
                        'application' => singleton('LeftAndMain')->ApplicationName,
                        'to'          => $email,
                        'message'     => nl2br(print_r($info, true)),
                    )
                ) . "\n\n<p>" . $this->Notes . '</p>'
            );

            if($notify)
                $e->Bcc = $notify;

            $e->setTemplate(array('SendThis_FailedNotificationEmail', 'GenericEmail'));

            $e->populateTemplate($this);

            $e->addCustomHeader('X-Milkyway-Priority', 1);
            $e->addCustomHeader('X-Priority', 1);

            $e->send();
        }

        if ($write)
        {
            $this->write();
        }
    }
} 