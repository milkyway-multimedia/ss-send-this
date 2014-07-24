<?php namespace Milkyway\SS\SendThis\Transports;

use Milkyway\SS\SendThis\Contracts\Transport;

/**
 * Milkyway Multimedia
 * SendThis_Default.php
 *
 * @package milkyway-multimedia/silverstripe-send-this
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */
class Mail implements Transport {
    public function __construct(\PHPMailer $messenger) {
        $messenger->isMail();
        return $messenger;
    }

    function start(\PHPMailer $messenger, \ViewableData $log = null)
    {
        $messenger->action_function = function($success, $to, $cc, $bcc, $subject, $body, $from) use ($messenger, $log) {
            $response = compact($success, $to, $cc, $bcc, $subject, $body, $from);

            if($success)
                SendThis::fire('sent', $messenger->getLastMessageID(), $to, $response, $response, $log);
            else
                throw new \SendThis_Exception('Message not successfully sent' . "\n\n" . nl2br(print_r($response, true)));
        };

        $messenger->send();
        $messenger->action_function = '';
    }

    function applyHeaders(array &$headers) { }
}