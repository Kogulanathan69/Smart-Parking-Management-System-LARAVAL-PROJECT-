<?php

namespace App\Services;

use App\Models\EvidenceFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Photo evidence is stored on a private disk under a random name; never in /public.
 */
class EvidenceService
{
    public function __construct(private AuditLogger $audit) {}

    public function store(Model $attachable, UploadedFile $file, User $by): EvidenceFile
    {
        $disk = config('parking.evidence.disk');
        $uuid = (string) Str::uuid();
        $extension = strtolower($file->guessExtension() ?: 'bin');
        $path = $file->storeAs('evidence/'.now()->format('Y/m'), $uuid.'.'.$extension, ['disk' => $disk]);

        $evidence = EvidenceFile::create([
            'uuid' => $uuid,
            'attachable_type' => $attachable->getMorphClass(),
            'attachable_id' => $attachable->getKey(),
            'disk' => $disk,
            'path' => $path,
            'original_name' => Str::limit(preg_replace('/[^\w.\- ]/', '_', $file->getClientOriginalName()), 200, ''),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'uploaded_by' => $by->id,
        ]);
        $this->audit->log('evidence.uploaded', $evidence, [], ['attachable' => $attachable->getMorphClass().'#'.$attachable->getKey()]);

        return $evidence;
    }

    public function download(EvidenceFile $evidence)
    {
        $this->audit->log('evidence.viewed', $evidence);

        return Storage::disk($evidence->disk)->response($evidence->path, $evidence->uuid, [
            'Content-Type' => $evidence->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
