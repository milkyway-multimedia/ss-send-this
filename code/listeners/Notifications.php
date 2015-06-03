<?php namespace Milkyway\SS\SendThis\Listeners;
use Milkyway\SS\SendThis\Events\Event;
use Milkyway\SS\SendThis\Mailer;

/**
 * Milkyway Multimedia
 * Notifications.php
 *
 * @package reggardocolaianni.com
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */
class Notifications {
    public function up($e, $messageId, $email, $params, $response, $log, $headers) {
        if ($log && isset($headers->{'X-NotifyOnFail'}) && $headers->{'X-NotifyOnFail'})
        {
            $log->Notify_Sender = $headers->{'X-NotifyOnFail'};
            unset($headers->{'X-NotifyOnFail'});
        }
    }

    public function hooked(Event $e, $messageId, $email, $params, $response = null) {
        if($e->mailer()->config()->debugging && $email = \Email::config()->admin_email) {
            $originalSMTP = ini_get('SMTP');

            if($customSMTP = $e->mailer()->config()->smtp_for_debugging)
                ini_set('SMTP', $customSMTP);

            mail(
                $email,
                isset($params['subject']) ?: 'Subscribed to web hook',
                isset($params['message']) ?: 'Subscribed to web hook',
                "Content-type: text/html\nFrom: " . $email
            );

            if($customSMTP)
                ini_set('SMTP', $originalSMTP);
        }
    }

	public function handled(Event $e, $event, $request = null, $params = null) {
		if($email = getenv('sendthis_notify_on_webhook_events')) {
			$originalSMTP = ini_get('SMTP');

			if($customSMTP = $e->mailer()->config()->smtp_for_debugging)
				ini_set('SMTP', $customSMTP);

			mail(
				$email,
				'Web hook called for event: ' . $event,
				'<h1>Params</h1><pre>' . print_r($params, true) . '</pre><br><br>' .
				'<h2>Request</h2><pre>' . print_r($request, true) . '</pre>',
				"Content-type: text/html\nFrom: " . $email
			);

			if($customSMTP)
				ini_set('SMTP', $originalSMTP);
		}
	}

    public function failed(Event $e, $messageId, $email, $params, $response) {
        $this->notifyByMessageId($messageId, $email, $response, $e->mailer());
    }

    public function bounced(Event $e, $messageId, $email, $params, $response) {
        $this->notifyByMessageId($messageId, $email, $response, $e->mailer());
    }

    public function spam(Event $e, $messageId, $email, $params, $response) {
        $this->notifyByMessageId($messageId, $email, $response, $e->mailer());
    }

    public function rejected(Event $e, $messageId, $email, $params, $response) {
        $this->notifyByMessageId($messageId, $email, $response, $e->mailer());
    }

    protected function notifyByMessageId($messageId, $email = '', $response = [], Mailer $mailer = null) {
        if($messageId) {
            $logs = \SendThis_Log::get()->filter('MessageID', $messageId);

            if($logs->exists()) {
                foreach($logs as $log) {
                    $this->sendNotificationToSender($log, $email, $response, false, $mailer);
                }
            }
        }
    }

    protected function sendNotificationToSender($log, $email, $response = [], $write = false, Mailer $mailer = null) {
        if ($log && ($log->Notify_Sender && ($log->From || $log->SentBy()->exists())) || ($mailer && $mailer->config()->notify_on_fail))
        {
            $from = $log->Notify_Sender;
            $notify = ($mailer && $mailer->config()->notify_on_fail);

            if (! \Email::is_valid_address($from))
            {
                $from = $log->SentBy()->exists() ? $log->SentBy()->ForEmail : $log->From;
            }

            if($notify) {
                if (! \Email::is_valid_address($notify))
                {
                    $from = \Email::config()->admin_email;
                }

                if(strpos($notify, '+') === false)
                    $notify = 'bounces+' . $notify;

                if(!$from) {
                    $from = $notify;
                    $notify = null;
                }
            }

            $e = \Email::create(
                $from,
                $from,
                _t(
                    'SendThis_Log.SUBJECT-EMAIL_BOUNCED',
                    'Email bounced: {subject}',
                    [
                        'subject' => isset($response['Subject']) ? $response['Subject'] : '',
                    ]
                ),
                _t(
                    'SendThis_Log.EMAIL_BOUNCED',
                    'An email (subject: {subject}) addressed to {to} sent via {application} has bounced. For security reasons, we cannot display its contents. {message}',
                    [
                        'subject' => isset($response['Subject']) ? $response['Subject'] : '',
                        'application' => singleton('LeftAndMain')->ApplicationName,
                        'to'          => $email,
                        'message'     => nl2br(print_r($response, true)),
                    ]
                ) . "\n\n<p>" . $log ? $log->Notes : '' . '</p>'
            );

            if($notify)
                $e->Bcc = $notify;

            $e->setTemplate(['SendThis_FailedNotificationEmail', 'GenericEmail']);

            $e->populateTemplate($this);

            $e->addCustomHeader('X-Milkyway-Priority', 1);
            $e->addCustomHeader('X-Priority', 1);

            $e->send();

            if ($write)
            {
                $log->write();
            }
        }
    }
} 