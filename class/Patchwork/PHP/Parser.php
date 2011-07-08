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

define('T_SEMANTIC',     0); // Primary type for semantic tokens
define('T_NON_SEMANTIC', 1); // Primary type for non-semantic tokens (whitespace and comment)

Patchwork_PHP_Parser::createToken(
    'T_CURLY_CLOSE', // Closing braces opened with T_CURLY_OPEN or T_DOLLAR_OPEN_CURLY_BRACES
    'T_KEY_STRING',  // Array access in interpolated string
    'T_UNEXPECTED_CHARACTER' // Unexpected character in input
);

defined('T_NS_SEPARATOR') || Patchwork_PHP_Parser::createToken('T_NS_SEPARATOR');


class Patchwork_PHP_Parser
{
    protected

    // Declarations used by __construct()
    $dependencyName = null,    // Fully qualified class identifier, defaults to get_class($this)
    $dependencies   = array(   // (dependencyName => shared properties) map before instanciation
                            ), // (dependencyName => dependency object) map after
    $callbacks      = array(), // Callbacks to register

    // Parse time state
    $index    = 0,             // Index of the next token to be parsed
    $tokens   = array(),       // To be parsed tokens, as returned by token_get_all()
    $types    = array(),       // Types of already parsed tokens, excluding non-semantic tokens
    $texts    = array(),       // Texts of already parsed tokens, including non-semantic tokens
    $line     = 0,             // Line number of the current token
    $inString = 0,             // Odd/even when outside/inside string interpolation context
    $lastType,                 // The last token type in $this->types
    $penuType,                 // The penultimate token type in $this->types
    $tokenRegistry = array();  // (token type => callbacks) map


    private

    $parents = array(),
    $errors  = array(),
    $nextRegistryIndex = 0,

    $parent,
    $registryIndex = 0;


    private static $tokenNames = array(
        0 => 'T_SEMANTIC',
        1 => 'T_NON_SEMANTIC',
    );


    function __construct(self $parent = null)
    {
        $parent || $parent = __CLASS__ === get_class($this) ? $this : new self;

        $this->dependencyName || $this->dependencyName = get_class($this);
        $this->dependencies = (array) $this->dependencies;
        $this->parent = $parent;

        // Link shared properties of $parent and $this by reference

        if ($parent !== $this)
        {
            $v = array(
                'index',
                'tokens',
                'types',
                'texts',
                'line',
                'inString',
                'lastType',
                'penuType',
                'tokenRegistry',
                'parents',
                'errors',
                'nextRegistryIndex',
            );

            foreach ($v as $v) $this->$v =& $parent->$v;
        }
        else $this->nextRegistryIndex = -1 - PHP_INT_MAX;

        // Verify and set $this->dependencies to the (dependencyName => dependency object) map

        foreach ($this->dependencies as $k => $v)
        {
            unset($this->dependencies[$k]);

            if (is_string($k))
            {
                $c = (array) $v;
                $v = $k;
            }
            else $c = array();

            $k = strtolower('\\' !== $v[0] ? __CLASS__ . '_' . $v : substr($v, 1));

            if (!isset($this->parents[$k]))
            {
                user_error(get_class($this) . " failed dependency: {$v}", E_USER_WARNING);
                return;
            }

            $parent = $this->dependencies[$v] = $this->parents[$k];

            foreach ($c as $c => $k)
            {
                is_int($c) && $c = $k;

                if (property_exists($parent, $c)) $this->$k =& $parent->$c;
                else user_error(get_class($this) . " undefined property: {$v}->{$c}", E_USER_NOTICE);
            }
        }

        // Keep track of parents chained parsers

        $k = strtolower($this->dependencyName);
        $this->parents[$k] = $this;

        // Keep parsers chaining order for callbacks ordering

        $this->registryIndex = $this->nextRegistryIndex;
        $this->nextRegistryIndex += 1 << (PHP_INT_SIZE << 2);

        $this->register($this->callbacks);
    }

    // Parse PHP source code

    function parse($code)
    {
        $this->tokens = $this->getTokens($code);
        return implode('', $this->parseTokens());
    }

    // Get the errors emitted while parsing

    function getErrors()
    {
        ksort($this->errors);
        $e = array();
        foreach ($this->errors as $v) foreach ($v as $e[]) {}
        return $e;
    }

