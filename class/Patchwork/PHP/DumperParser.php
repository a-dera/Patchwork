<?php /****************** vi: set fenc=utf-8 ts=4 sw=4 et: *****************
 *
 *   Copyright : (C) 2011 Nicolas Grekas. All rights reserved.
 *   Email     : p@tchwork.org
 *   License   : http://www.gnu.org/licenses/agpl.txt GNU/AGPL
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU Affero General Public License as
 *   published by the Free Software Foundation, either version 3 of the
 *   License, or (at your option) any later version.
 *
 ***************************************************************************/

namespace Patchwork\PHP;

class DumperParser
{
    protected

    $indentLevel = 0,
    $indentStack = array(),
    $inString = false,
    $tokens = array();


    function tokenizeLine($a)
    {
        $this->tokens = array();
        $a = rtrim($a, "\r\n");

        if ($this->inString)
        {
            $this->push('indent', substr($a, 0, $this->indentLevel+2));

            $a = substr($a, $this->indentLevel+2);

            if ('"""' === substr($a, -3))
            {
                $a = substr($a, 0, -3);
                $this->inString = false;
            }
            // TODO: ...""" => ...

            $this->push('string', "'{$a}'");
        }
        else
        {
            if (0 !== $this->indentLevel)
            {
                $a = substr($a, $this->indentLevel-2);

                if ($a !== ltrim($a, ']}'))
                {
                    $this->indentLevel -= 2;
                    array_pop($this->indentStack);
                }
                else $a = substr($a, 2);

                $this->indentLevel && $this->push('indent', str_repeat(' ', $this->indentLevel));
            }

            if ('"' === $a[0])
            {
                $i = strrpos($a, '"', 1);
                $kv = strpos(substr($a, 1, $i - 1), '" => "');
                false !== $kv && $i = $kv + 1;

                $kv = array(substr($a, 0, $i+1));

                if (false !== $i = strpos($a, ' => ', $i+1))
                {
                    $kv[1] = substr($a, $i+4);
                }
            }
            else $kv = explode(' => ', $a);

            $i = isset($kv[1]) ? ' key ' . end($this->indentStack) : '';

            foreach ($kv as $a => $kv)
            {
                if (1 === $a)
                {
                    $i = '';
                    $this->push('arrow', ' ⇨ ');
                }

                $grammar = array(
                     '(".*)' => array('string' . $i),
                     '([-\d].*)' => array('const' . $i),
                     '([\]\}])(\)?)' => array('bracket close', 'bracket'),
                     '(\.\.\.(?:"\d+)?)' => array('cut'),
                     '(Resource)( )(#\d+)( )(\()(.*)(?:(\))|(\[))' => array('res txt', 's', 'ref id', 's', 'bracket', 'res type', 'bracket', 'bracket open'),
                     '(?:([^# \[\{]+)( ?))?(#\d+)?(?:([\[\{])(?:(#\d+)|(\.\.\.))?([\}\]])|([\[\{]))' => array('class', 's', 'ref id', 'bracket', 'ref to', 'cut', 'bracket', 'bracket open'),
                     '(.*)' => array('const' . $i),
                );

                foreach ($grammar as $a => $grammar)
                {
                    if (preg_match("/^{$a}$/", $kv, $a))
                    {
                        foreach ($grammar as $i => $grammar)
                            if (isset($a[++$i]) && '' !== $a[$i])
                                $this->push($grammar, $a[$i]);
                        break;
                    }
                }
            }
        }

        return $this->tokens;
    }

    protected function push($tag, $data)
    {
        $t = array();

        foreach (explode(' ', $tag) as $tag) $t[$tag] = $tag;

        if (isset($t['string']))
        {
            if ('"""' === $data)
            {
                $this->inString = true;
                return;
            }

            $data = stripcslashes(substr($data, 1, -1));

            if ('' === $data) $t['empty'] = 'empty';
        }

        if (isset($t['open']))
        {
            $this->indentLevel += 2;
            $this->indentStack[] = '[' === $data ? 'array' : 'object';
        }

        if (isset($t['key'], $t['object']))
        {
            $tag = explode(':', $data);
            isset($tag[1]) || array_unshift($tag, '');
            $data = $tag[1];

            if ('' === $tag[0]) $tag = 'public';
            else if ('*' === $tag[0]) $tag = 'protected';
            else
            {
                $t['private-class'] = $tag[0];
                $tag = 'private';
            }

            $t[$tag] = $tag;
        }

        $t[] = $data;

        $this->tokens[] = $t;
    }
}
