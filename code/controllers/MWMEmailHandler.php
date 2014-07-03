<?php
/**
 * Milkyway Multimedia
 * MWMEmailHandler.php
 *
 * Add a controller specifically for allowing handling of
 * emails via a third party application (Amazon SNS) or
 * the owner of the site
 *
 * @package mwm/email
 * @author Mellisa Hankins <mellisa.hankins@me.com>
 */

class MWMEmailHandler extends Controller {
	private static $allowed_actions = array(
		'index',

		'manage',
		'ManageForm',

		'verify',
		'send-verification-email',
		'send_verification_email',
		'unsubscribe',

		'block',
		'BlockForm',

		'unblock',
		'UnblockForm',

		'links',
	);

	function Link($action = '') {
		if($action) $action = '/' . $action;
		return MWMDirector::get_link_for_controller(get_class()) . $action;
	}

	function index($r) {
		$controller = MWMDirector::create_view($this);

		$controller->init();

		$post = $r->getBody();
		$msg = json_decode($post);

		if($msg && isset($msg->Message))
			$msgArr = $msg->Message ? json_decode($msg->Message, true) : array();
		else
			$msgArr = $post ? json_decode($post, true) : array();

		if(!$msg) {
			return $controller->customise(array(
				'Title' => _t('MWMEmailHandler.FORBIDDEN', 'Forbidden'),
				'Content' => '<p>Please do not access this page directly</p>',
			))->renderWith($this->getTemplates());
		}

		$from = SiteConfig::current_site_config()->AdminEmail;

		if(isset($msg->Type) && $msg->Type == 'SubscriptionConfirmation' && isset($msg->SubscribeURL)) {
			if(MWMMailer::settings()->debugging) {
				mail(
					'webmaster@stavro.com.au',
					'Amazon SNS - Subscription Confirmation received',
					nl2br(print_r($msg, true)),
					"Content-type: text/html\nFrom: " . $from
				);
			}

			file_get_contents($msg->SubscribeURL);
		}
		elseif(($msg->Type == 'Bounce' || $msg->Type == 'Notification') && isset($msgArr['notificationType']) && $msgArr['notificationType'] == 'Bounce') {
			if(isset($msgArr['bounce']) && isset($msgArr['bounce']['bouncedRecipients'])) {
				$bounces = is_array($msgArr['bounce']['bouncedRecipients']) ? $msgArr['bounce']['bouncedRecipients'] : array($msgArr['bounce']['bouncedRecipients']);
				$blacklistAfter = MWMMailer::settings()->blacklist_after_bounced ? MWMMailer::settings()->blacklist_after_bounced : 1;
				foreach($bounces as $bounce) {
					if(isset($bounce['emailAddress'])) {
						$permanent = (isset($msgArr['bounce']['bounceType']) && $msgArr['bounce']['bounceType'] == 'Permanent');

						if(ClassInfo::exists('Recipient') && $recipient = Recipient::get()->filter('Email', $bounce['emailAddress'])->first()) {
							$recipient->BouncedCount = $recipient->BouncedCount + 1;

							if($recipient->ReceivedCount > 0)
								$recipient->ReceivedCount = $recipient->ReceivedCount - 1;

							if($recipient->BouncedCount >= $blacklistAfter || $permanent) {
								$recipient->Blacklisted = 1;
							}

							$recipient->write();
						}

						$base = '@' . MWMDirector::baseWebsiteURL();

						if(!(substr($bounce['emailAddress'], -strlen($base)) === $base)) {
							$bounceLog = MWMMailer_Bounce::create();
							$bounceLog->Email = $bounce['emailAddress'];
							$bounceLog->Message = print_r($msgArr, true);

							if (MWMMailer_Bounce::get()->filter('Email', $bounce['emailAddress'])->count() >= $blacklistAfter || $permanent) {
								$msg = "\n\n" . print_r($msgArr, true);
								$this->blacklist($bounce['emailAddress'], $permanent ? 'Permanent Bounce' . $msg : 'Bounced too many times' . $msg);
							}
						}

						if(isset($msgArr['mail']) && isset($msgArr['mail']['messageId']) && $mId = $msgArr['mail']['messageId']) {
							$logs = MWMMailer_Log::get()->filter('MessageID', $mId);

							if($logs->exists()) {
								foreach($logs as $log) {
									$log->Success = false;

									$msg = array();

									if(isset($bounce['status']))
										$msg[] = 'Status: ' . $bounce['status'];
									if(isset($bounce['action']))
										$msg[] = 'Action: ' . $bounce['action'];
									if(isset($bounce['diagnosticCode']))
										$msg[] = 'Diagnostic Code: ' . $bounce['diagnosticCode'];

									if($msg)
										$log->Notes = $log->Notes . "\n\nBounce Details:\n" . implode("\n", $msg);
									elseif(!$log->Notes)
										$log->Notes = 'Bounced';

									if(isset($bounceLog))
										$bounceLog->LogID = $log->ID;

									$log->bounced($bounce['emailAddress']);
									$log->write();
								}
							}
						}

						if(isset($bounceLog))
							$bounceLog->write();
					}
				}
			}
		}
		elseif(($msg->Type == 'Complaint' || $msg->Type == 'Notification') && isset($msgArr['notificationType']) && $msgArr['notificationType'] == 'Complaint') {
			if(isset($msgArr['complaint']) && isset($msgArr['complaint']['complainedRecipients'])) {
				$complaints = is_array($msgArr['complaint']['complainedRecipients']) ? $msgArr['complaint']['complainedRecipients'] : array($msgArr['complaint']['complainedRecipients']);

				foreach($complaints as $complaint) {
					if(isset($complaint['emailAddress'])) {
						$msg = sprintf('%s has logged a complaint, and has been blocked from receiving emails from this domain%s',
							$complaint['emailAddress'],
							isset($msgArr['complaint']['complaintFeedbackType']) ? '. Reason: ' . $msgArr['complaint']['complaintFeedbackType'] : ''
						);

						if(ClassInfo::exists('Recipient') && $recipient = Recipient::get()->filter('Email', $complaint['emailAddress'])->first()) {
							$recipient->Blacklisted = 1;
							$recipient->write();
						}

						$base = '@' . MWMDirector::baseWebsiteURL();

						if(!(substr($complaint, -strlen($base)) === $base)) {
							$this->blacklist($complaint['emailAddress'], $msg . "\n\n" . print_r($msgArr, true));
						}

						if(isset($msgArr['mail']) && isset($msgArr['mail']['messageId']) && $mId = $msgArr['mail']['messageId']) {
							$logs = MWMMailer_Log::get()->filter('MessageID', $mId);

							if($logs->exists()) {
								foreach($logs as $log) {
									$log->Notes = $msg;
									$log->write();
								}
							}
						}

						if(isset($bounceLog))
							$bounceLog->write();
					}
				}
			}
		}
		elseif(isset($msg->Type)) {
			$subject = isset($msg->Subject) ? $msg->Subject : $msg->Type;
			mail(
				'webmaster@stavro.com.au',
				'Amazon SNS - ' . $subject,
				$msg->Message . '<p>' . nl2br(print_r($msgArr, true)) . '</p>',
				"Content-type: text/html\nFrom: " . $from
			);
		}

		return $controller->customise(array(
			'Title' => _t('MWMEmailHandler.FORBIDDEN', 'Forbidden'),
			'Content' => '<p>Please do not access this page directly</p>',
		))->renderWith($this->getTemplates());
	}

