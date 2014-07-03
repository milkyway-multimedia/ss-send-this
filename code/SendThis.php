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
	protected static $mailer;

	protected static $throw_exceptions = false;

	public static function get_throw_exceptions() {
		return self::$throw_exceptions;
	}

	public static function throw_exceptions($flag = true) {
		self::$throw_exceptions = $flag;
	}

	public static function inst(){
		if(!self::$mailer) {
			$mailer = new PHPMailer(true);

			$method = self::settings()->method;

			if($method == 'smtp') {
				$mailer->IsSMTP();

				if(self::settings()->host)
					$mailer->Host = self::settings()->host;

				if(self::settings()->port)
					$mailer->Port = self::settings()->port;

				if(self::settings()->username) {
					$mailer->SMTPAuth = true;
					$mailer->Username = self::settings()->username;

					if(self::settings()->password)
						$mailer->Password = self::settings()->password;

					if(self::settings()->secure)
						$mailer->SMTPSecure = self::settings()->secure;
				}

				if(self::settings()->keep_alive)
					$mailer->SMTPKeepAlive = true;
			}
			elseif($method == 'sendmail')
				$mailer->IsSendmail();
			elseif($method == 'qmail')
				$mailer->IsQmail();
			else
				$mailer->IsMail();

			if(self::settings()->debug) {
				$mailer->SMTPDebug = true;
				$mailer->Debugoutput = 'html';
			}
			elseif(self::settings()->logging) {
				$mailer->SMTPDebug = true;
				$mailer->Debugoutput = 'error_log';
			}

			self::$mailer = $mailer;
		}

		return self::$mailer;
	}

	public static function split_email($in) {
		if (preg_match('/^\s*(.+)\s+<(.+)>\s*$/', trim($in), $m)){
			return array($m[2], $m[1]);
		} else {
			return array(trim($in), '');
		}
	}

	public static function is_blacklisted($email, $ignoreBlacklist = false) {
		$blacklist = SendThis_Blacklist::get()->filter('Email', $email);

		if($ignoreBlacklist)
            $blacklist->exclude('Valid', 1);

		return $blacklist->exists();
	}

	public static function is_invalid($email) {
		return SendThis_Blacklist::get()->filter(array('Email' => $email, 'Valid' => 0))->exists();
	}

	protected function addEmail($in, $func, $email = null, $break = false, $ignoreBlacklist = false, $hidden = false) {
		if(!$email) $email = self::inst();

		$success = false;

		$list = explode(',', $in);
		foreach ($list as $item) {
			if(!trim($item)) continue;

			list($a,$b) = $this->split_email($item);

			if($this->is_blacklisted($a, $ignoreBlacklist))  {
				if($break)
					return false;
				else
					continue;
			}

			if(!$a || !Email::is_valid_address(($a)))
				continue;

			$success = true;

			if(!$hidden && self::$no_to) {
				$email->AddAddress($a, $b);
				self::$no_to = false;
			}
			else
				$email->$func($a, $b);
		}

		return $success;
	}

	protected function email($to, $from, $subject, $attachedFiles = null, $headers = null) {
		$email = self::inst();

		if(!$headers) $headers = array();
        $configHeaders = $this->config()->headers;

		$ignoreBlacklist = false;

		if(isset($headers[$configHeaders['priority']])) {
			$ignoreBlacklist = true;
			unset($headers[$configHeaders['priority']]);
		}

		// set the to
		if(!$this->addEmail($to, 'AddAddress', $email, $ignoreBlacklist))
			self::$no_to = true;

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
					$doFrom = MWM::adminEmail();
					if(!$realFromName) $doFromName = singleton('LeftAndMain')->ApplicationName;
				}

				if(is_bool($sameDomain) && !(substr($doFrom, -strlen($base)) === $base)) {
					$doFrom = MWM::adminEmail();
					if(!$realFromName) $doFromName = singleton('LeftAndMain')->ApplicationName;
				}

				$email->AddReplyTo($realFrom, $realFromName);
			}
		}

		if(!$doFrom) {
			if(ClassInfo::exists('SiteConfig'))
				$doFrom = SiteConfig::current_site_config()->AdminEmail;
			else
				$doFrom = MWM::adminEmail();
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
				$this->addEmail($headers['Cc'], 'AddCC', $email, $ignoreBlacklist);
				unset($headers['Cc']);
			}

			if(isset($headers['Bcc'])) {
				$this->addEmail($headers['Bcc'], 'AddBCC', $email, $ignoreBlacklist);
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
		if(self::$no_to && $to = $this->settings()->default_to_email)
			$this->addEmail($to, 'AddAddress', $email, $ignoreBlacklist);

		$server = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : singleton('LeftAndMain')->ApplicationName;
		$email->XMailer = sprintf('Milkyway Mailer 2.0 (Sent from %s)', $server);

		if($this->settings()->confirm_reading_to)
			$email->ConfirmReadingTo = $this->settings()->confirm_reading_to;

		if($this->settings()->word_wrap)
			$email->WordWrap = $this->settings()->word_wrap;

		foreach ($headers as $k => $v)
			$email->AddCustomHeader($k, $v);

		return $email;
	}

	public function sendPlain($to, $from, $subject, $content, $attachedFiles = null, $headers = null) {
		$this->startDebug($to, $from);

		$log = MWMMailer_Log::create()->init('plain', $headers);
		$email = $this->email($to, $from, $subject, $attachedFiles, $headers);

		$log->log(false, $to, $from, $subject, $content, $attachedFiles, $headers, false);

		if($email) {
			$email->Body = $log->removeTracker(strip_tags($content));
			$email->IsHTML(false);

			try {
				$result = $this->send($email, $log);
			} catch(Exception $e) {
				$result = $e->getMessage();
			}

			if(!$result || $email->IsError())
				$result = $email->ErrorInfo;

			$log->log($result, $to, $from, $subject, $content, $attachedFiles, $headers);

			$this->endDebug($result && !$email->IsError());

			self::clear();

			if(isset($e) && self::$throw_exceptions)
				throw $e;

			return !is_string($result);
		}

		$log->log('Email has been unsubscribed/blacklisted', $to, $from, $subject, $content, $attachedFiles, $headers);

		$this->endDebug(false);

		self::clear();

		return false;
	}

	public function sendHTML($to, $from, $subject, $htmlContent, $attachedFiles = null, $headers = null, $plainContent = null) {
		$this->startDebug($to, $from);

		$log = MWMMailer_Log::create()->init('html', $headers);
		$email = $this->email($to, $from, $subject, $attachedFiles, $headers);

		$log->log(false, $to, $from, $subject, $htmlContent, $attachedFiles, $headers, false);

		if($email) {
			$email->Body = $log->insertTracker($log->trackLinks($htmlContent));
			$email->AltBody = $log->removeTracker($plainContent ? $plainContent : strip_tags($htmlContent));
			$email->IsHTML();

			try {
				$result = $this->send($email, $log);
			} catch(Exception $e) {
				$result = $e->getMessage();
			}

			if(!$result || $email->IsError())
				$result = $email->ErrorInfo;

			$log->log($result, $to, $from, $subject, $htmlContent, $attachedFiles, $headers);

			$this->endDebug($result && !$email->IsError());

			self::clear();

			if(isset($e) && self::$throw_exceptions)
				throw $e;

			return !is_string($result);
		}

		$log->log('Email has been unsubscribed/blacklisted', $to, $from, $subject, $htmlContent, $attachedFiles, $headers);

		$this->endDebug(false);

		self::clear();

		return false;
	}

	public function send($email = null, $log = null) {
		if(!$email) $email = self::inst();

		if($this->settings()->method == 'ses')
			$result = $this->sendViaSES($email, $log);
		elseif($this->settings()->method == 'mailgun')
			$result = $this->sendViaMailGun($email, $log);
		else
			$result = $email->Send();

		return $result;
	}

	protected function startDebug($to, $from, $subject = '') {
		if(self::inst()->SMTPDebug) {
			$renderer = new MWMDebugView();
			$renderer->writeHeader();
			$renderer->writeInfo('Debugging mailer: ' . get_class($this), "Sending email from: $from, to: $to\n (subject: $subject)");
		}
	}

	protected function endDebug($success = true) {
		if(self::inst()->SMTPDebug) {
			$renderer = new MWMDebugView();

			if($success) {
				if(!Director::is_cli()) echo '<p class="message success good">';
				echo 'Email was sent successfully';
				if(!Director::is_cli()) echo '</p>';
			}
			else {
				if(!Director::is_cli()) echo '<p class="message warning error">';
				echo 'Email was not sent successfully';
				if(!Director::is_cli()) echo '</p>';
			}

			$renderer->writeParagraph('Debug mode stopped execution to prevent url redirection');
			$renderer->writeFooter();
			die();
		}
	}

	protected function sendViaSES($email = null, $log = null) {
		if(!$email) $email = self::inst();

		$email->IsMail();

		if(($key = $this->settings()->access_key) && ($secret = $this->settings()->access_secret)) {
			if(!$email->PreSend())
				return false;

			$date = date('r');
			$message = $email->GetSentMIMEMessage();

			$data = array(
				'Action' => 'SendRawEmail',
				'RawMessage.Data' => base64_encode($message),
			);

			$headers = array(
				'Date: ' . $date,
				'Host: ' . str_replace(array('http://', 'https://'), '', $this->settings()->host),
				'Content-Type: application/x-www-form-urlencoded',
				'X-Amzn-Authorization: AWS3-HTTPS AWSAccessKeyId=' . urlencode($key) . ',Algorithm=HmacSHA256,Signature=' . base64_encode(hash_hmac('sha256', $date, $secret, true)),
			);

			if($log) {
				$headers['CURL_HANDLE_KEY'] = 'SES-' . get_class($log) . '-' . $log->ID;
				$args = array(
					$headers['CURL_HANDLE_KEY'] => array(
						'log' => $log,
						'email' => $email
					)
				);
			}
			else
				$args = array();

			$response = $this->server()->request('', 'POST', http_build_query($data), $headers, array(), $args);

			return $this->handleSESResponse($response, $email, $log);
		}
		else
			throw new MWMMailer_Exception('Please set an AWS Access Key ID & Secret for the application to use');
	}

	public function handleSESResponse($response, $email = null, $log = null) {
		if($response && !($response instanceof RestfulService)) {
			$body = $response->getBody();

			if($response->isError() || !$body) {
				if($body && $log) {
					if($mId = $this->server()->getValue($body, 'RequestId'))
						$log->MessageID = $mId;

					$log->Success = false;
				}

				if($body && ($e = $this->server()->getValues($body, 'Error')->first())) {
					$dev = Director::isDev() ? "\n\n" . urldecode(http_build_query($e->toMap(), '', "\n")) : '';

					throw new MWMMailer_Exception($e->Message . $dev);
				}
				else {
					$dev = Director::isDev() ? "\n" . $response->getStatusDescription() : '';

					throw new MWMMailer_Exception('Problem sending email via Amazon SES api: ' . $dev);
				}
			}

			if($log) {
				if($mId = $this->server()->getValue($body, 'SendRawEmailResult', 'MessageId'))
					$log->MessageID = $mId;

				$log->Success = true;
				$log->Sent = date('Y-m-d H:i:s');
			}
		}

		return true;
	}

	protected function sendViaMailGun($email = null, $log = null) {
		if(!$email) $email = self::inst();

		$email->IsMail();

		if(!$email->PreSend())
			return false;

		$domain = $this->settings()->domain;

		if(!$domain)
			$domain = MWMDirector::baseWebsiteURL();

		if($this->settings()->tracking) {
			$data = array(
				'o:tracking' => true,
				'o:tracking-opens' => true,
				'o:tracking-clients' => true,
			);
		}
		else
			$data = array();

		$response = $this->server()->sendMessage($domain, $data, $email->GetSentMIMEMessage());

		return $this->handleMailGunResponse($response, $email, $log);
	}

	public function handleMailGunResponse($response, $email = null, $log = null) {
		if($response && !($response instanceof RestfulService)) {
			$body = $response->getBody();

			if($response->isError() || !$body) {
				if($body && $log) {
					if($mId = $this->server()->getValue($body, 'RequestId'))
						$log->MessageID = $mId;

					$log->Success = false;
				}

				if($body && ($e = $this->server()->getValues($body, 'Error')->first())) {
					$dev = Director::isDev() ? "\n\n" . urldecode(http_build_query($e->toMap(), '', "\n")) : '';

					throw new MWMMailer_Exception($e->Message . $dev);
				}
				else {
					$dev = Director::isDev() ? "\n" . $response->getStatusDescription() : '';

					throw new MWMMailer_Exception('Problem sending email via Amazon SES api: ' . $dev);
				}
			}

			if($log) {
				if($mId = $this->server()->getValue($body, 'SendRawEmailResult', 'MessageId'))
					$log->MessageID = $mId;

				$log->Success = true;
				$log->Sent = date('Y-m-d H:i:s');
			}
		}

		return true;
	}

	private $_server;

	public function server() {
		$thread = true;

		if(!$this->_server) {
			if($this->settings()->method == 'mail_gun') {
				if($key = $this->settings()->api_key) {
					if($endPoint = $this->settings()->api_endpoint)
						$this->_server = new \Mailgun\Mailgun($key, $endPoint);
					else
						$this->_server = new \Mailgun\Mailgun($key);
				}
				else
					throw new MWMMailer_Exception('Please set a Mail Gun API Key in your config');

				$thread = false;
			}
			else
				$this->_server = MilkywayRestful::create($this->settings()->host, 0)->persist();
		}

		if($thread) {
			if(self::$multi_thread) {
				if(self::$manual_queue_handle)
					$this->_server->threading(true, 10, false);
				else
					$this->_server->threading();
			}
		}

		return $this->_server;
	}

	function __destruct() {
		if($this->_server)
			$this->_server->close();
	}

	public static function handle_mail_queue() {
		Email::mailer()->server()->consumeQueue();
	}

	public static function consume_curl_queue($responses, $server = null, $args = null) {
		if(count($responses)) {
			foreach($responses as $key => $res) {
				if(!isset($res['response'])) continue;

				if(count($args) && isset($args[$key]) && ($email = $args[$key]['email']) && ($log = $args[$key]['log'])) {
					Email::mailer()->handleSESResponse($res['response'], $email, $log);
					$log->write();
				}
				elseif(strstr('SES-', $key)) {
					list($class, $id) = explode('-', str_replace('SES-', '', $key));

					if($class && $id && ClassInfo::exists($class) && $log = DataObject::get($class)->byID((int)$id)) {
						Email::mailer()->handleSESResponse($res['response'], null, $log);
						$log->write();
					}
				}
			}
		}

		self::clear();
	}
}

class MWMMailer_Exception extends Exception { }