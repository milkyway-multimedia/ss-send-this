Send This (Email Transport)
======
**Send This** is another email transport/mailer for Silverstripe, but a bit more comprehensive in it can support different methods of delivery (transports) and has support for web hooks.

It is focused on sending email via API and SMTP, and supports the following methods of transport:
- Mandrill (via API)
- Amazon SES (via API)
- SendGrid (via API)
- Mailgun (via API)
- SparkPost (via API)
- SMTP
- PHP Mail

It also has logging and tracking support on the Silverstripe Side, and web hook handling if you are using Mandrill or Amazon SES (all configurable). It will also handle bounces if you are using the SMTP endpoint of Mandrill or Amazon SES if you prefer that route for some reason...

It is inspired by the Laravel Mail Transport by @taylorotwell, but uses the PHPMailer and HTTP/Guzzle libraries

In future hopefully it will integrate with a Silverstripe Queue Module, so you will be able to push mail off to a queue and make the whole process a little bit faster for the end user.

## Install
Add the following to your composer.json file

```

    "require"          : {
		"milkyway-multimedia/ss-send-this": "~0.2"
	}

```

### Web Hook Events
To handle web hooks, events are fired. The namespace events currently being used are (not all web hooks use all these events)

- **sendthis:up** The mailer is initialised (the initial headers are passed as an additional argument - casted as an object - at this point, which can be edited by listeners)
- **sendthis:down** The mailer has stopped processing the email
- **sendthis:sending** The email will enter the transport for sending
- **sendthis:sent** The email was successfully sent via the transport
- **sendthis:failed** The email was not sent successfully via the transport
- **sendthis:delivered** The email was successfully delivered by the end point (this differs to the 'sent' hook, which is called by the transport internally)
- **sendthis:bounced** The email bounced - on a hard bounce, the default logging handler fires the 'spam' event
- **sendthis:opened** The email was opened
- **sendthis:clicked** A link in the email was clicked (instead of passing a log, a link object will be passed as the fifth argument)
- **sendthis:spam** The email was marked as spam, or triggered a complaint
- **sendthis:delayed** The email was delayed to avoid flooding (note: most transactional email systems will implement this system, but not all will fire an even when doing so)
- **sendthis:rejected** The email was rejected by the end point
- **sendthis:unsubscribed** The email address was unsubscribed by the end point
- **sendthis:whitelisted** The email address was whitelisted at the end point
- **sendthis:blacklisted** The email address was blacklisted at the end point
- **sendthis:hooked** Web hook has been asked for confirmation from this application
- **sendthis:handled** An event from a web hook was handled

You can use these web hooks to sync your application to the transactional email system.

Events pass four arguments when called: string $messageId, string|array $email, array $params, array $response, $log = null (log is only passed for internal events. For web hooks, you can find the log by message id)

You can subscribe to an event hook by calling

```

    singleton('Milkyway\SS\SendThis\Mailer')->eventful()->listen(['sendthis:sent'], function($messageId = '', $email = '', $params = [], $response = [], $log = '', $headers = null) {});

```

The second parameter is a callable, so it can be an anonymous function, a callable array, or if you pass an object, it will assume the web hook is mapped to a method with the same name on the object (see _config/listeners.yml for examples)

#### You will have to set up web hooks in Mandrill and Amazon SES separately
The following urls will collect web hooks:
- yourwebsite.com/mailer/m: Mandrill
- yourwebsite.com/mailer/a: Amazon SES

### Transports
By default, this module will use PHP Mail (as per the normal Silverstripe mailer, but implementing PHPMailer). To use the other setups, please read on.

1. None: If no transport is specified, will use php mail() function
2. SMTP: To use this transport, you must specify a **host** that you will send the email through
3. Amazon SES: To use this transport, you must specify a **key** and an access **secret**
4. Mandrill: To use this transport, you must specify a **key**

The following options are available for your YAML config.

```

    SendThis:
      transport: 'default|smtp|ses|mandrill|sendgrid|mailgun|custom' # the default transport/driver

      drivers:
        smtp:
          params:
            host: 'only needed if you are using smtp transport'
            port: '' # optional
            username: '' # optional
            password: '' # optional
            secured_with: '' # optional (accepts tls or ssl)
            keep_alive: false # optional (true/false)

        ses:
          params:
            key: ''
            secret: ''

        mandrill:
          params:
            key: ''

        sendgrid:
          params:
            key: ''

        mailgun:
          params:
            key: ''

      logging: true
      tracking: false
      api_tracking: true (this is slightly different to the above, in that it only uses tracking on the transport rather than CMS based tracking)
      from_same_domain_only: false
      notify_on_fail: false
      blacklist_after_bounced: 2

      filter_from_reports: (you can specify some emails that should be filtered from reports in the CMS, such as test emails)
      filter_from_logs: (you can specify emails that will completely skip logging when sent to)
      headers: (you can specify some default headers that will be sent with all emails as an associative array)

```

## License
* MIT

## Version
* Version 0.2 (Alpha)

## Contact
#### Milkyway Multimedia
* Homepage: http://milkywaymultimedia.com.au
* E-mail: mell@milkywaymultimedia.com.au
* Twitter: [@mwmdesign](https://twitter.com/mwmdesign "mwmdesign on twitter")
