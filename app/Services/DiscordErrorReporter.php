<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Posts to a Discord channel webhook as a native embed: reportable exceptions
 * through report(), and noteworthy events through alert().
 *
 * Does nothing when no webhook is configured, and never lets a delivery failure
 * escape — it would travel back into the exception handler and loop.
 */
class DiscordErrorReporter
{
    private const COLOR_ERROR = 0xED4245;

    /** Worth knowing about, but nothing is broken — amber rather than red. */
    private const COLOR_ALERT = 0xE67E22;

    public function report(Throwable $e): void
    {
        // A broken deploy turns every request into the same exception. Without this
        // the channel drowns in identical messages, Discord starts rejecting them,
        // and the alert that mattered is lost among the copies.
        $fingerprint = 'discord-report:'.md5($e::class.$e->getFile().$e->getLine());

        if (! Cache::add($fingerprint, true, now()->addMinutes(config('services.discord.repeat_minutes')))) {
            return;
        }

        $this->send($this->embed($e));
    }

    /**
     * A security-relevant event: nothing crashed, but somebody should know. Separate
     * from report() because there is no exception and no stack here.
     *
     * @param  array<string, string>  $fields  label => value
     */
    public function alert(string $title, string $description, array $fields = []): void
    {
        $this->send([
            'title' => Str::limit('['.config('app.name').'] '.$title, 250),
            'description' => Str::limit($description, 4000),
            'color' => self::COLOR_ALERT,
            'fields' => array_map(
                fn (string $name, string $value) => ['name' => $name, 'value' => Str::limit($value, 1024)],
                array_keys($fields),
                array_values($fields),
            ),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $embed
     */
    private function send(array $embed): void
    {
        $webhook = config('services.discord.webhook');

        if (empty($webhook)) {
            return;
        }

        try {
            Http::timeout(5)->post($webhook, ['embeds' => [$embed]]);
        } catch (Throwable) {
            // Reporting must never break the request, nor feed itself.
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function embed(Throwable $e): array
    {
        return [
            'title' => Str::limit('['.config('app.name').'] ERROR', 250),
            'description' => Str::limit($e->getMessage()."\n\n```\n".$this->trace($e)."\n```", 4000),
            'color' => self::COLOR_ERROR,
            'fields' => [
                ['name' => 'Type', 'value' => Str::limit($e::class, 1024)],
                ['name' => 'Location', 'value' => Str::limit($this->relative($e->getFile()).':'.$e->getLine(), 1024)],
                ['name' => 'Source', 'value' => Str::limit($this->source(), 1024)],
            ],
            'timestamp' => now()->toIso8601String(),
        ];
    }

    private function trace(Throwable $e): string
    {
        $lines = [];

        foreach (array_slice($e->getTrace(), 0, 6) as $i => $frame) {
            $location = isset($frame['file'])
                ? $this->relative($frame['file']).':'.($frame['line'] ?? '?')
                : '[internal]';
            $call = ($frame['class'] ?? '').($frame['type'] ?? '').($frame['function'] ?? '');

            $lines[] = "#{$i} {$location} {$call}";
        }

        return $lines === [] ? '(no stack trace)' : implode("\n", $lines);
    }

    private function relative(string $path): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
    }

    private function source(): string
    {
        if (app()->runningInConsole()) {
            $argv = array_slice($_SERVER['argv'] ?? [], 1);

            return 'cli: '.(implode(' ', $argv) ?: 'artisan');
        }

        return request()->method().' '.request()->fullUrl();
    }
}
