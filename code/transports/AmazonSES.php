<?php namespace Milkyway\SS\SendThis\Transports;
use Milkyway\SS\SendThis\Events\Event;

/**
 * Milkyway Multimedia
 * SendThis_AmazonSES.php
 *
 * @package milkyway-multimedia/silverstripe-send-this
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */
class AmazonSes extends Mail {
	protected $params = [
		'endpoint' => 'https://email.{location}.amazonaws.com',
		'location' => 'us-east-1',
	];

    function start(\PHPMailer $messenger, \ViewableData $log = null)
    {
        if(isset($this->params['key']) && isset($this->params['secret'])) {
            if(!$messenger->PreSend())
                return false;

            $date = date('r');
            $message = $messenger->GetSentMIMEMessage();
            $userAgent = getenv('sendthis_user_agent') ?: isset($_ENV['sendthis_user_agent']) ? $_ENV['sendthis_user_agent'] : str_replace(' ', '', singleton('LeftAndMain')->ApplicationName) . '~AmazonSES';

            $response = $this->http()->post($this->endpoint(), array(
                    'headers' => array(
                        'User-Agent' => $userAgent,
                        'Date' => $date,
                        'Host' => str_replace(array('http://', 'https://'), '', $this->endpoint()),
                        'Content-Type' => 'application/x-www-form-urlencoded',
                        'X-Amzn-Authorization' => 'AWS3-HTTPS AWSAccessKeyId=' . urlencode($this->params['key']) . ',Algorithm=HmacSHA256,Signature=' . base64_encode(hash_hmac('sha256', $date, $this->params['secret'], true)),
                    ),
                    'body' => array(
                        'Action' => 'SendRawEmail',
                        'RawMessage.Data' => base64_encode($message),
                    ),
                )
            );

            return $this->handleResponse($response, $messenger, $log);
        }

        throw new Exception('Invalid credentials. Could not connect to Amazon SES');
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
            throw new Exception($message);
        }

        $messageId = '';

        if(($result = $results->SendRawEmailResult) && isset($result['MessageId']))
            $messageId = $result['MessageId'];

        $this->mailer->eventful()->fire(Event::named('sendthis.sent', $this->mailer), $messageId, $messenger->getToAddresses(), $results, $results, $log);

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
        return str_replace('{location}', $this->params['location'], $this->params['endpoint']);
    }

    function applyHeaders(array &$headers) {

    }
}