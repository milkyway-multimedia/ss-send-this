<?php namespace Milkyway\SS\SendThis;

/**
 * Milkyway Multimedia
 * Mailer.php
 *
 * @package milkyway-multimedia/ss-send-this
 * @author Mellisa Hankins <mellisa.hankins@me.com>
 */

use Mailer as Original;
use Email;
use Milkyway\SS\Eventful\Contract as Eventful;
use Milkyway\SS\SendThis\Contracts\MessageParser;
use Milkyway\SS\SendThis\Contracts\Transport;
use PHPMailer;
use Object;
use SendThis_Log as Log;
use Exception;
use Config;

class Mailer extends Original
{
    /**
     * Send an email immediately, with ability to provide a callback and alternate transport
     *
     * @param \Email|array $email
     * @param Callable $callback
     * @param array|string $transport
     */
    public static function now($email, $callback = null, $transport = null)
    {
        //@todo implement a quick send function
        if ($callback && is_a(Email::mailer(), __CLASS__)) {
            Email::mailer()->eventful()->listen('sendthis:sent', $callback, true);
        }
    }

    /**
     * Push an email to a queue, with ability to provide a time, callback, alternate transport
     *
     * @param \Email|array $email
     * @param string $time
     * @param Callable $callback
     * @param array|string $transport
     */
    public static function later($email, $time = '', $callback = null, $transport = [])
    {
        //@todo implement a quick queue function
        if ($callback && is_a(Email::mailer(), __CLASS__)) {
            Email::mailer()->eventful()->listen('sendthis:sent', $callback, true);
        }
    }

    public static function config()
    {
        return Config::inst()->forClass('SendThis');
    }

    /** @var array The loaded transports */
    protected $transports = [];

    /** @var \PHPMailer The PHP Mailer instance */
    protected $messenger;

    /** @var \Milkyway\SS\SendThis\Contracts\MessageParser  The message parser */
    protected $parser;

    /** @var \Milkyway\SS\Eventful\Contract  The events manager */
    protected $eventful;

    private $workingTransport;

    /**
     * Split a name <email> string
     *
     * @param $string
     * @return array
     */
    public static function split_email($string)
    {
        if (preg_match('/^\s*(.+)\s+<(.+)>\s*$/', trim($string), $parts)) {
            return [$parts[2], $parts[1]]; // Has name and email
        } else {
            return [trim($string), '']; // Has email
        }
    }

    /**
     * Get an administrator or default email
     *
     * @param string $prepend
     *
     * @return string
     */
    public static function admin_email($prepend = '')
    {
        if ($email = Email::config()->admin_email) {
            return $prepend ? $prepend . '+' . $email : $email;
        }

        $name = $prepend ? $prepend . '+no-reply' : 'no-reply';

        return $name . '@' . trim(str_replace(['http://', 'https://', 'www.'], '',
            singleton('director')->protocolAndHost()), ' /');
    }

    /**
     * Get message id from headers
     *
     * @param $headers
     *
     * @return mixed
     */
    public static function message_id_from_headers($headers)
    {
        if (isset($headers['Message-ID'])) {
            return $headers['Message-ID'];
        }

        if (isset($headers['X-SilverStripeMessageID'])) {
            return $headers['X-SilverStripeMessageID'];
        }

        if (isset($headers['X-MilkywayMessageID'])) {
            return $headers['X-MilkywayMessageID'];
        }

        return '';
    }

    public function __construct(
        PHPMailer $messenger,
        MessageParser $parser,
        Eventful $eventful
    ) {
        parent::__construct();
        $this->messenger = $messenger;
        $this->parser = $parser;
        $this->eventful = $eventful;
    }

    protected function resetMessenger()
    {
        if ($this->messenger) {
            $this->messenger->ClearAllRecipients();
            $this->messenger->ClearReplyTos();
            $this->messenger->ClearAttachments();
            $this->messenger->ClearCustomHeaders();
        }
    }

