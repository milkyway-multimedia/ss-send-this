<?php

use \Milkyway\SS\SendThis\Mailer;

/**
 * Milkyway Multimedia
 * SendThis_Log.php
 *
 * @package milkyway-multimedia/silverstripe-send-this
 * @author Mellisa Hankins <mellisa.hankins@me.com>
 */
class SendThis_Log extends DataObject {

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

    private static $extensions = array(
        'Sluggable',
    );

    private static $has_one = array(
        'SentBy' => 'Member',
    );

    private static $has_many = array(
        'Links'   => 'SendThis_Link',
        'Bounces' => 'SendThis_Bounce',
    );

    private static $singular_name = 'Email Log';

    private static $summary_fields = array(
        'To',
        'Subject',
        'Sent',
        'Success',
        'Opened'          => 'Opened',
        'Tracker_ForTemplate' => 'Details',
    );

    private static $casting = array(
        'Tracker_ForTemplate' => 'HTMLText',
    );

    private static $viewable_has_one = array(
        'SentBy',
    );

    private static $default_sort = 'Created DESC';

    /**
     * Find the recent emails sent by a member and/or email
     *
     * @param int    $limit
     * @param null   $member
     * @param string $email
     *
     * @return DataList
     */
    public static function recent($limit = 0, $member = null, $email = '')
    {
        $filters = array();
        $member  = $member && $member !== false ? $member : Member::currentUser();
        if (! $email && $member)
        {
            $email = array($member->Email, $member->ContactEmail, $member->ForEmail);
        }

        if (! $member && ! $email)
        {
            return ArrayList::create();
        }

        if ($member)
        {
            $filters['SentByID'] = $member->ID;
        }

        if ($email)
        {
            $filters['From'] = $email;
        }

        if (count($filters))
        {
            $emails = self::get()
                ->filter('Success', 1)
                ->filterAny($filters)
                ->sort('Created', 'DESC');

            if ($limit)
            {
                return $emails->limit($limit);
            }
        }

        return ArrayList::create();
    }

    /**
     * Map recent emails, ready for @DropdownField
     *
     * @param bool   $emailsOnly
     * @param bool   $limit
     * @param null   $member
     * @param string $email
     *
     * @return array
     */
    public static function recent_to_map($emailsOnly = false, $limit = false, $member = null, $email = '')
    {
        $emails = array();

        if (($recent = self::recent($limit, $member, $email)) && $recent->exists())
        {
            foreach ($recent as $r)
            {
                if ($emailsOnly)
                {
                    list($to, $name) = Mailer::split_email($r->To);
                    $emails[$to] = htmlspecialchars($r->To);
                } else
                {
                    $emails[htmlspecialchars($r->To)] = htmlspecialchars($r->To);
                }

                if ($r->Cc && $cc = explode(',', $r->Cc))
                {
                    foreach ($cc as $c)
                    {
                        $emails[trim($c)] = trim($c);
                    }
                }

                if ($r->Bcc && $bcc = explode(',', $r->Bcc))
                {
                    foreach ($bcc as $bc)
                    {
                        $emails[trim($bc)] = trim($bc);
                    }
                }
            }
        }

        return $emails;
    }

    function getTitle()
    {
        return $this->Subject . ' (' . $this->obj('Created')->Nice() . ')';
    }

