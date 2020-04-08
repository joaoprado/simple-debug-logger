<?php

namespace Joaoprado\SimpleDebugLogger;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class SimpleDebugLoggerServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->app->make('config')->set('logging.channels.query_logging', [
            'driver' => 'daily',
            'path' => storage_path('logs/queries.log'),
            'level' => 'debug',
            'days' => 30,
        ]);

        $this->enableQueryLogging();
        $this->enableJobsLogging();
    }

    protected function enableQueryLogging(): void
    {
        if(env('DEV_SQL_DEBUG', false) === false) {
            return;
        }

        DB::connection()->enableQueryLog();
        Event::listen(RequestHandled::class, function (RequestHandled $event) {
            $queries = DB::getQueryLog();
            $debug = [];
            $total = 0;
            if (!empty($queries)) {
                foreach ($queries as $query) {
                    $bindings = $query['bindings'];
                    $total += $query['time'];
                    $debug[] = "[{$query['time']}ms]: " . vsprintf(str_replace('?', '%s', $query['query']), $query['bindings']);
                }
                Log::channel('query_logging')->info([
                    'path' => $event->request->url(),
                    'total_queries' => count($debug),
                    'queries' => $debug,
                    'total' => $total,
                    'memoria' => $this->convert(memory_get_usage()),
                    'pico_de_memoria' => $this->convert(memory_get_peak_usage()),
                ]);
            }
        });
    }

    protected function enableJobsLogging(): void
    {
        if(env('DEV_JOB_DEBUG', false) === false) {
            return;
        }

        Queue::before(function ( JobProcessing $event ) {
            DB::connection()->enableQueryLog();

            Log::info(PHP_EOL . 'Job ready: ' . $event->job->resolveName());
            Log::info('Job started: ' . $event->job->resolveName());
        });

        Queue::after(function ( JobProcessed $event ) {
            Log::notice('Job done: ' . $event->job->resolveName() . PHP_EOL);

            $queries = DB::connection()->getQueryLog();
            $debug = [];
            $total = 0;
            if (!empty($queries)) {
                foreach ($queries as $query) {
                    $total += $query['time'];
                    $debug[] = "[{$query['time']}ms]: " . vsprintf(str_replace('?', '%s', $query['query']), $query['bindings']);
                }
                Log::channel('query_logging')->info([
                    'job' => $event->job->resolveName(),
                    'total_queries' => count($debug),
                    'queries' => $debug,
                    'total' => $total,
                    'memoria' => $this->convert(memory_get_usage()),
                    'pico_de_memoria' => $this->convert(memory_get_peak_usage()),
                ]);
            }

            DB::connection()->flushQueryLog();
        });
    }

    public function convert($size)
    {
        $unit=array('b','kb','mb','gb','tb','pb');
        return @round($size / (1024 ** ($i = floor(log($size, 1024)))),2) . ' ' . $unit[$i];
    }
}
