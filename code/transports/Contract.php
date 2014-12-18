<?php namespace Milkyway\SS\SendThis\Transports;
/**
 * Milkyway Multimedia
 * Contract.php
 *
 * @package milkywaymultimedia.com.au
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */


interface Contract {
	function start(\PHPMailer $email, \ViewableData $log = null);
	function applyHeaders(array &$headers);
} 