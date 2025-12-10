<?php

import('classes.handler.Handler');

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
 * @file plugins/generic/openid/handler/OpenIDLoginHandler.inc.php
 * @ingroup plugins_generic_openid
 * @brief Handler to overwrite default OJS/OMP/OPS login and registration
 *
 */
class OpenIDLoginHandler extends Handler
{
	/**
	 * This function overwrites the default login.
	 * There a 2 different workflows implemented:
	 * - If only one OpenID provider is configured and legacy login is disabled, the user is automatically redirected to the sign-in page of that provider.
	 * - If more than one provider is configured, a login page is shown within the OJS/OMP/OPS and the user can select his preferred OpenID provider for login/registration.
	 *
	 * In case of an error or incorrect configuration, a link to the default login page is provided to prevent a complete system lockout.
	 *
	 * @param $args
	 * @param $request
	 *
	 * @return false|void
	 */
	function index($args, $request)
	{
		$this->setupTemplate($request);
		if (Config::getVar('security', 'force_login_ssl') && $request->getProtocol() != 'https') {
			$request->redirectSSL();
		}

		$plugin = PluginRegistry::getPlugin('generic', KEYCLOAK_PLUGIN_NAME);
		$legacyLogin = false;
		$templateMgr = TemplateManager::getManager($request);
		$context = $request->getContext();

		if (!Validation::isLoggedIn()) {
			$router = $request->getRouter();
			$contextId = ($context == null) ? 0 : $context->getId();
			$settingsJson = $plugin->getSetting($contextId, 'openIDSettings');

			// Did the user try to access a specific page? 
			// Then we want to preserve this information to redirect correctly after login
			$source =  $request->getUserVar('source');
			$session = $request->getSession();
			$session->setSessionVar('source', $source);
			$sessionDao = DAORegistry::getDAO('SessionDAO');
			$sessionDao->updateObject($session);

			if ($settingsJson != null) {
				$settings = json_decode($settingsJson, true);
				$legacyLogin = key_exists('legacyLogin', $settings) && isset($settings['legacyLogin']) ? $settings['legacyLogin'] : false;
				$legacyRegister = key_exists('legacyRegister', $settings) && isset($settings['legacyRegister']) ? $settings['legacyRegister'] : false;
				$providerList = key_exists('provider', $settings) ? $settings['provider'] : null;

				if (isset($providerList)) {
					foreach ($providerList as $name => $settings) {
						if (key_exists('authUrl', $settings) && !empty($settings['authUrl'])
							&& key_exists('clientId', $settings) && !empty($settings['clientId'])) {
							if (sizeof($providerList) == 1 && !$legacyLogin && !$legacyRegister) {
								$request->redirectUrl(
									$settings['authUrl'].
									'?client_id='.$settings['clientId'].
									'&response_type=code&scope=openid&redirect_uri='.
									$router->url($request, null, "openid", "doAuthentication", null, array('provider' => $name),null,true)
									//($source != '' ? urlencode('&state=' . $source) : '')
								);

								return false;
							} else {
								if ($name == "custom") {
									$templateMgr->assign(
										'customBtnImg',
										key_exists('btnImg', $settings) && isset($settings['btnImg']) ? $settings['btnImg'] : null
									);
									$templateMgr->assign(
										'customBtnTxt',
										key_exists('btnTxt', $settings)
										&& isset($settings['btnTxt'])
										&& isset($settings['btnTxt'][AppLocale::getLocale()])
											? $settings['btnTxt'][AppLocale::getLocale()] : null
									);
								}
								if ($name == 'microsoft') {
									$linkList[$name] = $settings['authUrl'].
										'?client_id='.$settings['clientId'].
										'&response_type=code&scope=openid profile email';
								} else {
									$linkList[$name] = $settings['authUrl'].
										'?client_id='.$settings['clientId'].
										'&response_type=code&scope=openid profile email'.
										'&redirect_uri='.urlencode(
											$router->url($request, null, "openid", "doAuthentication", null, array('provider' => $name))
										);
								}
							}
						}
					}
				}
				if ($legacyRegister) {
					$linkList['legacyRegister'] = $router->url($request, null, "user", "registerUser");
				}
			}

			if (isset($linkList) && is_array($linkList) && sizeof($linkList) > 0) {
				$templateMgr->assign('linkList', $linkList);
				$ssoError = $request->getUserVar('sso_error');

				if (isset($ssoError) && !empty($ssoError)) {
					$this->_setSSOErrorMessages($ssoError, $templateMgr, $request);
				}

				if ($legacyLogin) {
					$this->_enableLegacyLogin($templateMgr, $request);
				}
			} else {
				$templateMgr->assign('openidError', true);
				$templateMgr->assign('errorMsg', 'plugins.generic.openid.settings.error');
			}

			return $templateMgr->display($plugin->getTemplateResource('openidLogin.tpl'));
		}

		$request->redirect($request->getRequestedContextPath(), 'index');

		return false;
	}

