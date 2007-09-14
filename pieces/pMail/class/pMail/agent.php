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


class extends pMail_mime
{
	protected

	$agent,
	$args,
	$lang;


	static protected $imageCache = array();


	function __construct($agent, $args = array(), $options = null)
	{
		$this->agent = $agent;
		$this->args = (array) $args;
		$this->lang = isset($options['lang']) ? $options['lang'] : p::__LANG__();

		parent::__construct($options);
	}

	protected function doSend()
	{
		$html = patchwork_serverside::returnAgent($this->agent, $this->args, $this->lang);

		if (!isset($this->_headers['Subject']) && preg_match("'<title[^>]*>(.*?)</title[^>]*>'isu", $html, $title))
		{
			$this->headers(array('Subject' => trim(html_entity_decode($title[1], ENT_QUOTES, 'UTF-8'))));
		}

		$html = preg_replace_callback('/(\s)(src|background)\s*=\s*(["\'])?((?(3)(?:[^\3]*)|[^\s>]*)\.(jpe?g|png|gif))(?(3)\3)/iu', array($this, 'addRawImage'), $html);

		$this->setHTMLBody($html);
		$this->setTXTBody( CONVERT::data($html, 'html', 'txt') );

		parent::doSend();
	}

	protected function addRawImage($match)
	{
		$url = p::base($match[4], true);

		if (isset(self::$imageCache[$url])) $data =& self::$imageCache[$url];
		else
		{
			if (ini_get('allow_url_fopen')) $data = file_get_contents($url);
			else
			{
				$data = new HTTP_Request($url);
				$data->sendRequest();
				$data = $data->getResponseBody();
			}

			self::$imageCache[$url] =& $data;
		}

		switch (strtolower($match[5]))
		{
			case 'png': $mime = 'image/png'; break;
			case 'gif': $mime = 'image/gif'; break;
			default: $mime = 'image/jpeg';
		}

		$this->addHtmlImage($data, $mime, $match[4], false);

		$a =& $this->_html_images[ count($this->_html_images) - 1 ];

		return $match[1] . $match[2] . '=cid:' . $a['cid'];
	}
}