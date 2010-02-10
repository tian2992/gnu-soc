<?php
/*
StatusNet Plugin: 0.9
Plugin Name: FeedSub
Plugin URI: http://status.net/wiki/Feed_subscription
Description: FeedSub allows subscribing to real-time updates from external feeds supporting PubHubSubbub protocol.
Version: 0.1
Author: Brion Vibber <brion@status.net>
Author URI: http://status.net/
*/

/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * @package FeedSubPlugin
 * @maintainer Brion Vibber <brion@status.net>
 */

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

define('FEEDSUB_SERVICE', 100); // fixme -- avoid hardcoding these?

// We bundle the XML_Parse_Feed library...
set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . '/extlib');

class FeedSubException extends Exception
{
}

class OStatusPlugin extends Plugin
{
    /**
     * Hook for RouterInitialized event.
     *
     * @param Net_URL_Mapper $m path-to-action mapper
     * @return boolean hook return
     */
    function onRouterInitialized($m)
    {
        // Discovery actions
        $m->connect('.well-known/host-meta',
                    array('action' => 'hostmeta'));
        $m->connect('main/webfinger',
                    array('action' => 'webfinger'));
        $m->connect('main/ostatus',
                    array('action' => 'ostatusinit'));
        $m->connect('main/ostatus?nickname=:nickname',
                  array('action' => 'ostatusinit'), array('nickname' => '[A-Za-z0-9_-]+'));
        $m->connect('main/ostatussub',
                    array('action' => 'ostatussub'));          
        $m->connect('main/ostatussub',
                    array('action' => 'ostatussub'), array('feed' => '[A-Za-z0-9\.\/\:]+'));          

        // PuSH actions
        $m->connect('main/push/hub', array('action' => 'pushhub'));

        $m->connect('main/push/callback/:feed',
                    array('action' => 'pushcallback'),
                    array('feed' => '[0-9]+'));
        $m->connect('settings/feedsub',
                    array('action' => 'feedsubsettings'));

        // Salmon endpoint
        $m->connect('main/salmon/user/:id',
                    array('action' => 'salmon'),
                    array('id' => '[0-9]+'));
        return true;
    }

    /**
     * Set up queue handlers for outgoing hub pushes
     * @param QueueManager $qm
     * @return boolean hook return
     */
    function onEndInitializeQueueManager(QueueManager $qm)
    {
        $qm->connect('hubverify', 'HubVerifyQueueHandler');
        $qm->connect('hubdistrib', 'HubDistribQueueHandler');
        $qm->connect('hubout', 'HubOutQueueHandler');
        return true;
    }

    /**
     * Put saved notices into the queue for pubsub distribution.
     */
    function onStartEnqueueNotice($notice, &$transports)
    {
        $transports[] = 'hubdistrib';
        return true;
    }

    /**
     * Set up a PuSH hub link to our internal link for canonical timeline
     * Atom feeds for users and groups.
     */
    function onStartApiAtom(Action $action)
    {
        if ($action instanceof ApiTimelineUserAction || $action instanceof ApiTimelineGroupAction) {
            $id = $action->arg('id');
            if (strval(intval($id)) === strval($id)) {
                // Canonical form of id in URL?
                // Updates will be handled for our internal PuSH hub.
                $action->element('link', array('rel' => 'hub',
                                               'href' => common_local_url('pushhub')));

                // Also, we'll add in the salmon link
                $action->element('link', array('rel' => 'salmon',
                                               'href' => common_local_url('salmon')));
            }
        }
        return true;
    }
    
    /**
     * Add the feed settings page to the Connect Settings menu
     *
     * @param Action &$action The calling page
     *
     * @return boolean hook return
     */
    function onEndConnectSettingsNav(&$action)
    {
        $action_name = $action->trimmed('action');

        $action->menuItem(common_local_url('feedsubsettings'),
                          _m('Feeds'),
                          _m('Feed subscription options'),
                          $action_name === 'feedsubsettings');

        return true;
    }

    /**
     * Automatically load the actions and libraries used by the plugin
     *
     * @param Class $cls the class
     *
     * @return boolean hook return
     *
     */
    function onAutoload($cls)
    {
        $base = dirname(__FILE__);
        $lower = strtolower($cls);
        $files = array("$base/classes/$cls.php",
                       "$base/lib/$lower.php");
        if (substr($lower, -6) == 'action') {
            $files[] = "$base/actions/" . substr($lower, 0, -6) . ".php";
        }
        foreach ($files as $file) {
            if (file_exists($file)) {
                include_once $file;
                return false;
            }
        }
        return true;
    }

    /**
     * Add in an OStatus subscribe button
     */
    function onStartProfilePageActionsElements($output, $profile)
    {
        $cur = common_current_user();

        if (empty($cur)) {
            // Add an OStatus subscribe
            $output->elementStart('li', 'entity_subscribe');
            $url = common_local_url('ostatusinit',
                                    array('nickname' => $profile->nickname));
            $output->element('a', array('href' => $url,
                                        'class' => 'entity_remote_subscribe'),
                                _('OStatus'));
            
            $output->elementEnd('li');
        }
    }

    /**
     * Check if we've got some Salmon stuff to send
     */
    function onEndNoticeSave($notice)
    {
        $count = preg_match_all('/(\w+\.)*\w+@(\w+\.)*\w+(\w+\-\w+)*\.\w+/', $notice->content, $matches);
        if ($count) {
            foreach ($matches[0] as $webfinger) {
                // Check to see if we've got an actual webfinger
                $w = new Webfinger;

                $endpoint_uri = '';
                
                $result = $w->lookup($webfinger);
                if (empty($result)) {
                    continue;
                }
                
                foreach ($result->links as $link) {
                    if ($link['rel'] == 'salmon') {
                        $endpoint_uri = $link['href'];
                    }
                }
                
                if (empty($endpoint_uri)) {
                    continue;
                }

                $xml = '<?xml version="1.0" encoding="UTF-8" ?>';
                $xml .= $notice->asAtomEntry();
               
                $salmon = new Salmon();
                $salmon->post($endpoint_uri, $xml);
            }
        }
    }
    
    
    function onCheckSchema() {
        // warning: the autoincrement doesn't seem to set.
        // alter table feedinfo change column id id int(11) not null  auto_increment;
        $schema = Schema::get();
        $schema->ensureTable('feedinfo', Feedinfo::schemaDef());
        $schema->ensureTable('hubsub', HubSub::schemaDef());
        return true;
    } 
}