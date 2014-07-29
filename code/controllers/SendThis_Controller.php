<?php
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

class SendThis_Controller extends Controller {
    // registered web hook handlers
    private static $web_hooks = [
        'm' => '\Milkyway\SS\SendThis\WebHookHandlers\Mandrill',
        'a' => '\Milkyway\SS\SendThis\WebHookHandlers\AmazonSES',
    ];

	private static $allowed_actions = [
		'webHook',

		'block',
		'BlockForm',

		'unblock',
		'UnblockForm',

		'links',
	];

    private static $url_handlers = array(
        'POST ' => 'webHook',
        'GET ' => 'webHook',
        'HEAD ' => 'webHook',
    );

    private static $slug = 'email-subscriptions';

	function Link($action = '') {
		return Controller::join_links($this->config()->slug, $action);
	}

	function webHook($request) {
        $handlers = $this->config()->web_hooks;
        $action = $request->param('Email');

        if(isset($handlers[$action])) {
            $settings = $handlers[$action];

            if(is_array($settings) && isset($settings['main'])) {
                list($class, $method) = explode('::', $settings['main']);
            }
            else {
                list($class, $method) = explode('::', $settings);
            }

            if(!$method) $method = 'handleWebHook';

            $response = call_user_func_array([singleton($class), $method], [$request]);

            if($response) return $response;
        }
        else {
            $body =  $request->getBody();

            if(!$body)
                return $this->httpError(403);

            $email = SendThis::config()->notify_on_web_hook;
            if(!$email)
                $email = Email::config()->admin_email;

            if($email) {
                if(strpos($email, '+') === false)
                    $email = 'subscriptions+' . $email;

                mail(
                    $email,
                    _t('SendThis.WEB_HOOK-CALLED', 'Web hook called - {application}', [ 'application' => singleton('LeftAndMain')->ApplicationName]),
                    nl2br(print_r(json_decode($body), true)),
                    "Content-type: text/html\nFrom: " . $email
                );
            }
        }

        $controller = $this->niceView($this);
        $controller->init();

		return $controller->customise([
			'Title' => _t('SendThis.FORBIDDEN', 'Forbidden'),
			'Content' => '<p>Please do not access this page directly</p>',
		])->renderWith($this->getTemplates());
	}

	function block($r) {
		$controller = $this->niceView($this);
		$controller->init();

		return $controller->customise([
			'Title' => _t('SendThis.UNSUBSCRIBE', 'Unsubscribe'),
			'Content' => '<p>' . _t('SendThis.UNSUBSCRIBE-CONTENT', 'Enter your email address below if you wish to no longer receive any emails from <a href="{url}">{site}</a>', array(
				'url' => Director::absoluteBaseURL(),
				'site' => ClassInfo::exists('SiteConfig') ? SiteConfig::current_site_config()->Title : singleton('LeftAndMain')->ApplicationName
			)) . '</p>',
			'Form' => $this->BlockForm(),
		])->renderWith($this->getTemplates('block'));
	}

	function unblock($r) {
		$controller = $this->niceView($this);
		$controller->init();

		return $controller->customise([
			'Title' => _t('SendThis_Controller.ALLOW_EMAIL', 'Allow email communications'),
			'Content' => '<p>' . _t('SendThis_Controller.ALLOW_EMAIL-CONTENT', 'Enter your email address below to allow email communications from <a href="{url}">{site}</a>', array(
				'url' => Director::absoluteBaseURL(),
				'site' => ClassInfo::exists('SiteConfig') ? SiteConfig::current_site_config()->Title : singleton('LeftAndMain')->ApplicationName
			)) . '</p>',
			'Form' => $this->UnblockForm(),
		])->renderWith($this->getTemplates('unblock'));
	}

	function links($r) {
		if(($slug = Convert::raw2sql($r->param('Email'))) && $link = MWMMailer_Link::get()->filter('Slug', $slug)->first()) {
            SendThis::fire('clicked', '', '', ['slug' => $slug], ['slug' => $slug], $link);
			return $this->redirect($link->Link(), 301);
		}

		return $this->httpError(404);
	}

	function BlockForm() {
		$r = $this->request;
		$email = $r->param('Email') ? $r->param('Email') : $r->requestVar('email');
		if($email && !Email::is_valid_address($email)) $email = '';

		$form =  Form::create(
			$this,
			'BlockForm',
			FieldList::create(
				EmailField::create('Email', _t('SendThis.EMAIL', 'Email'))->setValue($email)
			),
			FieldList::create(
				FormAction::create('doBlock', _t('SendThis.UNSUBSCRIBE', 'Unsubscribe'))
					->addExtraClass('btn-major-action')
			),
			RequiredFields::create(
				'Email'
			)
		);

        $this->extend('updateForms', $form);
        $this->extend('updateBlockForm', $form);

        return $form;
	}

	function UnblockForm() {
		$r = $this->request;
		$email = $r->param('Email') ? $r->param('Email') : $r->requestVar('email');
		if($email && !Email::is_valid_address($email)) $email = '';

		$form = Form::create(
			$this,
			'UnblockForm',
			FieldList::create(
				EmailField::create('Email', _t('SendThis.EMAIL', 'Email'))->setValue($email)
			),
			FieldList::create(
				FormAction::create('doUnblock', _t('SendThis.SUBMIT', 'Submit'))->addExtraClass('btn-major-action')
			),
			RequiredFields::create(
				'Email'
			)
		);

        $this->extend('updateForms', $form);
        $this->extend('updateUnblockForm', $form);

        return $form;
	}

	function doBlock($data, $form, $r) {
		$fields = $form->Data;

		if(!SendThis_Blacklist::get()->filter('Email', $fields['Email'])->exists()) {
            SendThis::fire('blacklisted', '', $fields['Email'], ['message' => _t('SendThis.BLACKLIST_BY_USER', 'Unsubscribe by user'), 'internal' => true], $fields);

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

            return $this->respond($response, $form);
		}
		else {
            return $this->respond([
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

        SendThis::fire('whitelisted', '', $fields['Email'], ['message' => _t('SendThis.WHITELIST_BY_USER', 'User has requested to be whitelisted'), 'internal' => true], $fields);

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

        return $this->respond($response, $form);
	}

	protected function getTemplates($action = '') {
		$templates = array('SendThis', 'Page', 'ContentController');

		if($action) array_unshift($templates, 'SendThis_' . $action);

		return $templates;
	}

    protected function niceView(\Controller $controller, $url = '', $action = '') {
        if(ClassInfo::exists('SiteTree')) {
            $page = Page::create();

            $page->URLSegment = $url ? $url : $controller->Link();
            $page->Action = $action;
            $page->ID = -1;

            $controller = Page_Controller::create($page);
        }

        return $controller;
    }

    protected function respond($params, $form = null){
        if($this->request->isAjax()) {
            $code = isset($params['success']) ? 200 : 400;
            $status = isset($params['success']) ? 'success' : 'fail';

            $response = new SS_HTTPResponse(json_encode($params), $code, $status);
            $response->addHeader('Content-type', 'application/json');
            return $response;
        }
        else {
            if($form && isset($params['message'])) {
                $form->sessionMessage($params['message'], 'good');
            }

            if(!$this->redirectedTo())
                $this->redirectBack();
        }
    }
}