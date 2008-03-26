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


class __patchwork_autoloader
{
	static

	$cache,
	$pool;


	static function autoload($req)
	{
		if (strspn($req, ";'")) return;

		$T = PATCHWORK_PATH_TOKEN;
		$lc_req = lowerascii($req);

		$amark = $GLOBALS['a'.$T];
		$GLOBALS['a'.$T] = false;
		$bmark = $GLOBALS['b'.$T];


		// Step 1 - Get basic info

		$i = strrpos($req, '__');
		$level = false !== $i ? substr($req, $i+2) : false;
		$isTop = false === $level || '' === $level || 0 !== strcspn($level, '0123456789');

		if ($isTop)
		{
			// Top class
			$top = $req;
			$lc_top = $lc_req;
			$level = PATCHWORK_PATH_LEVEL;
		}
		else
		{
			// Preprocessor renammed class
			$top = substr($req, 0, $i);
			$lc_top = substr($lc_req, 0, $i);
			$level = min(PATCHWORK_PATH_LEVEL, '00' === $level ? -1 : (int) $level);
		}


		// Step 2 - Get source file

		$src = false;

		if ($customSrc =& $GLOBALS['patchwork_autoload_prefix'] && $a = strlen($lc_top))
		{
			// Look for a registered prefix autoloader

			$i = 0;
			$cache = array();

			do
			{
				$code = ord($lc_top[$i]);
				if (isset($customSrc[$code]))
				{
					$customSrc =& $customSrc[$code];
					isset($customSrc[-1]) && $cache[] = $customSrc[-1];
				}
				else break;
			}
			while (++$i < $a);

			if ($cache) do
			{
				$src = array_pop($cache);
				$src = $i < $a || !is_string($src) || function_exists($src) ? call_user_func($src, $top) : $src;
			}
			while (!$src && $cache);
		}

		unset($customSrc);

		if ($customSrc = (bool) $src) {}
		else if ('_' !== substr($top, -1) && strncmp('_', $top, 1) && false === strpos($top, '__'))
		{
			$src = 'class/' . strtr($top, '_', '/') . '.php';
		}

		if ($src)
		{
			$src = resolvePath($src, $level, 0);
			$src && $a = $GLOBALS['patchwork_lastpath_level'];
		}


		// Step 3 - Get parent class

		$src || $a = -1;
		$isTop && ++$level;

		if ($level > $a)
		{
			do $parent = $top . '__' . (0 <= --$level ? $level : '00');
			while (!($parent_exists = class_exists($parent, false)) && $level > $a);
		}
		else
		{
			$parent = 0 <= $level ? $top . '__' . (0 < $level ? $level - 1 : '00') : false;
			$parent_exists = false;
		}


		// Step 4 - Load class definition

		$cache = false;

		if (!$src) {}
		else if ('patchwork_preprocessor' === $lc_top) patchwork_include($src);
		else if (!$parent_exists)
		{
			$cache = DEBUG . (0>$level ? -$level . '-' : $level);
			$cache = PATCHWORK_PROJECT_PATH . ".class_{$top}.php.{$cache}.{$T}.zcache.php";

			if (!(file_exists($cache) && (TURBO || filemtime($cache) > filemtime($src))))
				patchwork_preprocessor::run($src, $cache, $level, $top);

			$current_pool = array();
			$parent_pool =& self::$pool;
			self::$pool =& $current_pool;

			patchwork_include($cache);

			if ($parent && class_exists($req, false)) $parent = false;
			if (false !== $parent_pool) $parent_pool[$parent ? $parent : $req] = $cache;
		}


		// Step 5 - Finalize class loading

		$code = '';

		if ($parent ? class_exists($parent) : (class_exists($req, false) && !isset(self::$cache[$lc_req])))
		{
			if ($parent)
			{
				$code = "class {$req} extends {$parent}{}\$GLOBALS['c{$T}']['{$lc_req}']=1;";
				$parent = lowerascii($parent);

				if (isset($GLOBALS['patchwork_abstract'][$parent]))
				{
					$code = 'abstract ' . $code;
					$GLOBALS['patchwork_abstract'][$lc_req] = 1;
				}
			}
			else $parent = $lc_req;

			if ($isTop)
			{
				$a = "{$parent}::__cS{$T}";
				if (defined($a) ? $req === constant($a) : method_exists($parent, '__constructStatic'))
				{
					$code .= "{$parent}::__constructStatic();";
				}

				$a = "{$parent}::__dS{$T}";
				if (defined($a) ? $req === constant($a) : method_exists($parent, '__destructStatic'))
				{
					$code = str_replace('{}', "{static \$hunter{$T};}", $code);
					$code .= "{$req}::\$hunter{$T}=new hunter(array('{$parent}','__destructStatic'));";
				}
			}

			$code && eval($code);
		}

		if (!TURBO) return;

		if ($code && isset(self::$cache[$parent]))
		{
			// Include class declaration in its closest parent

			list($src, $marker, $a) = self::parseMarker(self::$cache[$parent], "\$GLOBALS['c{$T}']['{$parent}']=%marker%;");

			if (false !== $a)
			{
				if (!$isTop)
				{
					$i = (string) mt_rand(1, mt_getrandmax());
					self::$cache[$parent] = $src . '*' . $i;
					$code .= substr($marker, 0, strrpos($marker, '*') + 1) . $i . "';";
				}

				$a = str_replace($marker, $code, $a);
				($cache === $src && $current_pool) || self::write($a, $src);
			}
		}
		else $a = false;

		if ($cache)
		{
			self::$pool =& $parent_pool;

			if ($current_pool)
			{
				// Add an include directive of parent's code in the derivated class

				$code = '<?php ?>';
				$a || $a = file_get_contents($cache);
				if ('<?php ' != substr($a, 0, 6)) $a = '<?php ?>' . $a;

				$i = '/^' . preg_replace('/__[0-9]+$/', '', $lc_req) . '__[0-9]+$/i';

				foreach ($current_pool as $parent => $src)
				{
					$parent = preg_match($i, $parent) ? '' : "class_exists('{$parent}',0)||";
					$code = substr($code, 0, -2) . $parent . "include '{$src}';?>";
				}

				$a = substr($code, 0, -2) . substr($a, 6);
				self::write($a, $cache);
			}

			$cache = substr($cache, strlen(PATCHWORK_PROJECT_PATH) + 7, -12-strlen($T));

			if ($amark)
			{
				// Marker substitution

				list($src, $marker, $a) = self::parseMarker($amark, "\$a{$T}=%marker%");

				if (false !== $a)
				{
					if ($amark != $bmark)
					{
						$GLOBALS['a'.$T] = $bmark;
						$marker = "isset(\$c{$T}['{$lc_req}'])||{$marker}";
						$code = "isset(\$c{$T}['{$lc_req}'])||patchwork_include('./.class_{$cache}.{$T}.zcache.php')||1";
					}
					else
					{
						$marker = "\$e{$T}=\$b{$T}={$marker}";
						$i = (string) mt_rand(1, mt_getrandmax());
						$GLOBALS['a'.$T] = $GLOBALS['b'.$T] = $src . '*' . $i;
						$i = substr($marker, 0, strrpos($marker, '*') + 1) . $i . "'";
						$marker = "({$marker})&&\$d{$T}&&";
						$code = $customSrc ? "'{$cache}'" : ($level + PATCHWORK_PATH_OFFSET);
						$code = "\$c{$T}['{$lc_req}']={$code}";
						$code = "({$i})&&\$d{$T}&&({$code})&&";
					}

					$a = str_replace($marker, $code, $a);
					self::write($a, $src);
				}
			}
		}
	}

	protected static function parseMarker($marker, $template)
	{
		$a = strrpos($marker, '*');
		$src = substr($marker, 0, $a);
		$marker = substr($marker, $a);
		$marker = str_replace('%marker%', "__FILE__.'{$marker}'", $template);

		if ($a = @file_get_contents($src)) false === strpos($a, $marker) && $a = false;

		return array($src, $marker, &$a);
	}

	protected static function write(&$data, $to)
	{
		$a = PATCHWORK_PROJECT_PATH . uniqid(mt_rand(), true);
		if (false !== file_put_contents($a, $data))
		{
			touch($a, filemtime($to));

			if (IS_WINDOWS)
			{
				$h = new COM('Scripting.FileSystemObject');
				$h->GetFile($a)->Attributes |= 2; // Set hidden attribute
				file_exists($to) && @unlink($to);
				@rename($a, $to) || unlink($a);
			}
			else rename($a, $to);
		}
	}
}

__patchwork_autoloader::$cache =& $GLOBALS['patchwork_autoload_cache'];