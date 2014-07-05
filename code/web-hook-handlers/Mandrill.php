<?php namespace Milkyway\SendThis\WebHookHandlers;

/**
 * Milkyway Multimedia
 * Mandrill.php
 *
 * @package milkyway-multimedia/silverstripe-send-this
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */
class Mandrill {

    protected $eventMapping = [
        'send'        => 'sent',
        'deferral'    => 'delayed',
        'hard_bounce' => 'bounced',
        'soft_bounce' => 'bounced',
        'open'        => 'opened',
        'click'       => 'clicked',
        'unsub'       => 'unsubscribed',
        'reject'      => 'rejected',
        'whitelist'   => 'whitelisted',
        'blacklist'   => 'blacklisted',
    ];

    public function handleWebHook($request)
    {
        if ($request->isHEAD())
        {
            return $this->confirmSubscription();
        } else
        {
            $response  = json_decode($request->getBody(), true);
            $event     = isset($response['event']) ? $response['event'] : 'unknown';
            $messageId = '';
            $email     = '';

            $params = [
                'details'   => isset($response['msg']) ? $response['msg'] : [],
                'timestamp' => isset($response['ts']) ? $response['ts'] : '',
            ];

            if (count($params['details']))
            {
                if (isset($params['details']['_id']))
                {
                    $messageId = $params['details']['_id'];
                }

                if (isset($params['details']['email']))
                {
                    $email = $params['details']['email'];
                }
            }

            if (! $messageId && isset($response['_id']))
            {
                $messageId = $response['_id'];
            }

            if ($event == 'hard_bounce')
            {
                $params['blacklist'] = true;
            }

            if(isset($this->eventMapping[$event]))
                $event = $this->eventMapping[$event];

            SendThis::fire($event, $email, $messageId, $response, $params);
        }
    }

    protected function confirmSubscription()
    {
        return new \SS_HTTPResponse('', 200, 'success');
    }
} 