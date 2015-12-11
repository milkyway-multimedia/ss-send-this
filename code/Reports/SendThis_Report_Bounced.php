<?php
/**
 * Milkyway Mailer Reports
 * SendThis_Report_Bounced.php
 *
 * @package milkyway-multimedia/ss-send-this
 * @subpackage reports
 * @author Mell - Milkyway Multimedia <mellisa.hankins@me.com>
 */

if(!class_exists('SS_Report')) {
	return;
}

class SendThis_Report_Bounced extends SS_Report {
	protected $title = 'Bounced Emails';
	protected $description = 'View emails that have been bounced';

	protected $dataClass = 'SendThis_Bounce';

	public function sourceRecords(array $params, $sort, $limit) {
		return DataObject::get($this->dataClass)->sort('Created', 'DESC');
	}

	/**
	 * Which columns to show in the report
	 *
	 * @return array
	 */
	public function columns() {
        return array(
            'Email' => [
                'title' => _t('SendThis_Log.EMAIL', 'Email'),
            ],
            'Message' => array(
                'formatting' =>
                    function($value, $record) {
                        return '<pre>' . print_r($value, true) . '</pre>';
                    }
            ),
        );
	}

	public function canView($member = null) {
		return Permission::check('ADMIN') || Permission::check('CAN_VIEW_LOGS');
	}
}

