<?php

namespace Laravel\Surveyor\Debug;

use PhpParser\NodeAbstract;
use Throwable;

use function Laravel\Prompts\info;
use function Laravel\Prompts\table;

class Debug
{
    public static $dump = false;

    public static $throw = false;

    public static $logLevel = 0;

    public static $currentlyInterested = false;

    public static $ide = 'cursor';

    protected static $dumpTimes = null;

    protected static $depths = [];

    protected static $paths = [];

    protected static $tracking = [];

    protected static $count = [];

    protected static $currentlyTiming = [];

    protected static $timings = [];

    public static function startTiming(string $label)
    {
        self::$currentlyTiming[$label] = microtime(true);

        return fn () => self::stopTiming($label);
    }

    public static function stopTiming(string $label)
    {
        self::$timings[$label] ??= [];
        self::$timings[$label][] = microtime(true) - self::$currentlyTiming[$label];
    }

    public static function getTimings()
    {
        $timings = [];

        foreach (self::$timings as $label => $timing) {
            $timings[$label] = [
                'count' => count($timing),
                'total' => array_sum($timing),
                'average' => array_sum($timing) / count($timing),
                'minimum' => min($timing),
                'maximum' => max($timing),
            ];
        }

        table(
            ['Label', 'Count', 'Total (s)', 'Average (s)', 'Min (s)', 'Max (s)'],
            array_map(
                fn ($label, $data) => [
                    $label,
                    $data['count'],
                    number_format($data['total'], 4),
                    number_format($data['average'], 4),
                    number_format($data['minimum'], 4),
                    number_format($data['maximum'], 4),
                ],
                array_keys($timings),
                $timings,
            ),
        );

        return $timings;
    }

    public static function error(Throwable $e, $message, $data = null, $level = 1)
    {
        self::log('🚨 '.$message.' ['.$e->getMessage().'] at '.$e->getFile().':'.$e->getLine(), $data, $level);
    }

    public static function throwOr(Throwable $e, callable $callback)
    {
        if (self::$throw) {
            throw $e;
        }

        return $callback();
    }

    public static function log($message, $data = null, $level = 1)
    {
        if (self::$logLevel < $level) {
            return;
        }

        $indent = str_repeat('    ', self::depth());

        if (is_array($data) || is_object($data)) {
            $data = implode(
                PHP_EOL,
                array_map(
                    fn ($line) => $indent.$line,
                    explode(PHP_EOL, json_encode($data, JSON_PRETTY_PRINT)),
                ),
            );
        }

        $formattedMessage = (is_string($message) ? $message : json_encode($message));

        if ($data) {
            $formattedMessage .= PHP_EOL.$data;
        }

        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);

        info($indent.'> '.$backtrace[1]['class'].':'.($backtrace[1]['line'] ?? 0).PHP_EOL.$indent.$formattedMessage);
    }

    public static function count(string $key)
    {
        self::$count[$key] ??= 0;
        self::$count[$key]++;
    }

    public static function getCounts()
    {
        asort(self::$count);

        return self::$count;
    }

    public static function addPath(string $path)
    {
        self::$paths[$path] = 0;
        self::$depths[$path] = 0;
    }

    public static function removePath(string $path)
    {
        unset(self::$paths[$path]);
    }

    public static function track(string $key, mixed $value, int $keep = 1)
    {
        self::$tracking[$key] ??= [];
        self::$tracking[$key][] = $value;

        if (count(self::$tracking[$key]) > $keep) {
            array_shift(self::$tracking[$key]);
        }
    }

    public static function getTracked()
    {
        return self::$tracking;
    }

    protected static function activePath()
    {
        $paths = array_keys(self::$paths);

        return end($paths) ?? null;
    }

    public static function depth()
    {
        return self::$depths[self::activePath()] ?? 0;
    }

    public static function reportMemoryUsage($print = true)
    {
        $memory = memory_get_usage(true);
        $memory = $memory / 1024 / 1024;
        $memory = round($memory, 2);
        $memory = $memory.'MB';

        if ($print) {
            info('Memory Usage: '.$memory);
        }

        return $memory;
    }

    public static function increaseDepth()
    {
        self::$depths[self::activePath()]++;
    }

    public static function decreaseDepth()
    {
        self::$depths[self::activePath()] = max(0, self::$depths[self::activePath()] - 1);
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

    public static function ddAndOpen(...$args)
    {
        if (self::$dump) {
            $trace = debug_backtrace(limit: 1);
            $marker = $trace[0]['file'].':'.$trace[0]['line'];

            array_unshift($args, 'DEBUG START: '.$marker);
            array_push($args, 'DEBUG END: '.$marker);

            $command = match (self::$ide) {
                default => self::$ide.' --goto --reuse-window '.$trace[0]['file'].':'.$trace[0]['line'],
            };

            exec($command);

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
        return array_map(fn ($t) => [
            'file' => $t['file'] ?? null,
            'line' => $t['line'] ?? null,
        ], debug_backtrace(limit: $limit));
    }
}
