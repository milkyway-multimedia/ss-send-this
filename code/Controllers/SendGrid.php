<?php namespace Milkyway\SS\SendThis\Controllers;

/**
 * Milkyway Multimedia
 * SendGrid.php
 *
 * @package milkyway-multimedia/ss-send-this
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

use Milkyway\SS\SendThis\Mailer;
use Controller;
use Email;
use SS_HTTPResponse;

class SendGrid extends Controller
{
    protected $eventMapping = [
        'processed'         => 'sent',
        'dropped'           => 'failed',
        'deferred'          => 'delayed',
        'bounce'            => 'bounced',
        'expired'           => 'bounced',
        'blocked'           => 'rejected',
        'open'              => 'opened',
        'click'             => 'clicked',
        'spam report'       => 'spam',
        'unsubscribe'       => 'unsubscribed',
//        'group unsubscribe' => 'unsubscribed' // Handled via SendGrid
        'group resubscribe' => 'whitelisted',
    ];

    protected $softBounce = [
        '5.1.5',

        '5.2.0',
        '5.2.1',
        '5.2.2',
        '5.2.3',

        '5.3.1',
        '5.3.4',

        '5.4.5',

        '5.5.3',
    ];

    public function index($request)
    {
        if ($request->isHEAD()) {
            $this->confirmSubscription($request->getBody());
        } else {
            $response = json_decode($request->getBody(), true);

            foreach ((array)$response as $event) {
                $this->handleEvent($event);
            }
        }

        return new SS_HTTPResponse('', 200, 'success');
    }

    protected function confirmSubscription($message)
    {
        if (Email::mailer() instanceof Mailer) {
            Email::mailer()->eventful()->fire(singleton('sendthis-event')->named('sendthis:hooked', Email::mailer()),
                '', '',
                ['subject' => 'Subscribed to SendGrid Web Hook', 'message' => $message]);
        }
    }

    /**
     * @param $eventParams
     * @param $request
     */
    protected function handleEvent($eventParams, $request = null)
    {
        $event = isset($eventParams['event']) ? strtolower($eventParams['event']) : 'unknown';

        if($event == 'bounce' && !empty($eventParams['status'])) {
            $eventParams['permanent'] = !in_array($eventParams['status'], $this->softBounce);
        }

        if ($event == 'bounce' && !empty($eventParams['type'])) {
            $event = strtolower($eventParams['type']);
        }

        $messageId = isset($eventParams['send_this_message_id']) ? $eventParams['send_this_message_id'] : $eventParams['smtp-id'];
        $email = isset($eventParams['email']) ? $eventParams['email'] : '';

        $params = array_merge($eventParams, [
            'details'   => $eventParams,
        ]);

        if (isset($params['reason']) && empty($params['message'])) {
            $params['message'] = $params['reason'];
        }

        if (isset($params['response']) && empty($params['message'])) {
            $params['message'] = $params['response'];
        }

        if (isset($this->eventMapping[$event])) {
            $event = $this->eventMapping[$event];
        }

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
