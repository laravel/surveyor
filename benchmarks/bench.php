<?php

/**
 * Wayfinder / Surveyor benchmark harness.
 *
 * Measures `php artisan wayfinder:generate` in two modes:
 *
 *   fresh — cache emptied and `--fresh` passed, so everything is parsed and
 *           analyzed again. This is the Surveyor number.
 *   warm  — cache filled by a discarded priming run, then measured. This is
 *           the cache-read and codegen number.
 *
 * Results are written to benchmarks/results/<label>.json.
 *
 * Usage:
 *   php surveyor/benchmarks/bench.php --label=baseline
 *   php surveyor/benchmarks/bench.php --label=after-my-change --mode=fresh --runs=5
 *
 * Options:
 *   --label       name for this measurement, used as the result filename
 *   --mode        fresh | warm | both (default: both)
 *   --runs        measured iterations per mode (default: 3)
 *   --warmups     discarded iterations before measuring (default: 1)
 *   --prime       0 to skip priming the output directory if it is already primed
 *   --app         path to the Laravel application
 *   --out         directory to generate into
 *   --cache-dir   Wayfinder cache directory to clear between fresh runs
 *
 * Only compare warm numbers measured back to back in the same session. A warm
 * run reads hundreds of megabytes of cache, so its timing depends on the OS page
 * cache, which this script does not control, and the same code can measure 7s or
 * 13s. A tight standard deviation does not help: a cold page cache is slow on
 * every iteration, so the number looks steady while still being wrong.
 *
 * To find which part of the code is slow, add temporary timers and measure
 * exclusive time, meaning elapsed time minus time spent inside nested timers.
 * Inclusive timings mislead here, because analysis recurses across files, so
 * whatever sits nearest the root looks like the bottleneck. Keep temporary
 * timers off the hottest paths, and remove them before trusting a total.
 */
final class Sample
{
    public function __construct(
        public readonly float $seconds,
        public readonly ?int $peakBytes,
    ) {}
}

final class Benchmark
{
    public function __construct(
        private readonly string $appPath,
        private readonly string $outputPath,
        private readonly string $cacheDirectory,
        private readonly int $runs,
        private readonly int $warmups,
    ) {}

    /**
     * Generate once up front so every measured run finds the output directory
     * in the same already-populated state.
     */
    public function primeOutputDirectory(): void
    {
        echo "Priming output directory...\n";

        $this->clearCache();
        $this->generate(fresh: true);
    }

    /**
     * @return array{samples: list<float>, timing: array<string, float>, peak_rss_bytes: int|null}
     */
    public function measure(string $mode): array
    {
        echo "\n=== {$mode} ===\n";

        $fresh = $mode === 'fresh';

        if (! $fresh) {
            // A run without --fresh fills the disk cache. Discard it so every
            // measured run reads a full cache.
            echo "Priming disk cache...\n";

            $this->clearCache();
            $this->generate(fresh: false);
        }

        for ($i = 0; $i < $this->warmups; $i++) {
            $this->print('warmup', $this->iterate($fresh));
        }

        /** @var list<Sample> $samples */
        $samples = [];

        for ($i = 1; $i <= $this->runs; $i++) {
            $this->print("run {$i}", $samples[] = $this->iterate($fresh));
        }

        $seconds = array_map(fn (Sample $sample) => $sample->seconds, $samples);
        $peaks = array_filter(array_map(fn (Sample $sample) => $sample->peakBytes, $samples));

        $timing = $this->summarise($seconds);

        printf(
            "  ── min %.2fs  median %.2fs  mean %.2fs  stddev %.2fs\n",
            $timing['min'],
            $timing['median'],
            $timing['mean'],
            $timing['stddev'],
        );

        return [
            'samples' => $seconds,
            'timing' => $timing,
            'peak_rss_bytes' => $peaks === [] ? null : min($peaks),
        ];
    }

    private function iterate(bool $fresh): Sample
    {
        if ($fresh) {
            $this->clearCache();
        }

        return $this->generate($fresh);
    }

