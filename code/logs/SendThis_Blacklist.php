<?php /**
 * Milkyway Multimedia
 * SendThis_Blacklist.php
 *
 * @package milkyway-multimedia/silverstripe-send-this
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */
class SendThis_Blacklist extends DataObject {
    private static $db = array(
        'Email'         => 'Text',
        'Message'       => 'Text',
        'Valid'         => 'Boolean',
    );

    private static $has_one = array(
        'Member'        => 'Member',
    );

    private static $defaults = array(
        'Valid'         => 1,
    );

    private static $singular_name = 'Blacklisted Email';

    private static $summary_fields = array(
        'Email',
        'Message',
        'Valid',
    );

    /**
     * Check if email is blacklisted
     *
     * @param      $email
     * @param bool $ignoreValid
     *
     * @return bool
     */
    public static function check($email, $ignoreValid = false) {
        $blacklist = static::get()->filter('Email', $email);

        if($ignoreValid)
            $blacklist->exclude('Valid', 1);

        return $blacklist->exists();
    }

    /**
     * Check if email is invalid
     *
     * @param $email
     *
     * @return bool
     */
    public static function check_invalid($email) {
        return static::get()->filter(array('Email' => $email, 'Valid' => 0))->exists();
    }

    public static function log_invalid($email, $message = '', $valid = false) {
        $blacklist = static::get()->filter(['Email' => $email])->first();

        if(!$blacklist)
            $blacklist = static::create();

        $blacklist->Email = $email;
        $blacklist->Message = $message;
        $blacklist->Valid = $valid;
        $blacklist->write();

        if(!$valid) {
            $emails = SendThis_Log::get()->filter('Success', 1)->filterAny(
                array(
                    'To'   => array(
                        $email,
                        '<' . $email . ':PartialMatch',
                        $email . ',:PartialMatch',
                    ),
                    'From' => array(
                        $email,
                        '<' . $email . ':PartialMatch',
                        $email . ',:PartialMatch',
                    ),
                    'Cc'   => array(
                        $email,
                        '<' . $email . ':PartialMatch',
                        $email . ',:PartialMatch',
                    ),
                    'Bcc'  => array(
                        $email,
                        '<' . $email . ':PartialMatch',
                        $email . ',:PartialMatch',
                    ),
                )
            );

            if ($emails->exists()) {
                foreach ($emails as $email) {
                    $email->Success = false;
                    $email->write();
                }
            }
        }
    }

    public function getTitle() {
        return $this->Email;
    }

    public function getCMSFields() {
        $this->beforeUpdateCMSFields(function(FieldList $fields) {
                if($email = $fields->dataFieldByName('Email'))
                    $fields->replaceField('Email', $email->castedCopy(EmailField::create('Email')));

                if($fields->dataFieldByName('Valid'))
                    $fields->dataFieldByName('Valid')->setDescription(_t('SendThis_Blacklist.DESC-VALID', 'If ticked, important email communications are still sent to this email (such as those conferring receipts when a user purchases from the site etc)'));
            });

        $fields = parent::getCMSFields();
        return $fields;
    }

    function canView($member = null) {
        return Permission::check('CAN_VIEW_SEND_LOGS');
    }
} 