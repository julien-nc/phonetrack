<?php
namespace OCA\PhoneTrack\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\Settings\ISettings;

class Admin implements ISettings {

	public function __construct(
		private IConfig $config,
	) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm() {
		$quota = $this->config->getAppValue('phonetrack', 'pointQuota');

		$parameters = [
			'phonetrackPointQuota' => $quota
		];
		return new TemplateResponse('phonetrack', 'admin', $parameters, '');
	}

	/**
	 * @return string the section ID, e.g. 'sharing'
	 */
	public function getSection() {
		return 'additional';
	}

	/**
	 * @return int whether the form should be rather on the top or bottom of
	 * the admin section. The forms are arranged in ascending order of the
	 * priority values. It is required to return a value between 0 and 100.
	 *
	 * E.g.: 70
	 */
	public function getPriority() {
		return 5;
	}
}
