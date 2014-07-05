<?php namespace Milkyway\SendThis\Transports;

use Milkyway\SendThis\Contracts\Transport;

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

    function send(\PHPMailer $messenger, \ViewableData $log = null)
    {
        $messenger->action_function = function($result, $to, $cc, $bcc, $subject, $body, $from) use ($messenger) {
            $response = compact($result, $to, $cc, $bcc, $subject, $body, $from);
            SendThis::fire('sent', $messenger->getLastMessageID(), $to, $response, $response);
        };

        $messenger->send();
        $messenger->action_function = '';
    }

    function applyHeaders(array &$headers) { }
}