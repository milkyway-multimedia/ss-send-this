<?php

/**
 * Milkyway Multimedia
 * SendThis_Log.php
 *
 * @package milkyway-multimedia/ss-send-this
 * @author Mellisa Hankins <mellisa.hankins@me.com>
 */

use Milkyway\SS\SendThis\Mailer;

class SendThis_Log extends DataObject
{

    private static $web_based_clients = [
        'Gmail'       => 'google.co',
        'Hotmail'     => 'live.co',
        'Yahoo! Mail' => 'yahoo.co',
        'Mail.com'    => 'mail.co',
        'AOL Mail'    => 'aol.co',
        'Inbox'       => 'inbox.co',
        'FastMail'    => 'fastmail.fm',
        'Lycos Mail'  => 'lycos.co',
    ];

    private static $db = [
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
        'Opened'        => 'Datetime',
        'Opens'         => 'Int',
        'Track_Client'  => 'Varchar(256)',
        'Track_Data'    => 'Text',
        'Track_Links'   => 'Boolean',
        'Link_Data'     => 'Text',
        'Transport'     => 'Varchar(256)',
    ];

    private static $extensions = [
        'Milkyway\\SS\\Behaviours\\Extensions\\Sluggable',
    ];

    private static $has_one = [
        'SentBy' => 'Member',
    ];

    private static $has_many = [
        'Links'   => 'SendThis_Link',
        'Bounces' => 'SendThis_Bounce',
    ];

    private static $singular_name = 'Email Log';

    private static $summary_fields = [
        'To',
        'Subject',
        'Sent',
        'Success',
        'Opened'              => 'Opened',
        'Tracker_ForTemplate' => 'Details',
    ];

    private static $casting = [
        'Tracker_ForTemplate' => 'HTMLText',
    ];

    private static $viewable_has_one = [
        'SentBy',
    ];

    private static $default_sort = 'Created DESC';

    protected $excludeFromTrackerReports = [
        'Icon',
        'ClientBrand',
        'ClientIcon',
        'ClientLink',
        'ClientCompany',
        'ClientCompanyLink',
        'CountryCode',

        'OperatingSystemIcon',
        'OperatingSystemBrand',
        'OperatingSystemLink',
        'OperatingSystemCompany',
        'OperatingSystemCompanyLink',
    ];

    /**
     * Find the recent emails sent by a member and/or email
     *
     * @param int $limit
     * @param null $member
     * @param string $email
     *
     * @return DataList
     */
    public static function recent($limit = 0, $member = null, $email = '')
    {
        $filters = [];
        $member = $member && $member !== false ? $member : Member::currentUser();
        if (!$email && $member) {
            $email = [$member->Email, $member->ContactEmail, $member->ForEmail];
        }

        if (!$member && !$email) {
            return ArrayList::create();
        }

        if ($member) {
            $filters['SentByID'] = $member->ID;
        }

        if ($email) {
            $filters['From'] = $email;
        }

        if (!empty($filters)) {
            $emails = self::get()
                ->filter('Success', 1)
                ->filterAny($filters)
                ->sort('Created', 'DESC');

            if ($limit) {
                return $emails->limit($limit);
            }
        }

        return ArrayList::create();
    }

    /**
     * Map recent emails, ready for @DropdownField
     *
     * @param bool $emailsOnly
     * @param bool $limit
     * @param null $member
     * @param string $email
     *
     * @return array
     */
    public static function recent_to_map($emailsOnly = false, $limit = false, $member = null, $email = '')
    {
        $emails = [];

        if (($recent = self::recent($limit, $member, $email)) && $recent->exists()) {
            foreach ($recent as $r) {
                if ($emailsOnly) {
                    list($to, $name) = Mailer::split_email($r->To);
                    $emails[$to] = htmlspecialchars($r->To);
                } else {
                    $emails[htmlspecialchars($r->To)] = htmlspecialchars($r->To);
                }

                if ($r->Cc && $cc = explode(',', $r->Cc)) {
                    foreach ($cc as $c) {
                        $emails[trim($c)] = trim($c);
                    }
                }

                if ($r->Bcc && $bcc = explode(',', $r->Bcc)) {
                    foreach ($bcc as $bc) {
                        $emails[trim($bc)] = trim($bc);
                    }
                }
            }
        }

        return $emails;
    }

    public function getTitle()
    {
        return $this->Subject . ' (' . $this->obj('Created')->Nice() . ')';
    }

