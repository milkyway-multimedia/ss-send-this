<?php namespace Milkyway\SS\SendThis;
use Milkyway\SS\SendThis\Events\Event;

/**
 * Milkyway Multimedia
 * SendThis.php
 *
 * @package milkyway-multimedia/ss-send-this
 * @author Mellisa Hankins <mellisa.hankins@me.com>
 */

class Mailer extends \Mailer {
	/** @var array A map for the transports you can use with SendThis */
	private static $transports = [
		'default' => [
			'driver' => 'default',
		],
	];

    /** @var array Default settings for drivers you can use with SendThis */
    private static $drivers = [
        'default' => [
	        'class' => '\Milkyway\SS\SendThis\Transports\Mail',
	    ],
        'smtp' => [
	        'class' => '\Milkyway\SS\SendThis\Transports\SMTP',
	    ],
        'ses' => [
	        'class' => '\Milkyway\SS\SendThis\Transports\AmazonSES',
	    ],
        'mandrill' => [
	        'class' => '\Milkyway\SS\SendThis\Transports\Mandrill',
	    ],
    ];

    /** @var bool Whether to enabled logging for this application */
    private static $logging = false;

    /** @var bool Whether to enable api tracking for this application */
    private static $api_tracking = true;

    /** @var bool|string Only allow emails from a certain domain
     * (you can also enter an email here to override the From Address) */
    private static $from_same_domain_only = true;

    /** @var int After how many soft bounces do we blacklist */
    private static $blacklist_after_bounced = 2;

    /**
     * Send an email immediately, with ability to provide a callback and alternate transport
     *
     * @param \Email|array      $email
     * @param Callable  $callback
     * @param array $transport
     */
    public static function now($email, $callback = null, $transport = []) {
        //@todo implement a quick send function
        if($callback)
            static::listen('sent', $callback, true);
    }

    /**
     * Push an email to a queue, with ability to provide a time, callback, alternate transport
     *
     * @param \Email|array      $email
     * @param string $time
     * @param Callable  $callback
     * @param array $transport
     */
    public static function later($email, $time = '', $callback = null, $transport = []) {
        //@todo implement a quick queue function
        if($callback)
            static::listen(['sent'], $callback, true);
    }

    /** @var \Milkyway\SS\SendThis\Transports\Contract The mail transport */
    protected $transport;

    /** @var \PHPMailer  The PHP Mailer instance */
    protected $messenger;

	/** @var \Milkyway\SS\SendThis\MessageParsers\Contract  The message parser */
	protected $parser;

	/** @var \Milkyway\SS\Eventful\Contract  The events manager */
	protected $eventful;

    /**
     * Split a name <email> string
     *
     * @param $string
     * @return array
     */
    public static function split_email($string) {
		if (preg_match('/^\s*(.+)\s+<(.+)>\s*$/', trim($string), $parts)){
			return array($parts[2], $parts[1]); // Has name and email
		} else {
			return array(trim($string), ''); // Has email
		}
	}

    /**
     * Get an administrator or default email
     *
     * @param string $prepend
     *
     * @return string
     */
    public static function admin_email($prepend = '') {
        if($email = \Email::config()->admin_email)
            return $prepend ? $prepend . '+' . $email : $email;

        $name = $prepend ? $prepend . '+no-reply' : 'no-reply';

        return $name . '@' . trim(str_replace(['http://', 'https://', 'www.'], '', \Director::protocolAndHost()), ' /');
    }

    /**
     * Get message id from headers
     *
     * @param $headers
     *
     * @return mixed
     */
    public static function message_id_from_headers($headers) {
        if(isset($headers['Message-ID']))
            return $headers['Message-ID'];

        if(isset($headers['X-SilverStripeMessageID']))
            return $headers['X-SilverStripeMessageID'];

        if(isset($headers['X-MilkywayMessageID']))
            return $headers['X-MilkywayMessageID'];

        return '';
    }

    public function __construct(\PHPMailer $messenger, \Milkyway\SS\SendThis\MessageParsers\Contract $parser, \Milkyway\SS\Eventful\Contract $eventful) {
        parent::__construct();
        $this->messenger = $messenger;
        $this->parser = $parser;
        $this->eventful = $eventful;
	    $this->setTransport();
    }