	function manage($r) {
		$controller = MWMDirector::create_view($this);

		$controller->init();

		return $controller->customise(array(
			'Title' => _t('MWMEmailHandler.MANAGE_YOUR_SUBSCRIPTION', 'Manage your subscription'),
			'Form' => $this->ManageForm()
		))->renderWith($this->getTemplates('verify'));
	}

	function verify($r) {
		if(!$this->isNewsletterInstalled())
			return $this->httpError(404);

		$controller = MWMDirector::create_view($this);

		$controller->init();

		$hash = Convert::raw2sql($r->param('Email'));

		if($hash && ($recipient = Recipient::get()->filter('ValidateHash', $hash)->first()) && $recipient->exists()) {
			$now = date('Y-m-d H:i:s');

			$lists = trim(implode(', ', $recipient->MailingLists()->column('Title')));

			if($recipient->Verified) {
				$content = _t('Recipient.EMAIL_ALREADY_CONFIRMED', 'Your email has already been verified and is subscribed to the following lists: {lists}', array(
					'lists' => $lists
				));
			}
			elseif($now <= $recipient->ValidateHashExpired) {
				$recipient->Verified = true;

				$days = UnsubscribeController::get_days_unsubscribe_link_alive();
				$recipient->ValidateHashExpired = date('Y-m-d H:i:s', time() + (86400 * $days));

				$recipient->write();

				$content = _t('Recipient.EMAIL_CONFIRMED', 'Your email has been verified and is subscribed to the following lists: {lists}', array(
					'lists' => $lists
				));

				if($recipient->config()->send_email_after_verification) {
					$recipient->sendEmail('Newsletter_ConfirmVerificationEmail', array(
						'Subject' => _t('Recipient.SUBJECT-EMAIL_COMFIRMED', 'Subscription confirmed'),
						'Body' => $content,
					));
				}
			}
			else {
				$content = _t('Recipient.VALIDATE_HASH_EXPIRED', 'Your validation hash has expired. <a href="{url}" class="modal-control">Click here to resend the verification email to verify your email address and begin receiving emails from the following {lists}.</a>', array(
					'lists' => $lists,
					'url' => $this->Link('send-verification-email', $hash),
				));
			}

			$url = $this->Link('manage/' . $recipient->ValidateHash);
		}
		else {
			$content = _t('Recipient.NO_USER_MATCHING_HASH', 'Could not find a matching recipient to verify. Maybe you are not subscribed to our mailing list?');
			$url = $this->Link('manage');
		}

		$link = _t('MWMEmailHandler.MANAGE_YOUR_SUSBCRIPTION', '<a href="{url}" class="btn btn-primary">Manage your subscription</a>', array(
			'url' => $url,
		));

		return $controller->customise(array(
			'Title' => _t('MWMEmailHandler.VERIFY', 'Verify your email'),
			'Content' => '<p class="alert alert-info alert-block">' . $content . '</p><p class="text-center">' . $link . '</p>',
		))->renderWith($this->getTemplates('verify'));
	}

