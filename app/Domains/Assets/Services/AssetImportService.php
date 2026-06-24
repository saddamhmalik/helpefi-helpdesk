<?php

namespace App\Domains\Assets\Services;

use App\Domains\Assets\Models\Asset;
use App\Domains\Assets\Repositories\AssetRepository;
use App\Domains\Assets\Repositories\AssetTypeRepository;
use App\Domains\Billing\Contracts\FeatureEntitlementChecker;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Contacts\Models\Organization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AssetImportService
{
    public function __construct(
        private AssetRepository $assets,
        private AssetTypeRepository $types,
        private FeatureEntitlementChecker $entitlements,
    ) {
    }

    public function import(UploadedFile $file): array
    {
        $this->entitlements->assertFeature('assets');

        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw new InvalidArgumentException('Unable to read the uploaded file.');
        }

        $headers = fgetcsv($handle);

        if (! $headers) {
            fclose($handle);

            throw new InvalidArgumentException('CSV file is empty.');
        }

        $normalizedHeaders = array_map(fn ($header) => Str::slug(trim((string) $header), '_'), $headers);
        $required = ['name', 'type'];

        foreach ($required as $column) {
            if (! in_array($column, $normalizedHeaders, true)) {
                fclose($handle);

                throw new InvalidArgumentException('CSV must include Name and Type columns.');
            }
        }

        $created = 0;
        $skipped = 0;
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if (count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $data = $this->mapRow($normalizedHeaders, $row);

            if ($data['name'] === '' || $data['type'] === '') {
                $skipped++;

                continue;
            }

            $type = $this->types->findBySlug(Str::slug($data['type']))
                ?? $this->types->findByName($data['type']);

            if (! $type) {
                $skipped++;

                continue;
            }

            $contactId = null;
            $organizationId = null;

            if ($data['contact_email'] !== '') {
                $contact = Contact::query()->where('email', $data['contact_email'])->first();
                $contactId = $contact?->id;
                $organizationId = $contact?->organization_id;
            }

            if ($data['organization'] !== '') {
                $organizationId = Organization::query()->where('name', $data['organization'])->value('id') ?? $organizationId;
            }

            $payload = [
                'asset_type_id' => $type->id,
                'name' => $data['name'],
                'status' => $this->normalizeStatus($data['status']),
                'serial_number' => $data['serial_number'] ?: null,
                'contact_id' => $contactId,
                'organization_id' => $organizationId,
                'location' => $data['location'] ?: null,
                'ip_address' => $data['ip_address'] ?: null,
                'mac_address' => $data['mac_address'] ?: null,
                'hostname' => $data['hostname'] ?: null,
                'manufacturer' => $data['manufacturer'] ?: null,
                'model' => $data['model'] ?: null,
                'vendor' => $data['vendor'] ?: null,
                'purchase_cost' => $data['purchase_cost'] !== '' ? $data['purchase_cost'] : null,
                'purchased_at' => $data['purchased_at'] ?: null,
                'warranty_expires_at' => $data['warranty_expires_at'] ?: null,
                'notes' => $data['notes'] ?: null,
            ];

            if ($data['asset_tag'] !== '') {
                if (Asset::query()->where('asset_tag', $data['asset_tag'])->exists()) {
                    $skipped++;

                    continue;
                }

                $payload['asset_tag'] = $data['asset_tag'];
            }

            $this->assets->create($payload);
            $created++;
        }

        fclose($handle);

        return [
            'created' => $created,
            'skipped' => $skipped,
            'rows_processed' => $rowNumber - 1,
        ];
    }

    private function mapRow(array $headers, array $row): array
    {
        $values = array_pad($row, count($headers), '');
        $mapped = array_combine($headers, $values);

        return [
            'asset_tag' => trim((string) ($mapped['asset_tag'] ?? '')),
            'name' => trim((string) ($mapped['name'] ?? '')),
            'type' => trim((string) ($mapped['type'] ?? '')),
            'status' => trim((string) ($mapped['status'] ?? Asset::STATUS_IN_STOCK)),
            'serial_number' => trim((string) ($mapped['serial_number'] ?? '')),
            'contact_email' => trim((string) ($mapped['contact_email'] ?? '')),
            'organization' => trim((string) ($mapped['organization'] ?? '')),
            'location' => trim((string) ($mapped['location'] ?? '')),
            'ip_address' => trim((string) ($mapped['ip_address'] ?? '')),
            'mac_address' => trim((string) ($mapped['mac_address'] ?? '')),
            'hostname' => trim((string) ($mapped['hostname'] ?? '')),
            'manufacturer' => trim((string) ($mapped['manufacturer'] ?? '')),
            'model' => trim((string) ($mapped['model'] ?? '')),
            'vendor' => trim((string) ($mapped['vendor'] ?? '')),
            'purchase_cost' => trim((string) ($mapped['purchase_cost'] ?? '')),
            'purchased_at' => trim((string) ($mapped['purchased_at'] ?? '')),
            'warranty_expires_at' => trim((string) ($mapped['warranty_expires_at'] ?? '')),
            'notes' => trim((string) ($mapped['notes'] ?? '')),
        ];
    }

    private function normalizeStatus(string $status): string
    {
        $normalized = Str::slug($status, '_');

        return match ($normalized) {
            'in_use', 'in-use', 'assigned' => Asset::STATUS_IN_USE,
            'maintenance' => Asset::STATUS_MAINTENANCE,
            'retired' => Asset::STATUS_RETIRED,
            default => Asset::STATUS_IN_STOCK,
        };
    }
}
