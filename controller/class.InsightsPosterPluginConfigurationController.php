<?php
/**
 *
 * webapp/plugins/insightsposter/controller/class.InsightsPosterPluginConfigurationController.php
 *
 * LICENSE:
 *
 * This file is part of ThinkUp (http://thinkup.com).
 *
 * ThinkUp is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, either version 2 of the License, or (at your option) any
 * later version.
 *
 * ThinkUp is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along with ThinkUp.  If not, see
 * <http://www.gnu.org/licenses/>.
 *
 *
 * Insights Poster Plugin Configuration Controller
 *
 * Copyright (c) 2014 Gina Trapani
 *
 * @author Gina Trapani <ginatrapani [at] gmail [dot] com>
 * @license http://www.gnu.org/licenses/gpl.html
 * @copyright 2014 Gina Trapani
 */

class InsightsPosterPluginConfigurationController extends PluginConfigurationController {

    public function __construct($owner) {
        parent::__construct($owner, 'insightsposter');
        $this->disableCaching();
        $this->owner = $owner;
    }

    public function authControl() {
        $config = Config::getInstance();
        Loader::definePathConstants();
        $this->setViewTemplate( THINKUP_WEBAPP_PATH.'plugins/insightsposter/view/account.index.tpl');
        $this->view_mgr->addHelp('insightsposter', 'contribute/developers/plugins/buildplugin');

        /** set option fields **/
        $consumer_key_field = array('name' => 'consumer_key', 'label' => 'Twitter consumer key',
            'size' => 50);
        $this->addPluginOption(self::FORM_TEXT_ELEMENT, $consumer_key_field); // add element
        $this->addPluginOptionRequiredMessage('consumer_key',
            'Please enter your Twitter consumer key.');

        $consumer_secret_field = array('name' => 'consumer_secret', 'label' => 'Twitter consumer consumer_secret',
            'size' => 50);
        $this->addPluginOption(self::FORM_TEXT_ELEMENT, $consumer_secret_field); // add element
        $this->addPluginOptionRequiredMessage('consumer_secret',
            'Please enter your Twitter consumer secret.');

        $oauth_access_field = array('name' => 'oauth_access_token', 'label' => 'Twitter OAuth access token',
            'size' => 50);
        $this->addPluginOption(self::FORM_TEXT_ELEMENT, $oauth_access_field); // add element
        $this->addPluginOptionRequiredMessage('oauth_access_token',
            'Please enter your Twitter OAuth access token.');

        $oauth_secret_field = array('name' => 'oauth_access_token_secret', 'label' => 'Twitter OAuth access token secret',
            'size' => 50);
        $this->addPluginOption(self::FORM_TEXT_ELEMENT, $oauth_secret_field); // add element
        $this->addPluginOptionRequiredMessage('oauth_access_token_secret',
            'Please enter your Twitter OAuth access token secret.');

        $plugin = new InsightsPosterPlugin();
        $this->addToView('is_configured', $plugin->isConfigured());

        return $this->generateView();
    }
}
