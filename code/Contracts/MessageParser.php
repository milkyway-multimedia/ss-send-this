<?php namespace Milkyway\SS\SendThis\Contracts;

/**
 * Milkyway Multimedia
 * MessageParser.php
 *
 * @package milkyway-multimedia/ss-send-this
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

use PHPMailer;
use Config_ForClass;

interface MessageParser
{
    public function setMessage(PHPMailer $message);

    public function setConfig(Config_ForClass $config);

    public function parse($to, $from, $subject, $attachedFiles = null, $headers = null);
}
