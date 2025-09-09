<?php

namespace Laravel\StaticAnalyzer\Debug;

use PhpParser\NodeAbstract;
use Throwable;

use function Laravel\Prompts\info;

class Debug
{
    public static $dump = false;

    public static $throw = false;

    public static $log = false;

    public static $currentlyInterested = false;

    protected static $dumpTimes = null;

    public static function log($message, $data = null)
    {
        if (! self::$log) {
            return;
        }

        if (is_array($data) || is_object($data)) {
            $data = json_encode($data, JSON_PRETTY_PRINT);
        }

        $formattedMessage = is_string($message) ? $message : json_encode($message);

        if ($data) {
            $formattedMessage .= PHP_EOL.$data;
        }

        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);

        info('[DEBUG] '.$backtrace[1]['class'].':'.($backtrace[1]['line'] ?? 0).PHP_EOL.$formattedMessage);
    }

    public static function throw(Throwable $e)
    {
        if (self::$throw) {
            throw $e;
        }
    }

    public static function interested(bool $interested = true)
    {
        self::$currentlyInterested = $interested;
    }

    public static function dumpTimes(int $times, ...$args)
    {
        self::$dumpTimes ??= $times;

        if (self::$dump) {
            if (self::$dumpTimes === 1) {
                dd(...$args);
            } else {
                dump(...$args);
                self::$dumpTimes--;
            }
        }
    }

    public static function ddFromClass(...$args)
    {
        if (self::$dump) {
            $trace = debug_backtrace(limit: 1);
            array_push($args, $trace[0]['file'].':'.$trace[0]['line']);
            exec('cursor '.$trace[0]['file'].' --reuse-window');
            dd(...$args);
        }
    }

    public static function dumpIfInterested(...$args)
    {
        if (self::$dump && self::$currentlyInterested) {
            $trace = debug_backtrace(limit: 1);

            dump($trace[0]['file'].':'.$trace[0]['line'], ...array_map(function ($a) {
                if ($a instanceof NodeAbstract) {
                    $a->setAttribute('parent', null);
                }

                return $a;
            }, $args));

            echo PHP_EOL.str_repeat('-', 80).PHP_EOL.PHP_EOL;
        }
    }

    public static function ddIfInterested(...$args)
    {
        if (self::$dump && self::$currentlyInterested) {
            $trace = debug_backtrace(limit: 1);

            dd($trace[0]['file'].':'.$trace[0]['line'], ...array_map(function ($a) {
                if ($a instanceof NodeAbstract) {
                    $a->setAttribute('parent', null);
                }

                return $a;
            }, $args));
        }
    }

    public static function trace($limit = 10)
    {
        return debug_backtrace(limit: $limit);
    }
}