    public function getCMSFields()
    {
        $this->beforeUpdateCMSFields(
            function (FieldList $fields) {
                $fields->removeByName('Track_IP');
                $fields->removeByName('Links');
                $fields->removeByName('Link_Data');
                $fields->removeByName('Track_Links');

                $linksExists = false;

                $fields->insertBefore(
                    HeaderField::create('HEADER-ClientDetails', _t('SendThis_Log.ClientDetails', 'Email Client')),
                    'Type'
                );

                if (!$this->Notes) {
                    $fields->removeByName('Notes');
                }

                if ($this->Success && !empty($this->Tracker)) {
                    $fields->replaceField(
                        'Track_Data',
                        $tf = ReadonlyField::create(
                            'Readonly_Track_Data',
                            _t('SendThis_Log.Tracker', 'Tracker'),
                            $this->Tracker_ForTemplate(false, true)
                        )
                    );
                    $tf->dontEscape = true;
                } else {
                    $fields->removeByName('Track_Data');
                }

                if (!$this->Notify_Sender || !Email::is_valid_address($this->Notify_Sender)) {
                    $fields->replaceField(
                        'Notify_Sender',
                        CheckboxField::create(
                            'Notify_Sender',
                            _t('SendThis_Log.NotifySender', 'Notify sender of this email when it fails to deliver')
                        )
                    );
                }

                if ($this->Links()->exists()) {
                    $linksExists = true;

                    $fields->addFieldsToTab(
                        'Root.Links',
                        [
                            ReadonlyField::create(
                                'TrackedLinks_Count',
                                _t('SendThis_Log.TrackedLinks_Count', 'Number of tracked links'),
                                $this->Links()->count()
                            ),
                            GridField::create(
                                'TrackedLinks',
                                _t('SendThis_Log.TrackedLinks', 'Tracked Links'),
                                $this->Links(),
                                GridFieldConfig_RecordViewer::create()
                            ),
                        ]
                    );
                }

                if (!empty($this->LinkData)) {
                    $linkData = '';

                    foreach ($this->LinkData as $name => $value) {
                        $linkData .= $name . ': ' . $value . "\n";
                    }

                    $before = $linksExists ? 'Links' : null;

                    $fields->addFieldToTab(
                        'Root.Links',
                        $ldField = ReadonlyField::create(
                            'Readonly_Link_Data',
                            _t('SendThis_Log.LinkData', 'Link data'),
                            DBField::create_field('HTMLText', $linkData)->nl2list()
                        ),
                        $before
                    );
                    $ldField->dontEscape = true;
                }

                if (($hasOnes = $this->hasOne()) && !empty($hasOnes)) {
                    $viewable = (array)$this->config()->viewable_has_one;

                    foreach ($hasOnes as $field => $type) {
                        if (in_array($field, $viewable) && $this->$field()->exists()) {
                            if ($old = $fields->dataFieldByName($field)) {
                                $fields->removeByName($field);
                                $fields->removeByName($field . 'ID');
                                $fields->addFieldToTab('Root.Related', $old->castedCopy($old->class));
                            } elseif ($old = $fields->dataFieldByName($field . 'ID')) {
                                $fields->removeByName($field);
                                $fields->removeByName($field . 'ID');
                                $fields->addFieldToTab('Root.Related', $old->castedCopy($old->class));
                            }
                        } else {
                            $fields->removeByName($field);
                            $fields->removeByName($field . 'ID');
                        }
                    }
                }
            }
        );

        $fields = parent::getCMSFields();

        return $fields;
    }

    public function setTracker($data = [])
    {
        $this->Track_Data = json_encode($data);
    }

    public function getTracker()
    {
        return json_decode($this->Track_Data, true);
    }

    public function setLinkData($data = [])
    {
        $this->Link_Data = json_encode($data);
    }

    public function getLinkData()
    {
        return json_decode($this->Link_Data, true);
    }

