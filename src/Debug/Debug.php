<?php

namespace Laravel\Surveyor\Debug;

use PhpParser\NodeAbstract;
use Throwable;

use function Laravel\Prompts\info;

class Debug
{
    public static $dump = false;

    public static $throw = false;

    public static $logLevel = 0;

    public static $currentlyInterested = false;

    protected static $dumpTimes = null;

    protected static $depth = 0;

    public static function log($message, $data = null, $level = 1)
    {
        if (self::$logLevel < $level) {
            return;
        }

        if (is_array($data) || is_object($data)) {
            $data = json_encode($data, JSON_PRETTY_PRINT);
        }

        $indent = str_repeat('    ', self::$depth);

        $formattedMessage = (is_string($message) ? $message : json_encode($message));

        if ($data) {
            $formattedMessage .= PHP_EOL.$indent.$data;
        }

        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);

        info($indent.'[DEBUG] '.$backtrace[1]['class'].':'.($backtrace[1]['line'] ?? 0).PHP_EOL.$indent.$formattedMessage);
    }

    public static function depth(int $depth)
    {
        self::$depth = $depth;
    }

    public static function increaseDepth()
    {
        self::$depth++;
    }

    public static function decreaseDepth()
    {
        self::$depth--;
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
