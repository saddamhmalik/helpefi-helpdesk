<?php

namespace App\Domains\Channels\Services;

use App\Domains\Channels\Models\EmailTemplate;
use App\Domains\Channels\Repositories\EmailTemplateRepository;
use App\Domains\Channels\Support\EmailPlaceholders;
use App\Domains\Channels\Support\EmailTemplateRegistry;
use Illuminate\Notifications\Messages\MailMessage;

class EmailTemplateService
{
    public function __construct(private EmailTemplateRepository $templates)
    {
    }

    public function ensureDefaults(): void
    {
        foreach (EmailTemplateRegistry::definitions() as $definition) {
            $this->templates->firstOrCreate($definition['slug'], [
                'name' => $definition['name'],
                'subject' => $definition['subject'],
                'body_html' => $definition['body_html'],
                'is_active' => true,
                'is_system' => true,
            ]);
        }
    }

    public function list(): array
    {
        $this->ensureDefaults();

        $registry = collect(EmailTemplateRegistry::definitions())->keyBy('slug');

        return $this->templates->all()
            ->map(function (EmailTemplate $template) use ($registry) {
                $meta = $registry->get($template->slug, []);

                return $this->present($template, $meta['trigger'] ?? null);
            })
            ->all();
    }

    public function find(int $id): array
    {
        $this->ensureDefaults();
        $template = $this->templates->find($id);
        $meta = EmailTemplateRegistry::find($template->slug);

        return [
            ...$this->present($template, $meta['trigger'] ?? null),
            'placeholders' => $meta['placeholders'] ?? [],
        ];
    }

    public function update(int $id, array $data): array
    {
        $this->ensureDefaults();
        $template = $this->templates->find($id);

        $updated = $this->templates->update($template, [
            'name' => $data['name'],
            'subject' => $data['subject'],
            'body_html' => $data['body_html'],
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        $meta = EmailTemplateRegistry::find($updated->slug);

        return $this->present($updated, $meta['trigger'] ?? null);
    }

    public function reset(int $id): array
    {
        $template = $this->templates->find($id);
        $defaults = EmailTemplateRegistry::find($template->slug);

        if ($defaults === null) {
            return $this->present($template, null);
        }

        $updated = $this->templates->update($template, [
            'name' => $defaults['name'],
            'subject' => $defaults['subject'],
            'body_html' => $defaults['body_html'],
            'is_active' => true,
        ]);

        return $this->present($updated, $defaults['trigger'] ?? null);
    }

    public function render(string $slug, array $variables): ?array
    {
        $this->ensureDefaults();
        $template = $this->templates->activeBySlug($slug);

        if ($template === null) {
            return null;
        }

        return [
            'subject' => EmailPlaceholders::render($template->subject, $variables),
            'body_html' => EmailPlaceholders::render($template->body_html, $variables),
        ];
    }

    public function renderSubject(string $slug, array $variables, string $fallback): string
    {
        return $this->render($slug, $variables)['subject'] ?? $fallback;
    }

    public function mailMessage(string $slug, array $variables, callable $fallback): MailMessage
    {
        $rendered = $this->render($slug, $variables);

        if ($rendered === null) {
            return $fallback();
        }

        return (new MailMessage)
            ->subject($rendered['subject'])
            ->view('mail.tenant-template', ['bodyHtml' => $rendered['body_html']]);
    }

    public function wrapHtml(string $body): string
    {
        if (str_contains($body, '<html')) {
            return $body;
        }

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head><body style="font-family:system-ui,-apple-system,sans-serif;line-height:1.6;color:#1e293b;max-width:600px;margin:0 auto;padding:24px;">'.$body.'</body></html>';
    }

    private function present(EmailTemplate $template, ?string $trigger): array
    {
        return [
            'id' => $template->id,
            'slug' => $template->slug,
            'name' => $template->name,
            'subject' => $template->subject,
            'body_html' => $template->body_html,
            'is_active' => $template->is_active,
            'is_system' => $template->is_system,
            'trigger' => $trigger,
            'updated_at' => $template->updated_at?->toIso8601String(),
        ];
    }
}
