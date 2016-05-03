<?php namespace Milkyway\SS\SendThis\Transports;

/**
 * Milkyway Multimedia
 * SparkPost.php
 *
 * @package milkyway-multimedia/ss-send-this
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

use GuzzleHttp\Client;
use PHPMailer;
use ViewableData;
use Psr\Http\Message\ResponseInterface;
use Exception;
use Controller;

class SparkPost extends Mail
{
    protected $params = [
        'endpoint' => 'https://api.sparkpost.com/api/v1/',
        'domain'   => '',
        'user'     => 'api',
        'options' => [],
    ];

    protected $allowedPostParams = [
        'options',
        'recipients',
        'campaign_id',
        'description',
        'metadata',
        'substitution_data',
        'return_path',
        'content',
    ];

    public function start(PHPMailer $messenger, ViewableData $log = null)
    {
        if(empty($this->params['key'])) {
            throw new Exception('Invalid credentials. Could not connect to SparkPost');
        }

        // This does all checks to increase sendability
        if (!$messenger->PreSend()) {
            return false;
        }

        $response = $this->http()->post($this->endpoint('transmissions'), $this->getSendParams($messenger));

        return $this->handleResponse($response, $messenger, $log);
    }

    public function handleResponse(ResponseInterface $response, $messenger = null, $log = null)
    {
        $body = $response->getBody()->getContents();

        $message = '';

        if (!$body) {
            $message = 'Empty response received from SparkPost' . "\n";
        }

        $body = json_decode($body, true);

        if (empty($body['results']) || $message || $response->getStatusCode() < 200 || $response->getStatusCode() > 399) {
            $message .= 'Status Code: ' . $response->getStatusCode() . "\n";
            $message .= 'Message: ' . $response->getReasonPhrase();

            if (!empty($body['errors'])) {
                $message .= "\n" . print_r($body['errors'], true);
            }

            $this->mailer->eventful()->fire(singleton('sendthis-event')->named('sendthis:failed', $this->mailer),
                (empty($body['id']) ? $messenger->getLastMessageID() : $body['id']), $messenger->getToAddresses(),
                ['message' => $message],
                ['message' => $message], $log);

            throw new Exception($message);
        }

        $this->mailer->eventful()->fire(singleton('sendthis-event')->named('sendthis:sent', $this->mailer),
            (empty($body['results']['id']) ? $messenger->getLastMessageID() : $body['results']['id']),
            $messenger->getToAddresses(), ['message' => print_r($body['results'], true)], ['message' => print_r($body['results'], true)], $log);

        return true;
    }

    public function applyHeaders(array &$headers)
    {
        if (isset($headers['X-Track']) || isset($this->params['tracking']) || isset($this->params['api_tracking'])) {
            $this->params['options']['open_tracking'] = isset($headers['X-Track']) ? $headers['X-Track'] : (isset($this->params['tracking']) ? isset($this->params['tracking']) : isset($this->params['api_tracking']));
            $this->params['options']['click_tracking'] = $this->params['options']['open_tracking'];

            if (isset($headers['X-Track'])) {
                unset($headers['X-Track']);
            }
        }

        if (!empty($headers['X-SendAt'])) {
            $this->params['options']['start_time'] = $headers['X-SendAt'];
            unset($headers['X-SendAt']);
        }

        if (array_key_exists('X-Campaign', $headers)) {
            $this->params['campaign_id'] = $headers['X-Campaign'];
            unset($headers['X-Campaign']);
        }

        if (!empty($headers['X-Priority']) && $headers['X-Priority'] == 1) {
            $this->params['options']['transactional'] = true;
        }
    }

    /**
     * Get a new HTTP client instance.
     *
     * @return \GuzzleHttp\Client
     */
    protected function http()
    {
        return new Client;
    }

    protected function endpoint($action = '')
    {
        return Controller::join_links($this->param('endpoint'), $action);
    }

    /**
     * @param PHPMailer $messenger
     * @return array
     */
    protected function getSendParams(PHPMailer $messenger)
    {
        $params = [
            'headers' => [
                'Authorization' => $this->param('key'),
            ],
            'json' => [
                'content' => [
                    'email_rfc822' => $messenger->getSentMIMEMessage(),
                ]
            ]
        ];

        $params['json'] = array_merge(array_filter(array_intersect_key($this->params, array_flip($this->allowedPostParams)), function($var) {
            return !empty($var);
        }), $params['json']);

        if(empty($params['json']['list_id']) || empty($params['json']['recipients'])) {
            $params['json']['recipients'] = [];

            $params['json']['recipients'] = $this->appendAddressesTo($params['json']['recipients'], $messenger->getToAddresses());
            $params['json']['recipients'] = $this->appendAddressesTo($params['json']['recipients'], $messenger->getCcAddresses());
            $params['json']['recipients'] = $this->appendAddressesTo($params['json']['recipients'], $messenger->getBccAddresses());
        }

        return $params;
    }

    protected function appendAddressesTo($list, $addresses) {
        foreach($addresses as $address) {
            $item = ['address' => [],];

            if(is_array($address) && isset($address[1])) {
                $item['address']['email'] = $address[0];
                $item['address']['name'] = $address[1];
            }
            else if(is_array($address) && isset($address[0])) {
                $item['address']['email'] = $address[0];
            }
            else {
                $item['address']['email'] = (string)$address;
            }

            $list[] = $item;
        }

        return $list;
    }
}