    // Enhanced token_get_all()

    protected function getTokens($code)
    {
        // Recursively traverse the inheritance chain defined by $this->parent

        if ($this->parent !== $this) return $this->parent->getTokens($code);

        // For binary safeness, check for unexpected characters (see http://bugs.php.net/54089)

        if (!$bin = version_compare(PHP_VERSION, '5.3.0') < 0 && strpos($code, '\\'))
        {
            for ($i = 0; $i < 32; ++$i)
                if ($i !== 0x09 && $i !== 0x0A && $i !== 0x0D && strpos($code, chr($i)))
                    break $bin = true;
        }

        if (!$bin) return token_get_all($code);

        if (function_exists('mb_internal_encoding'))
        {
            // Workaround mbstring overloading
            $bin = @mb_internal_encoding();
            @mb_internal_encoding('8bit');
        }

        $t0     = @token_get_all($code);
        $t1     = array($t0[0]);
        $offset = strlen($t0[0][1]);
        $i      = 0;

        // Re-insert characters removed by token_get_all() as T_UNEXPECTED_CHARACTER tokens

        while (isset($t0[++$i]))
        {
            $t = isset($t0[$i][1]) ? $t0[$i][1] : $t0[$i];

            if (isset($t[0]))
                while ($t[0] !== $code[$offset])
                    $t1[] = array('\\' === $code[$offset] ? T_NS_SEPARATOR : T_UNEXPECTED_CHARACTER, $code[$offset++]);

            $offset += strlen($t);
            $t1[] = $t0[$i];
            unset($t0[$i]);
        }

        function_exists('mb_internal_encoding') && mb_internal_encoding($bin);

        return $t1;
    }

    // Parse raw tokens already loaded in $this->tokens

