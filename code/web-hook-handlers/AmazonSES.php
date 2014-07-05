<?php namespace Milkyway\SendThis\WebHookHandlers;
/**
 * Milkyway Multimedia
 * AmazonSES.php
 *
 * @package milkyway-multimedia/silverstripe-send-this
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */
class AmazonSES {
    public function handleWebHook($request) {
        $body = $request->getBody();
        $data = json_decode($body);

        if($data && isset($data->Message))
            $response = $data->Message ? json_decode($data->Message, true) : array();
        else
            $response = $body ? json_decode($body, true) : array();

        if(isset($data->Type)) {
            if($data->Type == 'SubscriptionConfirmation' && isset($data->SubscribeURL)) {
                $this->confirmSubscription($data->SubscribeURL, nl2br(print_r($data, true)));
            }
            elseif(($data->Type == 'Bounce' || $data->Type == 'Notification') && isset($response['notificationType']) && $response['notificationType'] == 'Bounce') {
                $this->doBounceHandling($response);
            }
            elseif(($data->Type == 'Complaint' || $data->Type == 'Notification') && isset($response['notificationType']) && $response['notificationType'] == 'Complaint') {
                $this->doComplaintHandling($response);
            }
        }
    }

    protected function confirmSubscription($url, $message = '') {
        if(SendThis::config()->debugging && $email = Email::config()->admin_email) {
            mail(
                $email,
                'Amazon SNS - Subscription Confirmation received',
                $message,
                "Content-type: text/html\nFrom: " . $email
            );
        }

        file_get_contents($url);
    }

    protected function doBounceHandling($response) {
        if(isset($response['bounce']) && isset($response['bounce']['bouncedRecipients'])) {
            $bounces = is_array($response['bounce']['bouncedRecipients']) ? $response['bounce']['bouncedRecipients'] : array($response['bounce']['bouncedRecipients']);

            foreach($bounces as $bounce) {
                if(isset($bounce['emailAddress'])) {
                    $permanent = (isset($response['bounce']['bounceType']) && $response['bounce']['bounceType'] == 'Permanent');
                    $messageId = isset($response['mail']) && isset($response['mail']['messageId']) ? $response['mail']['messageId'] : '';

                    SendThis::fire('bounced', $messageId, $bounce['emailAddress'], ['blacklist' => $permanent, 'details' => $bounce], $response);
                }
            }
        }
    }

    protected function doComplaintHandling($response) {
        if(isset($response['complaint']) && isset($response['complaint']['complainedRecipients'])) {
            $complaints = is_array($response['complaint']['complainedRecipients']) ? $response['complaint']['complainedRecipients'] : array($response['complaint']['complainedRecipients']);

            foreach($complaints as $complaint) {
                if(isset($complaint['emailAddress'])) {
                    $messageId = isset($response['mail']) && isset($response['mail']['messageId']) ? $response['mail']['messageId'] : '';
                    SendThis::fire('spam', $messageId, $complaint['emailAddress'], ['blacklist' => true, 'details' => $complaint], $response);
                }
            }
        }
    }
} 