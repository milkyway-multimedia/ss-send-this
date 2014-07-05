<?php namespace Milkyway\SendThis\Contracts;
/**
 * Milkyway Multimedia
 * Transport.php
 *
 * @package milkyway-multimedia/silverstripe-send-this
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 *
 */
interface Transport {
    function send(\PHPMailer $email, \ViewableData $log = null);
    function applyHeaders(array &$headers);
}