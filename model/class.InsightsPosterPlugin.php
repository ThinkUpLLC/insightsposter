<?php
/**
 *
 * webapp/plugins/insightsposter/model/class.InsightsPosterPlugin.php
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
 * Insights Poster Plugin
 *
 * Copyright (c) 2014 Gina Trapani
 *
 * @author Gina Trapani <ginatrapani [at] gmail [dot] com>
 * @license http://www.gnu.org/licenses/gpl.html
 * @copyright 2014 Gina Trapani
 */
require_once THINKUP_ROOT_PATH. 'webapp/plugins/twitter/model/class.TwitterAPIAccessorOAuth.php';
require_once THINKUP_ROOT_PATH. 'webapp/plugins/twitter/model/class.TwitterOAuthThinkUp.php';
require_once THINKUP_ROOT_PATH. 'webapp/plugins/twitter/model/class.TwitterAPIEndpoint.php';

class InsightsPosterPlugin extends Plugin implements CrawlerPlugin {
    /**
     * @var Current Unix timestamp, here for testing.
     */
    var $current_timestamp;

    public function __construct($vals=null) {
        parent::__construct($vals);
        $this->folder_name = 'insightsposter';
        $this->current_timestamp = time();
    }

    public function activate() {

    }

    public function deactivate() {

    }

    public function renderConfiguration($owner) {
        $controller = new InsightsPosterPluginConfigurationController($owner);
        return $controller->go();
    }

    public function crawl() {
        //set up logging
        $logger = Logger::getInstance();
        $logger->logUserSuccess("Starting insights poster", __METHOD__.','.__LINE__);

        //get plugin settings
        $plugin_option_dao = DAOFactory::getDAO('PluginOptionDAO');
        $options = $plugin_option_dao->getOptionsHash('insightsposter', true);

        if (isset($options['consumer_key']->option_value) && isset($options['consumer_secret']->option_value)
            && isset($options['oauth_access_token']->option_value)
            && isset($options['oauth_access_token_secret']->option_value)) {
            // Only send pushes after 7am local time
            $owner_dao = DAOFactory::getDAO('OwnerDAO');
            $owner = $owner_dao->getByEmail(Session::getLoggedInUser());
            $tz = $owner->timezone;
            if (empty($tz)) {
                $config = Config::getInstance();
                $tz = $config->getValue('timezone');
            }

            // Is it after 9am local time?
            if (!empty($tz)) {
                $original_tz = date_default_timezone_get();
                date_default_timezone_set($tz);
                $localized_hour = (int)date('G', $this->current_timestamp);
                date_default_timezone_set($original_tz);
            } else {
                $localize_hour = (int)date('G', $this->current_timestamp);
            }
//            if ($localized_hour >= 9) {
            if (true) {
                //Get the last time insights were posted
                $options = $plugin_option_dao->getOptionsHash('insightsposter');
                if (isset($options['last_push_completion']->option_value)) {
                    $last_push_completion = $options['last_push_completion']->option_value;
                    $logger->logUserInfo("Last push completion was ".$last_push_completion, __METHOD__.','.__LINE__);
                } else {
                    $last_push_completion = false;
                }

                //Get insights since last pushed ID, or latest insight
                $insight_dao = DAOFactory::getDAO('InsightDAO');
                $insights = array();
                if ($owner->is_admin) {
                    if ($last_push_completion !== false ) {
                        //Get all insights since last pushed insight creation date
                        $insights = $insight_dao->getAllInstanceInsightsSince($last_push_completion);
                    } else {
                        // get last insight generated
                        $insights = $insight_dao->getAllInstanceInsights($page_count=1);
                    }
                } else {
                    if ($last_push_completion !== false ) {
                        //Get insights since last pushed insight creation date
                        $insights = $insight_dao->getAllOwnerInstanceInsightsSince($owner->id, $last_push_completion);
                    } else {
                        // get last insight generated
                        $insights = $insight_dao->getAllOwnerInstanceInsights($owner->id, $page_count=1);
                    }
                }
                $total_pushed = 0;
                if (sizeof($insights) > 0) {
                    $logger->logUserInfo("Insight candidates to push, only choosing HIGH emphasis ",
                        __METHOD__.','.__LINE__);

                    foreach ($insights as $insight) {
                        if ($insight->instance->network == "twitter" && $insight->emphasis == Insight::EMPHASIS_HIGH) {
                            self::postInsight($insight, $logger, $options);
                            break;
                        }
                    }

                    // Update $last_push_completion in plugin settings
                    if (isset($options['last_push_completion']->id)) {
                        //update option
                        $result = $plugin_option_dao->updateOption($options['last_push_completion']->id,
                            'last_push_completion', date('Y-m-d H:i:s'));
                        $logger->logInfo("Updated ".$result." option", __METHOD__.','.__LINE__);
                    } else {
                        //insert option
                        $plugin_dao = DAOFactory::getDAO('PluginDAO');
                        $plugin_id = $plugin_dao->getPluginId('insightsposter');
                        $result = $plugin_option_dao->insertOption($plugin_id, 'last_push_completion',
                            date('Y-m-d H:i:s'));
                        $logger->logInfo("Inserted option ID ".$result, __METHOD__.','.__LINE__);
                    }
                }
                if ($total_pushed > 0) {
                    $logger->logUserSuccess("Pushed ".$total_pushed." insight".(($total_pushed == 1)?'':'s'),
                        __METHOD__.','.__LINE__);
                } else {
                    $logger->logInfo("No insights to push.", __METHOD__.','.__LINE__);
                }
            } else {
                $logger->logInfo("It's too early in the am for posting insights.", __METHOD__.','.__LINE__);
            }
        } else {
            $logger->logInfo("Insights Poster plugin isn't configured for use.", __METHOD__.','.__LINE__);
        }
        $logger->logUserSuccess("Completed insights poster.", __METHOD__.','.__LINE__);
    }

    public function getDashboardMenuItems($instance) {

    }

    public function getPostDetailMenuItems($post) {

    }
    public function renderInstanceConfiguration($owner, $instance_username, $instance_network) {
        return "";
    }

    private function postInsight($insight, $logger, $options) {
        $terms = new InsightTerms($insight->instance->network);
        $tweet = strip_tags(html_entity_decode($insight->headline));
        $tweet = $terms->swapInSecondPerson($insight->instance->network_username, $tweet);
        $tweet = '@'.$insight->instance->network_username." ".$tweet;
        $url = Utils::getApplicationURL()."?u=".$insight->instance->network_username."&n=".
            $insight->instance->network."&d=".$insight_date."&s=". $insight->slug;
        $tweet = $tweet . " " . $url;
        $logger->logUserInfo("Posting the following tweet: ".$tweet, __METHOD__.','.__LINE__);

        $endpoint = new TwitterAPIEndpoint("/statuses/update");
        print_r($endpoint);
        print_r($options);
        $api = new PosterTwitterAPIAccessorOAuth($options["oauth_access_token"]->option_value,
            $options["oauth_access_token_secret"]->option_value,
            $options["consumer_key"]->option_value, $options["consumer_secret"]->option_value, $insight->instance, 
            2300, 2);
        print_r($api);
        $result = $api->apiPostRequest( $endpoint, array('status'=>$tweet));
        print_r($result);
        $logger->logUserInfo("Tweet posted ", __METHOD__.','.__LINE__);
    }
}
