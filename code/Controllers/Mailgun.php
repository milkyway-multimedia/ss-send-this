<?php namespace Milkyway\SS\SendThis\Controllers;

/**
 * Milkyway Multimedia
 * Mailgun.php
 *
 * @package milkyway-multimedia/ss-send-this
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

use Milkyway\SS\SendThis\Mailer;
use Controller;
use Email;
use SS_HTTPResponse;

class Mailgun extends Controller
{
    protected $eventMapping = [
        'accepted'   => 'delayed',
        'dropped'    => 'failed',
        'stored'     => 'delayed',
        'complained' => 'spam',
    ];

    public function index($request)
    {
        if ($request->isHEAD()) {
            $this->confirmSubscription($request->getBody());
        } else {
            $response = $request->isPOST() ? $request->postVars() : json_decode($request->getBody(), true);

            if (!empty($response)) {
                $this->handleEvent($response);
            }
        }

        return new SS_HTTPResponse('', 200, 'success');
    }

    protected function confirmSubscription($message)
    {
        if (Email::mailer() instanceof Mailer) {
            Email::mailer()->eventful()->fire(singleton('sendthis-event')->named('sendthis:hooked', Email::mailer()),
                '', '',
                ['subject' => 'Subscribed to Mailgun Web Hook', 'message' => $message]);
        }
    }

    /**
     * @param $eventParams
     * @param $request
     */
    protected function handleEvent($eventParams, $request = null)
    {
        $event = isset($eventParams['event']) ? strtolower($eventParams['event']) : 'unknown';

        // bounced is always a hard bounce from mail gun I think?
        if ($event == 'bounced') {
            $eventParams['permanent'] = true;
        }

        $messageId = isset($eventParams['message-id']) ? $eventParams['message-id'] : (isset($eventParams['Message-Id']) ? $eventParams['Message-Id'] : '');
        $email = isset($eventParams['recipient']) ? $eventParams['recipient'] : '';

        if(isset($eventParams['message-headers']) && !is_array($eventParams['message-headers'])) {
            $eventParams['message-headers'] = json_decode($eventParams['message-headers'], true);
        }

        $params = array_merge($eventParams, [
            'details' => $eventParams,
        ]);

        if (isset($params['description']) && empty($params['message'])) {
            $params['message'] = $params['description'];
        }
        else if (isset($params['error']) && empty($params['message'])) {
            $params['message'] = $params['error'];
        }

        if(isset($params['reason'])) {
            if(isset($params['message'])) {
                $params['message'] .= "\n. Reason: " . $params['reason'];
            }
            else {
                $params['message'] = $params['reason'];
            }
        }

        if (isset($this->eventMapping[$event])) {
            $event = $this->eventMapping[$event];
        }

        $messageId = '<' . trim($messageId, '<>') . '>';

        if (Email::mailer() instanceof Mailer) {
            Email::mailer()->eventful()->fire(singleton('sendthis-event')->named('sendthis:' . $event, Email::mailer()),
                $messageId, $email,
                $params, $eventParams);
            Email::mailer()->eventful()->fire(singleton('sendthis-event')->named('sendthis:handled', Email::mailer()),
                $event, $request,
                $eventParams);
        }
    }
}
