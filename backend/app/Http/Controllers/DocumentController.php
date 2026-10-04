<?php

namespace App\Http\Controllers;

use App\Models\SourceDocument;
use App\Models\Statement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController
{
    public function store(Request $r, Statement $statement)
    {
        abort_unless($statement->status === 'draft', 409);
        $r->validate(['file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:20480']);
        $f = $r->file('file');
        $hash = hash_file('sha256', $f->getRealPath());
        $existing = $statement->documents()->where('sha256', $hash)->first();
        if ($existing) {
            return $existing;
        }$path = $f->store('sources', 'local');

        return $statement->documents()->create(['filename' => $f->getClientOriginalName(), 'path' => $path, 'sha256' => $hash, 'mime' => $f->getMimeType()]);
    }

    public function show(SourceDocument $document)
    {
        return Storage::disk('local')->response($document->path, $document->filename, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
