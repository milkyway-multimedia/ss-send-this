<?php

SendThis::listen(['up'], new \Milkyway\SendThis\Listeners\Relations());

SendThis::listen(['up', 'sent', 'failed', 'rejected', 'bounced', 'spam'], new \Milkyway\SendThis\Listeners\Logging());
SendThis::listen(['up', 'failed', 'rejected', 'bounced', 'spam', 'hooked'], new \Milkyway\SendThis\Listeners\Notifications());

SendThis::listen(['up', 'sending', 'opened', 'clicked'], new \Milkyway\SendThis\Listeners\Tracking());
SendThis::listen(['up', 'sending', 'opened', 'clicked'], new \Milkyway\SendThis\Listeners\Mandrill\Tracking());