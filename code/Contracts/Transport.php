<?php namespace Milkyway\SS\SendThis\Contracts;

/**
 * Milkyway Multimedia
 * Transport.php
 *
 * @package milkyway-multimedia/ss-send-this
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

use PHPMailer;
use ViewableData;

interface Transport
{
    public function start(PHPMailer $email, ViewableData $log = null);

    public function applyHeaders(array &$headers);

    public function param($key);
}
