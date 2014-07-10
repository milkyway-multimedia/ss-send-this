Send This (Email Transport)
======
**Send This** is another email transport/mailer for Silverstripe, but a bit more comprehensive in it can support different methods of delivery (transports) and has support for web hooks.

It is focused on sending email via API and SMTP, and supports the following methods of transport:
- Mandrill (via API)
- Amazon SES (via API)
- SMTP

It also has logging and tracking support on the Silverstripe Side, and web hook handling if you are using Mandrill or Amazon SES (all configurable). It will also handle bounces if you are using the SMTP endpoint of Mandrill or Amazon SES if you prefer that route for some reason...

It is inspired by the Laravel Mail Transport by @taylorotwell, but uses the PHPMailer and HTTP/Guzzle libraries

In future hopefully it will integrate with a Silverstripe Queue Module, so you will be able to push mail off to a queue and make the whole process a little bit faster for the end user.

## Install
Add the following to your composer.json file

```

    "require"          : {
		"milkyway-multimedia/silverstripe-send-this": "dev-master"
	}
	
```

### Web Hook Events
To handle web hooks, events are fired. The namespace events currently being used are (not all web hooks use all these events):

- up: The mailer is initialised (the initial headers are passed as an additional argument - casted as an object - at this point, which can be edited by listeners)
- down: The mailer has stopped processing the email
- sending: The email will enter the transport for sending
- sent: The email was successfully sent via the transport
- failed: The email was not sent successfully via the transport
- delivered: The email was successfully delivered by the end point (this differs to the 'sent' hook, which is called by the transport internally)
- bounced: The email bounced - on a hard bounce, the default logging handler fires the 'spam' event
- opened: The email was opened
- clicked: A link in the email was clicked (instead of passing a log, a link object will be passed as the fifth argument)
- spam: The email was marked as spam, or triggered a complaint
- delayed: The email was delayed to avoid flooding (note: most transactional email systems will implement this system, but not all will fire an even when doing so)
- rejected: The email was rejected by the end point
- unsubscribed: The email address was unsubscribed by the end point
- whitelisted: The email address was whitelisted at the end point
- blacklisted: The email address was blacklisted at the end point
- hooked: Web hook has been asked for confirmation from this application

You can use these web hooks to sync your application to the transactional email system.

Events pass four arguments when called: string $messageId, string|array $email, array $params, array $response, $log = null (log is only passed for internal events. For web hooks, you can find the log by message id)

You can subscribe to an event hook by calling

```

    SendThis::listen(['up', 'sent'], function($messageId = '', $email = '', $params = [], $response = [], $log = '', $headers = null) {});

```

The second parameter is a callable, so it can be an anonymous function, a callable array, or if you pass an object, it will assume the web hook is mapped to a method with the same name on the object (see _config.php for examples)

### Transports
By default, this module will use PHP Mail (as per the normal Silverstripe mailer, but implementing PHPMailer). To use the other setups, please read on.

1. None: If no transport is specified, will use php mail() function
2. SMTP: To use this transport, you must specify a **host** that you will send the email through
3. Amazon SES: To use this transport, you must specify a **key** and an access **secret**
4. Mandrill: To use this transport, you must specify a **key**

The following options are available for your YAML config.

```

    SendThis:
      transport: 'smtp|ses|mandrill'

      host: 'only needed if you are using smtp transport'
      port: '(optional) only set if you are using smtp transport'
      username: '(optional) only set if you are using smtp transport'
      password: '(optional) only set if you are using smtp transport'
      secured_with: '(optional) only set if you are using smtp transport'
      keep_alive: '(optional) only set if you are using smtp transport'

      key: 'only needed if you are using ses or mandrill transport'
      secret: 'only needed if you are using ses transport'

      logging: true
      tracking: false
      api_tracking: true (this is slightly different to the above, in that it only uses tracking on the transport rather than CMS based tracking)
      from_same_domain_only: true
      notify_on_fail: false
      blacklist_after_bounced: 2

      filter_from_reports: (you can specify some emails that should be filtered from reports in the CMS, such as test emails)
      filter_from_logs: (you can specify emails that will completely skip logging when sent to)
      headers: (you can specify some default headers that will be sent with all emails as an associative array)

```

## License 
* MIT

## Version 
* Version 0.1 - Alpha

## Contact
#### Milkyway Multimedia
* Homepage: http://milkywaymultimedia.com.au
* E-mail: mell@milkywaymultimedia.com.au
* Twitter: [@mwmdesign](https://twitter.com/mwmdesign "mwmdesign on twitter")