<?php namespace Milkyway\SS\SendThis\Transports;

/**
 * Milkyway Multimedia
 * AmazonSes.php
 *
 * @package milkyway-multimedia/ss-send-this
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

use GuzzleHttp\Client;
use PHPMailer;
use ViewableData;
use Psr\Http\Message\ResponseInterface;
use SimpleXMLElement;
use RuntimeException;
use Exception;

class AmazonSes extends Mail
{
    protected $params = [
        'endpoint' => 'https://email.{location}.amazonaws.com',
        'location' => 'us-east-1',
    ];

    public function start(PHPMailer $messenger, ViewableData $log = null)
    {
        if (isset($this->params['key']) && isset($this->params['secret'])) {
            if (!$messenger->PreSend()) {
                return false;
            }

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
                    'form_params' => array(
                        'Action' => 'SendRawEmail',
                        'RawMessage.Data' => base64_encode($message),
                    ),
                )
            );

            return $this->handleResponse($response, $messenger, $log);
        }

        throw new Exception('Invalid credentials. Could not connect to Amazon SES');
    }

    public function handleResponse(ResponseInterface $response, $messenger = null, $log = null)
    {
        $body = $response->getBody();
        $message = '';

        if (!$body) {
            $message = 'Empty response received from Amazon SES' . "\n";
        }

        $results = $this->parse($response);

        if ((($statusCode = $response->getStatusCode()) && ($statusCode < 200 || $statusCode > 399))) {
            if ($log) {
                $log->MessageID = $results->RequestId;
            }

            $message = 'Problem sending via Amazon SES' . "\n";

            if (($errors = $results->Error) && !empty($errors)) {
                $error = array_pop($errors);
                $message .= urldecode(http_build_query($error, '', "\n"));
            }
        }

        $messageId = '';

        if (($result = $results->SendRawEmailResult) && isset($result['MessageId'])) {
            $messageId = $result['MessageId'];
        }

        if ($message) {
            $message .= 'Status Code: ' . $response->getStatusCode() . "\n";
            $message .= 'Message: ' . $response->getReasonPhrase();

            $this->mailer->eventful()->fire(singleton('sendthis-event')->named('sendthis:failed', $this->mailer), $messageId ? $messageId : $messenger->getLastMessageID(), $messenger->getToAddresses(), $results, $results, $log);

            throw new Exception($message);
        }

        $this->mailer->eventful()->fire(singleton('sendthis-event')->named('sendthis:sent', $this->mailer), $messageId, $messenger->getToAddresses(), $results, $results, $log);

        return true;
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

    protected function endpoint()
    {
        return str_replace('{location}', $this->params['location'], $this->params['endpoint']);
    }

    public function applyHeaders(array &$headers)
    {
    }

    protected function parse(ResponseInterface $response)
    {
        $errorMessage = null;
        $internalErrors = libxml_use_internal_errors(true);
        $disableEntities = libxml_disable_entity_loader(true);
        libxml_clear_errors();

        try {
            $xml = new SimpleXMLElement((string) $response->getBody() ?: '<root />', LIBXML_NONET);
            if ($error = libxml_get_last_error()) {
                $errorMessage = $error->message;
            }
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
        }

        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);
        libxml_disable_entity_loader($disableEntities);

        if ($errorMessage) {
            throw new RuntimeException('Unable to parse response body into XML: ' . $errorMessage);
        }

        return $xml;
    }
}
