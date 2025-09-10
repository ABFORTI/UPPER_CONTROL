<?php
// app/Http/Controllers/Admin/BackupController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class BackupController extends Controller
{
    public function index()
    {
        $disk = Storage::disk('backups');
        $files = collect($disk->files())
            ->filter(fn($f)=>str_ends_with($f, '.zip'))
            ->map(fn($f)=>[
                'path' => $f,
                'name' => basename($f),
                'size' => $disk->size($f),
                'last_modified' => $disk->lastModified($f),
                'url'  => route('admin.backups.download', ['path'=>$f]),
            ])->sortByDesc('last_modified')->values();

        return Inertia::render('Admin/Backups/Index', [
            'files' => $files,
        ]);
    }

    public function download(Request $req)
    {
        $path = $req->string('path')->toString();
        abort_unless($path && Storage::disk('backups')->exists($path), 404);
        return Storage::disk('backups')->download($path);
    }

    public function run()
    {
        \Artisan::call('backup:run'); // completa (files+db)
        return back()->with('ok', 'Backup ejecutado. Revisa la lista en unos segundos.');
    }
}