	function send_verification_email($r) {
		if(!$this->isNewsletterInstalled()) {
			if($r && $r->isAjax())
				return MWMDirector::ajax_response(_t('MWMEmailHandler.NO_MAILING_LISTS', 'No mailing lists available'), 404, 'error');
			else
				return $this->httpError(404);
		}

		$hash = Convert::raw2sql($r->param('Email'));
		$success = true;

		if($hash && ($recipient = Recipient::get()->filter('ValidateHash', $hash)->first()) && $recipient->exists()) {
			$lists = trim(implode(', ', $recipient->MailingLists()->column('Title')));

			$recipient->sendVerificationEmail();

			$content = _t('Recipient.VERIFICATION_EMAIL_SENT', 'A verification email has been sent to {email}. Please click the link in your email to complete your subscription to {lists}', array(
				'email' => $recipient->Email,
				'lists' => $lists,
			));
		}
		else {
			$content = _t('Recipient.NO_USER_MATCHING_HASH', 'Could not find a matching recipient to verify. Maybe you are not subscribed to any of our mailing lists?');
			$success = false;
		}

		if($r && $r->isAjax()) {
			if(in_array('text/html', $r->getAcceptMimetypes()))
				return $content;
			else
				return MWMDirector::ajax_response($content, 200, $success ? 'success' : 'fail');
		}
		else {
			$controller = MWMDirector::create_view($this);

			$controller->init();

			return $controller->customise(array(
				'Title' => _t('Recipient.TITLE-VERIFICATION_EMAIL_SENT', 'Verification email sent'),
				'Content' => '<p class="alert alert-info alert-block">' . $content . '</p>',
			))->renderWith($this->getTemplates('send_verification_email'));
		}
	}

