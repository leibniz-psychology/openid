<?php

import('lib.pkp.classes.form.Form');

/**
 * This file is part of OpenID Authentication Plugin (https://github.com/leibniz-psychology/pkp-openid).
 *
 * OpenID Authentication Plugin is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * OpenID Authentication Plugin is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with OpenID Authentication Plugin.  If not, see <https://www.gnu.org/licenses/>.
 *
 * Copyright (c) 2020 Leibniz Institute for Psychology Information (https://leibniz-psychology.org/)
 *
 * @file plugins/generic/openid/forms/OpenIDPluginSettingsForm.inc.php
 * @ingroup plugins_generic_openid
 * @brief Form class for OpenID Authentication Plugin settings
 */
class OpenIDPluginSettingsForm extends Form
{
	/**
	 * List of OpenID provider.
	 * TODO should be loaded via json in the future
	 */
	private const PUBLIC_OPENID_PROVIDER = [
		"custom" => "",
		"orcid" => ["configUrl" => "https://orcid.org/.well-known/openid-configuration"],
		"google" => ["configUrl" => "https://accounts.google.com/.well-known/openid-configuration"],
		"microsoft" => ["configUrl" => "https://login.windows.net/common/v2.0/.well-known/openid-configuration"],
		"apple" => ["configUrl" => "https://appleid.apple.com/.well-known/openid-configuration"],
	];

	private const HIDDEN_CHARS = '******';

	private $plugin;