    /**
     * Peak memory comes from `/usr/bin/time -l`, because a child process's peak
     * is not visible from this one.
     */
    private function generate(bool $fresh): Sample
    {
        $command = sprintf(
            '/usr/bin/time -l %s artisan wayfinder:generate --path=%s %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($this->outputPath),
            $fresh ? '--fresh' : '',
        );

        $descriptors = [
            ['file', '/dev/null', 'r'],
            ['file', '/dev/null', 'w'],
            ['pipe', 'w'],
        ];

        $startedAt = hrtime(true);

        $process = proc_open($command, $descriptors, $pipes, $this->appPath);

        if (! is_resource($process)) {
            $this->fail('Could not start the generate command.');
        }

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        $elapsed = (hrtime(true) - $startedAt) / 1e9;

        if ($exitCode !== 0) {
            $this->fail("The generate command exited with {$exitCode}:\n{$stderr}");
        }

        $peakBytes = preg_match('/(\d+)\s+maximum resident set size/', $stderr, $matches)
            ? (int) $matches[1]
            : null;

        return new Sample($elapsed, $peakBytes);
    }

    private function clearCache(): void
    {
        foreach (glob($this->cacheDirectory.'/*.cache') ?: [] as $file) {
            unlink($file);
        }
    }

    /**
     * @param  list<float>  $seconds
     * @return array<string, float>
     */
    private function summarise(array $seconds): array
    {
        sort($seconds);

        $count = count($seconds);
        $mean = array_sum($seconds) / $count;

        $median = $count % 2 === 1
            ? $seconds[intdiv($count, 2)]
            : ($seconds[$count / 2 - 1] + $seconds[$count / 2]) / 2;

        $variance = $count > 1
            ? array_sum(array_map(fn (float $value) => ($value - $mean) ** 2, $seconds)) / ($count - 1)
            : 0.0;

        return [
            'min' => $seconds[0],
            'max' => $seconds[$count - 1],
            'median' => $median,
            'mean' => $mean,
            'stddev' => sqrt($variance),
        ];
    }

    private function print(string $label, Sample $sample): void
    {
        printf(
            "  %-12s %6.2fs%s\n",
            $label,
            $sample->seconds,
            $sample->peakBytes === null ? '' : sprintf('   %6.0f MB', $sample->peakBytes / 1024 / 1024),
        );
    }

    private function fail(string $message): never
    {
        fwrite(STDERR, $message.PHP_EOL);

        exit(1);
    }
}

$options = getopt('', ['app::', 'label::', 'runs::', 'warmups::', 'mode::', 'out::', 'cache-dir::', 'prime::']);

$appPath = realpath($options['app'] ?? dirname(__DIR__, 2));

if ($appPath === false || ! is_file($appPath.'/artisan')) {
    fwrite(STDERR, "Could not locate a Laravel application. Pass --app=/path/to/app\n");

    exit(1);
}

$label = $options['label'] ?? 'run';
$outputPath = $options['out'] ?? sys_get_temp_dir().'/wayfinder-bench-out';
$resultsDirectory = __DIR__.'/results';

$modes = match ($options['mode'] ?? 'both') {
    'both' => ['fresh', 'warm'],
    'fresh' => ['fresh'],
    'warm' => ['warm'],
    default => null,
};

if ($modes === null) {
    fwrite(STDERR, "Unknown --mode. Expected one of: fresh, warm, both.\n");

    exit(1);
}

if (! is_dir($resultsDirectory)) {
    mkdir($resultsDirectory, 0755, true);
}

if (! is_dir($outputPath)) {
    mkdir($outputPath, 0755, true);
}

$benchmark = new Benchmark(
    appPath: $appPath,
    outputPath: $outputPath,
    cacheDirectory: $options['cache-dir'] ?? $appPath.'/storage/wayfinder-cache',
    runs: max(1, (int) ($options['runs'] ?? 3)),
    warmups: max(0, (int) ($options['warmups'] ?? 1)),
);

if (($options['prime'] ?? '1') === '0') {
    echo "Skipping output priming (--prime=0).\n";
} else {
    $benchmark->primeOutputDirectory();
}

$results = [];

foreach ($modes as $mode) {
    $results[$mode] = $benchmark->measure($mode);
}

$resultsFile = $resultsDirectory.'/'.$label.'.json';

file_put_contents($resultsFile, json_encode([
    'label' => $label,
    'recorded_at' => date('c'),
    'php_version' => PHP_VERSION,
    'app_path' => $appPath,
    'results' => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

echo "\nWrote {$resultsFile}\n";