	function unsubscribe($r) {
		if(!$this->isNewsletterInstalled())
			return $this->httpError(404);

		$controller = MWMDirector::create_view($this);

		$controller->init();

		$hash = Convert::raw2sql($r->param('Email'));

		if($hash && ($recipient = Recipient::get()->filter('ValidateHash', $hash)->first()) && $recipient->exists()) {
			$selectedLists = Convert::raw2sql($r->param('Lists'));

			$lists = $selectedLists ? $recipient->MailingLists()->byIDs(array_map('trim', explode(',', $selectedLists))) : $recipient->MailingLists();

			if($lists->exists()) {
				foreach($lists as $list) {
					$recipient->MailingLists()->remove($list);
					$unsubscribe = UnsubscribeRecord::create();
					$unsubscribe->unsubscribe($recipient, $list);
				}

				$content = _t('Recipient.UNSUBSCRIBED_FROM_LISTS', 'You are now unsubscribed from the following list(s): {lists}. <a href="{link}">To manage subscriptions to our mailing list, please click here</a>.', array(
					'lists' => trim(implode(', ', $recipient->MailingLists()->column('Title'))),
					'link' => $this->Link('manage/' . $recipient->ValidateHash)
				));
			}
			else {
				$content = _t('Recipient.SUBSCRIBED_TO_NO_LISTS', 'You are not subscribed to any mailing list. <a href="{link}">To manage your subscription to our mailing list, please click here</a>.', array(
					'link' => $this->Link('manage/' . $recipient->ValidateHash)
				));
			}

			$url = $this->Link('manage/' . $recipient->ValidateHash);
		}
		else {
			$content = _t('Recipient.NO_USER_MATCHING_HASH', 'Could not find a matching recipient to verify. Maybe you are not subscribed to any of our mailing lists?');
			$url = $this->Link('manage');
		}

		$link = _t('MWMEmailHandler.MANAGE_YOUR_SUSBCRIPTION', '<a href="{url}" class="btn btn-primary">Manage your subscription</a>', array(
			'url' => $url,
		));

		return $controller->customise(array(
			'Title' => _t('MWMEmailHandler.UNSUBSCRIBE', 'Unsubscribe'),
			'Content' => '<p class="alert alert-info alert-block">' . $content . '</p><p class="text-center">' . $link . '</p>',
		))->renderWith($this->getTemplates('verify'));
	}

	function block($r) {
		$controller = MWMDirector::create_view($this);

		$controller->init();

		return $controller->customise(array(
			'Title' => _t('MWMEmailHandler.UNSUBSCRIBE', 'Unsubscribe'),
			'Content' => '<p>' . _t('MWMEmailHandler.UNSUBSCRIBE-CONTENT', 'Enter your email address below if you wish to no longer receive any emails from <a href="{url}">{site}</a>', array(
				'url' => Director::absoluteBaseURL(),
				'site' => ClassInfo::exists('SiteConfig') ? SiteConfig::current_site_config()->Title : singleton('LeftAndMain')->ApplicationName
			)) . '</p>',
			'Form' => $this->BlockForm(),
		))->renderWith($this->getTemplates('block'));
	}

	function unblock($r) {
		$controller = MWMDirector::create_view($this);

		$controller->init();

		return $controller->customise(array(
			'Title' => _t('MWMEmailHandler.ALLOW_EMAIL', 'Allow email communications'),
			'Content' => '<p>' . _t('MWMEmailHandler.ALLOW_EMAIL-CONTENT', 'Enter your email address below to allow email communications from <a href="{url}">{site}</a>', array(
				'url' => Director::absoluteBaseURL(),
				'site' => ClassInfo::exists('SiteConfig') ? SiteConfig::current_site_config()->Title : singleton('LeftAndMain')->ApplicationName
			)) . '</p>',
			'Form' => $this->UnblockForm(),
		))->renderWith($this->getTemplates('unblock'));
	}

	function links($r) {
		if(($hash = Convert::raw2sql($r->param('Email'))) && $link = MWMMailer_Link::get()->filter('Hash', $hash)->first()) {
			if($redirect = $link->track())
				return $this->redirect($redirect, 301);
		}

		return $this->httpError(404);
	}

