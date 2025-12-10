{*
 * templates/authStep2.tpl
 *
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
 * Display the OpenID Auth second step.
 *}
{include file="frontend/components/header.tpl" pageTitle="plugins.generic.openid.step2.title"}
<div>
	{include file="frontend/components/breadcrumbs.tpl" currentTitleKey="plugins.generic.openid.step2.title"}
	<form id="oauth" method="post" action="{url page="openid" op="registerOrConnect"}">
		{csrf}
		<input type="hidden" name="oauthId" id="oauthId" value="{$oauthId}">
		<input type="hidden" name="selectedProvider" id="selectedProvider" value="{$selectedProvider}">
		<input type="hidden" name="returnTo" id="returnTo" value="{$returnTo}">
		{if empty($disableConnect) || $disableConnect != "1"}
			<h1>
				{translate key="plugins.generic.openid.step2.headline" journalName=$siteTitle|escape}
			</h1>
			{*<p>
				{translate key="plugins.generic.openid.step2.help" journalName=$siteTitle|escape}
			</p>*}
			<ul id="openid-choice-select">
				<li><span id='showLoginForm' class='step2-choice-links'>{translate key="plugins.generic.openid.step2.choice.yes"}</span></li>
				<li><span id='showRegisterForm'
				          class='step2-choice-links'>{translate key="plugins.generic.openid.step2.choice.no" journalName=$siteTitle|escape}</span></li>
			</ul>
		{/if}		
		<div {if empty($disableConnect) || $disableConnect != "1" }id="register-form"{/if} class="page_register">
			<p>
				{translate key="plugins.generic.openid.step2.help" journalName=$siteTitle|escape}
			</p>
			<fieldset class="mt-10 grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
				{if $returnTo == 'register'}
					{include file="common/formErrors.tpl"}
				{/if}
				<div class="sm:col-span-4">
					<div class="block text-sm font-medium leading-6 text-gray-900">
						<label class="inline-grid grid-cols-3 gap-4">
							<span>
								{translate key="user.givenName"}
								<span aria-hidden="true">*</span>
								<span class="sr-only">
									{translate key="common.required"}
								</span>
							</span>
							<input class="col-span-2" type="text" name="givenName" id="givenName" value="{$givenName|escape}" maxlength="255" required aria-required="true">
						</label>
					</div>
					<div class="block text-sm font-medium leading-6 text-gray-900">
						<label class="inline-grid grid-cols-3 gap-4">
							<span>
								{translate key="user.familyName"}
								<span class="required" aria-hidden="true">*</span>
								<span class="sr-only">
									{translate key="common.required"}
								</span>
							</span>
							<input class="col-span-2" type="text" name="familyName" id="familyName" value="{$familyName|escape}" maxlength="255">
						</label>
					</div>
					<div class="block text-sm font-medium leading-6 text-gray-900">
						<label class="inline-grid grid-cols-3 gap-4">
							<span>
								{translate key="user.email"}
								<span class="required" aria-hidden="true">*</span>
								<span class="sr-only">
									{translate key="common.required"}
								</span>
							</span>
							<input class="col-span-2" type="email" name="email" id="email" value="{$email|escape}" maxlength="90" required aria-required="true">
						</label>
					</div>
					<div class="block text-sm font-medium leading-6 text-gray-900">
						<label class="inline-grid grid-cols-3 gap-4">
							<span>
								{translate key="user.username"}
								<span class="required" aria-hidden="true">*</span>
								<span class="sr-only">
									{translate key="common.required"}
								</span>
							</span>
							<input class="col-span-2" type="text" name="username" id="username" value="{$username|escape}" maxlength="32" required aria-required="true">
						</label>
					</div>
					<div class="block text-sm font-medium leading-6 text-gray-900">
						<label class="inline-grid grid-cols-3 gap-4">
							<span>
								{translate key="user.affiliation"}
								<span class="required" aria-hidden="true">*</span>
								<span class="sr-only">
									{translate key="common.required"}
								</span>
							</span>
							<input class="col-span-2" type="text" name="affiliation" id="affiliation" value="{$affiliation|escape}" required aria-required="true">
						</label>
					</div>
					<div class="block text-sm font-medium leading-6 text-gray-900">
						<label class="inline-grid grid-cols-3 gap-4">
							<span>
								{translate key="common.country"}
								<span class="required" aria-hidden="true">*</span>
								<span class="sr-only">
									{translate key="common.required"}
								</span>
							</span>
							<select class="col-span-2" name="country" id="country" required aria-required="true">
								<option></option>
								{html_options options=$countries selected=$country}
							</select>
						</label>
					</div>
				</div>
			</fieldset>
			<fieldset class="mt-10 grid grid-cols-1 gap-y-2 sm:grid-cols-6">
				{if isset($currentContext) and $currentContext->getData('privacyStatement')}
					{* Require the user to agree to the terms of the privacy policy *}
					<div class="sm:col-span-4">
						<div class="block text-sm font-medium leading-6 text-gray-900">
							<label>
								<input type="checkbox" name="privacyConsent" value="1" required{if $privacyConsent} checked="checked"{/if}>
								{capture assign="privacyUrl"}{url router=$smarty.const.ROUTE_PAGE page="about" op="privacy"}{/capture}
								{translate key="user.register.form.privacyConsent" privacyUrl=$privacyUrl}
								<span class="required" aria-hidden="true">*</span>
								<span class="sr-only">
										{translate key="common.required"}
									</span>
							</label>
						</div>
					</div>
				{/if}
				{* Ask the user to opt into public email notifications *}
				<div class="sm:col-span-4">
					<div class="block text-sm font-medium leading-6 text-gray-900">
						<label>
							<input type="checkbox" name="emailConsent" id="emailConsent" value="1" {if $emailConsent} checked="checked"{/if}>
							{translate key="user.register.form.emailConsent"}
						</label>
					</div>
				</div>
			</fieldset>
			{* Allow the user to sign up as a reviewer *}
			{if isset($currentContext) }
			{assign var=contextId value=$currentContext->getId()}
			{else}
			{assign var=contextId value=0}
			{/if}
			{assign var=userCanRegisterReviewer value=0}
			{foreach from=$reviewerUserGroups[$contextId] item=userGroup}
				{if $userGroup->getPermitSelfRegistration()}
					{assign var=userCanRegisterReviewer value=$userCanRegisterReviewer+1}
				{/if}
			{/foreach}
			{if $userCanRegisterReviewer}
				<fieldset class="reviewer">
					{if $userCanRegisterReviewer > 1}
						<legend>
							{translate key="user.reviewerPrompt"}
						</legend>
						{capture assign="checkboxLocaleKey"}user.reviewerPrompt.userGroup{/capture}
					{else}
						{capture assign="checkboxLocaleKey"}user.reviewerPrompt.optin{/capture}
					{/if}
					<div class="fields">
						<div id="reviewerOptinGroup" class="optin">
							{foreach from=$reviewerUserGroups[$contextId] item=userGroup}
								{if $userGroup->getPermitSelfRegistration()}
									<label>
										{assign var="userGroupId" value=$userGroup->getId()}
										<input type="checkbox" name="reviewerGroup[{$userGroupId}]" class="reviewerGroupInput"
										       value="1"{if in_array($userGroupId, $userGroupIds)} checked="checked"{/if}>
										{translate key=$checkboxLocaleKey userGroup=$userGroup->getLocalizedName()}
									</label>
								{/if}
							{/foreach}
						</div>
						<div id="reviewerInterests" class="reviewer_interests">
							<label>
								<span class="label">
									{translate key="user.interests"}
								</span>
								<input type="text" name="interests" id="interests" value="{$interests|escape}" class="reviewerGroupInput">
							</label>
						</div>
					</div>
				</fieldset>
			{/if}
			<div class="buttons">
				<button class="rounded-md bg-sky-600 mx-3 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600" type="submit" name="register">
					{translate key="plugins.generic.openid.step2.complete.btn"}
				</button>
			</div>
		</div>
		{if empty($disableConnect) || $disableConnect != "1"}
			<div id="login-form">
				<fieldset class="login">
					<p class="cmp_notification warning">
						{translate key="plugins.generic.openid.step2.connect" journalName=$siteTitle|escape}
					</p>
					{if $returnTo == 'connect'}
						{include file="common/formErrors.tpl"}
					{/if}
					<div class="username">
						<label>
						<span class="label">
							{translate key="plugins.generic.openid.step2.connect.username"}
							<span class="required" aria-hidden="true">*</span>
							<span class="sr-only">
								{translate key="common.required"}
							</span>
						</span>
							<input type="text" name="usernameLogin" id="usernameLogin" value="{$usernameLogin|escape}" maxlength="32" required
							       aria-required="true">
						</label>
					</div>
					<div class="password">
						<label>
						<span class="label">
							{translate key="user.password"}
							<span class="required" aria-hidden="true">*</span>
							<span class="sr-only">
								{translate key="common.required"}
							</span>
						</span>
							<input type="password" name="passwordLogin" id="passwordLogin" value="{$passwordLogin|escape}" maxlength="32" required
							       aria-required="true">
							<a href="{url page="login" op="lostPassword"}">
								{translate key="user.login.forgotPassword"}
							</a>
						</label>
					</div>
				</fieldset>
				<div class="buttons">
					<button class="submit" type="submit" name="connect">
						{translate key="plugins.generic.openid.step2.connect.btn"}
					</button>
				</div>
			</div>
		{/if}
	</form>
</div><!-- .page -->
{include file="frontend/components/footer.tpl"}
