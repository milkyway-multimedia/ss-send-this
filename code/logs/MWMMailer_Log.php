<?php
/**
 * Milkyway Multimedia
 * MWMMailer_Log.php
 *
 * @package
 * @author Mellisa Hankins <mellisa.hankins@me.com>
 */

class MWMMailer_Log extends DataObject {
	private static $db = array(
		'Notes'         => 'Text',

		'To'            => 'Text',
		'From'          => 'Text',
		'Cc'            => 'Text',
		'Bcc'           => 'Text',
		'ReplyTo'       => 'Varchar(256)',
		'Subject'       => 'Text',
		'Attachments'   => 'Text',

		'Sent'          => 'Datetime',

		'MessageID'     => 'Text',

		'Success'       => 'Boolean',

		'Notify_Sender' => 'Varchar(255)',

		'Type'          => "Enum('html,plain','html')",

		'Track_Open'    => 'Datetime',
		'Track_Client'  => 'Varchar(256)',
		'Track_Data'    => 'Text',
		'Track_Hash'    => 'Varchar(128)',

		'Track_Links'   => 'Boolean',
		'Link_Data'     => 'Text',

		'Mailer'        => 'Varchar(256)',
	);

	private static $has_one = array(
		'SentBy'        => 'Member',
	);

	private static $has_many = array(
		'Links'         => 'MWMMailer_Link',
	);

	private static $singular_name = 'Email Log';
	private static $plural_name = 'Email Logs';

	private static $summary_fields = array(
		'Subject',
		'To',
		'Sent',
		'Success',
		'Track_Open',
		'Tracker_ForTemplate',
	);

	private static $field_labels = array(
		'Track_Open' => 'Opened',
		'Track_Data' => 'Information',
		'Tracker_ForTemplate' => 'Information',
		'Track_IP' => 'IP',
		'Track_Client' => 'Email Client',
		'Track_Hash' => 'Hash',

		'Success' => 'Delivered',
		'Track_Links' => 'Tracking links...',
		'TrackedLinks_Count' => 'Number of links',
		'Notify_Sender' => 'Notify sender on failed delivery',
	);

	private static $casting = array(
		'Tracker_ForTemplate' => 'HTMLText',
		'Success' => 'HTMLText->CMSBoolean',
		'Opened' => 'HTMLText->NiceOrNot',
	);

	private static $viewable_has_one = array(
		'SentBy',
	);

	private static $default_sort = 'Created DESC';

	public static function recent($limit = null, $member = null, $email = null) {
		$filters = array();
		$member = $member && $member !== false ? $member : Member::currentUser();
		if(!$email && $member)
			$email = array($member->Email, $member->ContactEmail, $member->ForEmail);

		if(!$member && !$email) return ArrayList::create();

		if($member)
			$filters['SentByID'] = $member->ID;

		if($email)
			$filters['From'] = $email;

		if(count($filters)) {
			$emails = self::get()
				->filter('Success', 1)
				->filterAny($filters)
				->sort('Created', 'DESC');

			if($limit)
				return $emails->limit($limit);
		}

		return ArrayList::create();
	}

	public static function recent_to_map($emailsOnly = false, $limit = false, $member = null, $email = null) {
		$emails = array();

		if(($recent = self::recent($limit, $member, $email)) && $recent->exists()) {
			foreach($recent as $r) {
				if($emailsOnly) {
					list($to, $name) = MWMMailer::split_email($r->To);
					$emails[$to] = htmlspecialchars($r->To);
				}
				else
					$emails[htmlspecialchars($r->To)] = htmlspecialchars($r->To);

				if($r->Cc && $cc = explode(',', $r->Cc)) {
					foreach($cc as $c)
						$emails[trim($c)] = trim($c);
				}

				if($r->Bcc && $bcc = explode(',', $r->Bcc)) {
					foreach($bcc as $bc)
						$emails[trim($bc)] = trim($bc);
				}
			}
		}

		return $emails;
	}

