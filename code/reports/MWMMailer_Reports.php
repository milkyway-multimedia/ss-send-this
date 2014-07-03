<?php
/**
 * Milkyway Mailer Reports
 * MWMMailer_Reports.php
 *
 * @package mwm
 * @subpackage reports
 * @author Mell - Milkyway Multimedia <mellisa.hankins@me.com>
 */

class MWMMailer_Reports extends SS_Report {
	protected $title = 'Email Logs';
	protected $description = 'View all emails sent through this site. For privacy purposes, the body of the email is not saved into the database.';

	protected $dataClass = 'MWMMailer_Log';

	public function sourceRecords(array $params, $sort, $limit) {
		$list = DataObject::get($this->dataClass())->sort('Created', 'DESC');

		if(!Permission::check('ADMIN') && $emails = SiteConfig::current_site_config()->Mailer_FilterFromReports) {
			$list = $list->filter(array(
				'From' => array_map('trim', explode(',', $emails)),
			));
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
		$me = singleton($this->dataClass);

		return array(
			'Subject' => array(
				'title' => $me->fieldLabel('Subject')
			),
			'To' => array(
				'title' => $me->fieldLabel('To')
			),
			'Sent' => array(
				'title' => $me->fieldLabel('Sent'),
			),
			'Success' => array(
				'title' => $me->fieldLabel('Success'),
				'casting' => array('HTMLText->CMSBoolean')
			),
			'Track_Open' => array(
				'title' => $me->fieldLabel('Track_Open'),
				'casting' => array('HTMLText->NiceOrNot')
			),
			'Tracker_ForTemplate' => array(
				'title' => $me->fieldLabel('Tracker_ForTemplate')
			),
		);
	}

	public function canView($member = null) {
		return Permission::check('ADMIN') || Permission::check('CAN_VIEW_LOGS');
	}
}

class MWMMailer_Reports_Blacklisted extends SS_Report {
	protected $title = 'Blacklisted Emails';
	protected $description = 'View emails that have been blacklisted in the database. This means they will no longer receive emails originating from this interface.';

	protected $dataClass = 'MWMMailer_Blacklist';

	public function sourceRecords(array $params, $sort, $limit) {
		return DataObject::get($this->dataClass)->sort('Created', 'DESC');
	}

	/**
	 * Which columns to show in the report
	 *
	 * @return array
	 */
	public function columns() {
		$me = singleton($this->dataClass);

		return array(
			'Email' => array(
				'title' => $me->fieldLabel('Email')
			),
			'Created' => array(
				'title' => $me->fieldLabel('Created')
			),
			'Message' => array(
				'title' => $me->fieldLabel('Message'),
				'casting' => array('HTMLText->debugView')
			),
		);
	}

	public function getReportField() {
		$field = parent::getReportField();
		$field->Config->addComponents(new GridFieldDeleteAction(), new GridFieldButtonRow('before'), $btn = new GridFieldAddNewButton('buttons-before-left'), new GridFieldDetailForm());
		$btn->setButtonName(_t('MWMMailer.BLACKLIST_AN_EMAIL', 'Blacklist an email'));
		return $field;
	}

	public function canView($member = null) {
		return Permission::check('ADMIN') || Permission::check('CAN_VIEW_LOGS');
	}
}

class MWMMailer_Reports_Bounced extends SS_Report {
	protected $title = 'Bounced Emails';
	protected $description = 'View emails that have been bounced';

	protected $dataClass = 'MWMMailer_Bounce';

	public function sourceRecords(array $params, $sort, $limit) {
		return DataObject::get($this->dataClass)->sort('Created', 'DESC');
	}

	/**
	 * Which columns to show in the report
	 *
	 * @return array
	 */
	public function columns() {
		$me = singleton($this->dataClass);

		return array(
			'Email' => array(
				'title' => $me->fieldLabel('Email')
			),
			'Created' => array(
				'title' => $me->fieldLabel('Created')
			),
			'Message' => array(
				'title' => $me->fieldLabel('Message'),
				'casting' => array('HTMLText->debugView')
			),
		);
	}

	public function canView($member = null) {
		return Permission::check('ADMIN') || Permission::check('CAN_VIEW_LOGS');
	}
}

