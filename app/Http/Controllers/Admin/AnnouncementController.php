<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAnnouncementRequest;
use App\Http\Requests\Admin\UpdateAnnouncementRequest;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $q = Announcement::query()
            ->with('creator:id,name')
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%' . trim((string) $request->query('search')) . '%';
                $query->where(function ($w) use ($term) {
                    $w->where('title', 'like', $term)->orWhere('body', 'like', $term);
                });
            })
            ->when($request->filled('active'), function ($query) use ($request) {
                $query->where('is_active', (bool) ((int) $request->query('active')));
            })
            ->latest('created_at');

        $data = $q->paginate(12)->withQueryString();

        $data->getCollection()->transform(function (Announcement $item) use ($request) {
            return [
                'id' => $item->id,
                'title' => $item->title,
                'video_type' => $item->video_type,
                'is_active' => (bool) $item->is_active,
                'starts_at' => optional($item->starts_at)?->format('Y-m-d H:i'),
                'ends_at' => optional($item->ends_at)?->format('Y-m-d H:i'),
                'created_at' => optional($item->created_at)?->format('Y-m-d H:i'),
                'created_by_name' => $item->creator?->name,
                'video_src' => $this->videoSrcForRequest($item, $request),
            ];
        });

        return Inertia::render('Admin/Announcements/Index', [
            'data' => $data,
            'filters' => $request->only(['search', 'active']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Announcements/Create', [
            'maxUploadMb' => round(((int) config('announcements.max_upload_kb', 204800)) / 1024, 2),
        ]);
    }

    public function store(StoreAnnouncementRequest $request)
    {
        $data = $request->validated();

        $announcement = new Announcement();
        $announcement->title = trim((string) $data['title']);
        $announcement->body = $this->sanitizeBody($data['body'] ?? null);
        $announcement->video_type = $data['video_type'];
        $announcement->starts_at = $data['starts_at'] ?? null;
        $announcement->ends_at = $data['ends_at'] ?? null;
        $announcement->is_active = (bool) ($data['is_active'] ?? true);
        $announcement->created_by = (int) $request->user()->id;

        if ($announcement->video_type === 'upload') {
            $announcement->video_path = $request->file('video_file')?->store('announcements', 'public');
            $announcement->video_url = null;
        } else {
            $announcement->video_url = trim((string) ($data['video_url'] ?? ''));
            $announcement->video_path = null;
        }

        $announcement->save();

        return redirect()->route('admin.announcements.index')->with('ok', 'Anuncio creado correctamente.');
    }

    public function edit(Announcement $announcement)
    {
        return Inertia::render('Admin/Announcements/Edit', [
            'announcement' => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'body' => $announcement->body,
                'video_type' => $announcement->video_type,
                'video_url' => $announcement->video_url,
                'video_path' => $announcement->video_path,
                'video_src' => $this->videoSrcForRequest($announcement, request()),
                'starts_at' => optional($announcement->starts_at)?->format('Y-m-d\\TH:i'),
                'ends_at' => optional($announcement->ends_at)?->format('Y-m-d\\TH:i'),
                'is_active' => (bool) $announcement->is_active,
            ],
            'maxUploadMb' => round(((int) config('announcements.max_upload_kb', 204800)) / 1024, 2),
        ]);
    }

    public function update(UpdateAnnouncementRequest $request, Announcement $announcement)
    {
        $data = $request->validated();

        $previousPath = $announcement->video_path;
        $newType = $data['video_type'];

        $announcement->title = trim((string) $data['title']);
        $announcement->body = $this->sanitizeBody($data['body'] ?? null);
        $announcement->video_type = $newType;
        $announcement->starts_at = $data['starts_at'] ?? null;
        $announcement->ends_at = $data['ends_at'] ?? null;
        $announcement->is_active = (bool) ($data['is_active'] ?? false);

        if ($newType === 'upload') {
            if ($request->hasFile('video_file')) {
                $announcement->video_path = $request->file('video_file')->store('announcements', 'public');
                if ($previousPath) {
                    Storage::disk('public')->delete($previousPath);
                }
            }
            $announcement->video_url = null;
        } else {
            $announcement->video_url = trim((string) ($data['video_url'] ?? ''));
            $announcement->video_path = null;
            if ($previousPath) {
                Storage::disk('public')->delete($previousPath);
            }
        }

        $announcement->save();

        return redirect()->route('admin.announcements.index')->with('ok', 'Anuncio actualizado correctamente.');
    }

    public function destroy(Announcement $announcement)
    {
        if ($announcement->video_path) {
            Storage::disk('public')->delete($announcement->video_path);
        }

        $announcement->delete();

        return back()->with('ok', 'Anuncio eliminado correctamente.');
    }

    private function sanitizeBody(?string $body): ?string
    {
        if ($body === null) {
            return null;
        }

        $value = trim($body);
        if ($value === '') {
            return null;
        }

        return strip_tags($value);
    }

    private function videoSrcForRequest(Announcement $announcement, Request $request): ?string
    {
        if ($announcement->video_type !== 'upload') {
            return $announcement->videoSrc();
        }

        if (!$announcement->video_path) {
            return null;
        }

        $storagePath = Storage::url($announcement->video_path);
        $base = rtrim((string) $request->getBaseUrl(), '/');

        if ($base !== '' && str_starts_with($storagePath, '/')) {
            return $base . $storagePath;
        }

        return $storagePath;
    }
}
