<?php

/**
 * @file plugins/generic/publons/PublonsPlugin.inc.php
 *
 * Copyright (c) 2016 Publons Ltd.
 * Distributed under the GNU GPL v2.
 *
 * @class PublonsPlugin
 * @ingroup plugins_generic_publons
 *
 * @brief Publons plugin
 */

import('lib.pkp.classes.plugins.GenericPlugin');

class PublonsPlugin extends GenericPlugin {

    /**
     * Called as a plugin is registered to the registry
     * @param $category String Name of category plugin was registered to
     * @return boolean True iff plugin initialized successfully; if false,
     *  the plugin will not be registered.
     */
    function register($category, $path) {

        if (parent::register($category, $path)) {
            if ($this->getEnabled()) {
                $this->import('classes.PublonsReviews');
                $this->import('classes.PublonsReviewsDAO');
                $publonsReviewsDao = new PublonsReviewsDAO();
                DAORegistry::registerDAO('PublonsReviewsDAO', $publonsReviewsDao);

                HookRegistry::register('TemplateManager::display', array(&$this, 'handleTemplateDisplay'));
                HookRegistry::register('TemplateManager::fetch', array(&$this, 'handleTemplateFetch'));
                HookRegistry::register ('LoadHandler', array(&$this, 'handleRequest'));
            }
            return true;
        }
        return false;
    }

    /**
     * Get the symbolic name of this plugin
     * @return string
     */
    function getName() {
        // This should not be used as this is an abstract class
        return 'PublonsPlugin';
    }

    /**
     * Get the display name of this plugin
     * @return string
     * @see PKPPlugin::getDisplayName()
     */
    function getDisplayName() {
        return __('plugins.generic.publons.displayName');
    }

    /**
     * Get the description of this plugin
     * @return string
    */
    function getDescription() {
        return __('plugins.generic.publons.description');
    }

    /**
     * @see PKPPlugin::getTemplatePath()
     */
    function getTemplatePath($inCore = false) {
        return parent::getTemplatePath() . 'templates' . DIRECTORY_SEPARATOR;
    }

    /**
     * @see PKPPlugin::getInstallSchemaFile()
     * @return string
     */
    function getInstallSchemaFile() {
        return $this->getPluginPath() . DIRECTORY_SEPARATOR . 'schema.xml';
    }

        /**
     * Get the stylesheet for this plugin.
     */
    function getStyleSheet() {
        return $this->getPluginPath() . DIRECTORY_SEPARATOR . 'styles' . DIRECTORY_SEPARATOR . 'publons.css';
    }

    /**
     * @see Plugin::getActions()
     */
    function getActions($request, $verb) {
        $router = $request->getRouter();
        import('lib.pkp.classes.linkAction.request.AjaxModal');
        return array_merge(
            $this->getEnabled()?array(
                new LinkAction(
                    'connect',
                    new AjaxModal(
                        $router->url($request, null, null, 'manage', null, array('verb' => 'connect', 'plugin' => $this->getName(), 'category' => 'generic')),
                        $this->getDisplayName()
                    ),
                    __('plugins.generic.publons.settings.connection'),
                    null
                ),
                // new LinkAction(
                //     'settings',
                //     new AjaxModal(
                //         $router->url($request, null, null, 'manage', null, array('verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic')),
                //         $this->getDisplayName()
                //     ),
                //     __('plugins.generic.publons.settings.published'),
                //     null
                // ),
            ):array(),
            parent::getActions($request, $verb)
        );
    }

    /**
     * @see GenericPlugin::manage()
     */
    function manage($args, $request) {

        AppLocale::requireComponents(LOCALE_COMPONENT_APP_COMMON,  LOCALE_COMPONENT_PKP_MANAGER);
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->register_function('plugin_url', array($this, 'smartyPluginUrl'));

        $journal =& Request::getJournal();
        switch ($request->getUserVar('verb')) {
            case 'connect':
                $this->import('classes.form.publonsSettingsForm');
                $form = new PublonsSettingsForm($this, $journal->getId());
                if ($request->getUserVar('save')) {
                    $form->readInputData();
                    if ($form->validate()) {

                        $form->execute();
                        Request::redirect(null, 'manager', 'plugin', array('generic', $this->getName(), 'select'));
                        return false;
                    } else {
                        $form->display();
                    }
                } else {
                    $form->initData();
                }
                return new JSONMessage(true, $form->fetch($request));
            // case 'settings':
            //     $publonsReviewsDao =& DAORegistry::getDAO('PublonsReviewsDAO');
            //     $reviewsByJournal =& $publonsReviewsDao->getPublonsReviewsByJournal($journal->getId());

            //     $this->import('classes.form.SettingsForm');
            //     $form = new SettingsForm($this, $journal->getId());
            //         $form->initData();
            //     $form->display();
            //     return true;
        }

        return parent::manage($args, $request);
    }


    function handleRequest($hookName, $params) {
        $page =& $params[0];
        $request = Application::getRequest();
        AppLocale::requireComponents();
        if ($page == 'reviewer' && $this->getEnabled()) {
            $op =& $params[1];
            if ($op == 'exportReview') {

                define('HANDLER_CLASS', 'PublonsHandler');
                $this->import('PublonsHandler');
                PublonsHandler::setPlugin($this);
                return true;
            }
        }
        return false;

    }

