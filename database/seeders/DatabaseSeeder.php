<?php

namespace Database\Seeders;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Contacts\Models\Organization;
use App\Domains\Contacts\Models\Tag;
use App\Domains\Knowledge\Models\KnowledgeArticle;
use App\Domains\Knowledge\Models\KnowledgeCategory;
use App\Domains\Knowledge\Models\KnowledgeCollection;
use App\Domains\Sla\Services\SlaService;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Domains\Workforce\Models\Department;
use App\Domains\Workforce\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if ($this->shouldSeedCentral()) {
            $this->call(CentralDatabaseSeeder::class);

            return;
        }

        $this->call([
            TicketLookupSeeder::class,
            KnowledgeCategorySeeder::class,
            KnowledgeCollectionSeeder::class,
            SlaSeeder::class,
            ContactLookupSeeder::class,
            ChannelSeeder::class,
            EmailSeeder::class,
            EmailTemplateSeeder::class,
            ServiceCatalogSeeder::class,
            AssetSeeder::class,
            SecuritySeeder::class,
            NotificationSeeder::class,
            CsatSeeder::class,
            PermissionSeeder::class,
        ]);

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@helpdesk.test'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
            ],
        );
        $admin->assignRole('admin');

        $this->call(WorkforceSeeder::class);

        $this->call(ProductKnowledgeSeeder::class);

        if (app()->environment('local', 'testing')) {
            $this->seedDemoData($admin);
        }
    }

    private function seedDemoData(User $admin): void
    {
        $organization = Organization::query()->where('name', 'Acme Inc')->first();
        $vipTag = Tag::query()->where('slug', 'vip')->first();

        $contact = Contact::query()->updateOrCreate(
            ['email' => 'customer@example.com'],
            [
                'name' => 'Jane Customer',
                'phone' => '+1 555 0100',
                'organization_id' => $organization?->id,
            ],
        );

        if ($vipTag) {
            $contact->tags()->syncWithoutDetaching([$vipTag->id]);
        }

        $openStatus = TicketStatus::query()->where('slug', 'open')->first();
        $normalPriority = TicketPriority::query()->where('slug', 'normal')->first();
        $support = Department::query()->where('slug', 'support')->first();
        $tier1 = Team::query()->where('slug', 'tier-1')->first();

        $ticket = Ticket::query()->firstOrCreate(
            ['number' => 'HD-00001'],
            [
                'subject' => 'Cannot reset password',
                'description' => 'User reports password reset email never arrives.',
                'contact_id' => $contact->id,
                'assigned_to' => $admin->id,
                'department_id' => $support?->id,
                'team_id' => $tier1?->id,
                'ticket_status_id' => $openStatus->id,
                'ticket_priority_id' => $normalPriority->id,
            ],
        );

        app(SlaService::class)->applyToTicket($ticket);

        $category = KnowledgeCategory::query()->where('slug', 'getting-started')->first();
        $collection = KnowledgeCollection::query()->where('slug', 'getting-started')->first();

        if ($category && $collection && ! KnowledgeArticle::query()->where('slug', 'welcome-to-helpdesk')->exists()) {
            KnowledgeArticle::query()->create([
                'knowledge_category_id' => $category->id,
                'knowledge_collection_id' => $collection->id,
                'author_id' => $admin->id,
                'title' => 'Welcome to helpefi',
                'slug' => 'welcome-to-helpdesk',
                'excerpt' => 'A quick start guide for agents.',
                'body' => 'Use tickets to track customer issues, contacts to manage people, and the knowledge base for self-service articles.',
                'is_published' => true,
                'published_at' => now(),
            ]);
        }

        $customerUser = User::query()->updateOrCreate(
            ['email' => 'customer@example.com'],
            [
                'name' => 'Jane Customer',
                'password' => Hash::make('password'),
                'contact_id' => $contact->id,
            ],
        );
        $customerUser->syncRoles([]);
        $customerUser->assignRole('customer');
    }

    private function shouldSeedCentral(): bool
    {
        if (tenant('id')) {
            return false;
        }

        return config('database.default') === 'central';
    }
}