    protected function resetMessenger(){
        if($this->messenger) {
            $this->messenger->ClearAllRecipients();
            $this->messenger->ClearReplyTos();
            $this->messenger->ClearAttachments();
            $this->messenger->ClearCustomHeaders();
        }
    }

    protected function setTransport() {
        $drivers = (array)$this->config()->drivers;
        $transports = (array)$this->config()->transports;

        $transport = $this->config()->transport ?: 'default';

	    $driver = isset($transports[$transport]) && isset($transports[$transport]['driver']) ? $transports[$transport]['driver'] : $transport;

	    $class = isset($drivers[$driver]) && isset($drivers[$driver]['class']) ? $drivers[$driver]['class'] : $driver;

	    $params = (array)$this->config();

	    foreach(['transport', 'drivers', 'transports'] as $notAllowed) {
		    if(isset($params[$notAllowed]))
			    unset($params[$notAllowed]);
	    }

	    if(isset($drivers[$driver]) && isset($drivers[$driver]['params']))
		    $params = array_merge($params, (array)$drivers[$driver]['params']);
	    if(isset($transports[$transport]) && isset($transports[$transport]['params']))
		    $params = array_merge($params, (array)$transports[$transport]['params']);

	    $this->transport = \Injector::inst()->createWithArgs($class, [$this->messenger, $this, $params]);

        return $this->transport;
    }

	public function send($type = 'html', $to, $from, $subject, $content, $attachedFiles = null, $headers = null, $plainContent = null) {
        $this->resetMessenger();

        if($this->config()->logging) {
		    $log = \SendThis_Log::create()->init($type, $headers);
            $log->Transport = get_class($this->transport());
        }
        else
            $log = null;

        $messageId = $this->message_id_from_headers($headers);
        $params = compact('to', 'from', 'subject', 'content', 'attachedFiles', 'headers');

        $headers = (object) $headers;

		$this->eventful->fire(Event::named('sendthis.up', $this), $messageId, $to, $params, $params, $log, $headers);

        $headers = (array) $headers;

        $this->transport()->applyHeaders($headers);

		$this->parser->setMessage($this->messenger);
		$this->parser->setConfig($this->config());
		$message = $this->parser->parse($to, $from, $subject, $attachedFiles, $headers);

		if($message) {
            $params['message'] = $this->messenger;

			$this->messenger->Body = $type != 'html' ? strip_tags($content) : $content;

            if($type == 'html' || $plainContent)
	            $this->messenger->AltBody = $plainContent ? $plainContent : strip_tags($content);

			$this->eventful->fire(Event::named('sendthis.sending', $this), $messageId, $to, $params, $params, $log);

			$this->messenger->isHTML($type == 'html');

			try {
				$result = $this->transport()->start($this->messenger, $log);
			} catch(\Exception $e) {
				$result = $e->getMessage();

                $params['message'] = $result;

				$this->eventful->fire(Event::named('sendthis.failed', $this), $this->messenger->getLastMessageID() ?: $messageId, $to, $params, $params, $log);
			}

			if(!$result || $this->messenger->IsError())
				$result = $this->messenger->ErrorInfo;

            $messageId = $this->messenger->getLastMessageID() ?: $messageId;
		}
        else
            $result = 'Email has been unsubscribed/blacklisted';

        if($result !== true) {
            $params['message'] = $result;
            $this->eventful->fire(Event::named('sendthis.failed', $this), $messageId, $to, $params, $params, $log);
        }

		$this->eventful->fire(Event::named('sendthis.down', $this), $messageId, $to, $params, $params, $log);

        $this->resetMessenger();

		return $result;
	}

    public function sendHTML($to, $from, $subject, $htmlContent, $attachedFiles = null, $headers = null, $plainContent = null) {
        return $this->send('html', $to, $from, $subject, $htmlContent, $attachedFiles, $headers, $plainContent);
    }

    public function sendPlain($to, $from, $subject, $plainContent, $attachedFiles = null, $headers = null) {
        return $this->send('plain', $to, $from, $subject, $plainContent, $attachedFiles, $headers);
    }

    public function eventful() {
        return $this->eventful;
    }

    public function transport() {
        return $this->transport;
    }
}