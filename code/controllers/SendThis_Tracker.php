<?php /**
 * Milkyway Multimedia
 * SendThis_Tracker.php
 *
 * @package reggardocolaianni.com
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */
class SendThis_Tracker extends Controller {
    private static $slug = '$Slug/mail-footer.gif';

    function index($r) {
        if($r->param('Hash')) {
            $id = Convert::raw2sql($r->param('Slug'));

            if(($record = SendThis_Log::get()->filter('Track_Hash', $id)->first()) && !$record->Track_Open)
                $record->track($r->getIP());
        }

        $response = new SS_HTTPResponse(base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw=='), 200, 'OK');
        $response->addHeader('Content-type', 'image/gif');
        return $response;
    }
}