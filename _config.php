<?php

SendThis::listen(['bounced', 'spam'], new \Milkyway\SendThis\Listeners\Logging());
SendThis::listen(['bounced', 'spam'], new \Milkyway\SendThis\Listeners\Notifications());