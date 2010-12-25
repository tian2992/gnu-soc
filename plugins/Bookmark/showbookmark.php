<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Show a single bookmark
 * 
 * PHP version 5
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
 *
 * @category  Bookmark
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Show a single bookmark, with associated information
 *
 * @category  Bookmark
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class ShowbookmarkAction extends ShownoticeAction
{
    protected $bookmark = null;

    /**
     * For initializing members of the class.
     *
     * @param array $argarray misc. arguments
     *
     * @return boolean true
     */

    function prepare($argarray)
    {
        OwnerDesignAction::prepare($argarray);

        $this->user = User::staticGet('id', $this->trimmed('user'));

        if (empty($this->user)) {
            throw new ClientException(_('No such user.'), 404);
        }

        $this->profile = $this->user->getProfile();

        if (empty($this->profile)) {
            throw new ServerException(_('User without a profile.'));
        }

        $this->avatar = $this->profile->getAvatar(AVATAR_PROFILE_SIZE);

        $crc32 = pack("H*", $this->trimmed('crc32'));

        if (empty($crc32)) {
            throw new ClientException(_('No such URL.'), 404);
        }
        
        $dt = DateTime::createFromFormat(DateTime::W3C,
                                         $this->trimmed('created'),
                                         new DateTimeZone('UTC'));

        if (empty($dt)) {
            throw new ClientException(_('No such create date.'), 404);
        }

        $bookmarks = Bookmark::getByCRC32($this->profile,
                                                 $this->crc32);

        foreach ($bookmarks as $bookmark) {
            $bdt = new DateTime($bookmark->created);
            if ($bdt->getTimestamp() == $dt->getTimestamp()) {
                $this->bookmark = $bookmark;
                break;
            }
        } 

        if (empty($this->bookmark)) {
            throw new ClientException(_('No such bookmark.'), 404);
        }

        $this->notice = Notice::staticGet('uri', $this->bookmark->uri);

        if (empty($this->notice)) {
            // Did we used to have it, and it got deleted?
            throw new ClientException(_('No such bookmark.'), 404);
        }

        return true;
    }
}
