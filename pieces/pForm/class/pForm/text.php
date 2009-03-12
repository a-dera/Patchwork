<?php /*********************************************************************
 *
 *   Copyright : (C) 2007 Nicolas Grekas. All rights reserved.
 *   Email     : p@tchwork.org
 *   License   : http://www.gnu.org/licenses/agpl.txt GNU/AGPL
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU Affero General Public License as
 *   published by the Free Software Foundation, either version 3 of the
 *   License, or (at your option) any later version.
 *
 ***************************************************************************/


class extends pForm_hidden
{
	protected

	$type = 'text',
	$maxlength = 255;


	protected function init(&$param)
	{
		parent::init($param);
		if (isset($param['maxlength']) && $param['maxlength'] > 0) $this->maxlength = (int) $param['maxlength'];

		if (false !== strpos($this->value, "\r")) $this->value = strtr(str_replace("\r\n", "\n", $this->value), "\r", "\n");

		if (mb_strlen($this->value) > $this->maxlength) $this->value = mb_substr($this->value, 0, $this->maxlength);
	}

	protected function get()
	{
		$a = parent::get();
		if ($this->maxlength) $a->maxlength = $this->maxlength;
		return $a;
	}

	protected function addJsValidation($a)
	{
		$a->_valid = new loop_array(array_merge(array($this->valid), $this->validArgs));
		return $a;
	}
}
