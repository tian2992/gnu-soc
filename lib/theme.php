<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Utilities for theme files and paths
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
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
 * @category  Paths
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Class for querying and manipulating a theme
 *
 * Themes are directories with some expected sub-directories and files
 * in them. They're found in either local/theme (for locally-installed themes)
 * or theme/ subdir of installation dir.
 *
 * This used to be a couple of functions, but for various reasons it's nice
 * to have a class instead.
 *
 * @category Output
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class Theme
{
    var $dir  = null;
    var $path = null;

    /**
     * Constructor
     *
     * Determines the proper directory and path for this theme.
     *
     * @param string $name Name of the theme; defaults to config value
     */

    function __construct($name=null)
    {
        if (empty($name)) {
            $name = common_config('site', 'theme');
        }

        // Check to see if it's in the local dir

        $localroot = INSTALLDIR.'/local/theme';

        $fulldir = $localroot.'/'.$name;

        if (file_exists($fulldir) && is_dir($fulldir)) {
            $this->dir  = $fulldir;
            $this->path = common_path('local/theme/'.$name.'/');
            return;
        }

        // Check to see if it's in the distribution dir

        $instroot = common_config('theme', 'dir');

        if (empty($instroot)) {
            $instroot = INSTALLDIR.'/theme';
        }

        $fulldir = $instroot.'/'.$name;

        if (file_exists($fulldir) && is_dir($fulldir)) {

            $this->dir = $fulldir;

            $path = common_config('theme', 'path');

            if (empty($path)) {
                $path = common_config('site', 'path') . '/theme/';
            }

            if ($path[strlen($path)-1] != '/') {
                $path .= '/';
            }

            if ($path[0] != '/') {
                $path = '/'.$path;
            }

            $server = common_config('theme', 'server');

            if (empty($server)) {
                $server = common_config('site', 'server');
            }

            // XXX: protocol

            $this->path = 'http://'.$server.$path.$name;
        }
    }

    /**
     * Gets the full local filename of a file in this theme.
     *
     * @param string $relative relative name, like 'logo.png'
     *
     * @return string full pathname, like /var/www/mublog/theme/default/logo.png
     */

    function getFile($relative)
    {
        return $this->dir.'/'.$relative;
    }

    /**
     * Gets the full HTTP url of a file in this theme
     *
     * @param string $relative relative name, like 'logo.png'
     *
     * @return string full URL, like 'http://example.com/theme/default/logo.png'
     */

    function getPath($relative)
    {
        return $this->path.'/'.$relative;
    }

    /**
     * Gets the full path of a file in a theme dir based on its relative name
     *
     * @param string $relative relative path within the theme directory
     * @param string $name     name of the theme; defaults to current theme
     *
     * @return string File path to the theme file
     */

    static function file($relative, $name=null)
    {
        $theme = new Theme($name);
        return $theme->getFile($relative);
    }

    /**
     * Gets the full URL of a file in a theme dir based on its relative name
     *
     * @param string $relative relative path within the theme directory
     * @param string $name     name of the theme; defaults to current theme
     *
     * @return string URL of the file
     */

    static function path($relative, $name=null)
    {
        $theme = new Theme($name);
        return $theme->getPath($relative);
    }
}
