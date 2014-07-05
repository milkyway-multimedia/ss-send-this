<?php

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
        'Track_Open'    => 'Datetime',
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
        'Subject',
        'To',
        'Sent',
        'Success',
        'Track_Open' => 'Opened',
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
                    list($to, $name) = SendThis::split_email($r->To);
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

    /**
     * Append array as query string to url, making sure the $url takes preference
     *
     * @param string $url
     * @param array  $data
     *
     * @return String
     */
    public static function add_link_data($url, $data = array())
    {
        if (! count($data))
        {
            return $url;
        }

        // Make sure data in url takes preference over data from email log
        if (strpos($url, '?') !== false)
        {
            list($newURL, $query) = explode('?', $url, 2);

            $url = $newURL;

            if ($query)
            {
                @parse_str($url, $current);

                if ($current && count($current))
                {
                    $data = array_merge($data, $current);
                }
            }
        }

        if (count($data))
        {
            $linkData = array();

            foreach ($data as $name => $value)
            {
                $linkData[$name] = urlencode($value);
            }

            $url = Controller::join_links($url, '?' . http_build_query($linkData));
        }

        return $url;
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

    function track($ip = null, $write = true)
    {
        $info = array();

        $info['Referrer'] = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;

        if (isset($_SERVER['HTTP_USER_AGENT']) && $_SERVER['HTTP_USER_AGENT'])
        {
            $info['UserAgentString'] = $_SERVER['HTTP_USER_AGENT'];

            $agent    = base64_encode($_SERVER['HTTP_USER_AGENT']);
            $response = @file_get_contents("http://user-agent-string.info/rpc/rpctxt.php?key=free&ua={$agent}");

            if ($response)
            {
                $data = explode('|', $response);

                if (isset($data[0]) && $data[0] < 4)
                {
                    $info['Type']                = isset($data[1]) ? $data[1] : null;
                    $info['Client']              = isset($data[2]) ? $data[2] : null;
                    $info['ClientFull']          = isset($data[3]) ? $data[3] : null;
                    $info['Icon']                = isset($data[7]) ? $data[7] : null;
                    $info['OperatingSystem']     = isset($data[8]) ? $data[8] : null;
                    $info['OperatingSystemFull'] = isset($data[9]) ? $data[9] : null;
                    $info['OperatingSystemIcon'] = isset($data[13]) ? $data[13] : null;

                    if (strtolower($info['Type']) == 'email client')
                    {
                        $this->Track_Client = $info['Client'];
                    } elseif (strtolower($info['Type']) == 'browser' || strtolower($info['Type']) == 'mobile browser')
                    {
                        if (! preg_match('/.*[0-9]$/', $info['ClientFull']))
                        {
                            $this->Track_Client = _t(
                                'SendThis_Log.EMAIL_CLIENT-MAC',
                                'Mac Client (Apple Mail or Microsoft Entourage)'
                            );
                        } elseif ($info['Referrer'])
                        {
                            foreach (static::config()->web_based_clients as $name => $url)
                            {
                                if (preg_match("/$url/", $info['Referrer']))
                                {
                                    $this->Track_Client = _t(
                                        'SendThis_Log.WEB_CLIENT-' . strtoupper(str_replace(' ', '_', $name)),
                                        $name
                                    );
                                    break;
                                }
                            }
                        }

                        if (! $this->Track_Client)
                        {
                            $this->Track_Client = _t('SendThis_Log.BROWSER_BASED', 'Web Browser');
                        }
                    }
                }
            }
        }

        if ($ip)
        {
            $geo = @file_get_contents("http://www.geoplugin.net/json.gp?ip=" . $ip);

            if (($geo = json_decode($geo)) && $country = $geo->geoplugin_countryName)
            {
                $info['Country'] = $country;
            }
        }

        $this->Tracker    = $info;
        $this->Track_Open = SS_Datetime::now()->Rfc2822();

        if ($write)
        {
            $this->write();
        }
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
            }

            foreach ($headers as $k => $v)
            {
                if (strpos($k, 'Log-Relation-') === 0)
                {
                    $rel        = str_replace('Log-Relation-', '', $k) . 'ID';
                    $this->$rel = $v;

                    unset($headers[$k]);
                }
            }

            if (SendThis::config()->tracking && isset($headers['Track-Links']) && $headers['Track-Links'])
            {
                $this->Track_Links = true;
                unset($headers['Track-Links']);
            }

            if (isset($headers['Notify-On-Bounce']) && $headers['Notify-On-Bounce'])
            {
                $this->Notify_Sender = $headers['Notify-On-Bounce'];
                unset($headers['Notify-On-Bounce']);
            }

            if (isset($headers['Links-Data']) && $headers['Links-Data'])
            {
                $data = $headers['Links-Data'];

                if (is_array($data))
                {
                    $this->LinkData = $data;
                } elseif (is_object($data))
                {
                    $this->LinkData = json_decode(json_encode($data), true);
                } else
                {
                    @parse_str($data, $linkData);

                    if ($linkData && count($linkData))
                    {
                        $this->LinkData = $linkData;
                    }
                }

                unset($headers['Links-Data']);
            }

            if (isset($headers['Links-AttachHash']) && $headers['Links-AttachHash'])
            {
                $linkData = isset($linkData) ? $linkData : isset($data) ? $data : array();

                $this->generateHash();

                if ($headers['Links-AttachHash'] === true || $headers['Links-AttachHash'] == 1)
                {
                    if (! isset($linkData['utm_term']))
                    {
                        $linkData['utm_term'] = $this->Track_Hash;
                    }
                } else
                {
                    if (! isset($linkData[$headers['Links-AttachHash']]))
                    {
                        $linkData[$headers['Links-AttachHash']] = $this->Track_Hash;
                    }
                }

                $this->LinkData = $linkData;

                unset($headers['Links-AttachHash']);
            }
        }

        $this->write();

        return $this;
    }

    function log($result, $to, $from, $subject, $content, $attachedFiles = null, $headers = null, $write = true)
    {
        $success = true;

        if (is_array($result))
        {
            $to          = isset($result['to']) ? $result['to'] : $to;
            $from        = isset($result['from']) ? $result['from'] : $from;
            $headers     = isset($result['headers']) ? $result['headers'] : $headers;
            $this->Notes = isset($result['messages']) ? implode("\n", (array) $result['messages']) : null;
        } elseif (is_string($result))
        {
            $this->Notes = $result;
            $success     = false;
        }

        $this->To      = $to;
        $this->From    = $from;
        $this->Mailer  = get_class(Email::mailer());
        $this->Subject = $subject;

        $this->Success = $success;

        if ($this->Success && ! $this->Sent)
        {
            $this->Sent = date('Y-m-d H:i:s');
        }

        $this->Cc      = isset($headers['Cc']) ? $headers['Cc'] : null;
        $this->Bcc     = isset($headers['Bcc']) ? $headers['Bcc'] : null;
        $this->ReplyTo = isset($headers['Reply-To']) ? $headers['Reply-To'] : null;

        $attachments = array();
        $count       = 1;
        if (is_array($attachedFiles) && count($attachedFiles))
        {
            foreach ($attachedFiles as $attached)
            {
                $file = '';
                if (isset($attached['filename']))
                {
                    $file .= $attached['filename'];
                }
                if (isset($attached['mimetype']))
                {
                    $file .= ' <' . $attached['mimetype'] . '>';
                }

                if (! trim($file))
                {
                    $attachments[] = $count . '. File has no info';
                } else
                {
                    $attachments[] = $count . '. ' . $file;
                }

                $count ++;
            }
        }

        if (count($attachments))
        {
            $this->Attachments = count($attachments) . ' files attached: ' . "\n" . implode("\n", $attachments);
        }

        if ($member = Member::currentUser())
        {
            $this->SentByID = $member->ID;
        }

        if ($write)
        {
            $this->write();
        }
    }

    function insertTracker($content, $replace = array('{{tracker}}', '{{tracker-url}}'))
    {
        $url = Director::absoluteURL(str_replace('$Hash', urlencode($this->Slug), SendThis_Tracker::config()->slug));

        return str_replace($replace, array('<img src="' . $url . '" alt="" />', $url), $content);
    }

    function removeTracker($content, $replace = array('{{tracker}}', '{{tracker-url}}'))
    {
        $url = Director::absoluteURL(str_replace('$Hash', urlencode($this->Slug), SendThis_Tracker::config()->slug));

        return str_replace(array_merge($replace, array('<img src="' . $url . '" alt="" />', $url)), '', $content);
    }

    function trackLinks($content)
    {
        if (! $this->Track_Links && ! count($this->LinkData))
        {
            return $content;
        }

        if (preg_match_all("/<a\s[^>]*href=[\"|']([^\"]*)[\"|'][^>]*>(.*)<\/a>/siU", $content, $matches))
        {
            if (isset($matches[1]) && ($urls = $matches[1]))
            {
                $id = (int) $this->ID;

                $replacements = array();

                array_unique($urls);

                $sorted = array_combine($urls, array_map('strlen', $urls));
                arsort($sorted);

                foreach ($sorted as $url => $length)
                {
                    if ($this->Track_Links)
                    {
                        $link = $this->Links()->filter('Original', Convert::raw2sql($url))->first();

                        if (! $link)
                        {
                            $link           = SendThis_Link::create();
                            $link->Original = $this->getURLWithData($url);
                            $link->LogID    = $id;
                            $link->write();
                        }

                        $replacements['"' . $url . '"'] = $link->URL;
                        $replacements["'$url'"]         = $link->URL;
                    } else
                    {
                        $replacements['"' . $url . '"'] = $this->getURLWithData($url);
                        $replacements["'$url'"]         = $this->getURLWithData($url);
                    }
                }

                $content = str_ireplace(array_keys($replacements), array_values($replacements), $content);
            }
        }

        return $content;
    }

    function getURLWithData($url)
    {
        if (! count($this->LinkData))
        {
            return $url;
        }

        return static::add_link_data($url, $this->LinkData);
    }

    function bounced($email, $write = false)
    {
        if ($this->Notify_Sender && ($this->From || $this->SentBy()->exists()))
        {
            $from = $this->Notify_Sender;

            if (! Email::is_valid_address($from))
            {
                $from = $this->SentBy()->exists() ? $this->SentBy()->ForEmail : $this->From;
            }

            $e = Email::create(
                null,
                $from,
                _t(
                    'SendThis_Log.SUBJECT-EMAIL_BOUNCED',
                    'Email bounced: {subject}',
                    array('subject' => $this->Subject)
                ),
                _t(
                    'SendThis_Log.EMAIL_BOUNCED',
                    'An email (subject: {subject}) addressed to {to} sent via {application} has bounced. For security reasons, we cannot display its contents.',
                    array(
                        'subject'     => $this->Subject,
                        'application' => singleton('LeftAndMain')->ApplicationName,
                        'to'          => $email,
                    )
                ) . "\n\n<p>" . $this->Notes . '</p>'
            );

            $e->setTemplate(array('BounceNotification_Email', 'GenericEmail'));

            $e->populateTemplate($this);

            $e->addCustomHeader('X-Milkyway-Priority', 1);
            $e->addCustomHeader('X-Priority', 1);

            $e->send();
        }

        if ($write)
        {
            $this->write();
        }
    }

    function canView($member = null)
    {
        return Permission::check('CAN_VIEW_SEND_LOGS');
    }
}