	function getTitle() {
		return $this->Subject . ' (' . $this->obj('Created')->Nice() . ')';
	}

	function getCMSFields() {
		$this->beforeUpdateCMSFields(function(FieldList $fields) {
			$fields->removeByName('Track_IP');
			$fields->removeByName('Links');
			$fields->removeByName('Link_Data');
			$fields->removeByName('Track_Links');
			$fields->removeByName('Mailer');

			$linksExists = false;

			$fields->insertBefore(HeaderField::create('HEADER-ClientDetails', $this->fieldLabel('ClientDetails')), 'Type');

			if(!$this->Notes)
				$fields->removeByName('Notes');

			if($this->Success && count($this->Tracker)) {
				$fields->replaceField('Track_Data', $tf = ReadonlyField::create('Readonly_Track_Data',  $this->fieldLabel('Track_Data'), $this->Tracker_ForTemplate(false, true)));
				$tf->dontEscape = true;
			}
			else
				$fields->removeByName('Track_Data');

			if(!$this->Notify_Sender || !Email::is_valid_address($this->Notify_Sender))
				$fields->replaceField('Notify_Sender', CheckboxField::create('Notify_Sender', $this->fieldLabel('Notify_Sender')));

			if($this->Links()->exists()) {
				$linksExists =  true;

				$fields->addFieldsToTab('Root.Links', array(
					ReadonlyField::create('TrackedLinks_Count', $this->fieldLabel('TrackedLinks_Count'), $this->Links()->count()),
					GridField::create('TrackedLinks', $this->fieldLabel('TrackedLinks'), $this->Links(), GridFieldConfig_RecordViewer::create())
				));
			}

			if(count($this->LinkData)) {
				$linkData = '';

				foreach($this->LinkData as $name => $value)
					$linkData .= $name . ': ' . $value . "\n";

				$before = $linksExists ? 'Links' : null;

				$fields->addFieldToTab('Root.Links', $ldField = ReadonlyField::create('Readonly_Link_Data', $this->fieldLabel('LinkData'), DBField::create_field('HTMLText', $linkData)->nl2list()), $before);
				$ldField->dontEscape = true;
			}

			if(($hasOnes = $this->has_one()) && count($hasOnes)) {
				$viewable = (array)$this->config()->viewable_has_one;

				foreach($hasOnes as $field => $type) {
					if(in_array($field, $viewable) && $this->$field()->exists()) {
						if($old = $fields->dataFieldByName($field)) {
							$fields->removeByName($field);
							$fields->removeByName($field . 'ID');
							$fields->addFieldToTab('Root.Related', $old->castedCopy($old->class));
						}
						elseif($old = $fields->dataFieldByName($field . 'ID')) {
							$fields->removeByName($field);
							$fields->removeByName($field . 'ID');
							$fields->addFieldToTab('Root.Related', $old->castedCopy($old->class));
						}
					}
					else {
						$fields->removeByName($field);
						$fields->removeByName($field . 'ID');
					}
				}
			}
		});

		$fields = parent::getCMSFields();

		return $fields;
	}

	function onBeforeWrite() {
		parent::onBeforeWrite();
		$this->generateHash();
	}

	function generateHash() {
		if(!$this->Track_Hash) {
			$gen = new RandomGenerator();
			$hash = substr($gen->randomToken(), 0, 128);

			while(self::get()->filter('Track_Hash', $hash)->exists())
				$hash = substr($gen->randomToken(), 0, 128);

			$this->Track_Hash = $hash;
		}
	}

	function setTracker($data = array()) {
		$this->Track_Data = json_encode($data);
	}

	function getTracker() {
		return json_decode($this->Track_Data, true);
	}

	function setLinkData($data = array()) {
		$this->Link_Data = json_encode($data);
	}

	function getLinkData() {
		return json_decode($this->Link_Data, true);
	}

