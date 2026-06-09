<?php

namespace App\Domains\Assets\Support;

use InvalidArgumentException;

class NetworkDiscoveryScanner
{
    public function __construct(private DeviceNameResolver $names)
    {
    }

    public function validateSubnet(string $subnet): void
    {
        $this->expandSubnet($subnet);
    }

    public function expandSubnet(string $subnet): array
    {
        $subnet = trim($subnet);

        if (filter_var($subnet, FILTER_VALIDATE_IP)) {
            return [$subnet];
        }

        if (! str_contains($subnet, '/')) {
            throw new InvalidArgumentException('Enter a valid IP address or CIDR subnet (e.g. 192.168.1.0/24).');
        }

        [$network, $prefix] = explode('/', $subnet, 2);
        $prefix = (int) $prefix;

        if (! filter_var($network, FILTER_VALIDATE_IP) || $prefix < 24 || $prefix > 30) {
            throw new InvalidArgumentException('Subnet must be a /24 to /30 CIDR range.');
        }

        $this->assertLikelyPrivateSubnet($network);

        $networkLong = ip2long($network);
        $hostCount = 2 ** (32 - $prefix);
        $ips = [];

        for ($offset = 1; $offset < $hostCount - 1; $offset++) {
            $ips[] = long2ip($networkLong + $offset);
        }

        return $ips;
    }

    public function probe(string $ip): array
    {
        $isReachable = $this->ping($ip);

        if (! $isReachable) {
            return [
                'reachable' => false,
                'hostname' => null,
                'mac_address' => null,
                'vendor' => null,
                'display_name' => null,
            ];
        }

        $macAddress = $this->macFromArp($ip);
        $identity = $this->names->resolve($ip, $macAddress);

        return [
            'reachable' => true,
            'hostname' => $identity['hostname'],
            'mac_address' => $macAddress,
            'vendor' => $identity['vendor'],
            'display_name' => $identity['display_name'],
        ];
    }

    private function assertLikelyPrivateSubnet(string $network): void
    {
        if (! filter_var($network, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return;
        }

        $parts = array_map('intval', explode('.', $network));
        $isPrivate = $parts[0] === 10
            || ($parts[0] === 172 && $parts[1] >= 16 && $parts[1] <= 31)
            || ($parts[0] === 192 && $parts[1] === 168);

        if (! $isPrivate) {
            throw new InvalidArgumentException('Use a private LAN subnet such as 192.168.31.0/24. Check that the full address starts with 192, not 92.');
        }
    }

    private function ping(string $ip): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $command = sprintf('ping -n 1 -w 1000 %s', escapeshellarg($ip));
        } else {
            $command = sprintf('ping -c 1 -W 1 %s', escapeshellarg($ip));
        }

        exec($command, $output, $returnCode);

        return $returnCode === 0;
    }

    private function macFromArp(string $ip): ?string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $command = sprintf('arp -a %s', escapeshellarg($ip));
        } else {
            $command = sprintf('arp -n %s 2>/dev/null', escapeshellarg($ip));
        }

        $output = shell_exec($command);

        if (! is_string($output)) {
            return null;
        }

        if (preg_match('/([0-9a-f]{1,2}(?::[0-9a-f]{1,2}){5})/i', $output, $matches)) {
            return strtolower($matches[1]);
        }

        return null;
    }
}
