<?php namespace Milkyway\SS\SendThis\Transports;

/**
 * Milkyway Multimedia
 * Mailgun.php
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

class Mailgun extends Mail
{
    protected $params = [
        'endpoint' => 'https://api.mailgun.net/v3',
        'domain'   => '',
        'user'     => 'api',
    ];

    protected $allowedPostParams = [
        'o:tag',
        'o:campaign',
        'o:deliverytime',
        'o:dkim',
        'o:testmode',
        'o:tracking',
        'o:tracking-clicks',
        'o:tracking-opens',
    ];

    public function start(PHPMailer $messenger, ViewableData $log = null)
    {
        if (isset($this->params['key'])) {
            // This does all checks to increase sendability
            if (!$messenger->PreSend()) {
                return false;
            }

            $response = $this->http()->post($this->endpoint('messages.mime'), $this->getSendParams($messenger));

            return $this->handleResponse($response, $messenger, $log);
        }

        throw new Exception('Invalid credentials. Could not connect to Mailgun');
    }

    public function handleResponse(ResponseInterface $response, $messenger = null, $log = null)
    {
        $body = $response->getBody()->getContents();

        $message = '';

        if (!$body) {
            $message = 'Empty response received from Mailgun' . "\n";
        }

        $body = json_decode($body, true);

        if ($message || $response->getStatusCode() < 200 || $response->getStatusCode() > 399) {
            $message .= 'Status Code: ' . $response->getStatusCode() . "\n";
            $message .= 'Message: ' . $response->getReasonPhrase();

            if (!empty($body['message'])) {
                $message .= "\n" . $body['message'];
            }

            $this->mailer->eventful()->fire(singleton('sendthis-event')->named('sendthis:failed', $this->mailer),
                (empty($body['id']) ? $messenger->getLastMessageID() : $body['id']), $messenger->getToAddresses(),
                ['message' => $message],
                ['message' => $message], $log);

            throw new Exception($message);
        }

        if (!empty($body['message'])) {
            $message = $body['message'];
        }

        $this->mailer->eventful()->fire(singleton('sendthis-event')->named('sendthis:sent', $this->mailer),
            (empty($body['id']) ? $messenger->getLastMessageID() : $body['id']),
            $messenger->getToAddresses(), ['message' => $message], ['message' => $message], $log);

        return true;
    }

    public function applyHeaders(array &$headers)
    {
        if (isset($headers['X-Track']) || isset($this->params['tracking']) || isset($this->params['api_tracking'])) {
            $this->params['tracking'] = isset($headers['X-Track']) ? $headers['X-Track'] : (isset($this->params['tracking']) ? isset($this->params['tracking']) : isset($this->params['api_tracking']));

            if (isset($headers['X-Track'])) {
                unset($headers['X-Track']);
            }
        }

        if (!empty($headers['X-SendAt'])) {
            $this->params['o:deliverytime'] = $headers['X-SendAt'];
            unset($headers['X-SendAt']);
        }

        if (array_key_exists('X-Campaign', $headers)) {
            $this->params['o:campaign'] = $headers['X-Campaign'];
            unset($headers['X-Campaign']);
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
        $domain = $this->param('domain') ?: singleton('director')->baseWebsiteURL();
        return Controller::join_links($this->param('endpoint'), $domain, $action);
    }

    /**
     * @param PHPMailer $messenger
     * @return array
     */
    protected function getSendParams(PHPMailer $messenger)
    {
        $params = [
            'auth' => [$this->param('user'), $this->param('key'),],
        ];

        $fields = [
            'to' => implode(',', array_keys($messenger->getAllRecipientAddresses())),
        ];

        if (!empty($this->params['tracking']) || !empty($this->params['api_tracking'])) {
            $fields['o:tracking'] = empty($this->params['tracking']) ? $this->params['api_tracking'] : $this->params['tracking'];

            if (is_bool($fields['o:tracking'])) {
                $fields['o:tracking'] = $fields['o:tracking'] ? 'yes' : 'no';
            }
        }

        foreach ($this->params as $param => $value) {
            if (!in_array($param, $this->allowedPostParams) && strpos($param, 'h:X-') !== 0 && strpos($param,
                    'v:') !== 0
            ) {
                continue;
            }

            $fields[$param] = $value;
        }

        foreach ($fields as $name => $contents) {
            if (is_array($contents)) {
                foreach ($contents as $content) {
                    $params['multipart'][] = [
                        'name'     => $name,
                        'contents' => $content,
                    ];
                }
            } else {
                $params['multipart'][] = [
                    'name'     => $name,
                    'contents' => $contents,
                ];
            }
        }

        $params['multipart'][] = [
            'name'     => 'message',
            'filename' => 'message.mime',
            'contents' => $messenger->getSentMIMEMessage(),
        ];

        return $params;
    }
}
