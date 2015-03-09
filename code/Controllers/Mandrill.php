<?php
/**
 * Milkyway Multimedia
 * Mandrill.php
 *
 * @package reggardocolaianni.com
 * @author  Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

namespace Milkyway\SS\SendThis\Controllers;

use Milkyway\SS\SendThis\Events\Event;
use Milkyway\SS\SendThis\Mailer;

class Mandrill extends \Controller
{
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

	public function index($request)
	{
		if ($request->isHEAD()) {
			return $this->confirmSubscription($request->getBody());
		} else {
			$response = json_decode($request->getBody(), true);
			$event = isset($response['event']) ? $response['event'] : 'unknown';
			$messageId = '';
			$email = '';

			$params = [
				'details'   => isset($response['msg']) ? $response['msg'] : [],
				'timestamp' => isset($response['ts']) ? $response['ts'] : '',
			];

			if (count($params['details'])) {
				if (isset($params['details']['_id'])) {
					$messageId = $params['details']['_id'];
				}

				if (isset($params['details']['email'])) {
					$email = $params['details']['email'];
				}
			}

			if (!$messageId && isset($response['_id'])) {
				$messageId = $response['_id'];
			}

			if ($event == 'hard_bounce') {
				$params['permanent'] = true;
			}

			if (isset($this->eventMapping[$event]))
				$event = $this->eventMapping[$event];

			if(\Email::mailer() instanceof Mailer) {
				\Email::mailer()->eventful()->fire(Event::named('sendthis:' . $event, \Email::mailer()), $messageId, $email, $params, $response);
				\Email::mailer()->eventful()->fire(Event::named('sendthis:handled', \Email::mailer()), $event, $request);
			}
		}

		$controller = $this->displayNiceView($this);
		$controller->init();

		return $controller->customise([
			'Title'   => _t('SendThis.FORBIDDEN', 'Forbidden'),
			'Content' => '<p>Please do not access this page directly</p>',
		])->renderWith($this->getTemplates());
	}

	protected function confirmSubscription($message)
	{
		if(\Email::mailer() instanceof Mailer) {
			\Email::mailer()->eventful()->fire(Event::named('sendthis:hooked', \Email::mailer()), '', '', ['subject' => 'Subscribed to Mandrill Web Hook', 'message' => $message]);
		}

		return new \SS_HTTPResponse('', 200, 'success');
	}

	protected function getTemplates($action = '') {
		$templates = ['SendThis_Mandrill', 'SendThis', 'Page', 'ContentController'];

		if($action) {
			array_unshift($templates, 'SendThis_' . $action);
			array_unshift($templates, 'SendThis_Mandrill_' . $action);
		}

		return $templates;
	}
} 