	function ManageForm() {
		$r = $this->request;
		$nl = $this->isNewsletterInstalled();

		$email = Session::get('MWMEmailHandler.Email') ? Session::get('MWMEmailHandler.Email') : Convert::raw2sql($r->param('Email'));
		$hash = Session::get('MWMEmailHandler.ValidateHash') ? Session::get('MWMEmailHandler.ValidateHash') : Convert::raw2sql($r->param('Email'));

		if(!Email::is_valid_address($email))
			$email = null;

		$fields = FieldList::create();
		$actions = FieldList::create(
			$action = FormAction::create('doManage', _t('MWMEmailHandler.SUBMIT', 'Submit'))->addExtraClass('btn-major-action')
		);

		$before = '';

		$siteUrl =  Director::absoluteBaseURL();
		$site = ClassInfo::exists('SiteConfig') ? SiteConfig::current_site_config()->Title : singleton('LeftAndMain')->ApplicationName;

		if($nl && $hash && ($recipient = Recipient::get()->filter('ValidateHash', $hash)->first()) && $recipient->exists())
			$email = $recipient->Email;

		if($email) {
			if($nl) {
				$allowedLists = isset($recipient) ? $recipient->MailingLists()->column('ID') : array();
				$lists = MailingList::get()->filterAny(array(
					'ID' => $allowedLists,
					'Public' => 1
				));

				if($lists->exists()) {
					$fields->push($subscribe = MailingList_SubscribeField::create(
						'MailingLists',
						_t('MailingList_SubscribeField.SUBSCRIBED_TO_', 'Subscribed to: ')
					)->saveFormIntoSubscriber(false)->checkboxOnly(false));

					if($lists->count() == 1)
						$subscribe->setTitle(_t('MailingList_SubscribeField.SUBSCRIBED_TO_MAILING_LIST', 'Subscribed to our mailing list'));

					if(isset($recipient))
						$subscribe->setRecipient($recipient);
					else
						$subscribe->setRecipient(Recipient::create(array('Email' => $email)));

					$before = 'MailingLists';
				}

				if(isset($recipient) && !$recipient->Verified && $recipient->config()->double_opt_in) {
					$fields->push(FormMessageField::create(
						'NOTE-NotVerified',
						_t('MWMEmailHandler.UNVERIFIED_USER', 'Your email is not verified. You will not be able to receive any promotional emails from this site. <a href="{url}" class="modal-control">If you did not receive the confirmation email, please click here to resend it.</a>', array(
							'url' => Controller::join_links($this->Link('send-verification-email'), $recipient->ValidateHash)
						)),
						'alert-danger'
					));
				}
			}

			if(MWMMailer::is_invalid($email))
				return FormMessageField::create('INVALID-EMAIL', _t('MWMEmailHandler.ERROR-INVALID_EMAIL', 'This email is invalid (has been blacklisted as a non-existent or permanently bounced email). If you are sure this email exists, please contact the administrator to get your email unblocked.'), 'alert-danger')->FieldHolder();

			$blacklistField = CheckboxField::create('AllowEmailCommunications', _t('MWMEmailHandler.LABEL-ALLOW_EMAIL_COMMUNICATIONS', 'Allow email communications from <a href="{url}">{site}</a>', array(
					'url' => $siteUrl,
					'site' => $site,
				)), !MWMMailer::is_blacklisted($email))
					->setDescription(_t('MWMEmailHandler.DESC-ALLOW_EMAIL_COMMUNICATIONS', 'When unchecked, no emails from <a href="{url}">{site}</a> will be sent to {email}, unless the email is regarding an important transaction such as a receipt or invoice.', array(
						'email' => $email,
						'url' => $siteUrl,
						'site' => $site,
					)))
					->addHolderClass('alert alert-warning');

			if($before) {
				$fields->insertBefore(ReadonlyField::create('Readonly_Email', _t('MWMEmailHandler.EMAIL', 'Email'), $email), $before);
				$fields->insertBefore($blacklistField, $before);
			}
			else {
				$fields->push(ReadonlyField::create('Readonly_Email', _t('MWMEmailHandler.EMAIL', 'Email'), $email));
				$fields->push($blacklistField);
			}
		}

		if(!$email && !$hash) {
			$fields->push(
				EmailField::create('Email', _t('MWMEmailHandler.EMAIL', 'Email'))
			);

			$validator = MWMFormValidator::create(
				'Email'
			);
		}
		else {
			$actions->push(
				FormAction::create('doRestartManage', _t('MWMEmailHandler.MANAGE_ANOTHER_EMAIL', 'Manage another email'))
					->addExtraClass('btn-minor-action avoid-ajax-submit ignore')
					->setAttribute('formnovalidate', true)
			);

			$validator = null;
		}

		$form = MWMForm::create(
			$this,
			'ManageForm',
			$fields,
			$actions,
			$validator
		)->addExtraClass('form-figure')->removeExtraClass('form-horizontal');

		if($email || $hash)
			$form->addExtraClass('ajax-submit');

		if(isset($blacklistField)) {
			$action->addJSAlert(_t('MWMEmailHandler.ALERT-UNSUBSCRIBE', 'Are you sure you no longer want to receive any emails from {site}?', array(
				'site' => MWMDirector::baseWebsiteURL()
			)), 'confirm', '#' . $blacklistField->ID() . ':not(:checked)', array(
					'submit' => array(
						'text' => _t('YES', 'Yes'),
						'type' => 'submit',
                        'className' => 'vex-dialog-button-primary'
					),
					'cancel' => array(
						'text' => _t('NO', 'No'),
						'type' => 'button',
						'className' => 'vex-dialog-button-secondary',
					)
			));
		}

		return $form;
	}

