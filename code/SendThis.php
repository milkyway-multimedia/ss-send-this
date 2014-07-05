<?php
/**
 * Milkyway Multimedia
 * SendThis.php
 *
 * Sending emails with optional logging, bounce handling and tracking with the CMS
 * Currently supporting: SMTP, PHP Mail, Mandrill and Amazon SES API
 * Bounce handling is only handled if using either:
 * - Amazon SES
 * - Mandrill
 *
 * This class throws exceptions, but can be set to not do so, in order to
 * work like the SS Mailer class which does not throw exceptions
 *
 * @package milkyway-multimedia/silverstripe-send-this
 * @author Mellisa Hankins <mellisa.hankins@me.com>
 */

class SendThis extends Mailer {
    private static $transports = [
        'smtp' => '\Milkyway\SendThis\Transports\SMTP',
        'ses' => '\Milkyway\SendThis\Transports\AmazonSES',
        'mandrill' => '\Milkyway\SendThis\Transports\Mandrill',
    ];

    protected static $listeners = [];

	protected static $throw_exceptions = false;

	public static function get_throw_exceptions() {
		return self::$throw_exceptions;
	}

	public static function throw_exceptions($flag = true) {
		self::$throw_exceptions = $flag;
	}

    public static function listen($hooks, $item) {
        $hooks = (array) $hooks;

        foreach($hooks as $hook) {
            if(!isset(self::$listeners[$hook]))
                self::$listeners[$hook] = [];

            if(!is_callable($item))
                $item = [$item, $hook];

            self::$listeners[$hook][] = $item;
        }
    }

    public static function fire($hooks) {
        $hooks = (array)$hooks;

        foreach($hooks as $hook) {
            if(isset(self::$listeners[$hook])) {
                $args = func_get_args();
                array_shift($args);

                foreach(self::$listeners[$hook] as $listener)
                    call_user_func_array($listener, $args);
            }
        }
    }

    protected $transport; // The transport
    protected $messenger; // The PHP Mailer instance

    protected $noTo = false;

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

    public static function admin_email($prepend = '') {
        if($email = Config::inst()->get('Email', 'admin_email'))
            return $prepend ? $prepend . '+' . $email : $email;

        $name = $prepend ? $prepend . '+no-reply' : 'no-reply';

        return $name . '@' . trim(str_replace(array('http://', 'https://', 'www.'), '', Director::protocolAndHost()), ' /');
    }

    public function __construct() {
        parent::__construct();
        $this->setMessenger();
    }

    protected function setMessenger() {
        $this->messenger = new PHPMailer(true);
        $this->setTransport();
    }

    protected function resetMessenger(){
        if($this->messenger) {
            $this->messenger->ClearAllRecipients();
            $this->messenger->ClearReplyTos();
            $this->messenger->ClearAttachments();
            $this->messenger->ClearCustomHeaders();
        }

        $this->noTo = false;
    }

    protected function setTransport() {
        $available = $this->config()->transports;
        $transport = $this->config()->transport;

        if(isset($available[$transport]))
            $this->transport = Object::create($available['transport'], $this->messenger);
        else
            $this->transport = Object::create('\Milkyway\SendThis\Transports\SendThis_Default', $this->messenger);

        return $this->transport;
    }

	protected function addEmail($in, $func, $email = null, $break = false, $ignoreValid = false, $hidden = false) {
		if(!$email) $email = $this->messenger;

		$success = false;

		$list = explode(',', $in);
		foreach ($list as $item) {
			if(!trim($item)) continue;

			list($a,$b) = $this->split_email($item);

			if(SendThis_Blacklist::check($a, $ignoreValid))  {
				if($break)
					return false;
				else
					continue;
			}

			if(!$a || !Email::is_valid_address(($a)))
				continue;

			$success = true;

			if(!$hidden && $this->noTo) {
				$email->AddAddress($a, $b);
                $this->noTo = false;
			}
			else
				$email->$func($a, $b);
		}

		return $success;
	}

