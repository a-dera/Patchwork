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


class extends self
{
	// The original Mail_mime->_encodeHeaders() is bugged !

	function _encodeHeaders($input)
	{
		static $ns = "[^\(\)<>@,;:\"\/\[\]\r\n]*";

		foreach ($input as &$hdr_value)
		{
			$this->optimizeCharset($hdr_value, 'head');
			$hdr_value = preg_replace("/[\r\n](?!\s)/", '$0 ', $hdr_value); // Header injection protection
			$hdr_value = str_replace('=?', "={$this->_eol} ?", $hdr_value); // Encoded string injection protection
			$hdr_value = preg_replace_callback(
				"/{$ns}(?:[\\x80-\\xFF]{$ns})+/",
				array($this, '_encodeHeaderWord'),
				$hdr_value
			);
		}

		return $input;
	}

	protected function _encodeHeaderWord($word)
	{
		$pref = array(
			'scheme'           => 'B',
			'input-charset'    => $this->_build_params['head_charset'],
			'output-charset'   => $this->_build_params['head_charset'],
			'line-break-chars' => $this->_eol,
		);

		preg_match('/^( *)(.*?)( *)$/sD', $word[0], $word);

		$B = iconv_mime_encode('', $word[2], $pref);

		if ('quoted-printable' === $this->_build_params['head_encoding'])
		{
			$pref['scheme'] = 'Q';
			if (false !== $Q = @iconv_mime_encode('', $word[2], $pref))
			{
				$Q = str_replace('=20', '_', $Q);
				strlen($Q) <= strlen($B) && $B =& $Q;
			}
		}

		return $word[1] . substr($B, 2) . $word[3];
	}


	// Add line feeds correction

	function &_addTextPart(&$obj, $text)
	{
		$this->_fixEOL($text);
		$this->optimizeCharset($text, 'text');
		$text =& parent::_addTextPart($obj, $text);
		return $text;
	}

	function &_addHtmlPart(&$obj)
	{
		if (1 < func_num_args()) $text = func_get_arg(1);
		else $text =& $this->_htmlbody;

		foreach ($this->_html_images as $k => &$v)
		{
			$k = str_replace('%40', '@', $v['cid']);
			$text = str_replace($v['cid'], $k, $text);
			$v['cid'] = $k;
		}

		$this->_fixEOL($text);
		$this->optimizeCharset($text, 'html');
		$text =& parent::_addHtmlPart($obj, $text);
		return $text;
	}


	// Fix headers' case

	function &headers($xtra_headers = null, $overwrite = false)
	{
		empty($xtra_headers) || self::cleanHeaders($xtra_headers, 'Return-Path|Errors-To|From|To|Reply-To|Subject|Cc|Bcc');

		$xtra_headers =& parent::headers($xtra_headers, $overwrite);

		return $xtra_headers;
	}


	static function cleanHeaders(&$headers, $tpl)
	{
		$h = array();
		foreach ($headers as $k => $v) $h[strtolower($k)] = $k;

		foreach (explode('|', $tpl) as $v)
		{
			$k = strtolower(trim($v));
			if (isset($h[$k]) && $h[$k] !== $v)
			{
				$headers[$v] =& $headers[$h[$k]];
				unset($headers[$h[$k]]);
			}
		}
	}


	protected function _fixEOL(&$a)
	{
		false !== strpos($a, "\r") && $a = strtr(str_replace("\r\n", "\n", $a), "\r", "\n");
		"\n"  !== $this->_eol      && $a = str_replace("\n", $this->_eol, $a);
	}

	protected static $charsetCheck = array(
		'iso-8859-1'   => '1,iso8859-1,latin1',   // Western European
		'windows-1252' => '1,cp1252',             // Western European - more popular than iso-8859-15
		'iso-8859-15'  => '1,iso8859-15,latin9',  // Western European
		'iso-8859-2'   => '1,iso8859-2,latin2',   // Central European
		'iso-8859-3'   => '1,iso8859-3,latin3',   // South European
		'iso-8859-4'   => '1,iso8859-4,latin4',   // Baltic
		'iso-8859-10'  => '1,iso8859-10,latin6',  // Baltic
		'iso-8859-13'  => '1,iso8859-13,latin7',  // Baltic
		'koi8-r'       => '0,koi8',               // Cyrillic - more popular than iso-8859-5
		'iso-8859-5'   => '0,iso8859-5',          // Cyrillic
		'windows-1256' => '0,cp1256',             // Arabic - more popular than iso-8859-6
		'iso-8859-6'   => '0,iso8859-6',          // Arabic
		'iso-8859-7'   => '0,iso8859-7',          // Greek
		'windows-1255' => '0,cp1255',             // Hebrew-logical
		'iso-8859-8-i' => '0,iso8859-8-i',        // Hebrew-logical
		'iso-8859-8'   => '0,iso8859-8',          // Hebrew-visual
		'iso-8859-9'   => '1,iso8859-9,latin5',   // Turkish
		'tis-620'      =>  0,                     // Thai - national standard
		'iso-8859-11'  => '0,iso8859-11',         // Thai
		'iso-8859-14'  => '1,iso8859-14,latin8',  // Celtic
		'iso-8859-16'  => '1,iso8859-16,latin10', // South-Eastern European
		'windows-1258' => '1,cp1258',             // Vietnamese
		'viscii'       =>  1,                     // Vietnamese
		'iso-2022-jp'  =>  0,                     // Japanese
		'big5'         =>  0,                     // Chinese Traditional
	);

	protected function optimizeCharset(&$data, $type)
	{
		// In an ideal world, every email client would handle UTF-8...

		foreach (self::$charsetCheck as $charset => $enc)
		{
			$c = $charset;
			$a = @iconv('UTF-8', $c, $data);

			if (false === $a && is_string($c))
			{
				$c = explode(',', $c);
				unset($c[0]);
				foreach ($c as $c)
				{
					$b = @iconv('UTF-8', $c, $data);
					if (false !== $b)
					{
						$a = $b;
						break;
					}
				}
			}

			if (false !== $a && iconv($c, 'UTF-8', $a) === $data)
			{
				$data = $a;
				$enc = (int) $enc ? 'quoted-printable' : 'base64';

				$this->_build_params[$type . '_charset' ] = $charset;
				$this->_build_params[$type . '_encoding'] = $enc;

				return;
			}
		}

		$this->_build_params[$type . '_charset' ] = 'utf-8';
		$this->_build_params[$type . '_encoding'] = 'quoted-printable';
	}
}
