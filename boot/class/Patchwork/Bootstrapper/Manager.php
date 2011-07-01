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


class Patchwork_Bootstrapper_Manager
{
    protected

    $pwd,
    $cwd,
    $paths,
    $zcache,
    $last,
    $appId,

    $bootstrapper,
    $preprocessor,
    $lock = null,
    $steps = array(),
    $substeps = array(),
    $file,
    $overrides,
    $callerRx;


    function __construct($bootstrapper, $caller, $pwd, $cwd)
    {
        $cwd = (empty($cwd) ? '.' : rtrim($cwd, '/\\')) . DIRECTORY_SEPARATOR;

        $this->bootstrapper = $bootstrapper;
        $this->overrides =& $GLOBALS['patchwork_preprocessor_overrides'];
        $this->overrides = array();
        $this->callerRx = preg_quote($caller, '/');
        $this->pwd = $pwd;
        $this->cwd = $cwd;

        switch (true)
        {
        case isset($_GET['p:']) && 'exit' === $_GET['p:']:
            die('Exit requested');
        case !function_exists('token_get_all'):
            throw $this->error('Extension "tokenizer" is needed and not loaded');
        case !file_exists($cwd . 'config.patchwork.php'):
            throw $this->error("File config.patchwork.php not found in {$cwd}. Did you set PATCHWORK_BOOTPATH correctly?");
        case function_exists('__autoload') && !function_exists('spl_autoload_register'):
            throw $this->error('__autoload() is enabled and spl_autoload_register() is not available');
        case headers_sent($file, $line) || ob_get_length():
            throw $this->getEchoError($file, $line, ob_get_flush(), 'before bootstrap');
        case function_exists('mb_internal_encoding'):
            mb_internal_encoding('8bit'); // if mbstring overloading is enabled
            @ini_set('mbstring.internal_encoding', '8bit');
        }

        if ($this->getLock(true))
        {
            $s = '';

            // Turn off magic quotes runtime

            if (function_exists('get_magic_quotes_runtime') && @get_magic_quotes_runtime())
            {
                @set_magic_quotes_runtime(false);
                if (@get_magic_quotes_runtime())
                    throw $this->error('Failed to turn off magic_quotes_runtime');

                $s .= "set_magic_quotes_runtime(false);";
            }

            // Backport PHP_VERSION_ID and co.

            if (!defined('PHP_VERSION_ID'))
            {
                $v = array_map('intval', explode('.', PHP_VERSION, 3));
                $s .= "define('PHP_VERSION_ID',"      . (10000 * $v[0] + 100 * $v[1] + $v[2]) . ");";
                $s .= "define('PHP_MAJOR_VERSION',"   . $v[0] . ");";
                $s .= "define('PHP_MINOR_VERSION',"   . $v[1] . ");";
                $s .= "define('PHP_RELEASE_VERSION'," . $v[2] . ");";

                $v = substr(PHP_VERSION, strlen(implode('.', $v)));
                $s .= "define('PHP_EXTRA_VERSION','" . addslashes(false !== $v ? $v : '') . "');";
            }

            // Register the next steps

            $s && $this->steps[] = array($s, __FILE__);
            $this->steps[] = array(array($this, 'initAutoloader'  ), null);
            $this->steps[] = array(array($this, 'initPreprocessor'), null);
            $this->steps[] = array(null, $this->pwd . 'bootup.patchwork.php');
            $this->steps[] = array(array($this, 'initInheritance' ), null);
            $this->steps[] = array(array($this, 'initZcache'      ), null);
            $this->steps[] = array(array($this, 'exportPathData'  ), null);

            @set_time_limit(0);
            ob_start(array($this, 'ob_eval'));
        }
        else
        {
            $this->steps[] = array("require {$this->cwd}.patchwork.php; return false;", __FILE__);
        }
    }