    function getCMSFields()
    {
        $this->beforeUpdateCMSFields(
            function (FieldList $fields)
            {
                $fields->removeByName('Track_IP');
                $fields->removeByName('Links');
                $fields->removeByName('Link_Data');
                $fields->removeByName('Track_Links');

                $linksExists = false;

                $fields->insertBefore(
                    HeaderField::create('HEADER-ClientDetails', _t('SendThis_Log.ClientDetails', 'Email Client')),
                    'Type'
                );

                if (! $this->Notes)
                {
                    $fields->removeByName('Notes');
                }

                if ($this->Success && count($this->Tracker))
                {
                    $fields->replaceField(
                        'Track_Data',
                        $tf = ReadonlyField::create(
                            'Readonly_Track_Data',
                            _t('SendThis_Log.Tracker', 'Tracker'),
                            $this->Tracker_ForTemplate(false, true)
                        )
                    );
                    $tf->dontEscape = true;
                } else
                {
                    $fields->removeByName('Track_Data');
                }

                if (! $this->Notify_Sender || ! Email::is_valid_address($this->Notify_Sender))
                {
                    $fields->replaceField(
                        'Notify_Sender',
                        CheckboxField::create(
                            'Notify_Sender',
                            _t('SendThis_Log.NotifySender', 'Notify sender of this email when it fails to deliver')
                        )
                    );
                }

                if ($this->Links()->exists())
                {
                    $linksExists = true;

                    $fields->addFieldsToTab(
                        'Root.Links',
                        array(
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
                            )
                        )
                    );
                }

                if (count($this->LinkData))
                {
                    $linkData = '';

                    foreach ($this->LinkData as $name => $value)
                    {
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

                if (($hasOnes = $this->has_one()) && count($hasOnes))
                {
                    $viewable = (array) $this->config()->viewable_has_one;

                    foreach ($hasOnes as $field => $type)
                    {
                        if (in_array($field, $viewable) && $this->$field()->exists())
                        {
                            if ($old = $fields->dataFieldByName($field))
                            {
                                $fields->removeByName($field);
                                $fields->removeByName($field . 'ID');
                                $fields->addFieldToTab('Root.Related', $old->castedCopy($old->class));
                            } elseif ($old = $fields->dataFieldByName($field . 'ID'))
                            {
                                $fields->removeByName($field);
                                $fields->removeByName($field . 'ID');
                                $fields->addFieldToTab('Root.Related', $old->castedCopy($old->class));
                            }
                        } else
                        {
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

    function setTracker($data = array())
    {
        $this->Track_Data = json_encode($data);
    }

    function getTracker()
    {
        return json_decode($this->Track_Data, true);
    }

    function setLinkData($data = array())
    {
        $this->Link_Data = json_encode($data);
    }

    function getLinkData()
    {
        return json_decode($this->Link_Data, true);
    }

    function Tracker_ForTemplate($includeContent = true, $full = false)
    {
        $output = ArrayList::create();
        $vars   = array('Left' => 1);

        if ($this->Success)
        {
            if ($includeContent)
            {
                $output->push(
                    ArrayData::create(
                        array(
                            'Title'          => _t('SendThis_Log.Content', 'Content'),
                            'FormattedValue' => $this->Type
                        )
                    )
                );
            }

            if (($tracker = $this->Tracker) && count($tracker))
            {
                $exclude = array('OperatingSystem', 'Client', 'Icon', 'OperatingSystemIcon');

                if (! $full)
                {
                    $exclude[] = 'UserAgentString';
                }

                foreach ($tracker as $title => $value)
                {
                    if ($title == 'ClientFull')
                    {
                        $output->push(
                            ArrayData::create(
                                array(
                                    'Title'          => _t('SendThis_Log.LABEL-Client', 'Client'),
                                    'FormattedValue' => isset($tracker['Icon']) ? '<img src="' . $tracker['Icon'] . '" alt="" class="icon-tiny" /> ' . $value : $value
                                )
                            )
                        );
                    } elseif ($title == 'OperatingSystemFull')
                    {
                        $output->push(
                            ArrayData::create(
                                array(
                                    'Title'          => _t('SendThis_Log.LABEL-OS', 'Operating System'),
                                    'FormattedValue' => isset($tracker['OperatingSystemIcon']) ? '<img src="' . $tracker['OperatingSystemIcon'] . '" alt="" class="icon-tiny" /> ' . $value : $value
                                )
                            )
                        );
                    } elseif ($value && ! in_array($title, $exclude))
                    {
                        $output->push(
                            ArrayData::create(
                                array(
                                    'Title'          => _t(
                                        'SendThis_Log.LABEL-' . str_replace(' ', '_', $title),
                                        ucfirst(FormField::name_to_label($title))
                                    ),
                                    'FormattedValue' => $value
                                )
                            )
                        );
                    }
                }
            }

            if ($this->Track_Links && $this->Links()->exists())
            {
                $output->push(
                    ArrayData::create(
                        array(
                            'Title'          => _t('SendThis_Log.LABEL-LinksClicked', 'Links clicked'),
                            'FormattedValue' => $this->Links()->sum('Clicks')
                        )
                    )
                );

                $output->push(
                    ArrayData::create(
                        array(
                            'Title'          => _t('SendThis_Log.LABEL-LinksUnique', 'Unique clicks'),
                            'FormattedValue' => $this->Links()->sum('Visits')
                        )
                    )
                );
            }
        } else
        {
            $vars['Message'] = $this->Notes;
        }

        $vars['Values'] = $output;

        return $this->renderWith('SendThis_Log_Tracker', $vars);
    }

    public function getTrackerField($field)
    {
        $tracker = $this->Tracker;

        if (isset($tracker) && count($tracker) && isset($tracker[$field]))
        {
            return $tracker[$field];
        }

        return null;
    }

    function init($type = 'html', &$headers = null)
    {
        $this->Type = $type;

        if ($headers && is_array($headers))
        {
            if (isset($headers['X-SilverStripeMessageID']))
            {
                $this->MessageID = $headers['X-SilverStripeMessageID'];
            } elseif (isset($headers['X-MilkywayMessageID']))
            {
                $this->MessageID = $headers['X-MilkywayMessageID'];
            } elseif (isset($headers['Message-ID']))
            {
                $this->MessageID = $headers['Message-ID'];
            }
        }

        return $this;
    }

    function canView($member = null)
    {
        return Permission::check('CAN_VIEW_SEND_LOGS');
    }
}