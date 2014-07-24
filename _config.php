<?php

// Add relational data to email logs using email headers
SendThis::listen(['up'], new \Milkyway\SS\SendThis\Listeners\Relations());

// Handle internal and web hook logging
SendThis::listen(['up', 'sent', 'failed', 'bounced', 'spam', 'rejected', 'blacklisted', 'whitelisted'], new \Milkyway\SS\SendThis\Listeners\Logging());

// Handle notifications (may plug into the SS_Log emailing system in future)
SendThis::listen(['hooked', 'up', 'failed', 'rejected', 'bounced', 'spam'], new \Milkyway\SS\SendThis\Listeners\Notifications());

SendThis::listen(['up', 'sending', 'opened', 'clicked'], new \Milkyway\SS\SendThis\Listeners\Tracking());
SendThis::listen(['up', 'sending', 'opened', 'clicked'], new \Milkyway\SS\SendThis\Listeners\Mandrill\Tracking());