<?php namespace Milkyway\SS\SendThis\Controllers;

/**
 * Milkyway Multimedia
 * AmazonSes.php
 *
 * @package milkyway-multimedia/ss-send-this
 * @author Mellisa Hankins <mellisa.hankins@me.com>
 */

use Milkyway\SS\SendThis\Mailer;
use Controller;
use Email;

class AmazonSes extends Controller
{
	public function index($request)
	{
		$body = $request->getBody();
		$data = json_decode($body);

		if ($data && isset($data->Message)) {
			$response = $data->Message ? json_decode($data->Message, true) : [];
		} else {
			$response = $body ? json_decode($body, true) : [];
		}

		if (isset($data->Type)) {
			if ($data->Type == 'SubscriptionConfirmation' && isset($data->SubscribeURL)) {
				$this->confirmSubscription($data->SubscribeURL, nl2br(print_r($data, true)));
			} elseif (($data->Type == 'Bounce' || $data->Type == 'Notification') && isset($response['notificationType']) && $response['notificationType'] == 'Bounce') {
				$this->doBounceHandling($response);
			} elseif (($data->Type == 'Complaint' || $data->Type == 'Notification') && isset($response['notificationType']) && $response['notificationType'] == 'Complaint') {
				$this->doComplaintHandling($response);
			} elseif (($data->Type == 'Delivery' || $data->Type == 'Notification') && isset($response['notificationType']) && $response['notificationType'] == 'Delivery') {
				$this->doDeliveryHandling($response);
			}

			if (Email::mailer() instanceof Mailer) {
				Email::mailer()->eventful()->fire(singleton('sendthis-event')->named('sendthis:handled', Email::mailer()), $data->Type,
					$request);
			}
		}

		$controller = $this->displayNiceView($this);
		$controller->init();

		return $controller->customise([
			'Title'   => _t('SendThis.FORBIDDEN', 'Forbidden'),
			'Content' => '<p>Please do not access this page directly</p>',
		])->renderWith($this->getTemplates());
	}

	protected function confirmSubscription($url, $message = '')
	{
		if (Email::mailer() instanceof Mailer) {
			Email::mailer()->eventful()->fire(singleton('sendthis-event')->named('sendthis:hooked', Email::mailer()), '', '',
				['subject' => 'Subscribed to Amazon SNS', 'message' => $message]);
		}

		return file_get_contents($url);
	}

	protected function doBounceHandling($response)
	{
		if (isset($response['bounce']) && isset($response['bounce']['bouncedRecipients'])) {
			$bounces = is_array($response['bounce']['bouncedRecipients']) ? $response['bounce']['bouncedRecipients'] : [$response['bounce']['bouncedRecipients']];

			foreach ($bounces as $bounce) {
				if (isset($bounce['emailAddress'])) {
					$permanent = (isset($response['bounce']['bounceType']) && $response['bounce']['bounceType'] == 'Permanent');
					$messageId = isset($response['mail']) && isset($response['mail']['messageId']) ? $response['mail']['messageId'] : '';

					$message = [];

					if (isset($bounce['status'])) {
						$message[] = 'Status: ' . $bounce['status'];
					}
					if (isset($bounce['action'])) {
						$message[] = 'Action: ' . $bounce['action'];
					}
					if (isset($bounce['diagnosticCode'])) {
						$message[] = 'Diagnostic Code: ' . $bounce['diagnosticCode'];
					}

					if (!empty($message)) {
						$message = "\n\nBounce Details:\n" . implode("\n", $message);
					} else {
						$message = 'Bounced';
					}

					if (Email::mailer() instanceof Mailer) {
						Email::mailer()->eventful()->fire(singleton('sendthis-event')->named('sendthis:bounced', Email::mailer()),
							$messageId, $bounce['emailAddress'],
							['permanent' => $permanent, 'message' => $message, 'details' => $bounce], $response);
					}
				}
			}
		}
	}

	protected function doComplaintHandling($response)
	{
		if (isset($response['complaint']) && isset($response['complaint']['complainedRecipients'])) {
			$complaints = is_array($response['complaint']['complainedRecipients']) ? $response['complaint']['complainedRecipients'] : [$response['complaint']['complainedRecipients']];

			foreach ($complaints as $complaint) {
				if (isset($complaint['emailAddress'])) {
					$messageId = isset($response['mail']) && isset($response['mail']['messageId']) ? $response['mail']['messageId'] : '';

					$message = sprintf('%s has logged a complaint, and has been blocked from receiving emails from this domain%s',
						$complaint['emailAddress'],
						isset($complaint['complaintFeedbackType']) ? '. Reason: ' . $complaint['complaintFeedbackType'] : ''
					);

					if (Email::mailer() instanceof Mailer) {
						Email::mailer()->eventful()->fire(singleton('sendthis-event')->named('sendthis:spam', Email::mailer()), $messageId,
							$complaint['emailAddress'],
							['blacklist' => true, 'details' => $complaint, 'message' => $message], $response);
					}
				}
			}
		}
	}

	protected function doDeliveryHandling($response)
	{
		if (isset($response['delivery']) && isset($response['delivery']['recipients'])) {
			$recipients = is_array($response['delivery']['recipients']) ? $response['delivery']['recipients'] : [$response['delivery']['recipients']];

			foreach ($recipients as $recipient) {
				$messageId = isset($response['mail']) && isset($response['mail']['messageId']) ? $response['mail']['messageId'] : '';
				if (Email::mailer() instanceof Mailer) {
					Email::mailer()->eventful()->fire(singleton('sendthis-event')->named('sendthis:delivered', Email::mailer()), $messageId,
						$recipient, [
							'details'   => $response['delivery'],
							'timestamp' => isset($response['delivery']['timestamp']) ? strtotime($response['delivery']['timestamp']) : '',
						], $response
					);
				}
			}
		}
	}

	protected function getTemplates($action = '')
	{
		$templates = ['SendThis_AmazonSes', 'SendThis', 'Page', 'ContentController'];

		if ($action) {
			array_unshift($templates, 'SendThis_' . $action);
			array_unshift($templates, 'SendThis_AmazonSes_' . $action);
		}

		return $templates;
	}
} 