	function Tracker_ForTemplate($includeContent = true, $full = false) {
		$output = ArrayList::create();
		$vars = array('Left' => 1);

		if($this->Success) {
			if($includeContent) {
				$output->push(ArrayData::create(array(
					'Title' => _t('MWMMailer_Log.LABEL-CONTENT', 'Content'),
					'FormattedValue' => $this->Type
				)));
			}

			if(($tracker = $this->Tracker) && count($tracker)) {
				$exclude = array('OperatingSystem', 'Client', 'Icon', 'OperatingSystemIcon');

				if(!$full)
					$exclude[] = 'UserAgentString';

				foreach($tracker as $title => $value) {
					if($title == 'ClientFull') {
						$output->push(ArrayData::create(array(
							'Title' => _t('MWMMailer_Log.LABEL-CLIENT', 'Client'),
							'FormattedValue' => isset($tracker['Icon']) ? '<img src="' . $tracker['Icon'] . '" alt="" class="icon-tiny" /> ' . $value : $value
						)));
					}
					elseif($title == 'OperatingSystemFull') {
						$output->push(ArrayData::create(array(
							'Title' => _t('MWMMailer_Log.LABEL-OS', 'Operating System'),
							'FormattedValue' => isset($tracker['OperatingSystemIcon']) ? '<img src="' . $tracker['OperatingSystemIcon'] . '" alt="" class="icon-tiny" /> ' . $value : $value
						)));
					}
					elseif($value && !in_array($title, $exclude)) {
						$output->push(ArrayData::create(array(
							'Title' => _t('MWMMailer_Log.LABEL-' . str_replace(' ', '_', strtoupper($title)), ucfirst(FormField::name_to_label($title))),
							'FormattedValue' => $value
						)));
					}
				}
			}

			if($this->Track_Links && $this->Links()->exists()) {
				$output->push(ArrayData::create(array(
					'Title' => _t('MWMMailer_Log.LABEL-LINKS_CLICKED', 'Links clicked'),
					'FormattedValue' => $this->Links()->sum('Clicks')
				)));

				$output->push(ArrayData::create(array(
					'Title' => _t('MWMMailer_Log.LABEL-LINKS_UNIQUE', 'Unique clicks'),
					'FormattedValue' => $this->Links()->sum('Visits')
				)));
			}
		}
		else
			$vars['Message'] = $this->Notes;

		$vars['Values'] = $output;

		return $this->renderWith('SubmittedForm_Cell', $vars);
	}

	public function getTrackerField($field) {
		$tracker = $this->Tracker;

		if(isset($tracker) && count($tracker) && isset($tracker[$field]))
			return $tracker[$field];

		return null;
	}

	function track($ip = null, $write = true) {
		$info = array();

		$info['Referrer'] = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;

		if(isset($_SERVER['HTTP_USER_AGENT']) && $_SERVER['HTTP_USER_AGENT']) {
			$info['UserAgentString'] = $_SERVER['HTTP_USER_AGENT'];

			$agent = base64_encode($_SERVER['HTTP_USER_AGENT']);
			$response = @file_get_contents("http://user-agent-string.info/rpc/rpctxt.php?key=free&ua={$agent}");

			if($response) {
				$data = explode('|', $response);

				if(isset($data[0]) && $data[0] < 4) {
					$info['Type'] = isset($data[1]) ? $data[1] : null;
					$info['Client'] = isset($data[2]) ? $data[2] : null;
					$info['ClientFull'] = isset($data[3]) ? $data[3] : null;
					$info['Icon'] = isset($data[7]) ? $data[7] : null;
					$info['OperatingSystem'] = isset($data[8]) ? $data[8] : null;
					$info['OperatingSystemFull'] = isset($data[9]) ? $data[9] : null;
					$info['OperatingSystemIcon'] = isset($data[13]) ? $data[13] : null;

					if(strtolower($info['Type']) == 'email client')
						$this->Track_Client = $info['Client'];
					elseif(strtolower($info['Type']) == 'browser' || strtolower($info['Type']) == 'mobile browser') {
						if(!preg_match('/.*[0-9]$/', $info['ClientFull']))
							$this->Track_Client = _t('MWMMailer_Log.EMAIL_CLIENT-MAC', 'Mac Client (Apple Mail or Microsoft Entourage)');
						elseif($info['Referrer']) {
							foreach(Mailer::config()->web_based_clients as $name => $url) {
								if(preg_match("/$url/", $info['Referrer'])) {
									$this->Track_Client = _t('MWMMailer_Log.WEB_CLIENT-' . strtoupper(str_replace(' ', '_', $name)), $name);
									break;
								}
							}
						}

						$this->Track_Client = _t('MWMMailer_Log.BROWSER_BASED', 'Web Browser');
					}
				}
			}
		}

		if($ip) {
			$geo = @file_get_contents("http://www.geoplugin.net/json.gp?ip=".$ip);

			if(($geo = json_decode($geo)) && $country = $geo->geoplugin_countryName)
				$info['Country'] = $country;
		}

		$this->Tracker = $info;
		$this->Track_Open = SS_Datetime::now()->Rfc2822();

		if($write) $this->write();
	}

