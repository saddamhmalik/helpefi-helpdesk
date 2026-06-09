<?php

namespace App\Domains\Assets\Support;

class DeviceNameResolver
{
    public function resolve(string $ip, ?string $mac = null): array
    {
        $hostname = $this->resolveHostname($ip);
        $vendor = MacVendorLookup::lookup($mac);

        return [
            'hostname' => $hostname,
            'vendor' => $vendor,
            'display_name' => $this->displayName($ip, $hostname, $vendor),
        ];
    }

    public function displayName(string $ip, ?string $hostname, ?string $vendor): string
    {
        if ($hostname) {
            return $this->cleanHostname($hostname);
        }

        if ($vendor && $vendor !== 'Private / randomized MAC') {
            return "{$vendor} ({$ip})";
        }

        return "Device {$ip}";
    }

    private function resolveHostname(string $ip): ?string
    {
        $candidates = [
            $this->fromGetHostByAddr($ip),
            $this->fromDig($ip),
            $this->fromHostCommand($ip),
            $this->fromDnsSd($ip),
            $this->fromDscacheutil($ip),
        ];

        foreach ($candidates as $hostname) {
            if ($hostname) {
                return $hostname;
            }
        }

        return null;
    }

    private function fromGetHostByAddr(string $ip): ?string
    {
        $hostname = gethostbyaddr($ip);

        return $hostname !== $ip ? $hostname : null;
    }

    private function fromDig(string $ip): ?string
    {
        $command = sprintf('dig +short -x %s 2>/dev/null', escapeshellarg($ip));
        $output = shell_exec($command);

        if (! is_string($output) || trim($output) === '') {
            return null;
        }

        $line = trim(explode("\n", trim($output))[0]);

        return $this->cleanPtrRecord($line);
    }

    private function fromHostCommand(string $ip): ?string
    {
        $command = sprintf('host %s 2>/dev/null', escapeshellarg($ip));
        $output = shell_exec($command);

        if (! is_string($output)) {
            return null;
        }

        if (preg_match('/domain name pointer (.+)\.?$/mi', $output, $matches)) {
            return $this->cleanPtrRecord($matches[1]);
        }

        return null;
    }

    private function fromDnsSd(string $ip): ?string
    {
        if (PHP_OS_FAMILY !== 'Darwin') {
            return null;
        }

        $command = sprintf(
            'timeout 2 dns-sd -G v4v6 %s 2>/dev/null | head -5',
            escapeshellarg($ip)
        );
        $output = shell_exec($command);

        if (! is_string($output)) {
            return null;
        }

        if (preg_match('/^\s*\d+\s+(\S+)/m', $output, $matches)) {
            $candidate = rtrim($matches[1], '.');

            if (! filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }

        return null;
    }

    private function fromDscacheutil(string $ip): ?string
    {
        if (PHP_OS_FAMILY !== 'Darwin') {
            return null;
        }

        $command = sprintf('dscacheutil -q host -a ip_address %s 2>/dev/null', escapeshellarg($ip));
        $output = shell_exec($command);

        if (! is_string($output)) {
            return null;
        }

        if (preg_match('/^name:\s*(.+)$/mi', $output, $matches)) {
            return $this->cleanHostname($matches[1]);
        }

        return null;
    }

    private function cleanPtrRecord(string $value): ?string
    {
        $value = rtrim(trim($value), '.');

        if ($value === '' || filter_var($value, FILTER_VALIDATE_IP)) {
            return null;
        }

        return $this->cleanHostname($value);
    }

    private function cleanHostname(string $hostname): string
    {
        $hostname = rtrim(trim($hostname), '.');

        return str_ends_with(strtolower($hostname), '.local')
            ? substr($hostname, 0, -6)
            : $hostname;
    }
}