    public function Tracker_ForTemplate($includeContent = true, $full = false)
    {
        $output = ArrayList::create();
        $vars = ['Left' => 1];

        if ($this->Success) {
            if ($includeContent) {
                $output->push(
                    ArrayData::create(
                        [
                            'Title'          => _t('SendThis_Log.Content', 'Content'),
                            'FormattedValue' => $this->Type,
                        ]
                    )
                );
            }

            if (($tracker = $this->Tracker) && !empty($tracker)) {
                $exclude = $this->excludeFromTrackerReports;

                if (!$full) {
                    $exclude[] = 'UserAgentString';
                }

                foreach ($tracker as $title => $value) {
                    if ($title == 'Client') {
                        $formattedValue = isset($tracker['Icon']) ? '<img src="' . $tracker['Icon'] . '" alt="" class="icon-tiny" /> ' . $this->getClientFromTracker($value,
                                $tracker) : $this->getClientFromTracker($value, $tracker);

                        if (isset($tracker['ClientLink'])) {
                            $formattedValue = '<a href="' . $tracker['ClientLink'] . '" target="_blank">' . $formattedValue . '</a>';
                        }

                        $output->push(
                            ArrayData::create(
                                [
                                    'Title'          => _t('SendThis_Log.LABEL-Client', 'Client'),
                                    'FormattedValue' => $formattedValue,
                                ]
                            )
                        );
                    } elseif ($title == 'OperatingSystemFull') {
                        $output->push(
                            ArrayData::create(
                                [
                                    'Title'          => _t('SendThis_Log.LABEL-OS', 'Operating System'),
                                    'FormattedValue' => isset($tracker['OperatingSystemIcon']) ? '<img src="' . $tracker['OperatingSystemIcon'] . '" alt="" class="icon-tiny" /> ' . $value : $value,
                                ]
                            )
                        );
                    } elseif ($value && !in_array($title, $exclude)) {
                        $output->push(
                            ArrayData::create(
                                [
                                    'Title'          => _t(
                                        'SendThis_Log.LABEL-' . str_replace(' ', '_', $title),
                                        ucfirst(FormField::name_to_label($title))
                                    ),
                                    'FormattedValue' => $value,
                                ]
                            )
                        );
                    }
                }
            }

            if ($this->Track_Links && $this->Links()->exists()) {
                $output->push(
                    ArrayData::create(
                        [
                            'Title'          => _t('SendThis_Log.LABEL-LinksClicked', 'Links clicked'),
                            'FormattedValue' => $this->Links()->sum('Clicks'),
                        ]
                    )
                );

                $output->push(
                    ArrayData::create(
                        [
                            'Title'          => _t('SendThis_Log.LABEL-LinksUnique', 'Unique clicks'),
                            'FormattedValue' => $this->Links()->sum('Visits'),
                        ]
                    )
                );
            }
        } else {
            $vars['Message'] = $this->Notes;
        }

        $vars['Values'] = $output;

        return $this->renderWith('SendThis_Log_Tracker', $vars);
    }

    public function getTrackerField($field)
    {
        $tracker = $this->Tracker;

        if (isset($tracker) && !empty($tracker) && isset($tracker[$field])) {
            return $tracker[$field];
        }

        return null;
    }

    public function init($type = 'html', &$headers = null)
    {
        $this->Type = $type;

        if ($headers && is_array($headers)) {
            if (isset($headers['X-SilverStripeMessageID'])) {
                $this->MessageID = $headers['X-SilverStripeMessageID'];
            } elseif (isset($headers['X-MilkywayMessageID'])) {
                $this->MessageID = $headers['X-MilkywayMessageID'];
            } elseif (isset($headers['Message-ID'])) {
                $this->MessageID = $headers['Message-ID'];
            }
        }

        return $this;
    }

    public function canView($member = null)
    {
        $method = __FUNCTION__;

        $this->beforeExtending(__FUNCTION__, function ($member) use ($method) {
            if (Permission::check('CAN_VIEW_SEND_LOGS', 'any', $member)) {
                return true;
            }
        });

        return parent::canView($member);
    }

    protected function getClientFromTracker($client, $tracked = null)
    {
        if (!$tracked) {
            $tracked = $this->Tracker;
        }

        if (strtolower($client) == 'gmail image proxy') {
            return _t('SendThis_Log.WEB_CLIENT-GMAIL', 'Gmail');
        }
        if (isset($tracked['Type']) && strtolower($tracked['Type']) == 'email client') {
            $client = $tracked['Client'];
        } elseif (isset($tracked['Type']) && strtolower($tracked['Type']) == 'browser' || strtolower($tracked['Type']) == 'mobile browser') {
            if (isset($tracked['ClientFull']) && !preg_match('/.*[0-9]$/', $tracked['ClientFull'])) {
                $client = _t(
                    'SendThis_Log.EMAIL_CLIENT-MAC',
                    'Mac Client (Apple Mail or Microsoft Entourage)'
                );
            } elseif (isset($tracked['Referrer'])) {
                foreach ($this->config()->web_based_clients as $name => $url) {
                    if (preg_match("/$url/", $tracked['Referrer'])) {
                        $client = _t(
                            'SendThis_Log.WEB_CLIENT-' . strtoupper(str_replace(' ', '_', $name)),
                            $name
                        );
                        break;
                    }
                }
            }

            if (!$client) {
                $client = _t('SendThis_Log.BROWSER_BASED', 'Web Browser');
            }
        }

        return $client;
    }
}