	function BlockForm() {
		$r = $this->request;
		$email = $r->param('Email') ? $r->param('Email') : $r->requestVar('email');
		if($email && !Email::is_valid_address($email)) $email = '';

		return MWMForm::create(
			$this,
			'BlockForm',
			FieldList::create(
				EmailField::create('Email', _t('MWMEmailHandler.EMAIL', 'Email'))->setValue($email)
			),
			FieldList::create(
				FormAction::create('doBlock', _t('MWMEmailHandler.UNSUBSCRIBE', 'Unsubscribe'))
					->addExtraClass('btn-major-action')
					->addJSAlert(_t('MWMEmailHandler.ALERT-UNSUBSCRIBE', 'Are you sure you no longer want to receive any emails from {site}?', array(
						'site' => MWMDirector::baseWebsiteURL()
					)), 'confirm', '', array(
							'submit' => array(
								'text' => _t('YES', 'Yes'),
								'type' => 'submit',
								'className' => 'vex-dialog-button-primary'
							),
							'cancel' => array(
								'text' => _t('NO', 'No'),
								'type' => 'button',
								'className' => 'vex-dialog-button-secondary',
							)
					))
			),
			MWMFormValidator::create(
				'Email'
			)
		)->addExtraClass('form-figure ajax-submit')->removeExtraClass('form-horizontal')->setFormAction(Controller::join_links(MWMDirector::get_link_for_controller(get_class()), 'BlockForm'));
	}

	function UnblockForm() {
		$r = $this->request;
		$email = $r->param('Email') ? $r->param('Email') : $r->requestVar('email');
		if($email && !Email::is_valid_address($email)) $email = '';

		return MWMForm::create(
			$this,
			'UnblockForm',
			FieldList::create(
				EmailField::create('Email', _t('MWMEmailHandler.EMAIL', 'Email'))->setValue($email)
			),
			FieldList::create(
				FormAction::create('doUnblock', _t('MWMEmailHandler.SUBMIT', 'Submit'))->addExtraClass('btn-major-action')
			),
			MWMFormValidator::create(
				'Email'
			)
		)->addExtraClass('form-figure ajax-submit')->removeExtraClass('form-horizontal')->setFormAction(Controller::join_links(MWMDirector::get_link_for_controller(get_class()), 'UnblockForm'));
	}

	function doManage($data, $form, $r) {
		$fields = $form->Data;

		if(isset($fields['Email'])) {
			Session::set('MWMEmailHandler.Email', $fields['Email']);

			if($this->isNewsletterInstalled() && $recipient = Recipient::get()->filter('Email', $fields['Email'])->first())
				Session::set('MWMEmailHandler.ValidateHash', $recipient->ValidateHash);

			$form->sessionMessage(_t('MWMEmailHandler.MANAGE_EMAIL_TEXT', 'You may now manage your email below', array('email' => $fields['Email'])), 'good');
			return $this->redirectBack();
		}
		else {
			$email = Session::get('MWMEmailHandler.Email');

			$messages = array();

			if(isset($fields['AllowEmailCommunications']) && $fields['AllowEmailCommunications']) {
				if($blacklist = MWMMailer_Blacklist::get()->filter(array('Email' => $email, 'Valid' => 1))->first())
					$blacklist->delete();

				$messages[] = _t('MWMEmailHandler.DESC-EMAIL_COMMUNICATIONS_ALLOWED', 'Email communications are allowed to this email from this domain');
			}
			else {
				if(!MWMMailer::is_blacklisted($email)) {
					$blocked = MWMMailer_Blacklist::create();
					$blocked->Email = $email;
					$blocked->Message = _t('MWMEmailHandler.UNSUBSCRIBE_BY_USER', 'Unsubscribe by user');
					$blocked->write();
				}

				$messages[] = _t('MWMEmailHandler.DESC-EMAIL_COMMUNICATIONS_DISALLOWED', 'Email communications are no longer sent to this email from this domain');
			}

			if($form->Fields()->dataFieldByName('MailingLists')) {
				$messages[] = $form->Fields()->dataFieldByName('MailingLists')->subscribe($email);
				if($recipient = Recipient::get()->filter('Email', $email)->first())
					Session::set('MWMEmailHandler.ValidateHash', $recipient->ValidateHash);
			}

			return MWMDirector::finish_and_redirect(array(
				'message' => implode("\n<br />\n", $messages)
			));
		}
	}

