<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Sums up a day of storage/logs/requests-*.log (written by LogRequest): how many
 * requests and MySQL connections there were, the busiest seconds against Hostinger's
 * limit of 20 new connections a second, and who sent the most (IP, user, clinic,
 * endpoint, app or browser).
 */
class RequestsReport extends Command
{
    protected $signature = 'requests:report
                            {date? : Day to read, YYYY-MM-DD (default today)}
                            {--top=10 : Rows per table}
                            {--limit=20 : New connections a second the host allows}
                            {--tenant= : Only this clinic}';

    protected $description = 'Summarise the request log: busiest seconds, and who sends the most requests';

    public function handle(): int
    {
        $date = $this->argument('date') ?: now()->format('Y-m-d');
        $file = storage_path("logs/requests-{$date}.log");

        if (! is_file($file)) {
            $this->error("No request log for {$date} ({$file}).");

            return self::FAILURE;
        }

        $top = max(1, (int) $this->option('top'));
        $limit = max(1, (int) $this->option('limit'));
        $onlyTenant = $this->option('tenant');

        $total = ['requests' => 0, 'connections' => 0, 'persistent' => 0, 'retries' => 0, 'refused' => 0, 'errors' => 0, 'ms' => 0];
        $seconds = $minutes = $ips = $users = $tenants = $paths = $agents = $statuses = [];

        $handle = fopen($file, 'r');

        while (($line = fgets($handle)) !== false) {
            $r = json_decode($line, true);

            if (! is_array($r) || ! isset($r['t'])) {
                continue;
            }

            if ($onlyTenant && ($r['tn'] ?? null) !== $onlyTenant) {
                continue;
            }

            $c = (int) ($r['c'] ?? 0);
            $retries = (int) ($r['r'] ?? 0);
            $refused = (int) ($r['x'] ?? 0);
            $status = (int) ($r['st'] ?? 0);
            $ms = (int) ($r['ms'] ?? 0);

            $total['requests']++;
            $total['connections'] += $c;
            $total['persistent'] += (int) ($r['cp'] ?? 0);
            $total['retries'] += $retries;
            $total['refused'] += $refused;
            $total['errors'] += $status >= 500 ? 1 : 0;
            $total['ms'] += $ms;
            $statuses[$status] = ($statuses[$status] ?? 0) + 1;

            $second = substr($r['t'], 0, 19);
            $seconds[$second] ??= [0, 0, 0, 0];
            $seconds[$second][0]++;
            $seconds[$second][1] += $c;
            $seconds[$second][2] += $retries;
            $seconds[$second][3] += $refused;

            $minute = substr($r['t'], 0, 16);
            $minutes[$minute] = ($minutes[$minute] ?? 0) + 1;

            $this->add($ips, $r['ip'] ?? '-', $c, $ms, $r['tn'] ?? '-');
            $this->add($users, ($r['tn'] ?? '-').' / user '.($r['u'] ?? '-'), $c, $ms);
            $this->add($tenants, $r['tn'] ?? '-', $c, $ms);
            $this->add($paths, ($r['m'] ?? '').' '.($r['p'] ?? ''), $c, $ms);
            $this->add($agents, $this->agent($r['ua'] ?? ''), $c, $ms);
        }

        fclose($handle);

        if ($total['requests'] === 0) {
            $this->warn('No requests in the log'.($onlyTenant ? " for {$onlyTenant}" : '').'.');

            return self::SUCCESS;
        }

        $overLimit = count(array_filter($seconds, fn ($s) => $s[1] >= $limit));
        arsort($minutes);
        ksort($statuses);

        $this->info("Requests on {$date}".($onlyTenant ? " for {$onlyTenant}" : ''));
        $this->table(['', ''], [
            ['Requests', number_format($total['requests'])],
            ['Server errors (5xx)', number_format($total['errors'])],
            ['Status codes', collect($statuses)->map(fn ($n, $s) => "{$s}: {$n}")->implode(', ')],
            ['New MySQL connections', number_format($total['connections'])],
            ['Persistent connections (DB_PERSISTENT, mostly reused)', number_format($total['persistent'])],
            ['Connections retried', number_format($total['retries'])],
            ['Connections refused after all retries', number_format($total['refused'])],
            ["Seconds with {$limit}+ new connections", number_format($overLimit)],
            ['Busiest minute', array_key_first($minutes).' ('.reset($minutes).' requests)'],
            ['Average time', round($total['ms'] / $total['requests']).' ms'],
        ]);

        uasort($seconds, fn ($a, $b) => [$b[1], $b[0]] <=> [$a[1], $a[0]]);
        $this->info("Busiest seconds (new connections; the host allows {$limit})");
        $this->table(['Second', 'Requests', 'Connections', 'Retries', 'Refused'], collect($seconds)
            ->take($top)
            ->map(fn ($s, $second) => [$second, ...$s])
            ->values()
            ->all());

        $this->ranking('Top IP addresses', 'IP', $ips, $top, true);
        $this->ranking('Top users', 'Clinic / user', $users, $top);
        $this->ranking('Top clinics', 'Clinic', $tenants, $top);
        $this->ranking('Top endpoints', 'Endpoint', $paths, $top);
        $this->ranking('Clients', 'Client', $agents, $top);

        $this->line('Scheduled tasks and queue workers are not in this log; their connections count toward the same limit.');

        return self::SUCCESS;
    }

    private function add(array &$group, string $key, int $connections, int $ms, ?string $tenant = null): void
    {
        $group[$key] ??= ['requests' => 0, 'connections' => 0, 'ms' => 0, 'tenants' => []];
        $group[$key]['requests']++;
        $group[$key]['connections'] += $connections;
        $group[$key]['ms'] += $ms;

        if ($tenant !== null) {
            $group[$key]['tenants'][$tenant] = true;
        }
    }

    private function ranking(string $title, string $label, array $group, int $top, bool $withTenants = false): void
    {
        uasort($group, fn ($a, $b) => $b['requests'] <=> $a['requests']);

        $headers = [$label, 'Requests', 'Connections', 'Avg ms'];
        if ($withTenants) {
            $headers[] = 'Clinics';
        }

        $this->info($title);
        $this->table($headers, collect($group)->take($top)->map(function ($g, $key) use ($withTenants) {
            $row = [$key, $g['requests'], $g['connections'], round($g['ms'] / $g['requests'])];
            if ($withTenants) {
                $row[] = implode(', ', array_slice(array_keys($g['tenants']), 0, 5));
            }

            return $row;
        })->values()->all());
    }

    /**
     * Short client name from the user agent: the phone app (Dart) or a browser.
     */
    private function agent(string $ua): string
    {
        return match (true) {
            $ua === '' => '(none)',
            str_starts_with($ua, 'Dart') => 'Phone app (Dart)',
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'Chrome/') && str_contains($ua, 'Mobile') => 'Chrome (phone)',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Safari/') && (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad')) => 'Safari (iPhone/iPad)',
            str_contains($ua, 'Safari/') => 'Safari',
            default => mb_substr($ua, 0, 40),
        };
    }
}