    /**
     * Hook callback: register output filter to add data citation to submission
     * summaries; add data citation to reading tools' suppfiles and metadata views.
     * @see TemplateManager::display()
     */
    function handleTemplateDisplay($hookName, $args) {
        if ($this->getEnabled()) {
            $templateMgr =& $args[0];
            $request = PKPApplication::getRequest();

            // Assign our private stylesheet, for front and back ends.
            $templateMgr->addStyleSheet(
                'publons',
                $request->getBaseUrl() . '/' . $this->getStyleSheet(),
                array(
                    'contexts' => array('frontend', 'backend')
                )
            );

            return false;
        }
    }

    function handleTemplateFetch($hookName, $args) {
        if ($this->getEnabled()) {
            $templateMgr =& $args[0];
            $template =& $args[1];

            switch ($template) {
                case 'reviewer/review/reviewCompleted.tpl':
                    $templateMgr->register_outputfilter(array(&$this, 'completedSubmissionOutputFilter'));
                    break;
                case 'reviewer/review/step3.tpl':
                    $templateMgr->register_outputfilter(array(&$this, 'step3SubmissionOutputFilter'));
                    break;
                default:
                    return false;
            }

        }

        return false;
    }


    function step3SubmissionOutputFilter($output, &$templateMgr) {

        $plugin =& PluginRegistry::getPlugin('generic', $this->getName());
        $templateMgr->unregister_outputfilter('submissionOutputFilter');

        $reviewerSubmissionDao =& DAORegistry::getDAO('ReviewerSubmissionDAO');
        $reviewSubmission = $templateMgr->get_template_vars('submission');
        $reviewId = $reviewSubmission->getReviewId();
        $journalId = $reviewSubmission->getJournalId();
        $auth_token = $plugin->getSetting($journalId, 'auth_token');


        // Only display if the plugin has been setup
        if ($auth_token){

            preg_match_all('/<div class="section formButtons form_buttons ">/s', $output, $matches, PREG_OFFSET_CAPTURE);
            preg_match('/id="publons-info"/s', $output, $done);
            if (!is_null(array_values(array_slice($matches[0], -1))[0][1])){
                $match = array_values(array_slice($matches[0], -1))[0][1];

                $beforeInsertPoint = substr($output, 0, $match);
                $afterInsertPoint = substr($output, $match - strlen($output));

                $templateMgr =& TemplateManager::getManager();

                $newOutput = $beforeInsertPoint;
                if (empty($done)){
                    $newOutput .= $templateMgr->fetch($this->getTemplatePath() . 'publonsNotificationStep.tpl');
                }
                $newOutput .= $afterInsertPoint;

                $output = $newOutput;
            }

        }

        $templateMgr->unregister_outputfilter('step3SubmissionOutputFilter');
        return $output;
    }

    /**
     * Output filter adds publons export step to submission process.
     * @param $output string
     * @param $templateMgr TemplateManager
     * @return $string
     */
    function completedSubmissionOutputFilter($output, &$templateMgr) {
        $plugin =& PluginRegistry::getPlugin('generic', $this->getName());

        $reviewerSubmissionDao =& DAORegistry::getDAO('ReviewerSubmissionDAO');
        $reviewSubmission = $templateMgr->get_template_vars('submission');
        $reviewId = $reviewSubmission->getReviewId();
        $journalId = $reviewSubmission->getJournalId();
        $auth_token = $plugin->getSetting($journalId, 'auth_token');


        // Only display if the plugin has been setup
        if ($auth_token){
            $publonsReviewsDao =& DAORegistry::getDAO('PublonsReviewsDAO');
            $published = $publonsReviewsDao->getPublonsReviewsIdByReviewId($reviewId);
            $info_url = $this->getSetting($journalId, 'info_url');

            $templateMgr =& TemplateManager::getManager();
            $templateMgr->unregister_outputfilter(array(&$this, 'completedSubmissionOutputFilter'));
            $request = Application::getRequest();
            $router = $request->getRouter();

            import('lib.pkp.classes.linkAction.request.AjaxModal');
            $templateMgr->assign(
                'exportReviewAction',
                new LinkAction(
                    'exportReview',
                    new AjaxModal(
                        $router->url($request, null, null, 'exportReview', array('reviewId' =>  $reviewId)),
                        __('plugins.generic.publons.settings.connection')
                    ),
                    __('plugins.generic.publons.settings.connection'),
                    null
                )
            );
            $templateMgr->assign('reviewId', $reviewId);
            $templateMgr->assign('published', $published);
            $templateMgr->assign('infoURL', $info_url);

            $output .= $templateMgr->fetch($this->getTemplatePath() . 'publonsExportStep.tpl');
        }
        return $output;
    }

    /**
     * Get whether we're running php 5
     * @return boolean
     */
    function php5Installed() {
        return version_compare(PHP_VERSION, '5.0.0', '>=');
    }

    /**
     * Get whether curl is available
     * @return boolean
     */
    function curlInstalled() {
        return function_exists('curl_version');
    }
    /**
     * @see PKPPlugin::smartyPluginUrl()
     */
    function smartyPluginUrl($params, &$smarty) {
        $path = array($this->getCategory(), $this->getName());
        if (is_array($params['path'])) {
            $params['path'] = array_merge($path, $params['path']);
        } elseif (!empty($params['path'])) {
            $params['path'] = array_merge($path, array($params['path']));
        } else {
            $params['path'] = $path;
        }

        if (!empty($params['id'])) {
            $params['path'] = array_merge($params['path'], array($params['id']));
            unset($params['id']);
        }
        return $smarty->smartyUrl($params, $smarty);
    }

}

?>
