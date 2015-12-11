<?php
/**
 * Milkyway Mailer Reports
 * SendThis_Report_Blacklisted.php
 *
 * @package milkyway-multimedia/ss-send-this
 * @subpackage reports
 * @author Mell - Milkyway Multimedia <mellisa.hankins@me.com>
 */

if(!class_exists('SS_Report')) {
	return;
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
            'Email' => [
                'title' => _t('SendThis_Log.EMAIL', 'Email'),
            ],
			'Message' => array(
                'casting' => 'HTMLText',
                'formatting' =>
                    function($value, $record) {
                        return '<pre>' . print_r($value, true) . '</pre>';
                    }
			),
            'Valid' => [
                'casting' => 'HTMLText',
                'formatting' =>
                    function($value, $record) {
                        return $value ? '<span class="ui-button-icon-primary ui-icon btn-icon-accept boolean-yes"></span>' : '<span class="ui-button-icon-primary ui-icon btn-icon-decline boolean-no"></span>';
                    }
            ],
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