    protected function getLock($retry)
    {
        $lock = $this->cwd . '.patchwork.lock';
        $file = $this->cwd . '.patchwork.php';

        if ($this->lock = @fopen($lock, 'xb'))
        {
            if (file_exists($file))
            {
                fclose($this->lock);
                $this->lock = null;
                @unlink($lock);
                if ($retry)
                {
                    $file = $this->getBestPath($file);

                    throw $this->error("File {$file} exists. Please fix your front bootstrap file.");
                }
                else return false;
            }

            flock($this->lock, LOCK_EX);
            fwrite($this->lock, '<?php ');

            return true;
        }
        else if ($h = $retry ? @fopen($lock, 'rb') : fopen($lock, 'rb'))
        {
            usleep(1000);
            flock($h, LOCK_SH);
            fclose($h);
            file_exists($file) || usleep(1000);
        }
        else if ($retry)
        {
            $dir = dirname($lock);

            if (@!(touch($dir . '/.patchwork.touch') && unlink($dir . '/.patchwork.touch')))
            {
                $dir = $this->getBestPath($dir);

                throw $this->error("Please change the permissions of the {$dir} directory so that the current process can write in it.");
            }
        }

        if ($retry && !file_exists($file))
        {
            @unlink($lock);
            return $this->getLock(false);
        }
        else return false;
    }

    function getNextStep()
    {
        while (1)
        {
            if ($this->substeps)
            {
                $this->steps = array_merge($this->substeps, $this->steps);
                $this->substeps = array();
            }
            else if (!$this->steps)
            {
                $this->release();
                return '';
            }

            if (function_exists('patchwork_include'))
            {
                ob_flush();
                $code = 'spl_autoload_register';
                function_exists('__patchwork_' . $code) && $code = '__patchwork_' . $code;
                $code(array($this, 'autoload'));
            }

            list($code, $this->file) = array_shift($this->steps);

            if (null === $this->file)
            {
                call_user_func($code);
                continue;
            }
            else if (empty($this->preprocessor))
            {
                null === $code && $code = substr(file_get_contents($this->file), 5);
                $this->lock && fwrite($this->lock, $code);
            }
            else
            {
                $code = null !== $code ? '<?php ' . $code : file_get_contents($this->file);
                $code = $this->preprocessor->staticPass1($code, $this->file) .
                    ";return eval({$this->bootstrapper}::\$manager->preprocessorPass2());";
            }

            return $code;
        }
    }

    function preprocessorPass2()
    {
        $code = $this->preprocessor->staticPass2();
        '' === $code && $code = ' ';
        fwrite($this->lock, $code);
        ob_get_length() && $this->release();
        $a = 'spl_autoload_unregister';
        function_exists('__patchwork_' . $a) && $a = '__patchwork_' . $a;
        $a(array($this, 'autoload'));
        return $code;
    }

    function autoload($class)
    {
        $class = $this->pwd . 'class/' . strtr($class, '\\_', '//') . '.php';
        file_exists($class) && patchwork_include($class);
    }

    function ob_eval($buffer)
    {
        return preg_replace("/{$this->callerRx}\(\d+\) : eval\(\)'d code/", $this->file, $buffer);
    }

    protected function release()
    {
        if ('' !== $buffer = ob_get_clean())
            throw $this->getEchoError($this->file, 0, $buffer, 'during bootstrap');

        file_put_contents("{$this->cwd}.patchwork.overrides.ser", serialize($this->overrides));
        fclose($this->lock);
        $this->lock = null;

        $a = $this->cwd . '.patchwork.lock';
        touch($a, $_SERVER['REQUEST_TIME'] + 1);
        rename($a, $this->cwd . '.patchwork.php');

        $a = 'spl_autoload_unregister';
        function_exists('__patchwork_' . $a) && $a = '__patchwork_' . $a;
        function_exists($a) && spl_autoload_unregister(array($this, 'autoload'));

        @set_time_limit(ini_get('max_execution_time'));
    }

