<?php namespace Milkyway\SS\SendThis\Transports;

use Milkyway\SS\Eventful\Contract as Eventful;

/**
 * Milkyway Multimedia
 * SendThis_Default.php
 *
 * @package milkyway-multimedia/silverstripe-send-this
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */
class Mail implements Contract {
	protected $eventful;
	protected $params = [];

    public function __construct(\PHPMailer $messenger, Eventful $eventful, $params = []) {
        $messenger->isMail();
	    $this->eventful = $eventful;
	    $this->params = array_merge($this->params, $params);
    }

    function start(\PHPMailer $messenger, \ViewableData $log = null)
    {
        $messenger->action_function = function($success, $to, $cc, $bcc, $subject, $body, $from) use ($messenger, $log) {
            $response = compact($success, $to, $cc, $bcc, $subject, $body, $from);

            if($success)
                $this->eventful->fire('sent', $messenger->getLastMessageID(), $to, $response, $response, $log);
            else
                throw new Exception('Message not successfully sent' . "\n\n" . nl2br(print_r($response, true)));
        };

        $messenger->send();
        $messenger->action_function = '';
    }

    function applyHeaders(array &$headers) { }
}