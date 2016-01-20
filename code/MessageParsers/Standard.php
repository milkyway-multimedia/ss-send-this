<?php namespace Milkyway\SS\SendThis\MessageParsers;

/**
 * Milkyway Multimedia
 * Standard.php
 *
 * @package milkyway-multimedia/ss-send-this
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

use Milkyway\SS\SendThis\Contracts\MessageParser as Contract;
use Milkyway\SS\SendThis\Contracts\Transport;
use PHPMailer;
use Milkyway\SS\SendThis\Mailer;
use SendThis_Blacklist;
use Config_ForClass;
use Email;

class Standard implements Contract
{
    protected $message;
    protected $config;

    /** @var bool Whether there is no to email in current message */
    protected $noTo = false;

    public function __construct(PHPMailer $message = null, Config_ForClass $config = null)
    {
        $this->message = $message;
        $this->config = $config;
    }

    public function setMessage(PHPMailer $message)
    {
        $this->message = $message;
        return $this;
    }

    public function setConfig(Config_ForClass $config)
    {
        $this->config = $config;
        return $this;
    }

    public function parse($to, $from, $subject, $attachedFiles = null, $headers = null, Transport $transport = null)
    {
        if (!$headers) {
            $headers = [];
        }

        $sameDomain = $transport->param('from_same_domain_only') ?: $this->config->from_same_domain_only;

        $ignoreValid = false;

        if (isset($headers['X-Milkyway-Priority'])) {
            $ignoreValid = true;
            unset($headers['X-Milkyway-Priority']);
        }

        // set the to
        if (!$this->addEmail($to, 'addAddress', $this->message, $ignoreValid)) {
            $this->noTo = true;
        }

        list($doFrom, $doFromName) = Mailer::split_email($from);

        if ($sameDomain) {
            $base = '@' . singleton('director')->baseWebsiteURL();

            if (!is_bool($sameDomain) || !$doFrom || !(substr($doFrom, -strlen($base)) === $base)) {
                $realFrom = $doFrom;
                $realFromName = $doFromName;

                if (!is_bool($sameDomain)) {
                    list($doFrom, $doFromName) = Mailer::split_email($sameDomain);
                    if (!$realFromName && !$doFromName) {
                        $doFromName = class_exists('SiteConfig') ? singleton('SiteConfig')->current_site_config()->AdminName : singleton('LeftAndMain')->ApplicationName;
                    }
                }

                if ((!$doFrom || (is_bool($sameDomain) && !(substr($doFrom,
                                    -strlen($base)) === $base))) && class_exists('SiteConfig')
                ) {
                    list($doFrom, $doFromName) = Mailer::split_email(singleton('SiteConfig')->current_site_config()->AdminEmail);
                    if (!$realFromName && !$doFromName) {
                        $doFromName = singleton('SiteConfig')->current_site_config()->AdminName;
                    }
                }

                if (!$doFrom || (is_bool($sameDomain) && !(substr($doFrom, -strlen($base)) === $base))) {
                    list($doFrom, $doFromName) = Mailer::split_email(Mailer::admin_email());
                    if (!$realFromName && !$doFromName) {
                        $doFromName = singleton('LeftAndMain')->ApplicationName;
                    }
                }

                if (!isset($headers['Reply-To'])) {
                    $this->message->addReplyTo($realFrom, $realFromName);
                }
            }
        }

        if (!$doFrom) {
            if (class_exists('SiteConfig')) {
                list($doFrom, $doFromName) = Mailer::split_email(singleton('SiteConfig')->current_site_config()->AdminEmail);
            }

            if (!$doFrom) {
                list($doFrom, $doFromName) = Mailer::split_email(Mailer::admin_email());
            }
        }

        $this->message->setFrom($doFrom, $doFromName);
        $this->message->Subject = $subject;

        if (is_array($attachedFiles)) {
            foreach ($attachedFiles as $file) {
                if (isset($file['tmp_name']) && isset($file['name'])) {
                    $this->message->addAttachment($file['tmp_name'], $file['name']);
                } elseif (isset($file['contents'])) {
                    $this->message->addStringAttachment($file['contents'], $file['filename']);
                } else {
                    $this->message->addAttachment($file);
                }
            }
        }

        if (is_array($headers) && !empty($headers)) {
            // the carbon copy header has to be 'Cc', not 'CC' or 'cc' -- ensure this.
            if (isset($headers['CC'])) {
                $headers['Cc'] = $headers['CC'];
                unset($headers['CC']);
            }
            if (isset($headers['cc'])) {
                $headers['Cc'] = $headers['cc'];
                unset($headers['cc']);
            }

            // the carbon copy header has to be 'Bcc', not 'BCC' or 'bcc' -- ensure this.
            if (isset($headers['BCC'])) {
                $headers['Bcc'] = $headers['BCC'];
                unset($headers['BCC']);
            }
            if (isset($headers['bcc'])) {
                $headers['Bcc'] = $headers['bcc'];
                unset($headers['bcc']);
            }

            if (isset($headers['Cc'])) {
                $this->addEmail($headers['Cc'], 'AddCC', $this->message, $ignoreValid);
                unset($headers['Cc']);
            }

            if (isset($headers['Bcc'])) {
                $this->addEmail($headers['Bcc'], 'AddBCC', $this->message, $ignoreValid);
                unset($headers['Bcc']);
            }

            if (isset($headers['X-SilverStripeMessageID'])) {
                if (!isset($headers['Message-ID'])) {
                    $headers['Message-ID'] = $headers['X-SilverStripeMessageID'];
                }

                $headers['X-MilkywayMessageID'] = $headers['X-SilverStripeMessageID'];
                unset($headers['X-SilverStripeMessageID']);
            }

            if (isset($headers['X-SilverStripeSite'])) {
                $headers['X-MilkywaySite'] = class_exists('SiteConfig') ? singleton('SiteConfig')->current_site_config()->Title : singleton('LeftAndMain')->ApplicationName;
                unset($headers['X-SilverStripeSite']);
            }

            if (isset($headers['Reply-To'])) {
                $this->addEmail($headers['Reply-To'], 'AddReplyTo', $this->message, true);
                unset($headers['Reply-To']);
            }

            if (isset($headers['X-Priority'])) {
                $this->message->Priority = $headers['X-Priority'];
                unset($headers['X-Priority']);
            }
        }

        // Email has higher chance of being received if there is a too email sent...
        if ($this->noTo && $to = Email::config()->default_to_email) {
            $this->addEmail($to, 'AddAddress', $this->message, $ignoreValid);
            $this->noTo = false;
        }

        $server = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : singleton('LeftAndMain')->ApplicationName;
        $this->message->XMailer = sprintf('SendThis Mailer 0.2 (Sent from %s)', $server);

        if ($this->config->confirm_reading_to) {
            $this->message->ConfirmReadingTo = $this->config->confirm_reading_to;
        }

        if ($this->config->word_wrap) {
            $this->message->WordWrap = $this->config->word_wrap;
        }

        foreach ($headers as $k => $v) {
            $this->message->AddCustomHeader($k, $v);
        }

        foreach ((array)$this->config->headers as $k => $v) {
            $this->message->AddCustomHeader($k, $v);
        }

        return $this->message;
    }

    protected function addEmail($in, $func, $email = null, $break = false, $ignoreValid = false, $hidden = false)
    {
        if (!$email) {
            $email = $this->message;
        }

        $success = false;

        $list = explode(',', $in);
        foreach ($list as $item) {
            if (!trim($item)) {
                continue;
            }

            list($a, $b) = Mailer::split_email($item);

            if (SendThis_Blacklist::check($a, $ignoreValid)) {
                if ($break) {
                    return false;
                } else {
                    continue;
                }
            }

            if (!$a || !Email::is_valid_address(($a))) {
                continue;
            }

            $success = true;

            if (!$hidden && $this->noTo) {
                $email->AddAddress($a, $b);
                $this->noTo = false;
            } else {
                $email->$func($a, $b);
            }
        }

        return $success;
    }
}
