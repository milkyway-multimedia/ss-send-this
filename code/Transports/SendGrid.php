<?php namespace Milkyway\SS\SendThis\Transports;

/**
 * Milkyway Multimedia
 * SendGrid.php
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

class SendGrid extends Mail
{
    protected $params = [
        'endpoint' => 'https://api.sendgrid.com/api',
        'smtp_api' => [],
    ];

    public function start(PHPMailer $messenger, ViewableData $log = null)
    {
        if (isset($this->params['key'])) {
            // This does all checks to increase sendability
            if (!$messenger->PreSend()) {
                return false;
            }

            $response = $this->http()->post($this->endpoint('mail.send'), $this->getSendParams($messenger));

            return $this->handleResponse($response, $messenger, $log);
        }

        throw new Exception('Invalid credentials. Could not connect to SendGrid');
    }

    public function handleResponse(ResponseInterface $response, $messenger = null, $log = null)
    {
        $body = $response->getBody()->getContents();

        $message = '';

        if (!$body) {
            $message = 'Empty response received from SendGrid' . "\n";
        }

        $body = json_decode($body, true);
        $success = !empty($body['message']) && strtolower($body['message']) == 'success' ? true : false;

        if ($message || !$success || $response->getStatusCode() < 200 || $response->getStatusCode() > 399) {
            $message .= 'Status Code: ' . $response->getStatusCode() . "\n";
            $message .= 'Message: ' . $response->getReasonPhrase();

            if(!empty($body['message'])) {
                $message .= "\n" . $body['message'];
            }

            $this->mailer->eventful()->fire(singleton('sendthis-event')->named('sendthis:failed', $this->mailer),
                $messenger->getLastMessageID(), $messenger->getToAddresses(), ['message' => $message],
                ['message' => $message], $log);

            throw new Exception($message);
        }

        if(!empty($body['message'])) {
            $message = $body['message'];
        }

        $this->mailer->eventful()->fire(singleton('sendthis-event')->named('sendthis:sent', $this->mailer), $messenger->getLastMessageID(),
            $messenger->getToAddresses(), ['message' => $message], ['message' => $message], $log);

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

    protected function endpoint($action = '')
    {
        return Controller::join_links($this->params['endpoint'], $action . '.json');
    }

    /**
     * @param PHPMailer $messenger
     * @return array
     */
    protected function getSendParams(PHPMailer $messenger)
    {
        $params = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->params['key'],
            ],
        ];

        $headers = [];

        if (!empty($messenger->Priority)) {
            $headers['X-Priority'] = $messenger->Priority;
        }

        if (!empty($messenger->XMailer)) {
            $headers['X-Mailer'] = trim($messenger->XMailer);
        }

        if (!empty($messenger->ConfirmReadingTo)) {
            $headers['Disposition-Notification-To'] = $messenger->ConfirmReadingTo;
        }

        foreach($messenger->getCustomHeaders() as $header) {
            $headers[$header[0]] = $header[1];
        }

        $headers['X-Sendthismessageid'] = $messenger->getLastMessageID();

        $smtpApi = $this->params['smtp_api'];

        if (isset($headers['X-SMTPAPI'])) {
            $smtpApiHeader = is_array($headers['X-SMTPAPI']) ? $headers['X-SMTPAPI'] : json_decode($headers['X-SMTPAPI'],
                true);
            $smtpApi = array_merge($smtpApi, $smtpApiHeader);
            unset($headers['X-SMTPAPI']);
        }

        if (isset($headers['X-SendAt'])) {
            if(!isset($smtpApi['send_each_at'])) {
                $smtpApi['send_at'] = $headers['X-SendAt'];
            }

            unset($headers['X-SendAt']);
        }

        if(!$this->param('dont_bypass_filters_for_priority_emails') && isset($headers['X-Priority']) && $headers['X-Priority'] == 1) {
            if(!isset($smtpApi['filters'])) {
                $smtpApi['filters'] = [];
            }

            $smtpApi['filters']['bypass_list_management'] = [
                'settings' => [
                    'enabled' => 1,
                ]
            ];
        }

        if ((!isset($smtpApi['filters']) || !isset($smtpApi['filters']['clicktrack']) || !isset($smtpApi['filters']['opentrack'])) && (isset($this->params['tracking']) || isset($this->params['api_tracking']))) {
            if(!isset($smtpApi['filters'])) {
                $smtpApi['filters'] = [];
            }

            if(!isset($smtpApi['filters']['clicktrack'])) {
                $smtpApi['filters']['clicktrack'] = [
                    'settings' => [
                        'enabled' => 1,
                    ]
                ];
            }

            if(!isset($smtpApi['filters']['opentrack'])) {
                $smtpApi['filters']['opentrack'] = [
                    'settings' => [
                        'enabled' => 1,
                    ]
                ];
            }
        }

        if(!isset($smtpApi['unique_args'])) {
            $smtpApi['unique_args'] = [];
        }

        $smtpApi['unique_args']['send_this_message_id'] = $headers['X-Sendthismessageid'];

        $fields = [
            'subject' => $messenger->Subject,
            'html'    => $messenger->Body,
            'text'    => $messenger->AltBody,
            'from'    => $messenger->Sender,
            'headers' => json_encode($headers),
        ];

        if (!empty($smtpApi)) {
            $fields['x-smtpapi'] = json_encode($smtpApi);
        }

        $attachments = [];

        if (!empty($messenger->FromName)) {
            $fields['fromname'] = $messenger->FromName;
        }

        foreach (['To', 'Cc', 'Bcc'] as $kind) {
            if($kind == 'To' && isset($smtpApi['to'])) {
                continue;
            }

            $emails = $messenger->{'get' . $kind . 'Addresses'}();

            if (!empty($emails)) {
                $kind = strtolower($kind);

                if (!isset($fields[$kind])) {
                    $fields[$kind] = [];
                }

                foreach ($emails as $i => $email) {
                    $email = (array)$email;

                    $fields[$kind][] = $email[0];

                    if (!empty($email[1])) {
                        if (!isset($fields[$kind . 'name'])) {
                            $fields[$kind . 'name'] = [];
                        }

                        $fields[$kind . 'name'] = $email[1];
                    }
                }

                if(count($fields[$kind]) == 1) {
                    $fields[$kind] = array_pop($fields[$kind]);

                    if (isset($fields[$kind . 'name'])) {
                        $fields[$kind . 'name'] = array_pop($fields[$kind . 'name']);
                    }
                }
            }
        }

        if (!empty($messenger->getReplyToAddresses())) {
            foreach ($messenger->getReplyToAddresses() as $email) {
                $fields['replyto'][] = $email[0];
            }

            $fields['replyto'] = implode(',', $fields['replyto']);
        }

        if (!empty($messenger->getAttachments())) {
            $fields['files'] = [];

            $included = [];
            $contentIds = [];

            foreach ($messenger->getAttachments() as $attachment) {
                $hash = md5(serialize($attachment));

                if (in_array($hash, $included)) {
                    continue;
                }

                $included[] = $hash;

                if ($attachment[6] == 'inline' && isset($contentIds[$attachment[7]])) {
                    continue;
                } else {
                    if ($attachment[6] == 'inline') {
                        $contentIds[$attachment[7]] = $attachment[1];
                    }
                }

                $attachments[] = [
                    'name'     => $attachment[2],
                    'filename' => $attachment[1],
                    'contents' => $attachment[5] ? $messenger->encodeString($attachment[0]) : $messenger->encodeString(file_get_contents($attachment[0])),
                ];

                $fields['files'][$attachment[2]] = $attachment[1];
            }

            if (!empty($contentIds)) {
                $fields['content'] = array_flip($contentIds);
            }
        }

//        if (empty($attachments)) {
//            $params['form_params'] = $fields;
//            return $params;
//        } else {
            foreach ($fields as $name => $contents) {
                if(is_array($contents)) {
                    foreach ($contents as $content) {
                        $params['multipart'][] = [
                            'name'     => $name,
                            'contents' => $content,
                        ];
                    }
                }
                else {
                    $params['multipart'][] = [
                        'name'     => $name,
                        'contents' => $contents,
                    ];
                }
            }

            foreach ($attachments as $attachment) {
                $params['multipart'][] = $attachment;
            }
            return $params;
//        }
    }
}