	function doRestartManage($data, $form, $r) {
		Session::clear('MWMEmailHandler.Email');
		Session::clear('MWMEmailHandler.ValidateHash');
		return $this->redirectBack();
	}

	function doBlock($data, $form, $r) {
		$fields = $form->Data;

		if(!MWMMailer_Blacklist::get()->filter('Email', $fields['Email'])->exists()) {
			$blocked = MWMMailer_Blacklist::create();
			$blocked->Email = $fields['Email'];
			$blocked->Message = _t('MWMEmailHandler.UNSUBSCRIBE_BY_USER', 'Unsubscribe by user');
			$blocked->write();

			return MWMDirector::finish_and_redirect(array(
				'message' => _t('MWMEmailHandler.UNSUBSCRIBED', 'The email {email} has successfully been unsubscribed and will no longer receive emails originating from this domain', array('email' => $fields['Email']))
			));
		}
		else {
			return MWMDirector::finish_and_redirect(array(
				'message' => _t('MWMEmailHandler.ALREADY_UNSUBSCRIBED', 'The email {email} is already unsubscribed from this domain. <a href="{unblock}">Would you like to allow emails from this domain?</a>', array('email' => $fields['Email'], 'unblock' => Controller::join_links(MWMDirector::get_link_for_controller(get_class()), 'unblock', urlencode($fields['Email']))))
			));
		}
	}

	function doUnblock($data, $form, $r) {
		$fields = $form->Data;
		$blocked = MWMMailer_Blacklist::get()->filter('Email', $fields['Email']);

		if($blocked->exists()) {
			foreach($blocked as $block) {
				$block->delete();
				$block->destroy();
			}
		}

		return MWMDirector::finish_and_redirect(array(
			'message' => _t('MWMEmailHandler.UNBLOCKED', 'The email {email} can now receive email communications from this domain', array('email' => $fields['Email']))
		));
	}

	protected function getTemplates($action = '') {
		$templates = array('MWMEmailHandler', 'Page', 'ContentController');

		if($action) array_unshift($templates, 'MWMEmailHandler_' . $action);

		return $templates;
	}

	protected function blacklist($email, $message = '') {
		$blacklist = MWMMailer_Blacklist::create();
		$blacklist->Email = $email;
		$blacklist->Message = $message;
		$blacklist->Valid = false;
		$blacklist->write();

		$emails = MWMMailer_Log::get()->filter('Success', 1)->filterAny(array(
			'To' => array(
				$email,
				'<' . $email . ':PartialMatch',
				$email . ',:PartialMatch',
			),
			'From' => array(
				$email,
				'<' . $email . ':PartialMatch',
				$email . ',:PartialMatch',
			),
			'Cc' => array(
				$email,
				'<' . $email . ':PartialMatch',
				$email . ',:PartialMatch',
			),
			'Bcc' => array(
				$email,
				'<' . $email . ':PartialMatch',
				$email . ',:PartialMatch',
			),
		));

		if($emails->exists()) {
			foreach($emails as $email) {
				$email->Success = false;
				$email->write();
			}
		}
	}

	private function isNewsletterInstalled() {
		return ClassInfo::exists('Recipient') && MailingList::get()->exists();
	}
}

class MWMEmailHandler_Tracker extends Controller {
	function index($r) {
		if($r->param('Hash')) {
			$id = Convert::raw2sql($r->param('Hash'));

			if(($record = MWMMailer_Log::get()->filter('Track_Hash', $id)->first()) && !$record->Track_Open)
				$record->track($r->getIP());
		}

		$response = new SS_HTTPResponse(base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw=='), 200, 'OK');
		$response->addHeader('Content-type', 'image/gif');
		return $response;
	}
}