    protected function parseTokens()
    {
        // Recursively traverse the inheritance chain defined by $this->parent

        if ($this->parent !== $this) return $this->parent->parseTokens();

        // Alias properties to local variables, initialize them

        $line     =& $this->line;     $line     = 1;
        $i        =& $this->index;    $i        = 0;
        $inString =& $this->inString; $inString = 0;
        $types    =& $this->types;    $types    = array();
        $texts    =& $this->texts;    $texts    = array('');
        $lastType =& $this->lastType; $lastType = false;
        $penuType =& $this->penuType; $penuType = false;
        $tokens   =& $this->tokens;
        $reg      =& $this->tokenRegistry;

        $j         = 0;
        $curly     = 0;
        $curlyPool = array();

        while (isset($tokens[$i]))
        {
            $t =& $tokens[$i];    // Get the next token
            unset($tokens[$i++]); // Free memory and move $this->index forward

            // Set primary type and fix string interpolation context
            //
            // String interpolation is hard, especially before PHP 5.2.3.
            // See this thread on the PHP internals mailing-list for detailed background:
            // http://www.mail-archive.com/internals@lists.php.net/msg27154.html
            //
            // Since PHP before 5.2.3 is supported, many tokens have to be tagged
            // as T_ENCAPSED_AND_WHITESPACE when inside interpolated strings.
            //
            // Further than that, two gotchas remain inside string interpolation:
            // - tag closing braces as T_CURLY_CLOSE when they are opened with curly braces
            //   tagged as T_CURLY_OPEN or T_DOLLAR_OPEN_CURLY_BRACES, to make
            //   them easy to distinguish from regular code "{" / "}" pairs.
            // - mimic T_NUM_STRING usage for numerical array indexes and tag string indexes
            //   as T_KEY_STRING rather than T_STRING in "$array[key]".

            $priType = 0; // T_SEMANTIC

            if (isset($t[1]))
            {
                if ($inString & 1) switch ($t[0])
                {
                case T_VARIABLE:
                case T_KEY_STRING:
                case T_CURLY_OPEN:
                case T_CURLY_CLOSE:
                case T_END_HEREDOC:
                case T_DOLLAR_OPEN_CURLY_BRACES: break;
                case T_STRING:     if ('[' === $lastType) $t[0] = T_KEY_STRING;
                case T_NUM_STRING: if ('[' === $lastType) break;
                case T_OBJECT_OPERATOR: if (T_VARIABLE === $lastType) break;
                default: $t[0] = T_ENCAPSED_AND_WHITESPACE;
                }
                else switch ($t[0])
                {
                case T_WHITESPACE:
                case T_COMMENT:
                case T_DOC_COMMENT:
                case T_UNEXPECTED_CHARACTER: $priType = 1; // T_NON_SEMANTIC
                }
            }
            else
            {
                $t = array($t, $t);

                if ($inString & 1) switch ($t[0])
                {
                case '"':
                case '`': break;
                case ']': if (T_KEY_STRING === $lastType || T_NUM_STRING === $lastType) break;
                case '[': if (T_VARIABLE   === $lastType && '[' === $t[0]) break;
                default: $t[0] = T_ENCAPSED_AND_WHITESPACE;
                }
                else if ('}' === $t[0] && !$curly) $t[0] = T_CURLY_CLOSE;
            }

            // Trigger callbacks

            if (isset($reg[$t[0]]) || isset($reg[$priType]))
            {
                $k = $t[0];
                $t[2] = array($priType => $priType);
                $callbacks = isset($reg[$priType]) ? $reg[$priType] : array();

                do
                {
                    $t[2][$k] = $k;

                    if (isset($reg[$k]))
                    {
                        $callbacks += $reg[$k];

                        // Callbacks triggering are always ordered:
                        // - first by parsers' instanciation order
                        // - then by callbacks' registration order
                        ksort($callbacks);
                    }

                    foreach ($callbacks as $k => $c)
                    {
                        unset($callbacks[$k]);

                        // $t is the current token:
                        // $t = array(
                        //     0 => token's main type - a single character or a T_* constant,
                        //          as returned by token_get_all()
                        //     1 => token's text - its source code excerpt as a string
                        //     2 => an array of token's types and subtypes
                        // )

                        $k = $c[0]->$c[1]($t);

                        // A callback can return:
                        // - false, which cancels the current token
                        // - a new token type, which is added to $t[2] and loads the
                        //   related callbacks in the current callbacks stack
                        // - or nothing (null)

                        if (false === $k) continue 3;
                        if ($k && empty($t[2][$k])) continue 2;
                    }

                    break;
                }
                while (1);
            }

            // Commit to $this->texts

            $texts[++$j] =& $t[1];

            if ($priType) // T_NON_SEMANTIC
            {
                $line += substr_count($t[1], "\n");
                continue;
            }

            // For semantic tokens only: populate $this->types, $this->lastType and $this->penuType

            $penuType  = $lastType;
            $types[$j] = $lastType = $t[0];

            // Parsing context analysis related to string interpolation and line numbering

            if (isset($lastType[0])) switch ($lastType)
            {
            case '{': ++$curly; break;
            case '}': --$curly; break;
            case '"':
            case '`': $inString += ($inString & 1) ? -1 : 1;
            }
            else switch ($lastType)
            {
            case T_CONSTANT_ENCAPSED_STRING:
            case T_ENCAPSED_AND_WHITESPACE:
            case T_OPEN_TAG_WITH_ECHO:
            case T_INLINE_HTML:
            case T_CLOSE_TAG:
            case T_OPEN_TAG:
                $line += substr_count($t[1], "\n");
                break;

            case T_DOLLAR_OPEN_CURLY_BRACES:
            case T_CURLY_OPEN:    $curlyPool[] = $curly; $curly = 0;
            case T_START_HEREDOC: ++$inString; break;

            case T_CURLY_CLOSE:   $curly = array_pop($curlyPool);
            case T_END_HEREDOC:   --$inString; break;

            case T_HALT_COMPILER: break 2; // See http://bugs.php.net/54089
            }
        }

        // Free memory thanks to copy-on-write
        $j = $texts;
        $types = $texts = $tokens = $reg = $this->parents = $this->parent = null;
        return $j;
    }


    // Set an error on input code inside parsers.

    protected function setError($message, $type)
    {
        $this->errors[(int) $this->line][] = array(
            $message,
            (int) $this->line,
            get_class($this),
            $type
        );
    }

    // Register/unregister callbacks for the next tokens

    protected function   register($method) {$this->registryApply($method, true );}
    protected function unregister($method) {$this->registryApply($method, false);}