	/**
	 * OpenIDPluginSettingsForm constructor.
	 *
	 * @param $plugin
	 */
	public function __construct($plugin)
	{
		parent::__construct($plugin->getTemplateResource('settings.tpl'));
		$this->plugin = $plugin;
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	/**
	 * @copydoc Form::initData()
	 */
	function initData()
	{
		$request = Application::get()->getRequest();
		$contextId = ($request->getContext() == null) ? 0 : $request->getContext()->getId();
		$settingsJson = $this->plugin->getSetting($contextId, 'openIDSettings');
		$settings = json_decode($settingsJson, true);
		$provider = $settings['provider'];

		if ($provider && is_array($provider)) {
			foreach ($provider as &$prov) {
				if (key_exists('clientId', $prov) && !empty($prov['clientId'])) {
					$prov['clientId'] = self::HIDDEN_CHARS;
				}
				if (key_exists('clientSecret', $prov) && !empty($prov['clientSecret'])) {
					$prov['clientSecret'] = self::HIDDEN_CHARS;
				}
			}
		}
		if (isset($settings)) {
			$this->_data = array(
				'initProvider' => self::PUBLIC_OPENID_PROVIDER,
				'provider' => $provider,
				'legacyLogin' => key_exists('legacyLogin', $settings) ? $settings['legacyLogin'] : true,
				'legacyRegister' => key_exists('legacyRegister', $settings) ? $settings['legacyRegister'] : true,
				'disableConnect' => key_exists('disableConnect', $settings) ? $settings['disableConnect'] : false,
				'hashSecret' => $settings['hashSecret'],
				'generateAPIKey' => $settings['generateAPIKey'] ? $settings['generateAPIKey'] : 0,
				'providerSync' => key_exists('providerSync', $settings) ? $settings['providerSync'] : false,
				'disableFields' => $settings['disableFields'],
				'orcid2fa' => key_exists('orcid2fa', $settings) ? $settings['orcid2fa'] : false,
			);
		} else {
			$this->_data = array(
				'initProvider' => self::PUBLIC_OPENID_PROVIDER,
				'legacyLogin' => true,
				'legacyRegister' => false,
				'generateAPIKey' => false,
			);
		}
		parent::initData();
	}

	/**
	 * @copydoc Form::readInputData()
	 */
	function readInputData()
	{
		$this->readUserVars(
			array('provider', 'legacyLogin', 'legacyRegister', 'disableConnect', 'hashSecret', 'generateAPIKey', 'providerSync', 'disableFields', 'orcid2fa')
		);
		parent::readInputData();
	}

	/**
	 * @copydoc Form::fetch()
	 *
	 * @param $request
	 * @param null $template
	 * @param bool $display
	 * @return string|null
	 */
	public function fetch($request, $template = null, $display = false)
	{
		$context = $request->getContext();
		$redirectURL = $context == null ? $request->getIndexUrl().'/openid/doAuthentication' : $request->getIndexUrl().'/'.$context->getPath(
			).'/openid/doAuthentication';
		$redirectMSURL = $context == null ? $request->getIndexUrl().'/openid/doMicrosoftAuthentication' : $request->getIndexUrl().'/'.$context->getPath(
			).'/openid/doMicrosoftAuthentication';
		$templateMgr = TemplateManager::getManager($request);
		$request->getBasePath();
		$templateMgr->assign('pluginName', $this->plugin->getName());
		$templateMgr->assign('redirectUrl', $redirectURL);
		$templateMgr->assign('redirectMSUrl', $redirectMSURL);

		return parent::fetch($request, $template, $display);
	}

	/**
	 * @copydoc Form::execute()
	 *
	 * @param mixed ...$functionArgs
	 * @return mixed|null
	 */
	function execute(...$functionArgs)
	{
		$request = Application::get()->getRequest();
		$contextId = ($request->getContext() == null) ? 0 : $request->getContext()->getId();
		$settingsJson = $this->plugin->getSetting($contextId, 'openIDSettings');
		$settingsTMP = json_decode($settingsJson, true);
		$providerList = $this->getData('provider');
		$providerListResult = $this->_createProviderList($providerList, $settingsTMP['provider']);
		$settings = array(
			'provider' => $providerListResult,
			'legacyLogin' => $this->getData('legacyLogin'),
			'legacyRegister' => $this->getData('legacyRegister'),
			'disableConnect' => $this->getData('disableConnect'),
			'hashSecret' => $this->getData('hashSecret'),
			'generateAPIKey' => $this->getData('generateAPIKey'),
			'providerSync' => $this->getData('providerSync'),
			'disableFields' => $this->getData('disableFields'),
			'orcid2fa' => $this->getData('orcid2fa'),
		);
		$this->plugin->updateSetting($contextId, 'openIDSettings', json_encode($settings), 'string');
		import('classes.notification.NotificationManager');
		$notificationMgr = new NotificationManager();
		$notificationMgr->createTrivialNotification(
			$request->getUser()->getId(),
			NOTIFICATION_TYPE_SUCCESS,
			['contents' => __('common.changesSaved')]
		);

		return parent::execute();
	}

	/**
	 * Creates a complete list of the provider with all necessary endpoint URL's.
	 * Therefore this->_loadOpenIdConfig is called, to get the URL's via openid-configuration endpoint.
	 * This function is called when the settings are executed to refresh the auth, token, cert and logout/revoke URL's.
	 *
	 * @param $providerList
	 * @param $providerListDB
	 * @return array complete list of enabled provider including all necessary endpoint URL's
	 */
	private function _createProviderList($providerList, $providerListDB)
	{
		$providerListResult = array();
		if (isset($providerList) && is_array($providerList)) {
			foreach ($providerList as $name => $provider) {
				if (key_exists('active', $provider) && $provider['active'] == 1) {
					if (isset($providerListDB) && is_array($providerListDB) && key_exists($name, $providerListDB)) {
						$providerDB = $providerListDB[$name];
						if (key_exists('clientId', $provider) && key_exists('clientId', $providerDB) &&
							(empty($provider['clientId']) || $provider['clientId'] == self::HIDDEN_CHARS)) {
							if (!empty($providerDB['clientId'])) {
								$provider['clientId'] = $providerDB['clientId'];
							} else {
								$provider['clientId'] = '';
							}
						}
						if (key_exists('clientSecret', $provider) && key_exists('clientSecret', $providerDB) &&
							(empty($provider['clientSecret']) || $provider['clientSecret'] == self::HIDDEN_CHARS)) {
							if (!empty($providerDB['clientSecret'])) {
								$provider['clientSecret'] = $providerDB['clientSecret'];
							} else {
								$provider['clientSecret'] = '';
							}
						}
					}
					$openIdConfig = $this->_loadOpenIdConfig($provider['configUrl']);
					if (is_array($openIdConfig)
						&& key_exists('authorization_endpoint', $openIdConfig)
						&& key_exists('token_endpoint', $openIdConfig)
						&& key_exists('jwks_uri', $openIdConfig)) {
						$provider['authUrl'] = $openIdConfig['authorization_endpoint'];
						$provider['tokenUrl'] = $openIdConfig['token_endpoint'];
						$provider['userInfoUrl'] = key_exists('userinfo_endpoint', $openIdConfig) ? $openIdConfig['userinfo_endpoint'] : null;
						$provider['certUrl'] = $openIdConfig['jwks_uri'];
						$provider['logoutUrl'] = key_exists('end_session_endpoint', $openIdConfig) ? $openIdConfig['end_session_endpoint'] : null;
						$provider['revokeUrl'] = key_exists('revocation_endpoint', $openIdConfig) ? $openIdConfig['revocation_endpoint'] : null;
						$providerListResult[$name] = $provider;
					}
				}
			}
		}

		return $providerListResult;
	}

	/**
	 * Calls the .well-known/openid-configuration which is provided in the $configURL and returns the result on success
	 *
	 * @param $configUrl
	 * @return mixed|null
	 */
	private function _loadOpenIdConfig($configUrl)
	{
		$curl = curl_init();
		curl_setopt_array(
			$curl,
			array(
				CURLOPT_URL => $configUrl,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HTTPHEADER => array('Accept: application/json'),
				CURLOPT_POST => false,
			)
		);
		$result = curl_exec($curl);
		if (isset($result)) {
			return json_decode($result, true);
		}

		return null;
	}

}
