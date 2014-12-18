<?php
/**
 * Milkyway Multimedia
 * Contract.php
 *
 * @package milkywaymultimedia.com.au
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

namespace Milkyway\SS\SendThis\MessageParsers;


interface Contract {
	public function setMessage(\PHPMailer $message);
	public function setConfig(\Config_ForClass $config);
	public function parse($to, $from, $subject, $attachedFiles = null, $headers = null);
} 