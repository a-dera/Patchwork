<?php /*********************************************************************
 *
 *   Copyright : (C) 2007 Nicolas Grekas. All rights reserved.
 *   Email     : p@tchwork.org
 *   License   : http://www.gnu.org/licenses/gpl.txt GNU/GPL
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 3 of the License, or
 *   (at your option) any later version.
 *
 ***************************************************************************/


class extends adapter_convertTo_abstract
{
	function file($file)
	{
		$file = escapeshellarg($file);
		$file = `w3m -dump -cols 80 -T text/html -I UTF-8 -O UTF-8 {$file}`;

		if (false !== strpos($file, '━')) $file = str_replace('━', '_', $file);

		return VALIDATE::get($file, 'text');
	}

}
