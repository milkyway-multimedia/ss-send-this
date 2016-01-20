<?php namespace Milkyway\SS\SendThis\Transports;

/**
 * Milkyway Multimedia
 * Mail.php
 *
 * @package milkyway-multimedia/ss-send-this
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

use Milkyway\SS\SendThis\Contracts\Transport;
use Milkyway\SS\SendThis\Mailer;
use PHPMailer;
use ViewableData;

class Mail implements Transport
{
    protected $mailer;
    protected $params = [];

    public function __construct(PHPMailer $messenger, Mailer $mailer, $params = [])
    {
        $messenger->isMail();
        $this->mailer = $mailer;
        $this->params = array_merge($this->params, $params);
    }

    public function start(PHPMailer $messenger, ViewableData $log = null)
    {
        $messenger->action_function = function ($success, $to, $cc, $bcc, $subject, $body, $from) use (
            $messenger,
            $log
        ) {
            $response = compact($success, $to, $cc, $bcc, $subject, $body, $from);

            if ($success) {
                $this->mailer->eventful()->fire(singleton('sendthis-event')->named('sendthis:sent', $this->mailer),
                    $messenger->getLastMessageID(), $to, $response, $response, $log);
            } else {
                $this->mailer->eventful()->fire(singleton('sendthis-event')->named('sendthis:failed', $this->mailer),
                    $messenger->getLastMessageID(), $to, $response, $response, $log);
                throw new Exception('Message not successfully sent' . "\n\n" . nl2br(print_r($response, true)));
            }
        };

        $messenger->send();
        $messenger->action_function = '';
    }

    public function applyHeaders(array &$headers)
    {
    }

    public function param($key)
    {
        return isset($this->params[$key]) ? $this->params[$key] : null;
    }
}
