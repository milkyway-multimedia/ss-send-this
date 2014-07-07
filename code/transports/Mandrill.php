<?php namespace Milkyway\SendThis\Transports;

/**
 * Milkyway Multimedia
 * SendThis_Mandrill.php
 *
 * @package milkyway-multimedia/silverstripe-send-this
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */
class Mandrill extends Mail {
    protected $endpoint = 'https://mandrillapp.com/api/1.0';

    protected $async = true;
    protected $sendAt;
    protected $returnPathDomain;

    function __construct(\PHPMailer $messenger) {
        parent::__construct($messenger);

        if(\SendThis::config()->endpoint)
            $this->endpoint = \SendThis::config()->endpoint;
    }

    function start(\PHPMailer $messenger, \ViewableData $log = null)
    {
        if(($key = \SendThis::config()->key)) {
            if(!$messenger->PreSend())
                return false;

            $response = $this->http()->post($this->endpoint('messages/send-raw'), [
                    'body' => [
                        'key' => $key,
                        'raw_message' => $messenger->GetSentMIMEMessage(),
                        'async' => true,
                    ],
                ]
            );

            return $this->handleResponse($response, $messenger, $log);
        }

        throw new \SendThis_Exception('Invalid API Key. Could not connect to Mandrill.');
    }

    public function handleResponse(\GuzzleHttp\Message\ResponseInterface $response, $messenger = null, $log = null) {
        $body = $response->getBody();
        $failed = ($statusCode = $response->getStatusCode()) && ($statusCode < 200 || $statusCode > 399);
        $message = '';

        if(!$body)
            $message = 'Empty response received from Mandrill' . "\n";

        $results = $response->json();

        if(!count($results))
            $message = 'No results received from Mandrill' . "\n";

        foreach($results as $result) {
            $messageId = isset($result['_id']) ? $result['_id'] : '';
            $email = isset($result['email']) ? $result['email'] : '';

            $status = isset($result['status']) ? $result['status'] : 'failed';

            if($failed || !in_array($status, ['sent', 'queued', 'scheduled']) || isset($results['reject_reason'])) {
                $message = 'Problem sending via Mandrill' . "\n";
                $message .= urldecode(http_build_query($results, '', "\n"));
            }

            if($message) {
                if($log)
                    $log->Success = false;

                $message .= 'Status Code: ' . $response->getStatusCode() . "\n";
                $message .= 'Message: ' . $response->getReasonPhrase();
                throw new \SendThis_Exception($message);
            }

            \SendThis::fire('sent', $messageId ? $messageId : $messenger->getLastMessageID(), $email, $results, $results, $log);
        }

        return true;
    }

    /**
     * Get a new HTTP client instance.
     *
     * @return \Guzzle\Http\Client
     */
    protected function http()
    {
        return new \GuzzleHttp\Client;
    }

    protected function endpoint($action = '')
    {
        return \Controller::join_links($this->endpoint, $action . '.json');
    }

    public function applyHeaders(array &$headers) {
        if(isset($headers['X-SendAt'])) {
            $this->sendAt = $headers['X-SendAt'];
            unset($headers['X-SendAt']);
        }

        if(array_key_exists('X-Async', $headers)) {
            $this->async = $headers['X-Async'];
            unset($headers['X-Async']);
        }

        if(array_key_exists('X-ReturnPathDomain', $headers)) {
            $this->returnPathDomain = $headers['X-ReturnPathDomain'];
            unset($headers['X-ReturnPathDomain']);
        }

        if(!isset($headers['X-MC-Track'])) {
            if(\SendThis::config()->tracking || \SendThis::config()->api_tracking) {
                $headers['X-MC-Track'] = 'opens,clicks_htmlonly';
            }
        }

        $mandrill = \SendThis::config()->mandrill;

        if($mandrill && count($mandrill)) {
            foreach($mandrill as $setting => $value) {
                $header = 'X-MC-' . $setting;

                if(!isset($headers[$header]))
                    $headers[$header] = $value;
            }
        }

        if(\SendThis::config()->sub_account)
            $headers['X-MC-Subaccount'] = \SendThis::config()->sub_account;
    }
}