    protected function initAutoloader()
    {
        function_exists('__autoload') && $this->substeps[] = array("spl_autoload_register('__autoload');", __FILE__);

        if (PHP_VERSION_ID < 50300 || !function_exists('spl_autoload_register'))
        {
            // Before PHP 5.3, backport spl_autoload_register()'s $prepend argument
            // and workaround http://bugs.php.net/44144

            $this->substeps[] = array(null, $this->pwd . 'compat/class/Patchwork/PHP/Override/SplAutoload.php');
            $this->substeps[] = array(
                $this->override('__autoload',              ':SplAutoload::spl_autoload_call', array('$class')) .
                $this->override('spl_autoload_call',       ':SplAutoload:', array('$class')) .
                $this->override('spl_autoload_functions',  ':SplAutoload:', array()) .
                $this->override('spl_autoload_register',   ':SplAutoload:', array('$callback', '$throw' => true, '$prepend' => false)) .
                $this->override('spl_autoload_unregister', ':SplAutoload:', array('$callback')) .
                (function_exists('spl_autoload_register')
                    ? "spl_autoload_register(array('Patchwork_PHP_Override_SplAutoload','spl_autoload_call'));"
                    : 'class LogicException extends Exception {}'),
                __FILE__
            );
        }
        else
        {
            $this->substeps[] = array($this->override('__autoload', 'spl_autoload_call', array('$class')), __FILE__);
        }

        $this->substeps[] = array('function patchwork_include($file) {return include $file;}', __FILE__);
    }

    protected function initPreprocessor()
    {
        $p = $this->bootstrapper . '_Preprocessor';
        $this->preprocessor = new $p;
    }

    protected function initInheritance()
    {
        $this->cwd = rtrim(patchwork_realpath($this->cwd), '/\\') . DIRECTORY_SEPARATOR;

        $a = $this->bootstrapper . '_Inheritance';
        $a = new $a;
        $a = $a->linearizeGraph($this->pwd, $this->cwd);

        $b = array_slice($a[0], 0, $a[1]);

        foreach (array_reverse($b) as $c)
            if (file_exists($c .= 'bootup.patchwork.php'))
                $this->steps[] = array(null, $c);

        $this->steps[] = array('$CONFIG = array();', __FILE__);
        $b[] = $this->pwd;

        foreach ($b as $c)
            if (file_exists($c .= 'config.patchwork.php'))
                $this->steps[] = array(null, $c);

        $this->paths = $a[0];
        $this->last  = $a[1];
        $this->appId = $a[2];
    }

    protected function initZcache()
    {
        // Get zcache's location

        $zc = false;

        for ($i = 0; $i <= $this->last; ++$i)
        {
            if (file_exists($this->paths[$i] . 'zcache/'))
            {
                $zc = $this->paths[$i] . 'zcache' . DIRECTORY_SEPARATOR;
                @(touch($zc . '/.patchwork.touch') && unlink($zc . '/.patchwork.touch')) || $zc = false;
                break;
            }
        }

        if (!$zc)
        {
            $zc = $this->cwd . 'zcache' . DIRECTORY_SEPARATOR;
            file_exists($zc) || mkdir($zc);
        }

        $this->zcache = $zc;
    }

