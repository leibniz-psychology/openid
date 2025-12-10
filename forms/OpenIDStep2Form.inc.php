<?php

use Sokil\IsoCodes\IsoCodesFactory;

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
 * @file plugins/generic/openid/forms/OpenIDStep2Form.inc.php
 * @ingroup plugins_generic_openid
 * @brief Form class for the second step which is needed if no local user was found with the OpenID identifier
 */
class OpenIDStep2Form extends Form
{
	private $credentials;
	private $plugin;
	private $contextId;

	/**
	 * OpenIDStep2Form constructor.
	 *
	 * @param $plugin
	 * @param $credentials
	 */
	function __construct($plugin, $credentials = array())
	{
		$context = Application::get()->getRequest()->getContext();
		$this->contextId = ($context == null) ? 0 : $context->getId();
		$this->plugin = $plugin;
		$this->credentials = $credentials;
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
		parent::__construct($plugin->getTemplateResource('authStep2.tpl'));
	}

	/**
	 *
	 * @copydoc Form::fetch()
	 *
	 * @param $request
	 * @param $template
	 * @param $display
	 * @return string|null
	 */
	function fetch($request, $template = null, $display = false)
	{
		$templateMgr = TemplateManager::getManager($request);
		$settingsJson = $this->plugin->getSetting($this->contextId, 'openIDSettings');
		$settings = json_decode($settingsJson, true);
		$isoCodes = new IsoCodesFactory();
		$countries = array();
		foreach ($isoCodes->getCountries() as $country) {
			$countries[$country->getAlpha2()] = $country->getLocalName();
		}
		asort($countries);
		$templateMgr->assign('disableConnect', $settings['disableConnect']);
		$templateMgr->assign('countries', $countries);
		import('lib.pkp.classes.user.form.UserFormHelper');
		$userFormHelper = new UserFormHelper();
		$userFormHelper->assignRoleContent($templateMgr, $request);

		return parent::fetch($request, $template, $display);
	}

	/**
	 * @copydoc Form::initData()
	 */
	function initData()
	{
		if (is_array($this->credentials) && !empty($this->credentials)) {
			// generate username if username is orchid id
			if (key_exists('username', $this->credentials)) {
				if (preg_match('/\d{4}-\d{4}-\d{4}-\d{4}/', $this->credentials['username'])) {
					$given = key_exists('given_name', $this->credentials) ? $this->credentials['given_name'] : '';
					$family = key_exists('family_name', $this->credentials) ? $this->credentials['family_name'] : '';
					$this->credentials['username'] = mb_strtolower($given.$family, 'UTF-8');
				}
			}
			$this->_data = array(
				'selectedProvider' => $this->credentials['selectedProvider'],
				'oauthId' => OpenIDHandler::encryptOrDecrypt($this->plugin, $this->contextId, 'encrypt', $this->credentials['id']),
				'username' => $this->credentials['username'],
				'givenName' => $this->credentials['given_name'],
				'familyName' => $this->credentials['family_name'],
				'email' => $this->credentials['email'],
				'userGroupIds' => array(),
			);
		}
	}

	/**
	 * @copydoc Form::readInputData()
	 */
	function readInputData()
	{
		parent::readInputData();
		$this->readUserVars(
			array(
				'selectedProvider',
				'oauthId',
				'username',
				'email',
				'givenName',
				'familyName',
				'affiliation',
				'country',
				'privacyConsent',
				'emailConsent',
				'register',
				'connect',
				'usernameLogin',
				'passwordLogin',
				'readerGroup',
				'reviewerGroup',
				'interests',
			)
		);
		// Collect the specified user group IDs into a single piece of data
		$this->setData(
			'userGroupIds',
			array_merge(
				array_keys((array)$this->getData('readerGroup')),
				array_keys((array)$this->getData('reviewerGroup'))
			)
		);
	}

	/**
	 * @copydoc Form::validate()
	 *
	 * @param $callHooks
	 * @return bool|mixed|null
	 */
	function validate($callHooks = true)
	{
		$userDao = DAORegistry::getDAO('UserDAO');
		$register = is_string($this->getData('register'));
		$connect = is_string($this->getData('connect'));
		if ($register) {
			$this->_data['returnTo'] = "register";
			$this->addCheck(new FormValidator($this, 'username', 'required', 'plugins.generic.openid.form.error.username.required'));
			$this->addCheck(
				new FormValidatorCustom(
					$this, 'username', 'required', 'plugins.generic.openid.form.error.usernameExists',
					array(DAORegistry::getDAO('UserDAO'), 'userExistsByUsername'), array(), true
				)
			);
			$this->addCheck(new FormValidator($this, 'givenName', 'required', 'plugins.generic.openid.form.error.givenName.required'));
			$this->addCheck(new FormValidator($this, 'familyName', 'required', 'plugins.generic.openid.form.error.familyName.required'));
			$this->addCheck(new FormValidator($this, 'country', 'required', 'plugins.generic.openid.form.error.country.required'));
			$this->addCheck(new FormValidator($this, 'affiliation', 'required', 'plugins.generic.openid.form.error.affiliation.required'));
			$this->addCheck(new FormValidatorEmail($this, 'email', 'required', 'plugins.generic.openid.form.error.email.required'));
			$this->addCheck(
				new FormValidatorCustom(
					$this, 'email', 'required', 'plugins.generic.openid.form.error.emailExists',
					array(DAORegistry::getDAO('UserDAO'), 'userExistsByEmail'), array(), true
				)
			);
			$context = Application::get()->getRequest()->getContext();
			if ($context && $context->getData('privacyStatement')) {
				$this->addCheck(new FormValidator($this, 'privacyConsent', 'required', 'plugins.generic.openid.form.error.privacyConsent.required'));
			}
		} elseif ($connect) {
			$this->_data['returnTo'] = "connect";
			$this->addCheck(new FormValidator($this, 'usernameLogin', 'required', 'plugins.generic.openid.form.error.usernameOrEmail.required'));
			$this->addCheck(new FormValidator($this, 'passwordLogin', 'required', 'plugins.generic.openid.form.error.password.required'));
			$username = $this->getData('usernameLogin');
			$password = $this->getData('passwordLogin');
			$user = $userDao->getByUsername($username, true);
			if (!isset($user)) {
				$user = $userDao->getUserByEmail($username, true);
			}
			if (!isset($user)) {
				$this->addError('usernameLogin', __('plugins.generic.openid.form.error.user.not.found'));
			} else {
				$valid = Validation::verifyPassword($user->getUsername(), $password, $user->getPassword(), $rehash);
				if (!$valid) {
					$this->addError('passwordLogin', __('plugins.generic.openid.form.error.invalid.credentials'));
				}
			}
		}

		return parent::validate($callHooks);
	}

