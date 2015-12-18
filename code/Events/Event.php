<?php namespace Milkyway\SS\SendThis\Events;

/**
 * Milkyway Multimedia
 * Event.php
 *
 * @package milkyway-multimedia/ss-send-this
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

use Milkyway\SS\SendThis\Contracts\Event as Contract;
use League\Event\Event as AbstractEvent;
use Milkyway\SS\SendThis\Mailer;

class Event extends AbstractEvent implements Contract
{
    protected $mailer;

    /**
     * Create a new event instance.
     *
     * @param string $name
     * @param \Milkyway\SS\SendThis\Mailer $mailer
     */
    public function __construct($name, Mailer $mailer = null)
    {
        $this->mailer = $mailer;
        parent::__construct($name);
    }

    public function mailer()
    {
        return $this->mailer;
    }

    public static function named($name, Mailer $mailer = null)
    {
        return new static($name, $mailer);
    }
}