    protected function exportPathData()
    {
        $this->substeps[] = array(
            '$patchwork_appId = (int) ' . var_export(sprintf('%020d', $this->appId), true) . ";
            define('PATCHWORK_PROJECT_PATH', " . var_export($this->cwd, true) . ");
            define('PATCHWORK_ZCACHE',       " . var_export($this->zcache, true) . ");
            define('PATCHWORK_PATH_LEVEL',   " . var_export($this->last, true) . ');
            $patchwork_path = ' . var_export($this->paths, true) . ';',
            __FILE__
        );
    }

    function error($msg)
    {
        $e = $this->bootstrapper . '_Exception';
        return new $e($msg);
    }

    function pushFile($file)
    {
        $this->substeps[] = array(null, dirname($this->file) . DIRECTORY_SEPARATOR . $file);
    }

    function getCurrentDir()
    {
        return dirname($this->file) . DIRECTORY_SEPARATOR;
    }

    function override($function, $override, $args, $return_ref = false)
    {
        ':' === substr($override, 0, 1) && $override = 'Patchwork_PHP_Override_' . substr($override, 1);
        ':' === substr($override, -1)   && $override .= ':' . $function;
        $override = ltrim($override, '\\');

        if (function_exists($function))
        {
            $inline = 0 === strcasecmp($function, $override) ? -1 : 2;
            $function = "__patchwork_{$function}";
        }
        else
        {
            $inline = 1;

            if (0 === strcasecmp($function, $override))
            {
                return "throw {$this->bootstrapper}::\$manager->error('Circular overriding of function {$function}()');";
            }
        }

        $args = array($args, array(), array());

        foreach ($args[0] as $k => $v)
        {
            if (is_string($k))
            {
                $k = trim(strtr($k, "\n\r", '  '));
                $args[1][] = $k . '=' . var_export($v, true);
                0 > $inline && $inline = 0;
            }
            else
            {
                $k = trim(strtr($v, "\n\r", '  '));
                $args[1][] = $k;
            }

            $v = '[a-zA-Z_\x7F-\xFF][a-zA-Z0-9_\x7F-\xFF]*';
            $v = "'^(?:(?:(?: *\\\\ *)?{$v})+(?:&| +&?)|&?) *(\\\${$v})$'D";

            if (!preg_match($v, $k, $v))
            {
                1 !== $inline && $function = substr($function, 12);
                return "throw {$this->bootstrapper}::\$manager->error('Invalid parameter for {$function}()\'s override ({$override}: {$k})');";
            }

            $args[2][] = $v[1];
        }

        $args[1] = implode(',', $args[1]);
        $args[2] = implode(',', $args[2]);

        $inline && $this->overrides[1 !== $inline ? substr($function, 12) : $function] = $override;

        // FIXME: when overriding a user function, this will throw a can not redeclare fatal error!
        // Some help is required from the main preprocessor to rename overridden user functions.
        // When done, overriding will be perfect for user functions. For internal functions,
        // the only uncatchable case would be when using an internal caller (especially objects)
        // with an internal callback. This also means that functions with callback could be left
        // untracked, at least when we are sure that an internal function will not be used as a callback.

        return $return_ref
            ? "function &{$function}({$args[1]}) {\${''}=&{$override}({$args[2]});return \${''}}"
            : "function  {$function}({$args[1]}) {return {$override}({$args[2]});}";
    }

    protected function getEchoError($file, $line, $what, $when)
    {
        // Try to build a nice error message about early echos

        if ($type = strlen($what))
        {
            if ('' === trim($what))
            {
                $type = $type > 1 ? "{$type} bytes of whitespace have" : 'One byte of whitespace has';
            }
            else if (0 === strncmp($what, "\xEF\xBB\xBF", 3))
            {
                $type = 'An UTF-8 byte order mark (BOM) has';
            }
            else
            {
                $type = $type > 1 ? "{$type} bytes have" : 'One byte has';
            }
        }
        else $type = 'Something has';

        if ($line)
        {
            $line = " in {$file} on line {$line} or before";
        }
        else if ($file)
        {
            $line = " in {$file}";
        }
        else
        {
            $line = array_slice(get_included_files(), 0, -3);
            $file = array_pop($line);
            $line = ' in ' . ($line ? implode(', ', $line) . ' or in ' : '') . $file;
        }

        return $this->error("{$type} been echoed {$when}{$line}");
    }

    protected function getBestPath($a)
    {
        // This function tries to work around very disabled hosts,
        // to get the best "realpath" for comprehensible error messages.

        function_exists('realpath') && $a = realpath($a);

        is_dir($a) && $a = trim($a, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if ('.' === $a[0] && function_exists('getcwd') && @getcwd())
        {
            $a = getcwd() . DIRECTORY_SEPARATOR . $a;
        }

        return $a;
    }
}

class Patchwork_Bootstrapper_Exception extends Exception {}
