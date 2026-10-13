<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use Illuminate\Support\Facades\Storage;
final class MediaController
{
    public function show(string $path)
    {
        abort_unless(preg_match('~^[a-f0-9-]{36}\.(png|jpg|jpeg|webp)$~D', $path), 404);
        $disk = Storage::disk('media'); abort_unless($disk->exists($path), 404);
        $mime = $disk->mimeType($path);
        abort_unless(in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true), 404);
        return response()->file($disk->path($path), ['Content-Type' => $mime, 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'", 'Cache-Control' => 'public, max-age=86400']);
    }
}