	/**
	 * Used for legacy login in case of errors or other bad things.
	 *
	 * @param $args
	 * @param $request
	 */
	function legacyLogin($args, $request)
	{
		$templateMgr = TemplateManager::getManager($request);
		$this->_enableLegacyLogin($templateMgr, $request);
		$templateMgr->assign('disableUserReg', true);

		return $templateMgr->display('frontend/pages/userLogin.tpl');
	}

	/**
	 * Overwrites the default registration, because it is not needed anymore.
	 * User registration is done via OpenID provider.
	 *
	 * @param $args
	 * @param $request
	 */
	function register($args, $request)
	{
		$this->index($args, $request);
	}

	/**
	 * Overwrites default signOut.
	 * Performs OJS logout and if logoutUrl is provided (e.g. Apple doesn't provide this url) it redirects to the oauth logout to delete session and tokens.
	 *
	 * @param $args
	 * @param $request
	 */
	function signOut($args, $request)
	{
		if (Validation::isLoggedIn()) {
			$plugin = PluginRegistry::getPlugin('generic', KEYCLOAK_PLUGIN_NAME);
			$router = $request->getRouter();
			$lastProvider = $request->getUser()->getSetting('openid::lastProvider');
			$context = Application::get()->getRequest()->getContext();
			$user = Application::get()->getRequest()->getUser();
			$contextId = ($context == null) ? 0 : $context->getId();
			$settingsJson = $plugin->getSetting($contextId, 'openIDSettings');

			if (isset($user)) {
				$userSettingsDao = DAORegistry::getDAO('UserSettingsDAO');
				$userSettingsDao->deleteSetting($user->getId(), 'openid::lastProvider');
			}

			Validation::logout();
			if (isset($settingsJson) && isset($lastProvider)) {
				$providerList = json_decode($settingsJson, true)['provider'];
				$settings = $providerList[$lastProvider];

				if (isset($settings) && key_exists('logoutUrl', $settings) && !empty($settings['logoutUrl']) && key_exists('clientId', $settings)) {
					$request->redirectUrl(
						$settings['logoutUrl'].
						'?client_id='.$settings['clientId'].
						'&redirect_uri='.$router->url($request, $context, "index")
					);
				}
			}
		}
		$request->redirect($request->getRequestedContextPath(), 'index');
	}

	/**
	 * Sets user friendly error messages, which are thrown during the OpenID auth process.
	 *
	 * @param $ssoError
	 * @param $templateMgr
	 * @param $request
	 */
	private function _setSSOErrorMessages($ssoError, $templateMgr, $request)
	{
		$templateMgr->assign('openidError', true);
		switch ($ssoError) {
			case 'connect_data':
				$templateMgr->assign('errorMsg', 'plugins.generic.openid.error.openid.connect.desc.data');
				break;
			case 'connect_key':
				$templateMgr->assign('errorMsg', 'plugins.generic.openid.error.openid.connect.desc.key');
				break;
			case 'cert':
				$templateMgr->assign('errorMsg', 'plugins.generic.openid.error.openid.cert.desc');
				break;
			case 'disabled':
				$reason = $request->getUserVar('sso_error_msg');
				$templateMgr->assign('accountDisabled', true);
				if (isset($reason) && !empty($reason)) {
					$templateMgr->assign('errorMsg', 'plugins.generic.openid.error.openid.disabled.with');
					$templateMgr->assign('reason', $reason);
				} else {
					$templateMgr->assign('errorMsg', 'plugins.generic.openid.error.openid.disabled.without');
				}
				break;
		}

		$context = $request->getContext();
		$supportEmail = $context != null ? $context->getSetting('supportEmail') : null;
		if ($supportEmail) {
			$templateMgr->assign('supportEmail', $supportEmail);
		}
	}

	/**
	 * This function is used
	 *  - if the legacy login is activated via plugin settings,
	 *  - or an error occurred during the Auth process to ensure that the Journal Manager can log in.
	 *
	 * @param $templateMgr
	 * @param $request
	 */
	private function _enableLegacyLogin($templateMgr, $request)
	{
		$sessionManager = SessionManager::getManager();
		$session = $sessionManager->getUserSession();
		$context = $request->getContext();
		$loginUrl = $request->url(null, 'login', 'signIn');

		if (Config::getVar('security', 'force_login_ssl')) {
			$loginUrl = PKPString::regexp_replace('/^http:/', 'https:', $loginUrl);
		}

		$templateMgr->assign(
			array(
				'loginMessage' => $request->getUserVar('loginMessage'),
				'username' => $session->getSessionVar('username'),
				'remember' => $request->getUserVar('remember'),
				'source' => $request->getUserVar('source'),
				'showRemember' => Config::getVar('general', 'session_lifetime') > 0,
				'legacyLogin' => true,
				'loginUrl' => $loginUrl,
				'journalName' => $context != null ? $context->getName(AppLocale::getLocale()) : null,
			)
		);
	}
}
