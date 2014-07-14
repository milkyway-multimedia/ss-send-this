<?php
/**
 * Milkyway Mailer Reports
 * MWMMailer_Reports.php
 *
 * @package mwm
 * @subpackage reports
 * @author Mell - Milkyway Multimedia <mellisa.hankins@me.com>
 */

class SendThis_Report extends SS_Report {
	protected $title = 'Email Logs';
	protected $description = 'View all emails sent through this site. For privacy purposes, the body of the email is not saved into the database.';

	protected $dataClass = 'SendThis_Log';

	public function sourceRecords(array $params, $sort, $limit) {
		$list = DataObject::get($this->dataClass())->sort('Created', 'DESC');

		if(!Permission::check('ADMIN') && $filterEmails = SendThis::config()->filter_from_reports) {
			$list = $list->filter([
				'From' => array_map('trim', explode(',', (array)$filterEmails)),
			]);
		}

		return $list;
	}

	public function getReportField() {
		$field = parent::getReportField();

		if($c = $field->Config) {
			$c->addComponent(new GridFieldDetailForm());
			$c->addComponent(new GridFieldEditButton());

			if(ClassInfo::exists('GridFieldBulkManager')) {
				$c->addComponent($manager = new GridFieldBulkManager());
				$manager->removeBulkAction('bulkedit')->removeBulkAction('unlink');
			}
		}

		return $field;
	}

	/**
	 * Which columns to show in the report
	 *
	 * @return array
	 */
	public function columns() {
		return [
            'To' => [
                'title' => _t('SendThis_Log.TO', 'To'),
            ],
            'Subject' => [
                'title' => _t('SendThis_Log.SUBJECT', 'Subject'),
            ],
            'Sent' => [
                'title' => _t('SendThis_Log.SENT', 'Sent'),
            ],
			'Success' => [
                'title' => _t('SendThis_Log.SUCCESS', 'Success'),
				'formatting' =>
                    function($value, $record) {
                        return $value ? '<span class="ui-button-icon-primary ui-icon btn-icon-accept boolean-yes"></span>' : '<span class="ui-button-icon-primary ui-icon btn-icon-decline boolean-no"></span>';
                    }
			],
			'Opened' => [
                'title' => _t('SendThis_Log.OPENED', 'Opened'),
                'formatting' =>
                    function($value, $record) {
                        return $value && $value != '0000-00-00 00:00:00' ? $record->obj('Opened')->Nice() : '<span class="ui-button-icon-primary ui-icon btn-icon-decline"></span>';
                    }
			],
            'Tracker_ForTemplate' => [
                'title' => _t('SendThis_Log.DETAILS', 'Details'),
            ],
		];
	}

	public function canView($member = null) {
		return Permission::check('ADMIN') || Permission::check('CAN_VIEW_LOGS');
	}
}

class SendThis_Report_Blacklisted extends SS_Report {
	protected $title = 'Blacklisted Emails';
	protected $description = 'View emails that have been blacklisted in the database. This means they will no longer receive emails originating from this interface.';

	protected $dataClass = 'SendThis_Blacklist';

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
			'Message' => array(
                'casting' => 'HTMLText',
                'formatting' =>
                    function($value, $record) {
                        return '<pre>' . print_r($value, true) . '</pre>';
                    }
			),
		);
	}

	public function getReportField() {
		$field = parent::getReportField();
		$field->Config->addComponents(new GridFieldDeleteAction(), new GridFieldButtonRow('before'), $btn = new GridFieldAddNewButton('buttons-before-left'), new GridFieldDetailForm());
		$btn->setButtonName(_t('SendThis.BLACKLIST_AN_EMAIL', 'Blacklist an email'));
		return $field;
	}

	public function canView($member = null) {
		return Permission::check('ADMIN') || Permission::check('CAN_VIEW_LOGS');
	}
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

