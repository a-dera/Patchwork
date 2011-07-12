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

class DebugLog
{
    protected static

    $session,
    $logFile,
    $logFileStream = null,
    $loggers = array();

    protected

    $token,
    $startTime  = 0,
    $prevTime   = 0,
    $prevMemory = 0,
    $seenErrors = array(),
    $logStream;


    static function start($log_file, $session = null, self $logger = null)
    {
        null === $logger && $logger = new self;
        null === $session && $session = empty(self::$session) ? mt_rand() : self::$session;

        // Too bad: formatting errors with html_errors, error_prepend_string
        // or error_append_string only works with display_errors=1
        ini_set('display_errors', false);
        ini_set('log_errors', true);
        ini_set('error_log', $log_file);
        ini_set('ignore_repeated_errors', true);
        ini_set('ignore_repeated_source', false);

        if (function_exists('error_get_last'))
            register_shutdown_function(array(__CLASS__, 'shutdown'));

        self::$session = $session;
        self::$logFile = $log_file;

        $logger->register();

        return $logger;
    }

    static function getLogger()
    {
        return end(self::$loggers);
    }

    static function shutdown()
    {
        if (false === $logger = self::getLogger()) return;

        if ($e = self::popLastError())
        {
            switch ($e['type'])
            {
            // Get the last fatal error and format it appropriately!
            case E_ERROR: case E_PARSE: case E_CORE_ERROR:
            case E_COMPILE_ERROR: case E_COMPILE_WARNING:
                $logger->logError($e['type'], $e['message'], $e['file'], $e['line'], array(), 1);
            }
        }
    }

    static function popLastError()
    {
        if ( function_exists('error_get_last')
            && ($e = error_get_last())
            && !empty($e['message']) )
        {
            set_error_handler(array(__CLASS__, 'falseError'));
            $r = error_reporting(0);
            user_error('', E_USER_NOTICE);
            error_reporting($r);
            restore_error_handler();
            return $e;
        }
        else return false;
    }

    static function falseError()
    {
        return false;
    }


    function register()
    {
        set_exception_handler(array($this, 'logException'));
        set_error_handler(array($this, 'logError'));
        self::$loggers[] = $this;
        $this->token = mt_rand();
        $this->startTime = microtime(true);
    }

    function unregister()
    {
        if ($this === end(self::$loggers))
        {
            $this->token = null;
            array_pop(self::$loggers);
            restore_error_handler();
            restore_exception_handler();
        }
        else
        {
            user_error(__CLASS__ . ' objects have to be unregistered in the exact reverse order they have been registered', E_USER_WARNING);
        }
    }

    function logError($code, $msg, $file, $line, $context, $trace_offset = 0)
    {
        $k = md5("{$code}/{$line}/{$file}\x00{$msg}", true);
        if (isset($this->seenErrors[$k])) return;
        $this->seenErrors[$k] = 1;

        if (isset($context['GLOBALS']))
        {
            // Exclude auto-globals from $context

            $trace = array();

            foreach ($context as $k => $v)
            {
                switch ($k)
                {
                default: $trace[$k] = $v; break;
                case 'GLOBALS': case '_SERVER': case '_GET': case '_POST':
                case '_FILES': case '_REQUEST': case '_SESSION': case '_ENV':
                case '_COOKIE': case 'php_errormsg': case 'HTTP_RAW_POST_DATA':
                case 'http_response_header': case 'argc': case 'argv':
                }
            }

            unset($context);
            $context = $trace;
        }

        $trace = debug_backtrace(false);

        do unset($trace[$trace_offset]);
        while ($trace_offset--);

        $this->log('php-error', array(
            'code'    => $code,
            'message' => $msg,
            'file'    => $file,
            'line'    => $line,
            'context' => $context,
            'trace'   => $trace,
        ));
    }

    function logException(\Exception $e)
    {
        $this->log('php-exception', array(
            'class'    => get_class($e),
            'code'     => $e->getCode(),
            'message'  => $e->getMessage(),
            'file'     => $e->getFile(),
            'line'     => $e->getLine(),
            'traceStr' => $e->getTraceAsString(),
            'trace'    => $e->getTrace(),
        ));
    }

    function log($type, array $context = array())
    {
        $log_time = microtime(true);

        $this->prevTime
            || ($this->prevTime = $this->startTime)
            || ($this->prevTime = $this->startTime = $log_time);

        $delta_ms  = sprintf('%0.3f', 1000*($log_time - $this->prevTime));
        $total_ms  = sprintf('%0.3f', 1000*($log_time - $this->startTime));
        $delta_mem = isset($this->prevMemory) ? memory_get_usage(true) - $this->prevMemory : 0;
        $peak_mem  = memory_get_peak_usage(true);
        $log_time  = date('c', $log_time) . sprintf(' %06dus', 100000*($log_time - floor($log_time)));

        if (null === $this->token)
        {
            return user_error('This ' . __CLASS__ . ' object has been unregistered', E_USER_WARNING);
        }

        foreach ($context as $k => $v)
        {
            $v = serialize($v);

            if (strcspn($v, "\r\n\0") !== strlen($v))
            {
                $v = str_replace(
                    array(  "\0",  "\r",  "\n"),
                    array("\0\0", "\0r", "\0n"),
                    $v
                );
            }

            $context[$k] = "\n  " . strtr($k, "\r\n:", '---') . ': ' . $v;
        }

        $v = self::$session . ':' . $this->token . ':' . mt_rand();
        $context = implode('', $context);
        $type = strtr($type, "\r\n", '--');

        isset($this->logStream)
            || ($this->logStream = self::$logFileStream)
            || ($this->logStream = self::$logFileStream = fopen(self::$logFile, 'ab'));

        fwrite(
            $this->logStream,
            <<<EOTXT
<event:{$v}>
  type: {$type}
  log-time: {$log_time}
  peak-mem: {$peak_mem}
  delta-ms: {$delta_ms}
  total-ms: {$total_ms}
  delta-mem: {$delta_mem}
  ---{$context}
</event:{$v}>


EOTXT
        );

        $context = '';
        $this->prevMemory = memory_get_usage(true);
        $this->prevTime = microtime(true);
    }
}
