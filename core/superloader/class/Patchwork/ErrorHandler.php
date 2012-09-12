<?php // vi: set fenc=utf-8 ts=4 sw=4 et:
/*
 * Copyright (C) 2012 Nicolas Grekas - p@tchwork.com
 *
 * This library is free software; you can redistribute it and/or modify it
 * under the terms of the (at your option):
 * Apache License v2.0 (http://apache.org/licenses/LICENSE-2.0.txt), or
 * GNU General Public License v2.0 (http://gnu.org/licenses/gpl-2.0.txt).
 */

namespace Patchwork;

// http://bugs.php.net/42098 workaround
class_exists('Patchwork\PHP\ErrorHandler') || eval(';') || __autoload('Patchwork\PHP\ErrorHandler');

class ErrorHandler extends PHP\ErrorHandler
{
    protected $screamErrors = /*<*/DEBUG ? -1 : 0/*>*/;

    protected static $caughtToStringException;


    function handleError($type, $message, $file, $line, $scope, $trace_offset = 0, $log_time = 0)
    {
        if (isset(self::$caughtToStringException))
        {
            $type = self::$caughtToStringException;
            self::$caughtToStringException = null;
            throw $type;
        }

        // Silence strict and deprecated notices for classes and files coming from include_path
        if (/*<*/(E_NOTICE | E_STRICT | E_DEPRECATED)/*>*/ & $type)
            if (strpos($message, '__00::') || '-' === substr($file, -12, 1))
                $e = error_reporting(81);

        0 <= $trace_offset && ++$trace_offset;
        parent::handleError($type, $message, $file, $line, $scope, $trace_offset, $log_time);
        isset($e) && error_reporting($e);

        return (bool) (error_reporting() & $type);
    }

    function getLogger()
    {
        if (isset($this->logger)) return $this->logger;
        isset(self::$logStream) || self::$logStream = fopen(self::$logFile, 'ab');

        // http://bugs.php.net/42098 workaround
        class_exists('Patchwork\Logger') || eval(';') || __autoload('Patchwork\Logger');
        $l = new Logger(self::$logStream, $_SERVER['REQUEST_TIME_FLOAT']);

        return $this->logger = $l;
    }

    static function handleToStringException(\Exception $e)
    {
        self::$caughtToStringException = $e;
        return null;
    }
}