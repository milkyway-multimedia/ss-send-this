<?php namespace Milkyway\SS\SendThis\Controllers;

/**
 * Milkyway Multimedia
 * Tracker.php
 *
 * @package milkyway-multimedia/ss-send-this
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

use Milkyway\SS\SendThis\Mailer;
use Controller;
use SS_HTTPResponse;
use Email;
use Convert;
use SendThis_Log as Log;

class Tracker extends Controller
{
    private static $slug = '$Slug/mail-footer.gif';

    public function index($r)
    {
        if ($r->param('Slug') && Email::mailer() instanceof Mailer && $log = Log::get()->filter('Slug',
                Convert::raw2sql($r->param('Slug')))->first()
        ) {
            Email::mailer()->eventful()->fire(singleton('sendthis-event')->named('sendthis:opened', Email::mailer()), $log->MessageID,
                $log->To, ['IP' => $r->getIP()], [
                    'Referrer'  => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null,
                    'UserAgent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null,
                ], $log
            );
        }

        // Create a response with a blank 1x1 pixel image
        $response = new SS_HTTPResponse(base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw=='),
            200, 'OK');
        $response->addHeader('Content-type', 'image/gif');

        return $response;
    }
}