    // Read-ahead the input token stream

    protected function &getNextToken(&$i = null)
    {
        static $ns = array( // Non-semantic types
            T_COMMENT => 1,
            T_WHITESPACE => 1,
            T_DOC_COMMENT => 1,
            T_UNEXPECTED_CHARACTER => 1
        );

        null === $i && $i = $this->index;
        while (isset($this->tokens[$i], $ns[$this->tokens[$i][0]])) ++$i;
        isset($this->tokens[$i]) || $this->tokens[$i] = array(T_WHITESPACE, '');

        return $this->tokens[$i++];
    }

    // Inject tokens in the input stream

    protected function unshiftTokens()
    {
        $token = func_get_args();
        isset($token[1]) && $token = array_reverse($token);

        foreach ($token as $token)
            $this->tokens[--$this->index] = $token;

        return false;
    }

    // Internal use for $this->register/unregister() factorization

    private function registryApply($method, $reg)
    {
        foreach ((array) $method as $method => $type)
        {
            if (is_int($method))
            {
                $method = $type;
                $type = array(0); // T_SEMANTIC
            }

            foreach ((array) $type as $type)
            {
                if ($reg)
                {
                    0 === $type && $s0 = 1; // T_SEMANTIC
                    1 === $type && $s1 = 1; // T_NON_SEMANTIC
                    $this->tokenRegistry[$type][++$this->registryIndex] = array($this, $method);
                }
                else if (isset($this->tokenRegistry[$type]))
                {
                    foreach ($this->tokenRegistry[$type] as $k => $v)
                        if (array($this, $method) === $v)
                            unset($this->tokenRegistry[$type][$k]);

                    if (!$this->tokenRegistry[$type]) unset($this->tokenRegistry[$type]);
                }
            }
        }

        isset($s0) && ksort($this->tokenRegistry[0]); // T_SEMANTIC
        isset($s1) && ksort($this->tokenRegistry[1]); // T_NON_SEMANTIC
    }


    // Create new sub-token types

    static function createToken($name)
    {
        static $type = 0;
        $name = func_get_args();
        foreach ($name as $name)
        {
            define($name, --$type);
            self::$tokenNames[$type] = $name;
        }
    }

    // Get the symbolic name of a given PHP token or sub-token as created by self::createToken

    static function getTokenName($type)
    {
        if (is_string($type)) return $type;
        return isset(self::$tokenNames[$type]) ? self::$tokenNames[$type] : token_name($type);
    }


    // Returns a parsable string representation of a variable.
    // Similar to var_export() with these differencies:
    // - it can be used inside output buffering callbacks,
    // - it always returns a single ligne of code,
    //   even for arrays or when the input contains CR/LF.
    // - but it doesn't detect recursive structures

    static function export($a)
    {
        switch (true)
        {
        default:           return (string) $a;
        case true  === $a: return 'true';
        case false === $a: return 'false';
        case null  === $a: return 'null';
        case  INF  === $a: return  'INF';
        case -INF  === $a: return '-INF';
        case NAN   === $a: return 'NAN';

        case is_string($a):
            return $a === strtr($a, "\r\n\0", '---')
                ? ("'" . str_replace(
                        array(  '\\',   "'"),
                        array('\\\\', "\\'"), $a
                    ) . "'")
                : ('"' . str_replace(
                        array(  "\\",   '"',   '$',  "\r",  "\n",  "\0"),
                        array('\\\\', '\\"', '\\$', '\\r', '\\n', '\\0'), $a
                    ) . '"');

        case is_array($a):
            $i = 0;
            $b = array();

            foreach ($a as $k => $a)
            {
                if (is_int($k) && 0 <= $k)
                {
                    $b[] = ($k !== $i ? $k . '=>' : '') . self::export($a);
                    $i = $k + 1;
                }
                else
                {
                    $b[] = self::export($k) . '=>' . self::export($a);
                }
            }

            return 'array(' . implode(',', $b) . ')';

        case is_object($a):
            return 'unserialize(' . self::export(serialize($a)) . ')';

        case is_float($a):
            $b = sprintf('%.14F', $a);
            $a = sprintf('%.17F', $a);
            return rtrim((float) $b === (float) $a ? $b : $a, '.0');
        }
    }
}
