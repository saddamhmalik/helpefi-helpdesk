<?php

namespace App\Domains\Tickets\Services;

use App\Domains\Tenancy\Services\TenantStorageResolver;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketAttachment;
use App\Domains\Tickets\Models\TicketMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TicketAttachmentService
{
    public function __construct(
        private TenantStorageResolver $storage,
    ) {
    }

    public function addToTicket(Ticket $ticket, int $userId, UploadedFile $file): TicketAttachment
    {
        $diskName = $this->storage->diskName();
        $path = $file->store('ticket-attachments', $diskName);

        return $ticket->attachments()->create([
            'user_id' => $userId,
            'filename' => $file->getClientOriginalName(),
            'path' => $path,
            'storage_disk' => $diskName,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize() ?: 0,
        ]);
    }

    public function hasMatching(Ticket $ticket, string $filename, int $size): bool
    {
        return $ticket->attachments()
            ->where('filename', $filename)
            ->where('size', $size)
            ->exists();
    }

    public function existingFingerprints(Ticket $ticket, array $filenames): Collection
    {
        if ($filenames === []) {
            return collect();
        }

        return $ticket->attachments()
            ->whereIn('filename', $filenames)
            ->get(['filename', 'size'])
            ->keyBy(fn (TicketAttachment $attachment) => $attachment->filename.'|'.$attachment->size);
    }

    public function addFromUpload(
        Ticket $ticket,
        TicketMessage $message,
        int $userId,
        UploadedFile $file,
    ): TicketAttachment {
        $diskName = $this->storage->diskName();
        $path = $file->store('ticket-attachments', $diskName);

        return $ticket->attachments()->create([
            'ticket_message_id' => $message->id,
            'user_id' => $userId,
            'filename' => $file->getClientOriginalName(),
            'path' => $path,
            'storage_disk' => $diskName,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize() ?: 0,
        ]);
    }

    public function addFromContent(
        Ticket $ticket,
        TicketMessage $message,
        string $filename,
        string $content,
        ?string $mimeType = null,
        ?int $userId = null,
    ): TicketAttachment {
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename) ?: 'attachment';
        $path = 'ticket-attachments/'.Str::uuid().'_'.$safeName;
        $diskName = $this->storage->diskName();
        $this->storage->disk()->put($path, $content);

        return $ticket->attachments()->create([
            'ticket_message_id' => $message->id,
            'user_id' => $userId,
            'filename' => $filename,
            'path' => $path,
            'storage_disk' => $diskName,
            'mime_type' => $mimeType,
            'size' => strlen($content),
        ]);
    }

    public function deleteStored(iterable $attachments): void
    {
        foreach ($attachments as $attachment) {
            if (! empty($attachment->path)) {
                $this->storage->delete($attachment->path, $attachment->storage_disk ?? null);
            }
        }
    }
}