    public function send(
        $type = 'html',
        $to,
        $from,
        $subject,
        $content,
        $attachedFiles = null,
        $headers = null,
        $plainContent = null
    ) {
        $this->resetMessenger();
        $transport = $this->transport($this->workingTransport);

        if (singleton('env')->get('SendThis.logging')) {
            $log = Log::create()->init($type, $headers);
            $log->Transport = get_class($transport);
        } else {
            $log = null;
        }

        $messageId = $this->message_id_from_headers($headers);
        $params = compact('to', 'from', 'subject', 'content', 'attachedFiles', 'headers');

        $headers = (object)$headers;

        $this->eventful->fire(singleton('sendthis-event')->named('sendthis:up', $this), $messageId, $to, $params,
            $params, $log, $headers);

        $headers = (array)$headers;

        $transport->applyHeaders($headers);

        $this->parser->setMessage($this->messenger);
        $this->parser->setConfig($this->config());
        $message = $this->parser->parse($to, $from, $subject, $attachedFiles, $headers, $transport);

        if ($message) {
            $params['message'] = $message;

            $params['message']->Body = $type != 'html' ? strip_tags($content) : $content;

            if ($type == 'html' || $plainContent) {
                $params['message']->AltBody = $plainContent ? $plainContent : strip_tags($content);
            }

            $this->eventful->fire(singleton('sendthis-event')->named('sendthis:sending', $this), $messageId, $to,
                $params, $params, $log);

            $params['message']->isHTML($type == 'html');

            try {
                $result = $transport->start($params['message'], $log);
            } catch (Exception $e) {
                $result = $e->getMessage();

                $params['message'] = $result;

                $this->eventful->fire(singleton('sendthis-event')->named('sendthis:failed', $this),
                    $this->messenger->getLastMessageID() ?: $messageId, $to, $params, $params, $log);
            }

            if (!$result || $this->messenger->IsError()) {
                $result = $this->messenger->ErrorInfo;
            }

            $messageId = $this->messenger->getLastMessageID() ?: $messageId;
        } else {
            $result = 'Email has been unsubscribed/blacklisted';
        }

        if ($result !== true) {
            $params['message'] = $result;
            $this->eventful->fire(singleton('sendthis-event')->named('sendthis:failed', $this), $messageId, $to,
                $params, $params, $log);
        }

        $this->eventful->fire(singleton('sendthis-event')->named('sendthis:down', $this), $messageId, $to, $params,
            $params, $log);

        $this->resetMessenger();
        $this->workingTransport = null;

        return $result;
    }

    public function sendHTML(
        $to,
        $from,
        $subject,
        $htmlContent,
        $attachedFiles = null,
        $headers = null,
        $plainContent = null
    ) {
        return $this->send('html', $to, $from, $subject, $htmlContent, $attachedFiles, $headers, $plainContent);
    }

    public function sendPlain($to, $from, $subject, $plainContent, $attachedFiles = null, $headers = null)
    {
        return $this->send('plain', $to, $from, $subject, $plainContent, $attachedFiles, $headers);
    }

    public function eventful()
    {
        return $this->eventful;
    }

    protected function transport($transport = null)
    {
        if ($transport instanceof Transport) {
            return $transport;
        }

        if (!is_array($transport)) {
            $transport = $transport ?: $this->config()->transport ?: 'default';

            if (isset($this->transports[$transport])) {
                return $this->transports[$transport];
            }
        }

        $drivers = (array)$this->config()->drivers;
        $transports = (array)$this->config()->transports;

        $driver = null;
        $class = '';

        if (is_array($transport)) {
            $driver = isset($transport['driver']) ? $transport['driver'] : null;
            $class = $driver && is_array($driver) && isset($driver['class']) ? $driver['class'] : '';
        }

        if (!$driver) {
            $driver = isset($transports[$transport]) && isset($transports[$transport]['driver']) ? $transports[$transport]['driver'] : $transport;
        }

        if (!$class) {
            $class = isset($drivers[$driver]) && isset($drivers[$driver]['class']) ? $drivers[$driver]['class'] : $driver;
        }

        $params = (array)$this->config()->params;

        if (isset($drivers[$driver]) && isset($drivers[$driver]['params'])) {
            $params = array_merge($params, (array)$drivers[$driver]['params']);
        }

        if (is_array($transport) && isset($transport['params'])) {
            $params = array_merge($params, (array)$transport['params']);
        } elseif (!isset($transports[$transport]) && isset($transports[$transport]['params'])) {
            $params = array_merge($params, (array)$transports[$transport]['params']);
        }

        if (!isset($params['key']) && singleton('env')->get($driver . '|SendThis.key')) {
            $params['key'] = singleton('env')->get($driver . '|SendThis.key');
        }

        if (!isset($params['secret']) && singleton('env')->get($driver . '|SendThis.secret')) {
            $params['secret'] = singleton('env')->get($driver . '|SendThis.secret');
        }

        $this->transports[$transport] = Object::create($class, $this->messenger, $this, $params);

        return $this->transports[$transport];
    }
}
