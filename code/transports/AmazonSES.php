<?php namespace Milkyway\SS\SendThis\Transports;

/**
 * Milkyway Multimedia
 * SendThis_AmazonSES.php
 *
 * @package milkyway-multimedia/silverstripe-send-this
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */
class AmazonSES extends Mail {
    protected $endpoint = 'https://email.{location}.amazonaws.com';
    protected $location = 'us-east-1';

    function __construct(\PHPMailer $messenger) {
        parent::__construct($messenger);

        if(\SendThis::config()->endpoint)
            $this->endpoint = \SendThis::config()->endpoint;

        if(\SendThis::config()->location)
            $this->location = \SendThis::config()->location;
    }

    function start(\PHPMailer $messenger, \ViewableData $log = null)
    {
        if(($key = \SendThis::config()->key) && ($secret = \SendThis::config()->secret)) {
            if(!$messenger->PreSend())
                return false;

            $date = date('r');
            $message = $messenger->GetSentMIMEMessage();
            $userAgent = getenv('sendthis_user_agent') ?: isset($_ENV['sendthis_user_agent']) ? $_ENV['sendthis_user_agent'] : str_replace(' ', '', singleton('LeftAndMain')->ApplicationName) . '~AmazonSES';

            $response = $this->http()->post($this->endpoint(), array(
                    'headers' => array(
                        'User-Agent' => $userAgent,
                        'Date' => $date,
                        'Host' => str_replace(array('http://', 'https://'), '', $this->endpoint),
                        'Content-Type' => 'application/x-www-form-urlencoded',
                        'X-Amzn-Authorization' => 'AWS3-HTTPS AWSAccessKeyId=' . urlencode($key) . ',Algorithm=HmacSHA256,Signature=' . base64_encode(hash_hmac('sha256', $date, $secret, true)),
                    ),
                    'body' => array(
                        'Action' => 'SendRawEmail',
                        'RawMessage.Data' => base64_encode($message),
                    ),
                )
            );

            return $this->handleResponse($response, $messenger, $log);
        }

        throw new \SendThis_Exception('Invalid credentials. Could not connect to Amazon SES');
    }

    public function handleResponse(\GuzzleHttp\Message\ResponseInterface $response, $messenger = null, $log = null) {
        $body = $response->getBody();
        $message = '';

        if(!$body)
            $message = 'Empty response received from Amazon SES' . "\n";

        $results = $response->xml();

        if((($statusCode = $response->getStatusCode()) && ($statusCode < 200 || $statusCode > 399))) {
            if($log)
                $log->MessageID = $results->RequestId;

            $message = 'Problem sending via Amazon SES' . "\n";

            if(($errors = $results->Error) && count($errors)) {
                $error = array_pop($errors);
                $message .= urldecode(http_build_query($error, '', "\n"));
            }
        }

        if($message) {
            $message .= 'Status Code: ' . $response->getStatusCode() . "\n";
            $message .= 'Message: ' . $response->getReasonPhrase();
            throw new \SendThis_Exception($message);
        }

        $messageId = '';

        if(($result = $results->SendRawEmailResult) && isset($result['MessageId']))
            $messageId = $result['MessageId'];

        SendThis::fire('sent', $messageId, $messenger->getToAddresses(), $results, $results, $log);

        return true;
    }

    /**
     * Get a new HTTP client instance.
     *
     * @return \GuzzleHttp\Client
     */
    protected function http()
    {
        return new \GuzzleHttp\Client;
    }

    protected function endpoint()
    {
        return str_replace('{location}', $this->location, $this->endpoint);
    }

    function applyHeaders(array &$headers) {

    }
}