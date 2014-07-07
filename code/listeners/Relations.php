<?php namespace Milkyway\SendThis\Listeners;
/**
 * Milkyway Multimedia
 * Relations.php
 *
 * @package reggardocolaianni.com
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */
class Relations {
    public function up($messageId, $email, $params, $response, $log, $headers) {
        if(!$log) return;

        foreach (get_object_vars($headers) as $k => $v)
        {
            if (strpos($k, 'X-HasOne-') === 0)
            {
                $rel        = str_replace('X-HasOne-', '', $k) . 'ID';
                $log->$rel = $v;

                unset($headers->$k);
            }
        }
    }
} 