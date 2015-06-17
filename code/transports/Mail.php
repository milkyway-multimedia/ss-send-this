<?php namespace Milkyway\SS\SendThis\Transports;

use Milkyway\SS\SendThis\Events\Event;
use Milkyway\SS\SendThis\Mailer;

/**
 * Milkyway Multimedia
 * SendThis_Default.php
 *
 * @package milkyway-multimedia/silverstripe-send-this
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */
class Mail implements Contract {
	protected $mailer;
	protected $params = [];

    public function __construct(\PHPMailer $messenger, Mailer $mailer, $params = []) {
        $messenger->isMail();
	    $this->mailer = $mailer;
	    $this->params = array_merge($this->params, $params);
    }

    function start(\PHPMailer $messenger, \ViewableData $log = null)
    {
        $messenger->action_function = function($success, $to, $cc, $bcc, $subject, $body, $from) use ($messenger, $log) {
            $response = compact($success, $to, $cc, $bcc, $subject, $body, $from);

            if($success)
                $this->mailer->eventful()->fire(Event::named('sendthis:sent', $this->mailer), $messenger->getLastMessageID(), $to, $response, $response, $log);
            else {
                $this->mailer->eventful()->fire(Event::named('sendthis:failed', $this->mailer), $messenger->getLastMessageID(), $to, $response, $response, $log);
                throw new Exception('Message not successfully sent' . "\n\n" . nl2br(print_r($response, true)));
            }
        };

        $messenger->send();
        $messenger->action_function = '';
    }

    function applyHeaders(array &$headers) { }
}