	function init($type = 'html', &$headers = null) {
		$this->Type = $type;

		if($headers && is_array($headers)) {
			$mailGun = Mailer::config()->method == 'mail_gun';

			if($mailGun) {
				$headers['X-Mailgun-Track'] = 'yes';
				$headers['X-Mailgun-Track-Opens'] = 'yes';

				if(MWMMailer::settings()->debug) {
					$headers['X-Mailgun-Drop-Message'] = 'yes';
				}
			}

			if(isset($headers['X-SilverStripeMessageID']))
				$this->MessageID = $headers['X-SilverStripeMessageID'];
			elseif(isset($headers['X-MilkywayMessageID']))
				$this->MessageID = $headers['X-MilkywayMessageID'];

			foreach ($headers as $k => $v) {
				if (strpos($k, 'Log-Relation-') === 0) {
					$rel = str_replace('Log-Relation-', '', $k) . 'ID';
					$this->$rel = $v;

					unset($headers[$k]);
				}
			}

			if(isset($headers['Track-Links']) && $headers['Track-Links']) {
				$this->Track_Links = true;
				unset($headers['Track-Links']);

				if($mailGun)
					$headers['X-Mailgun-Track-Clicks'] = 'yes';
			}

			if(isset($headers['Notify-On-Bounce']) && $headers['Notify-On-Bounce']) {
				$this->Notify_Sender = $headers['Notify-On-Bounce'];
				unset($headers['Notify-On-Bounce']);
			}

			if(isset($headers['Links-Data']) && $headers['Links-Data']) {
				$data = $headers['Links-Data'];

				if(is_array($data))
					$this->LinkData = $data;
				elseif(is_object($data))
					$this->LinkData = json_decode(json_encode($data), true);
				else {
					@parse_str($data, $linkData);

					if($linkData && count($linkData))
						$this->LinkData = $linkData;
				}

				unset($headers['Links-Data']);
			}

			if(isset($headers['Links-AttachHash']) && $headers['Links-AttachHash']) {
				$linkData = isset($linkData) ? $linkData : isset($data) ? $data : array();

				$this->generateHash();

				if($headers['Links-AttachHash'] === true || $headers['Links-AttachHash'] == 1) {
					if(!isset($linkData['utm_term']))
						$linkData['utm_term'] = $this->Track_Hash;
				}
				else {
					if(!isset($linkData[$headers['Links-AttachHash']]))
						$linkData[$headers['Links-AttachHash']] = $this->Track_Hash;
				}

				$this->LinkData = $linkData;

				unset($headers['Links-AttachHash']);
			}
		}

		$this->write();

		return $this;
	}

