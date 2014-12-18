<?php namespace Milkyway\SS\SendThis\Transports;
use Milkyway\SS\SendThis\Mailer;

/**
 * Milkyway Multimedia
 * SendThis_SMTP.php
 *
 * @package milkyway-multimedia/silverstripe-send-this
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */
class SMTP extends Mail {
	public function __construct(\PHPMailer $messenger, Mailer $mailer, $params = []) {
		parent::__construct($messenger, $mailer, $params);

        $messenger->isSMTP();

        if(isset($params['host']))
            $messenger->Host = $params['host'];

		if(isset($params['port']))
			$messenger->Port = $params['port'];

        $messenger->SMTPKeepAlive = isset($params['keep_alive']) ? (bool) $params['keep_alive'] : false;

        if(isset($params['username'])) {
            $messenger->SMTPAuth = true;
            $messenger->Username = $params['username'];

            if(isset($params['password']))
                $messenger->Password = $params['password'];

            if(isset($params['secured_with']))
                $messenger->SMTPSecure = $params['secured_with'];
        }
    }
}