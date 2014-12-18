<?php
/**
 * Milkyway Multimedia
 * Event.php
 *
 * @package milkywaymultimedia.com.au
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

namespace Milkyway\SS\SendThis\Events;

use League\Event\Event as AbstractEvent;
use Milkyway\SS\SendThis\Mailer;

class Event extends AbstractEvent {
    protected $mailer;

    /**
     * Create a new event instance.
     *
     * @param string $name
     * @param \Milkyway\SS\SendThis\Mailer $mailer
     */
    public function __construct($name, Mailer $mailer = null)
    {
        $this->name = $name;
        $this->mailer = $mailer;
    }

    public function mailer() {
        return $this->mailer;
    }

    public static function named($name, Mailer $mailer = null)
    {
        return new static($name, $mailer);
    }
} 