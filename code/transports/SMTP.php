<?php namespace Milkyway\SS\SendThis\Transports;

/**
 * Milkyway Multimedia
 * SendThis_SMTP.php
 *
 * @package milkyway-multimedia/silverstripe-send-this
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */
class SMTP extends Mail {
    public function __construct(\PHPMailer $messenger) {
        $messenger->isSMTP();

        $config = SendThis::config();

        if($config->host)
            $messenger->Host = $config->host;

        if($config->port)
            $messenger->Port = $config->port;

        $messenger->SMTPKeepAlive = (bool) $config->keep_alive;

        if($config->username) {
            $messenger->SMTPAuth = true;
            $messenger->Username = $config->username;

            if($config->password)
                $messenger->Password = $config->password;

            if($config->secured_with)
                $messenger->SMTPSecure = $config->secured_with;
        }
    }
}