	protected function message($to, $from, $subject, $attachedFiles = null, $headers = null) {
		$email = $this->messenger;

		if(!$headers) $headers = array();

		$ignoreValid = false;

		if(isset($headers['X-Milkyway-Priority'])) {
            $ignoreValid = true;
			unset($headers['X-Milkyway-Priority']);
		}

		// set the to
		if(!$this->addEmail($to, 'AddAddress', $email, $ignoreValid))
			$this->noTo = true;

		list($doFrom, $doFromName) = $this->split_email($from);

		if($sameDomain = self::settings()->from_same_domain_only) {
			$base = '@' . MWMDirector::baseWebsiteURL();

			if(!is_bool($sameDomain) || !$doFrom || !(substr($doFrom, -strlen($base)) === $base)) {
				$realFrom = $doFrom;
				$realFromName = $doFromName;

				if(!is_bool($sameDomain)) {
					$doFrom = $sameDomain;
					if(!$realFromName) $doFromName = ClassInfo::exists('SiteConfig') ? SiteConfig::current_site_config()->AdminName : singleton('LeftAndMain')->ApplicationName;
				}
				elseif(ClassInfo::exists('SiteConfig')) {
					$doFrom = SiteConfig::current_site_config()->AdminEmail;
					if(!$realFromName) $doFromName = SiteConfig::current_site_config()->AdminName;
				}
				else {
					$doFrom = $this->admin_email();
					if(!$realFromName) $doFromName = singleton('LeftAndMain')->ApplicationName;
				}

				if(is_bool($sameDomain) && !(substr($doFrom, -strlen($base)) === $base)) {
					$doFrom = $this->admin_email();
					if(!$realFromName) $doFromName = singleton('LeftAndMain')->ApplicationName;
				}

				$email->AddReplyTo($realFrom, $realFromName);
			}
		}

		if(!$doFrom) {
			if(ClassInfo::exists('SiteConfig'))
				$doFrom = SiteConfig::current_site_config()->AdminEmail;
			else
				$doFrom = $this->admin_email();
		}

		$email->setFrom($doFrom, $doFromName);

		$email->Subject = $subject;

		if (is_array($attachedFiles)) {
			foreach($attachedFiles as $file) {
				if (isset($file['tmp_name']) && isset($file['name']))
					$email->AddAttachment($file['tmp_name'], $file['name']);
				elseif (isset($file['contents']))
					$email->AddStringAttachment($file['contents'], $file['filename']);
				else
					$email->AddAttachment($file);
			}
		}

		if(is_array($headers) && count($headers)) {
			// the carbon copy header has to be 'Cc', not 'CC' or 'cc' -- ensure this.
			if (isset($headers['CC'])) { $headers['Cc'] = $headers['CC']; unset($headers['CC']); }
			if (isset($headers['cc'])) { $headers['Cc'] = $headers['cc']; unset($headers['cc']); }

			// the carbon copy header has to be 'Bcc', not 'BCC' or 'bcc' -- ensure this.
			if (isset($headers['BCC'])) {$headers['Bcc']=$headers['BCC']; unset($headers['BCC']); }
			if (isset($headers['bcc'])) {$headers['Bcc']=$headers['bcc']; unset($headers['bcc']); }

			if(isset($headers['Cc'])) {
				$this->addEmail($headers['Cc'], 'AddCC', $email, $ignoreValid);
				unset($headers['Cc']);
			}

			if(isset($headers['Bcc'])) {
				$this->addEmail($headers['Bcc'], 'AddBCC', $email, $ignoreValid);
				unset($headers['Bcc']);
			}

			if(isset($headers['X-SilverStripeMessageID'])) {
				if(defined('BOUNCE_EMAIL')) {
					$bounceAddress = BOUNCE_EMAIL;

					if($doFrom)
						$bounceAddress = "$doFrom <$bounceAddress>";

					$email->ReturnPath = $bounceAddress;
				}
				else {
					$headers['X-MilkywayMessageID'] = $headers['X-SilverStripeMessageID'];
					unset($headers['X-SilverStripeMessageID']);
				}
			}

			if(isset($headers['X-SilverStripeSite'])) {
				$headers['X-MilkywaySite'] = ClassInfo::exists('SiteConfig') ? SiteConfig::current_site_config()->Title : singleton('LeftAndMain')->ApplicationName;
				unset($headers['X-SilverStripeSite']);
			}

			if(isset($headers['Reply-To'])) {
				$this->addEmail($headers['Reply-To'], 'AddReplyTo', $email, true);
				unset($headers['Reply-To']);
			}

			if(isset($headers["X-Priority"])) {
				$email->Priority = $headers["X-Priority"];
				unset($headers["X-Priority"]);
			}
		}

		// Email has higher chance of being received if there is a too email sent...
		if($this->noTo && $to = Email::config()->default_to_email) {
			$this->addEmail($to, 'AddAddress', $email, $ignoreValid);
            $this->noTo = false;
        }

		$server = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : singleton('LeftAndMain')->ApplicationName;
		$email->XMailer = sprintf('SendThis Mailer 2.0 (Sent from %s)', $server);

		if($this->config()->confirm_reading_to)
			$email->ConfirmReadingTo = $this->config()->confirm_reading_to;

		if($this->config()->word_wrap)
			$email->WordWrap = $this->config()->word_wrap;

		foreach ($headers as $k => $v)
			$email->AddCustomHeader($k, $v);

		return $email;
	}

	public function sendPlain($to, $from, $subject, $content, $attachedFiles = null, $headers = null) {
		$log = SendThis_Log::create()->init('plain', $headers);
        $log->Transport = get_class($this->transport);
        $this->transport->applyHeaders($headers);
		$email = $this->message($to, $from, $subject, $attachedFiles, $headers);

		if($email) {
			$email->Body = $log->removeTracker(strip_tags($content));
			$email->isHTML(false);

			try {
				$result = $this->transport->start($email, $log);
			} catch(Exception $e) {
				$result = $e->getMessage();

                if(self::$throw_exceptions)
                    throw $e;
			}

			if(!$result || $email->IsError())
				$result = $email->ErrorInfo;
		}
        else
            $result = 'Email has been unsubscribed/blacklisted';

        $log->log($result, $to, $from, $subject, $content, $attachedFiles, $headers);
        $this->resetMessenger();

		return $result;
	}

	public function sendHTML($to, $from, $subject, $htmlContent, $attachedFiles = null, $headers = null, $plainContent = null) {
		$log = SendThis_Log::create()->init('html', $headers);
        $log->Transport = get_class($this->transport);
        $this->transport->applyHeaders($headers);
		$email = $this->message($to, $from, $subject, $attachedFiles, $headers);

		if($email) {
			$email->Body = $log->insertTracker($log->trackLinks($htmlContent));
			$email->AltBody = $log->removeTracker($plainContent ? $plainContent : strip_tags($htmlContent));
			$email->IsHTML();

			try {
				$result = $this->transport->start($email, $log);
			} catch(Exception $e) {
				$result = $e->getMessage();

                if(self::$throw_exceptions)
                    throw $e;
			}

			if(!$result || $email->IsError())
				$result = $email->ErrorInfo;
		}
        else
		    $result = 'Email has been unsubscribed/blacklisted';

        $log->log($result, $to, $from, $subject, $htmlContent, $attachedFiles, $headers);
		$this->resetMessenger();

		return $result;
	}
}

class SendThis_Exception extends Exception { }