	function log($result, $to, $from, $subject, $content, $attachedFiles = null, $headers = null, $write = true) {
		$success = true;

		if(is_array($result)) {
			$to = isset($result['to']) ? $result['to'] : $to;
			$from = isset($result['from']) ? $result['from'] : $from;
			$headers = isset($result['headers']) ? $result['headers'] : $headers;
			$this->Notes = isset($result['messages']) ? implode("\n", (array)$result['messages']) : null;
		}
		elseif(is_string($result)) {
			$this->Notes = $result;
			$success = false;
		}

		$this->To = $to;
		$this->From = $from;
		$this->Mailer = get_class(Email::mailer());
		$this->Subject = $subject;

		$this->Success = $success;

		if($this->Success && !$this->Sent)
			$this->Sent = date('Y-m-d H:i:s');

		$this->Cc = isset($headers['Cc']) ? $headers['Cc'] : null;
		$this->Bcc = isset($headers['Bcc']) ? $headers['Bcc'] : null;
		$this->ReplyTo = isset($headers['Reply-To']) ? $headers['Reply-To'] : null;

		$attachments = array();
		$count = 1;
		if(is_array($attachedFiles) && count($attachedFiles)) {
			foreach($attachedFiles as $attached) {
				$file = '';
				if(isset($attached['filename']))
					$file .= $attached['filename'];
				if(isset($attached['mimetype']))
					$file .= ' <' . $attached['mimetype'] . '>';

				if(!trim($file))
					$attachments[] = $count . '. File has no info';
				else
					$attachments[] = $count . '. ' . $file;

				$count++;
			}
		}

		if(count($attachments))
			$this->Attachments = count($attachments) . ' files attached: ' . "\n" . implode("\n", $attachments);

		if($member = Member::currentUser())
			$this->SentByID = $member->ID;

		if($write) $this->write();
	}

	function insertTracker($content, $replace = array('{{tracker}}', '{{tracker-url}}')) {
		$url = Director::absoluteURL(str_replace('$Hash', urlencode($this->Track_Hash), MWMDirector::get_link_for_controller('MWMEmailHandler_Tracker')));
		return str_replace($replace, array('<img src="' . $url . '" alt="" />', $url), $content);
	}

	function removeTracker($content, $replace = array('{{tracker}}', '{{tracker-url}}')) {
		$url = Director::absoluteURL(str_replace('$Hash', urlencode($this->Track_Hash), MWMDirector::get_link_for_controller('MWMEmailHandler_Tracker')));
		return str_replace(array_merge($replace, array('<img src="' . $url . '" alt="" />', $url)), '', $content);
	}

	function trackLinks($content) {
		if(!$this->Track_Links && !count($this->LinkData))
			return $content;

		if(preg_match_all("/<a\s[^>]*href=[\"|']([^\"]*)[\"|'][^>]*>(.*)<\/a>/siU", $content, $matches)) {
			if(isset($matches[1]) && ($urls = $matches[1])) {
				$id = (int)$this->ID;

				$replacements = array();

				array_unique($urls);

				$sorted = array_combine($urls, array_map('strlen', $urls));
				arsort($sorted);

				foreach($sorted as $url => $length) {
					if($this->Track_Links) {
						$link = $this->Links()->filter('Original', Convert::raw2sql($url))->first();

						if(!$link) {
							$link = MWMMailer_Link::create();
							$link->Original = $this->getURLWithData($url);
							$link->LogID = $id;
							$link->write();
						}

						$replacements['"' . $url . '"'] = $link->URL;
						$replacements["'$url'"] = $link->URL;
					}
					else {
						$replacements['"' . $url . '"'] = $this->getURLWithData($url);
						$replacements["'$url'"] = $this->getURLWithData($url);
					}
				}

				$content = str_ireplace(array_keys($replacements), array_values($replacements), $content);
			}
		}

		return $content;
	}

	function getURLWithData($url) {
		if(!count($this->LinkData)) return $url;
		return MWMDirector::add_link_data($url, $this->LinkData);
	}

