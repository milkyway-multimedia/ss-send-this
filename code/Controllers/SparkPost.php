<?php namespace Milkyway\SS\SendThis\Controllers;

/**
 * Milkyway Multimedia
 * SparkPost.php
 *
 * @package milkyway-multimedia/ss-send-this
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

use Milkyway\SS\SendThis\Mailer;
use Controller;
use Email;
use SS_HTTPResponse;

class SparkPost extends Controller
{
    protected $eventMapping = [
        'bounce'               => 'bounced',
        'injection'            => 'sent',
        'relay_injection'      => 'sent',
        'delivery'             => 'delivered',
        'relay_delivery'       => 'delivered',
        'out_of_band'          => 'failed',
        'spam_complaint'       => 'spam',
        'delay'                => 'delayed',
        'policy_rejection'     => 'rejected',
        'generation_rejection' => 'rejected',
        'relay_rejection'      => 'rejected',
        'relay_tempfail'       => 'rejected',
        'relay_permfail'       => 'rejected',
        'open'                 => 'opened',
        'click'                => 'clicked',
        'list_unsubscribe'     => 'unsubscribed',
    ];

    protected $softBounce = [
        1,
        20,
        21,
        22,
        23,
        24,
        40,
        60,
        70,
        100,
    ];

    public function index($request)
    {
        if ($request->isHEAD()) {
            $this->confirmSubscription($request->getBody());
        } else {
            $response = json_decode($request->getBody(), true);

            foreach ((array)$response as $eventTypes) {
                foreach($eventTypes as $type => $events) {
                    if($type != 'msys') {
                        continue;
                    }

                    foreach($events as $event) {
                        if(!is_array($event)) {
                            continue;
                        }

                        $this->handleEvent($event);
                        \debug::log(print_r($event, true));
                    }
                }
            }
        }

        return new SS_HTTPResponse('', 200, 'success');
    }

    protected function confirmSubscription($message)
    {
        if (Email::mailer() instanceof Mailer) {
            Email::mailer()->eventful()->fire(singleton('sendthis-event')->named('sendthis:hooked', Email::mailer()),
                '', '',
                ['subject' => 'Subscribed to SparkPost Web Hook', 'message' => $message]);
        }
    }

    /**
     * @param $eventParams
     * @param $request
     */
    protected function handleEvent($eventParams, $request = null)
    {
        if(empty($eventParams['type'])) {
            return;
        }

        $event = isset($eventParams['type']) ? strtolower($eventParams['type']) : 'unknown';

        if ($event == 'bounce' && !empty($eventParams['bounce_class'])) {
            $eventParams['permanent'] = !in_array($eventParams['bounce_class'], $this->softBounce);
        }

        $messageId = isset($eventParams['transmission_id']) ? $eventParams['transmission_id'] : $eventParams['message_id'];
        $email = isset($eventParams['rcpt_to']) ? $eventParams['rcpt_to'] : '';

        $params = array_merge($eventParams, [
            'details' => $eventParams,
        ]);

        if (isset($params['reason']) && empty($params['message'])) {
            $params['message'] = $params['reason'];
        }
        elseif (isset($params['raw_reason']) && empty($params['message'])) {
            $params['message'] = $params['raw_reason'];
        }
        elseif (isset($params['response']) && empty($params['message'])) {
            $params['message'] = $params['response'];
        }

        if (!empty($params['user_str'])) {
            if(isset($params['message'])) {
                $params['message'] .= "\n\n" . $params['user_str'];
            }
            else {
                $params['message'] = $params['user_str'];
            }
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
