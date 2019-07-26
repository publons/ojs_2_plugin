<?php

/**
 * @file plugins/generic/publons/classes/form/PublonsAuthForm.inc.php
 *
 * Copyright (c) 2016 Publons Ltd.
 * Distributed under the GNU GPL v3.
 *
 * @class PublonsAuthForm
 * @ingroup plugins_generic_publons
 *
 * @brief Plugin settings: connect to a Publons Network
 */

import('lib.pkp.classes.form.Form');
import('plugins.generic.publons.classes.PublonsHelpURLFormValidator');

class PublonsAuthForm extends Form {

    /** @var $_plugin PublonsPlugin */
    var $_plugin;

    /** @var $_journalId int */
    var $_journalId;

    /**
     * Constructor.
     * @param $plugin PublonsPlugin
     * @param $journalId int
     * @see Form::Form()
     */
    function PublonsAuthForm(&$plugin, $journalId) {
        $this->_plugin =& $plugin;
        $this->_journalId = $journalId;

        parent::Form($plugin->getTemplatePath() . 'publonsAuthForm.tpl');
        $this->addCheck(new FormValidator($this, 'auth_token', FORM_VALIDATOR_REQUIRED_VALUE, 'plugins.generic.publons.settings.auth_tokenRequired'));
        $this->addCheck(new FormValidator($this, 'auth_key', FORM_VALIDATOR_REQUIRED_VALUE, 'plugins.generic.publons.settings.authKeyRequired'));
        $this->addCheck(new FormValidator($this, 'info_url', FORM_VALIDATOR_OPTIONAL_VALUE, 'plugins.generic.publons.settings.invalidHelpUrl', new PublonsHelpURLFormValidator()));
        $this->addCheck(new FormValidatorPost($this));
    }

    /**
     * @see Form::initData()
     */
    function initData() {
        $plugin =& $this->_plugin;

        // Initialize from plugin settings
        $this->setData('auth_key', $plugin->getSetting($this->_journalId, 'auth_key'));
        $this->setData('auth_token', $plugin->getSetting($this->_journalId, 'auth_token'));
        $this->setData('info_url', $plugin->getSetting($this->_journalId, 'info_url'));
    }

    /**
     * @see Form::readInputData()
     */
    function readInputData() {
        $this->readUserVars(array('auth_token', 'auth_key', 'password', 'info_url'));
    }

    /**
     * @see Form::execute()
     */
    function execute() {
        $plugin =& $this->_plugin;

        $plugin->updateSetting($this->_journalId, 'auth_token', $this->getData('auth_token') , 'string');
        $plugin->updateSetting($this->_journalId, 'auth_key', $this->getData('auth_key'), 'string');
        $plugin->updateSetting($this->_journalId, 'info_url', $this->getData('info_url'), 'string');
    }

}
