<?php // vi: set fenc=utf-8 ts=4 sw=4 et:
/*
 * Copyright (C) 2012 Nicolas Grekas - p@tchwork.com
 *
 * This library is free software; you can redistribute it and/or modify it
 * under the terms of the (at your option):
 * Apache License v2.0 (http://apache.org/licenses/LICENSE-2.0.txt), or
 * GNU General Public License v2.0 (http://gnu.org/licenses/gpl-2.0.txt).
 */

namespace Patchwork\PHP\Shim;

/**
 * utf8_encode/decode enhanced to Windows-1252.
 */
class Xml
{
    protected static

    $cp1252 = array('','','','','','','','','','','','','','','','','','','','','','','','','','',''),
    $utf8   = array('€','‚','ƒ','„','…','†','‡','ˆ','‰','Š','‹','Œ','Ž','‘','’','“','”','•','–','—','˜','™','š','›','œ','ž','Ÿ');


    static function cp1252_to_utf8($s)
    {
/**/    if (extension_loaded('xml'))
            $s = utf8_encode($s);
/**/    else
            $s = self::utf8_encode($s);

        if (false === strpos($s, "\xC2")) return $s;
        else return str_replace(self::$cp1252, self::$utf8, $s);
    }

    static function utf8_to_cp1252($s)
    {
        $s = str_replace(self::$utf8, self::$cp1252, $s);

/**/    if (extension_loaded('xml'))
            return utf8_decode($s);
/**/    else
            return self::utf8_decode($s);
    }

/**/if (!extension_loaded('xml')):

    static function utf8_encode($s)
    {
        $len = strlen($s);
        $e = $s . $s;

        for ($i = 0, $j = 0; $i < $len; ++$i, ++$j) switch (true)
        {
        case $s[$i] < "\x80": $e[$j] = $s[$i]; break;
        case $s[$i] < "\xC0": $e[$j] = "\xC2"; $e[++$j] = $s[$i]; break;
        default:              $e[$j] = "\xC3"; $e[++$j] = chr(ord($s[$i]) - 64); break;
        }

        return substr($e, 0, $j);
    }

    static function utf8_decode($s)
    {
        $len = strlen($s);

        for ($i = 0, $j = 0; $i < $len; ++$i, ++$j)
        {
            switch ($s[$i] & "\xF0")
            {
            case "\xC0":
            case "\xD0":
                $c = (ord($s[$i] & "\x1F") << 6) | ord($s[++$i] & "\x3F");
                $s[$j] = $c < 256 ? chr($c) : '?';
                break;

            case "\xF0": ++$i;
            case "\xE0":
                $s[$j] = '?';
                $i += 2;
                break;

            default:
                $s[$j] = $s[$i];
            }
        }

        return substr($s, 0, $j);
    }

/**/endif;
}