	function bounced($email, $write = false) {
		if($this->Notify_Sender && ($this->From || $this->SentBy()->exists())) {
			$from = $this->Notify_Sender;

			if(!Email::is_valid_address($from))
				$from = $this->SentBy()->exists() ? $this->SentBy()->ForEmail : $this->From;

			$e = Email::create(
				null,
				$from,
				_t('MWMMailer_Log.SUBJECT-EMAIL_BOUNCED', 'Email bounced: {subject}', array('subject' => $this->Subject)),
				_t('MWMMailer_Log.EMAIL_BOUNCED', 'An email (subject: {subject}) addressed to {to} sent via the CMS has bounced. For security reasons, we cannot display its contents.', array(
						'subject' => $this->Subject,
						'to' => $email,
					)
				) . "\n\n<p>" . $this->Notes . '</p>');

			$e->setTemplate(array('BounceNotificationEmail', 'GenericEmail'));

			$e->populateTemplate($this);

			$e->addCustomHeader('X-Milkyway-Priority', 1);

			$e->send();
		}

		if($write)
			$this->write();
	}

	function canView($member = null) {
		return Permission::check('CAN_VIEW_LOGS');
	}
}

class MWMMailer_Link extends DataObject {
	private static $db = array(
		'Original'  => 'Varchar(255)',
		'Hash'      => 'Varchar(100)',
		'Visits'    => 'Int',
		'Clicks'    => 'Int',
	);

	private static $has_one = array(
		'Log'        => 'MWMMailer_Log',
	);

	private static $summary_fields = array(
		'Original',
		'Visits',
		'Clicks',
	);

	private static $field_labels = array(
		'Original' => 'URL',
	);

	function onBeforeWrite() {
		parent::onBeforeWrite();

		if(!$this->Hash) {
			$gen = new RandomGenerator();
			$hash = substr($gen->randomToken(), 0, 100);

			while(self::get()->filter('Hash', $hash)->exists())
				$hash = substr($gen->randomToken(), 0, 100);

			$this->Hash = $hash;
		}
	}

	function getURL() {
		if(!$this->Hash) $this->write();

		return Director::absoluteURL(Controller::join_links(MWMDirector::get_link_for_controller('MWMEmailHandler'), 'links', urlencode($this->Hash)));
	}

	function track() {
		if(!Cookie::get('mwm-email-link-' . $this->Hash)) {
			$this->Visits++;
			Cookie::set('mwm-email-link-' . $this->Hash, true);
		}

		$this->Clicks++;
		$this->write();

		return $this->Original;
	}

	function canView($member = null) {
		return true;
	}
}

class MWMMailer_Bounce extends DataObject {
	private static $db = array(
		'Email'         => 'Text',
		'Message'       => 'Text',
	);

	private static $has_one = array(
		'Log'           => 'MWMMailer_Log',
	);

	private static $singular_name = 'Bounced Email';

	public function getTitle() {
		return $this->Email;
	}
}

class MWMMailer_Blacklist extends DataObject {
	private static $db = array(
		'Email'         => 'Text',
		'Message'       => 'Text',
		'Valid'         => 'Boolean',
	);

	private static $has_one = array(
		'Member'        => 'Member',
	);

	private static $defaults = array(
		'Valid'         => 1,
	);

	private static $singular_name = 'Blacklisted Email';

	public function getTitle() {
		return $this->Email;
	}

	public function getCMSFields() {
		$this->beforeUpdateCMSFields(function(FieldList $fields) {
			if($email = $fields->dataFieldByName('Email'))
				$fields->replaceField('Email', $email->castedCopy(EmailField::create('Email')));

			if($fields->dataFieldByName('Valid'))
				$fields->dataFieldByName('Valid')->setDescription(_t('MWMMailer_Blacklist.DESC-VALID', 'If ticked, important email communications are still sent to this email (such as those conferring receipts when a user purchases from the site etc)'));
		});

		$fields = parent::getCMSFields();

		return $fields;
	}
}