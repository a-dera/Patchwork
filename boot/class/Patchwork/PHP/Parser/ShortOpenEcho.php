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


class Patchwork_PHP_Parser_ShortOpenEcho extends Patchwork_PHP_Parser
{
    protected $tag;

    protected function getTokens($code)
    {
        if (false !== strpos($code, '<?='))
        {
            $c = token_get_all('<?=');

            if (T_INLINE_HTML === $c[0][0])
            {
                $this->tag = '<?php __echo_soe_' . mt_rand() . ' ';
                $code = str_replace('<?=', $this->tag, $code);

                $this->register(array(
                    'echoTag'   => T_OPEN_TAG,
                    'removeTag' => array(
                        T_CONSTANT_ENCAPSED_STRING,
                        T_ENCAPSED_AND_WHITESPACE,
                        T_COMMENT,
                        T_DOC_COMMENT,
                    ),
                ));
            }
        }

        return parent::getTokens($code);
    }

    protected function echoTag(&$token)
    {
        isset($this->tokens[$this->index][1])
            && '<?php ' . $this->tokens[$this->index][1] . ' ' === $this->tag
            && $this->tokens[$this->index] = array(T_ECHO, 'echo');
    }

    protected function removeTag(&$token)
    {
        if (false !== strpos($token[1], '<?php __echo_soe_'))
        {
            $token[1] = str_replace($this->tag, '<?=', $token[1]);
        }
        else if (T_ENCAPSED_AND_WHITESPACE === $token[0] && 0 === substr_compare($token[1], '<?', -2))
        {
            // This case is for pre-5.2.3 interpolated strings
            $t =& $this->tokens;
            $i = $this->index;

            if (!isset($t[$i][1], $t[$i+1][1], $t[$i+2][1], $t[$i+3][1])) return;

            if (0 === strpos('<?' . $t[$i][1] . $t[$i+1][1] . $t[$i+2][1] . $t[$i+3][1], $this->tag))
            {
                $t[$i+3][1][0] = '=';
                $token[1] .= $t[$i+3][1];
                unset($t[$i], $t[$i+1], $t[$i+2]);
                $t[$this->index = $i+3] = $token;
                return false;
            }
        }
    }
}
