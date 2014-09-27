<?php
/**
 *
 * ThinkUp/webapp/plugins/twitter/model/class.PosterTwitterAPIAccessorOAuth.php
 *
 * Copyright (c) 2009-2014 Gina Trapani
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
 * Poster Twitter API Accessor, via OAuth
 *
 * @license http://www.gnu.org/licenses/gpl.html
 * @copyright 2009-2014 Gina Trapani
 * @author Gina Trapani <ginatrapani[at]gmail[dot]com>
 */
class PosterTwitterAPIAccessorOAuth extends TwitterAPIAccessorOAuth {
    /**
     * Maxiumum percent of available API calls that should be used for endpoint.
     * @var int
     */
    var $percent_use_ceiling = 90;
    /**
     * Constructor
     * @param str $oauth_token
     * @param str $oauth_token_secret
     * @param str $oauth_consumer_key
     * @param str $oauth_consumer_secret
     * @param Instance $instance
     * @param int $archive_limit
     * @param int $num_twitter_errors
     * @return PosterTwitterAPIAccessorOAuth
     */
    public function __construct($oauth_access_token, $oauth_access_token_secret, $oauth_consumer_key,
        $oauth_consumer_secret, $num_twitter_errors, $log=true) {
        parent::__construct($oauth_access_token, $oauth_access_token_secret, $oauth_consumer_key,
            $oauth_consumer_secret, $num_twitter_errors, $log=true);
    }
    /**
     * Make a Twitter API POST request.
     * @param TwitterAPIEndpoint $endpoint
     * @param arr $args URL query string parameters
     * @param str $id ID for use in endpoint path
     * @param bool $suppress_404_error Defaults to false, don't log 404 errors from deleted tweets
     * @return arr HTTP status code, payload
     */
    public function apiPostRequest(TwitterAPIEndpoint $endpoint, $args=array(), $id=null, $suppress_404_error=false) {
        $url = $endpoint->getPathWithID($id);
        echo $url;
        $content = $this->to->OAuthRequest($url, 'POST', $args);
        $status = $this->to->lastStatusCode();
        return array($status, $content);
    }
}
