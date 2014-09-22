<?php namespace Milkyway\SS\SendThis\Controllers;
/**
 * Milkyway Multimedia
 * SendThis_Controller.php
 *
 * Add a controller specifically for allowing handling of
 * email subscriptions via a third party application (Amazon SNS), a web hook,
 * or the owner of the site
 *
 * @package milkyway-multimedia/silverstripe-send-this
 * @author Mellisa Hankins <mellisa.hankins@me.com>
 */

class EmailSubscriptions extends \Controller {
	private static $allowed_actions = [
		'block',
		'BlockForm',

		'unblock',
		'UnblockForm',
	];

    private static $slug = 'email-subscriptions';

	function Link($action = '') {
		return \Controller::join_links($this->config()->slug, $action);
	}

	function index($request) {
        return $this->block($request);
	}

	function block($r) {
		$controller = $this->displayNiceView($this);
		$controller->init();

		return $controller->customise([
			'Title' => _t('SendThis.UNSUBSCRIBE', 'Unsubscribe'),
			'Content' => '<p>' . _t('SendThis.UNSUBSCRIBE-CONTENT', 'Enter your email address below if you wish to no longer receive any emails from <a href="{url}">{site}</a>', array(
				'url' => \Director::absoluteBaseURL(),
				'site' => \ClassInfo::exists('SiteConfig') ? \SiteConfig::current_site_config()->Title : singleton('LeftAndMain')->ApplicationName
			)) . '</p>',
			'Form' => $this->BlockForm(),
		])->renderWith($this->getTemplates('block'));
	}

	function unblock($r) {
		$controller = $this->displayNiceView($this);
		$controller->init();

		return $controller->customise([
			'Title' => _t('SendThis_Controller.ALLOW_EMAIL', 'Allow email communications'),
			'Content' => '<p>' . _t('SendThis_Controller.ALLOW_EMAIL-CONTENT', 'Enter your email address below to allow email communications from <a href="{url}">{site}</a>', array(
				'url' => \Director::absoluteBaseURL(),
				'site' => \ClassInfo::exists('SiteConfig') ? \SiteConfig::current_site_config()->Title : singleton('LeftAndMain')->ApplicationName
			)) . '</p>',
			'Form' => $this->UnblockForm(),
		])->renderWith($this->getTemplates('unblock'));
	}

	function BlockForm() {
		$r = $this->request;
		$email = $r->param('Email') ? $r->param('Email') : $r->requestVar('email');
		if($email && !\Email::is_valid_address($email)) $email = '';

		$form =  \Form::create(
			$this,
			'BlockForm',
			\FieldList::create(
				\EmailField::create('Email', _t('SendThis.EMAIL', 'Email'))->setValue($email)
			),
			\FieldList::create(
				\FormAction::create('doBlock', _t('SendThis.UNSUBSCRIBE', 'Unsubscribe'))
					->addExtraClass('btn-major-action')
			),
			\RequiredFields::create(
				'Email'
			)
		);

        $this->extend('updateForms', $form);
        $this->extend('updateBlockForm', $form);

        return $this->bootstrapped($form);
	}

	function UnblockForm() {
		$r = $this->request;
		$email = $r->param('Email') ? $r->param('Email') : $r->requestVar('email');
		if($email && !\Email::is_valid_address($email)) $email = '';

		$form = \Form::create(
			$this,
			'UnblockForm',
			\FieldList::create(
				\EmailField::create('Email', _t('SendThis.EMAIL', 'Email'))->setValue($email)
			),
			\FieldList::create(
				\FormAction::create('doUnblock', _t('SendThis.SUBMIT', 'Submit'))->addExtraClass('btn-major-action')
			),
			\RequiredFields::create(
				'Email'
			)
		);

        $this->extend('updateForms', $form);
        $this->extend('updateUnblockForm', $form);

        return $this->bootstrapped($form);
	}

	function doBlock($data, $form, $r) {
		$fields = $form->Data;

		if(!\SendThis_Blacklist::get()->filter('Email', $fields['Email'])->exists()) {
            \SendThis::fire('blacklisted', '', $fields['Email'], ['message' => _t('SendThis.BLACKLIST_BY_USER', 'Unsubscribe by user'), 'internal' => true], $fields);

            $response = [
                'message' => _t(
                    'SendThis.BLACKLIST',
                    'The email {email} has successfully been unsubscribed and will no longer receive emails originating from this domain',
                    [
                        'email' => $fields['Email'],
                    ]
                )
            ];

            $this->extend('onBlock', $data, $form, $request, $blocked, $response);

            return $this->respondToFormAppropriately($response, $form);
		}
		else {
            return $this->respondToFormAppropriately([
                    'message' => _t(
                        'SendThis.ALREADY_BLACKLISTED',
                        '{email} is already unsubscribed from this domain. <a href="{unblock}">Would you like to allow emails from this domain?</a>',
                        [
                            'email' => $fields['Email'],
                            'unblock' => $this->Link('unblock/' . str_replace(['.', '-'], ['%2E', '%2D'], rawurlencode($fields['Email'])))
                        ]
                    )
                ]
            , $form);
		}
	}

	function doUnblock($data, $form, $request) {
		$fields = $form->Data;

        \SendThis::fire('whitelisted', '', $fields['Email'], ['message' => _t('SendThis.WHITELIST_BY_USER', 'User has requested to be whitelisted'), 'internal' => true], $fields);

        $response = [
            'message' => _t(
                'SendThis.UNBLOCKED',
                'The email {email} can now receive email communications from this domain',
                [
                    'email' => $fields['Email'],
                ]
            )
        ];

        $this->extend('onUnblock', $data, $form, $request, $blocked, $response);

        return $this->respondToFormAppropriately($response, $form);
	}

	protected function getTemplates($action = '') {
		$templates = array('SendThis', 'Page', 'ContentController');

		if($action) array_unshift($templates, 'SendThis_' . $action);

		return $templates;
	}

	protected function bootstrapped($form) {
		return \ClassInfo::exists('FormBootstrapper') && !($form instanceof \FormBootstrapper) ? \FormBootstrapper::create($form) : $form;
	}
}