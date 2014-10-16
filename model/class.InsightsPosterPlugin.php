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

    /**
     * Insights to exclude from posting because they're spammy or annoying by filename
     * @var array
     */
    var $filename_blacklist = array('biotracker', 'weeklygraph');

    /**
     * Insights to exclude from posting because they're spammy or annoying by slug
     * @var array
     */
    var $slug_blacklist = array('fave_spike_30_day', 'retweet_spike_30_day', 'reply_spike_30_day_',
        'least_likely_followers');

    /**
     * Twitter users associated with the insight candidates.
     * @var array
     */
    var $twitter_users = array();

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
        $logger->setUsername(Session::getLoggedInUser());
        $logger->logUserSuccess("Starting insights poster", __METHOD__.','.__LINE__);

        //get plugin settings
        $plugin_option_dao = DAOFactory::getDAO('PluginOptionDAO');
        $options = $plugin_option_dao->getOptionsHash('insightsposter', true);

        if (isset($options['consumer_key']->option_value) && isset($options['consumer_secret']->option_value)
            && isset($options['oauth_access_token']->option_value)
            && isset($options['oauth_access_token_secret']->option_value)) {

            // Get owner timezone
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
            if ($localized_hour >= 9) {
                //Get the last time insights were posted
                $do_post = false;
                $options = $plugin_option_dao->getOptionsHash('insightsposter');
                if (isset($options['last_post_completion']->option_value)) {
                    $last_post_completion = $options['last_post_completion']->option_value;
                    $logger->logUserInfo("Last post completion was ".$last_post_completion, __METHOD__.','.__LINE__);
                    $last_post_day = substr($last_post_completion, 0, 10);
                    $today = date('Y-m-d');
                    $yesterday = date('Y-m-d', strtotime("-1 days"));
                    if ($last_post_day !== $today && $last_post_day !== $yesterday) {
                        $logger->logUserInfo("No post today or yesterday ".$last_post_day. " != ".$today.
                            " or ".$yesterday, __METHOD__.','.__LINE__);
                        $do_post = true;
                    }
                } else {
                    $last_post_completion = false;
                    $do_post = true;
                }

                if ($do_post) {
                    //Get insights since last posted ID, or latest insight
                    $insight_dao = DAOFactory::getDAO('InsightDAO');
                    $insights = array();
                    if ($owner->is_admin) {
                        if ($last_post_completion !== false ) {
                            //Get all insights since last posted insight creation date
                            $insights = $insight_dao->getAllInstanceInsightsSince($last_post_completion);
                        } else {
                            // get last insight generated
                            $insights = $insight_dao->getAllInstanceInsights($page_count=1);
                        }
                    } else {
                        if ($last_post_completion !== false ) {
                            //Get insights since last posted insight creation date
                            $insights = $insight_dao->getAllOwnerInstanceInsightsSince($owner->id,
                                $last_post_completion);
                        } else {
                            // get last insight generated
                            $insights = $insight_dao->getAllOwnerInstanceInsights($owner->id, $page_count=1);
                        }
                    }
                    $total_posted = 0;
                    if (sizeof($insights) > 0) {
                        $user_dao = DAOFactory::getDAO('UserDAO');
                        $logger->logUserInfo("Insight candidates to push, only choosing HIGH emphasis",
                            __METHOD__.','.__LINE__);

                        //First, push HIGH emphasis
                        foreach ($insights as $insight) {
                            if (self::shouldPostInsight($insight, Insight::EMPHASIS_HIGH)) {
                                $logger->logUserInfo("About to post insight  ". Utils::varDumpToString($insight),
                                    __METHOD__.','.__LINE__);
                                self::postInsight($insight, $options);
                                $total_posted++;
                                break;
                            }
                        }
                        $logger->logUserInfo("Moving onto MED emphasis", __METHOD__.','.__LINE__);
                        //If HIGH emphasis insight didn't exist, push MED
                        if ($total_posted == 0) {
                            foreach ($insights as $insight) {
                                if (self::shouldPostInsight($insight, Insight::EMPHASIS_MED)) {
                                    $logger->logUserInfo("About to post insight ". Utils::varDumpToString($insight),
                                        __METHOD__.','.__LINE__);
                                    self::postInsight($insight, $options);
                                    $total_posted++;
                                    break;
                                }
                            }
                        }

                        if ($total_posted > 0) {
                            // Update $last_post_completion in plugin settings
                            if (isset($options['last_post_completion']->id)) {
                                //update option
                                $result = $plugin_option_dao->updateOption($options['last_post_completion']->id,
                                    'last_post_completion', date('Y-m-d H:i:s'));
                                $logger->logInfo("Updated ".$result." option", __METHOD__.','.__LINE__);
                            } else {
                                //insert option
                                $plugin_dao = DAOFactory::getDAO('PluginDAO');
                                $plugin_id = $plugin_dao->getPluginId('insightsposter');
                                $result = $plugin_option_dao->insertOption($plugin_id, 'last_post_completion',
                                    date('Y-m-d H:i:s'));
                                $logger->logInfo("Inserted option ID ".$result, __METHOD__.','.__LINE__);
                            }
                        }
                    }
                    if ($total_posted > 0) {
                        $logger->logUserSuccess("Posted ".$total_posted." insight".(($total_posted == 1)?'':'s'),
                            __METHOD__.','.__LINE__);
                    } else {
                        $logger->logInfo("No insights to post", __METHOD__.','.__LINE__);
                    }
                } else {
                    $logger->logInfo("Insight has been posted already today", __METHOD__.','.__LINE__);
                }
            } else {
                $logger->logInfo("It's too early in the am for posting insights", __METHOD__.','.__LINE__);
            }
        } else {
            $logger->logInfo("Insights poster is not configured", __METHOD__.','.__LINE__);
        }
    }

    public function getDashboardMenuItems($instance) {

    }

    public function getPostDetailMenuItems($post) {

    }
    public function renderInstanceConfiguration($owner, $instance_username, $instance_network) {
        return "";
    }
    /**
     * Should this insight get posted to Twitter? It only should if:
     * - The insight network is Twitter
     * - The instance is public
     * - The emphasis is high enough
     * - The insight is not on the blacklist
     * - The instance Twitter user has more than 1k followers
     *
     * @param  Insight $insight
     * @param  int $emphasis Either Insight::EMPHASIS_HIGH or Insight::EMPHASIS_MED
     * @return bool
     */
    public function shouldPostInsight($insight, $emphasis) {
        if ($insight->instance->network == "twitter" && $insight->instance->is_public == 1
            && $insight->emphasis == $emphasis && !in_array($insight->filename, $this->filename_blacklist)
            && !$this->isOnSlugBlacklist($insight)) {
            if (!isset($this->twitter_users[$insight->instance->network_username])) {
                $user_dao = DAOFactory::getDAO('UserDAO');
                $this->twitter_users[$insight->instance->network_username] =
                    $user_dao->getUserByName($insight->instance->network_username, 'twitter');
            }
            if ($this->twitter_users[$insight->instance->network_username]->follower_count > 1000) {
                return true;
            } else {
                $logger = Logger::getInstance();
                $logger->logUserInfo( $this->twitter_users[$insight->instance->network_username]->follower_count.
                    " under follower count threshold", __METHOD__.','.__LINE__);
                return false;
            }
        }
        return false;
    }
    /**
     * Is this insight on the slug blacklist? Does the insight's slug start with any on the blacklist?
     * @param  Insight  $insight
     * @return bool
     */
    public function isOnSlugBlacklist($insight) {
        foreach ($this->slug_blacklist as $blacklisted_slug) {
            if (0 === strpos($insight->slug, $blacklisted_slug)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Post insight headline and link to Twitter as an @reply to the user it applies to, with headline shortened to
     * fit in 140 characters.
     * @param  Insight $insight
     * @param  arr $options
     * @return void
     */
    private function postInsight($insight, $options) {
        $tweet = $this->getTweetToPost($insight);
        $logger = Logger::getInstance();
        $logger->logUserInfo("Posting the following tweet: ".$tweet, __METHOD__.','.__LINE__);
        $endpoint = new TwitterAPIEndpoint("/statuses/update");
        $api = new PosterTwitterAPIAccessorOAuth($options["oauth_access_token"]->option_value,
            $options["oauth_access_token_secret"]->option_value,
            $options["consumer_key"]->option_value, $options["consumer_secret"]->option_value, $insight->instance,
            2300, 2);
        $result = $api->apiPostRequest( $endpoint, array('status'=>$tweet));
        $logger->logUserInfo(Utils::varDumpToString($result), __METHOD__.','.__LINE__);
        $logger->logUserInfo("Tweet posted ", __METHOD__.','.__LINE__);
    }
    /**
     * Get insight tweet consisting of the insight headline and link as an @reply to the user
     * it applies to, with headline shortened to fit in 140 characters.
     * @param  Insight $insight
     * @return str
     */
    public function getTweetToPost($insight) {
        $terms = new InsightTerms($insight->instance->network);
        $headline = strip_tags(html_entity_decode($insight->headline));
        $headline = $terms->swapInSecondPerson($insight->instance->network_username, $headline);

        $tweet = '@'.$insight->instance->network_username." ";
        $insight_date = urlencode(date('Y-m-d', strtotime($insight->date)));
        $url = Utils::getApplicationURL()."?u=".$insight->instance->network_username."&n=".
            $insight->instance->network."&d=".$insight_date."&s=". $insight->slug;

        //If the headline is too lengthy, shorten it and add an ellipse
        // https t.co links are now 23 characters long
        // https://api.twitter.com/1.1/help/configuration.json
        $available_chars_in_tweet = 140 - (strlen($tweet) + 23 /*strlen($url)*/);
        if (strlen($headline) > $available_chars_in_tweet) {
            $headline_size = 140 - (strlen($tweet) + 23 /* strlen($url)*/) - 4;
            $headline = substr($headline, 0, $headline_size);
            $tweet = $tweet . trim($headline) . "... " . $url;
        } else {
            $tweet = $tweet . $headline . " ". $url;
        }
        return $tweet;
    }
}
