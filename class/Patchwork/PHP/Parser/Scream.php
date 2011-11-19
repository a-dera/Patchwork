<?php /****************** vi: set fenc=utf-8 ts=4 sw=4 et: *****************
 *
 *   Copyright : (C) 2011 Nicolas Grekas. All rights reserved.
 *   Email     : p@tchwork.org
 *   License   : http://www.gnu.org/licenses/lgpl.txt GNU/LGPL
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU Lesser General Public License as
 *   published by the Free Software Foundation, either version 3 of the
 *   License, or (at your option) any later version.
 *
 ***************************************************************************/

/**
 * The Scream parser removes shutdown operators, making the code scream on otherwise silenced errors.
 */
class Patchwork_PHP_Parser_Scream extends Patchwork_PHP_Parser
{
    protected $callbacks = array('cancelToken' => '@');

    protected function cancelToken(&$token)
    {
        return false;
    }
}
