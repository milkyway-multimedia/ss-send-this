<?php namespace Milkyway\SS\SendThis\Listeners\Mandrill;

/**
 * Milkyway Multimedia
 * Tracking.php
 *
 * @package reggardocolaianni.com
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

class Logging extends \Milkyway\SS\SendThis\Listeners\Logging {
	protected function allowed(\Object $mailer) {
		return !parent::allowed($mailer) && $mailer->config()->api_tracking;
	}
} 