	/**
	 * @copydoc Form::execute()
	 *
	 * @param mixed ...$functionArgs
	 * @return bool|mixed|null
	 */
	function execute(...$functionArgs)
	{
		$userDao = DAORegistry::getDAO('UserDAO');
		$userSettingsDao = DAORegistry::getDAO('UserSettingsDAO');
		$register = is_string($this->getData('register'));
		$connect = is_string($this->getData('connect'));
		$oauthId = $this->getData('oauthId');
		$selectedProvider = $this->getData('selectedProvider');
		$result = false;

		if (!empty($oauthId) && !empty($selectedProvider)) {
			$oauthId = OpenIDHandler::encryptOrDecrypt($this->plugin, $this->contextId, 'decrypt', $oauthId);
			// prevent saving one openid:ident to multiple accounts
			$user = $userDao->getBySetting('openid::'.$selectedProvider, $oauthId);
			if (!isset($user)) {
				$user = $userDao->getBySetting('openid::'.$selectedProvider, hash('sha256', $oauthId));
			}
			if (!isset($user)) {
				if ($register) {
					$user = $this->_registerUser();
					if (isset($user)) {
						if($selectedProvider == 'orcid')
						{
							$user->setOrcid($oauthId);
						}
						$userSettingsDao->updateSetting($user->getId(), 'openid::'.$selectedProvider, $oauthId, 'string');
						$result = true;
					}
				} elseif ($connect) {
					$payload = ['given_name' => $this->getData('givenName'), 'family_name' => $this->getData('familyName'), 'id' => $oauthId];
					$username = $this->getData('usernameLogin');
					$password = $this->getData('passwordLogin');
					$user = $userDao->getByUsername($username, true);
					if (!isset($user)) {
						$user = $userDao->getUserByEmail($username, true);
					}
					if (isset($user) && Validation::verifyPassword($user->getUsername(), $password, $user->getPassword(), $rehash)) {
						$result = true;
					}
				}
				if ($result && isset($user)) {
					OpenIDHandler::updateUserDetails(isset($payload) ? $payload : null, $user, Application::get()->getRequest(), $selectedProvider, true);
					Validation::registerUserSession($user, $reason, true);
				}
			}
		}
		parent::execute(...$functionArgs);

		return $result;
	}


	/**
	 * This function registers a new OJS User if no user exists with the given username, email or openid::{provider_name}!
	 *
	 * @return User|null
	 */
	private function _registerUser()
	{
		$userDao = DAORegistry::getDAO('UserDAO');
		$user = $userDao->newDataObject();
		$user->setUsername($this->getData('username'));
		$request = Application::get()->getRequest();
		$site = $request->getSite();
		$sitePrimaryLocale = $site->getPrimaryLocale();
		$currentLocale = AppLocale::getLocale();
		$user->setGivenName($this->getData('givenName'), $currentLocale);
		$user->setFamilyName($this->getData('familyName'), $currentLocale);
		$user->setEmail($this->getData('email'));
		$user->setCountry($this->getData('country'));
		$user->setAffiliation($this->getData('affiliation'), $currentLocale);
		if ($sitePrimaryLocale != $currentLocale) {
			$user->setGivenName($this->getData('givenName'), $sitePrimaryLocale);
			$user->setFamilyName($this->getData('familyName'), $sitePrimaryLocale);
			$user->setAffiliation($this->getData('affiliation'), $sitePrimaryLocale);
		}
		$user->setDateRegistered(Core::getCurrentDate());
		$user->setInlineHelp(1);
		$user->setPassword(Validation::encryptCredentials($this->getData('username'), openssl_random_pseudo_bytes(16)));
		$userDao->insertObject($user);
		if ($user->getId()) {

			// Insert the user interests
			import('lib.pkp.classes.user.InterestManager');
			$interestManager = new InterestManager();
			$interestManager->setInterestsForUser($user, $this->getData('interests'));

			// Save the selected roles or assign the Reader role if none selected
			if ($request->getContext() && !$this->getData('reviewerGroup')) {
				$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
				/* @var $userGroupDao UserGroupDAO */
				$defaultReaderGroup = $userGroupDao->getDefaultByRoleId($request->getContext()->getId(), ROLE_ID_READER);
				if ($defaultReaderGroup) {
					$userGroupDao->assignUserToGroup($user->getId(), $defaultReaderGroup->getId());
				}
			} else {
				import('lib.pkp.classes.user.form.UserFormHelper');
				$userFormHelper = new UserFormHelper();
				$userFormHelper->saveRoleContent($this, $user);
			}
		} else {
			$user = null;
		}

		// allow other plugins to provide user data (i.e. orcidProfile)
		HookRegistry::call('openid::registerUser', array($this,&$user));

		return $user;
	}
}
