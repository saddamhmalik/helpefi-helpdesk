<?php

namespace Database\Seeders;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Contacts\Models\Organization;
use App\Domains\Contacts\Models\Tag;
use App\Domains\Tenancy\Support\BootstrapDemoContent;
use Illuminate\Database\Seeder;

class ContactLookupSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            ['name' => 'VIP', 'slug' => 'vip', 'color' => 'amber'],
            ['name' => 'Enterprise', 'slug' => 'enterprise', 'color' => 'purple'],
            ['name' => 'Trial', 'slug' => 'trial', 'color' => 'blue'],
        ];

        foreach ($tags as $tag) {
            Tag::query()->updateOrCreate(['slug' => $tag['slug']], $tag);
        }

        $org = Organization::query()->updateOrCreate(
            ['name' => 'Acme Inc'],
            [
                'website' => 'https://acme.example.com',
                'phone' => '+1 555 0100',
                'description' => 'Demo enterprise customer.',
            ],
        );

        $org->domains()->updateOrCreate(
            ['domain' => 'example.com'],
            ['domain' => 'example.com'],
        );

        $contact = Contact::query()->updateOrCreate(
            ['email' => BootstrapDemoContent::DEMO_CONTACT_EMAILS[0]],
            [
                'name' => 'Jane Customer',
                'phone' => '+1 555 0100',
                'organization_id' => $org->id,
            ],
        );

        if ($vipTag = Tag::query()->where('slug', 'vip')->first()) {
            $contact->tags()->syncWithoutDetaching([$vipTag->id]);
        }
    }
}
