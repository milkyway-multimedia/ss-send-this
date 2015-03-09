<?php namespace Milkyway\SS\SendThis\Controllers;

use Milkyway\SS\SendThis\Events\Event;
use Milkyway\SS\SendThis\Mailer;

/**
 * Milkyway Multimedia
 * SendThis_Tracker.php
 *
 * @package reggardocolaianni.com
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

class Tracker extends \Controller {
    private static $slug = '$Slug/mail-footer.gif';

    function index($r) {
        if($r->param('Slug')) {
            $id = \Convert::raw2sql($r->param('Slug'));

            if(($log = \SendThis_Log::get()->filter('Slug', $id)->first())) {
	            if(\Email::mailer() instanceof Mailer) {
		            \Email::mailer()->eventful()->fire(Event::named('sendthis:opened', \Email::mailer()), $log->MessageID, $log->To, ['IP' => $r->getIP()], [
	                        'Referrer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null,
	                        'UserAgent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null,
	                    ], $log
	                );
	            }
            }
        }

	    // Create a response with a blank 1x1 pixel image
        $response = new \SS_HTTPResponse(base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw=='), 200, 'OK');
        $response->addHeader('Content-type', 'image/gif');
        return $response;
    }
}