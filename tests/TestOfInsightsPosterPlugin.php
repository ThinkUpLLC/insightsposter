<?php
/**
 *
 * webapp/plugins/pushover/tests/TestOfInsightsPosterPlugin.php
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
 * Test Of InsightsPosterPlugin
 *
 * Copyright (c) 2014 Gina Trapani
 *
 * @author Gina Trapani <ginatrapani [at] gmail [dot] com>
 * @license http://www.gnu.org/licenses/gpl.html
 * @copyright 2014 Gina Trapani
 */

require_once 'tests/init.tests.php';
require_once THINKUP_ROOT_PATH.'webapp/_lib/extlib/simpletest/autorun.php';
require_once THINKUP_ROOT_PATH.'webapp/config.inc.php';
require_once THINKUP_ROOT_PATH.'tests/classes/class.ThinkUpBasicUnitTestCase.php';
require_once THINKUP_ROOT_PATH. 'webapp/plugins/insightsposter/model/class.InsightsPosterPlugin.php';
require_once THINKUP_ROOT_PATH. 'webapp/plugins/twitter/model/class.TwitterAPIAccessorOAuth.php';
require_once THINKUP_ROOT_PATH. 'webapp/plugins/twitter/model/class.TwitterOAuthThinkUp.php';
require_once THINKUP_ROOT_PATH. 'webapp/plugins/twitter/model/class.TwitterAPIEndpoint.php';
require_once THINKUP_ROOT_PATH. 'webapp/plugins/insightsposter/model/class.PosterTwitterAPIAccessorOAuth.php';

class TestOfInsightsPosterPlugin extends ThinkUpUnitTestCase {

    public function setUp(){
        parent::setUp();
        $webapp_plugin_registrar = PluginRegistrarWebapp::getInstance();
        $webapp_plugin_registrar->registerPlugin('Insights Poster', 'InsightsPosterPlugin');
        $webapp_plugin_registrar->setActivePlugin('Insights Poster');
    }

    public function tearDown() {
        parent::tearDown();
    }

    public function testConstructor() {
        $plugin = new InsightsPosterPlugin();
        $this->assertNotNull($plugin);
        $this->assertIsA($plugin, 'InsightsPosterPlugin');
    }

    public function testGetTweetToPost() {
        $builders = array();
        $builders[] = FixtureBuilder::build('users', array('network_user_id'=>'930061', 'network'=>'twitter',
            'follower_count'=>1050));
        $instance = new Instance();
        $instance->network = "twitter";
        $instance->network_username = 'testifer';
        $instance->network_user_id = '930061';

        $insight = new Insight();
        $insight->date = "2014-09-30";
        $insight->slug = 'testinsight';
        $insight->instance = $instance;
        $insight->headline ="322,650 more people saw @helenhousandi's tweet thanks to you that's a really long ".
            "headline";

        $plugin = new InsightsPosterPlugin();
        $tweet = $plugin->getTweetToPost($insight);
        $this->assertTrue(strlen($tweet) <= 140);
        $this->debug($tweet);
    }

    public function testAPI() {
        $options = array();
        $options["oauth_token"] = '123';
        $options["oauth_token_secret"] = '456';
        $options["consumer_key"] = 'abc';
        $options["consumer_secret"] = 'abc';
        $insight = new Insight();
        $insight->instance = new Instance();
        $api = new PosterTwitterAPIAccessorOAuth( $options["oauth_token"], $options["oauth_token_secret"],
        $options["consumer_key"], $options["consumer_secret"], $insight->instance, 2300, 2);
    }
}
