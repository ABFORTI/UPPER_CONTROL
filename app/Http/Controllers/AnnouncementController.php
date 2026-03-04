<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AnnouncementView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AnnouncementController extends Controller
{
    public function pending(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;

        $announcement = Announcement::query()
            ->activeWithin()
            ->whereDoesntHave('views', function ($q) use ($userId) {
                $q->where('user_id', $userId)
                    ->where(function ($w) {
                        $w->whereNotNull('seen_at')->orWhere('dismissed_forever', true);
                    });
            })
            ->latest('created_at')
            ->first();

        if (!$announcement) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'body' => $announcement->body,
                'video_type' => $announcement->video_type,
                'video_url' => $announcement->video_url,
                'video_path' => $announcement->video_path,
                'starts_at' => optional($announcement->starts_at)?->toIso8601String(),
                'ends_at' => optional($announcement->ends_at)?->toIso8601String(),
                'video_src' => $this->videoSrcForRequest($announcement, $request),
            ],
        ]);
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

    public function seen(Request $request, Announcement $announcement): JsonResponse
    {
        AnnouncementView::updateOrCreate(
            [
                'announcement_id' => $announcement->id,
                'user_id' => (int) $request->user()->id,
            ],
            [
                'seen_at' => now(),
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function dismiss(Request $request, Announcement $announcement): JsonResponse
    {
        AnnouncementView::updateOrCreate(
            [
                'announcement_id' => $announcement->id,
                'user_id' => (int) $request->user()->id,
            ],
            [
                'seen_at' => now(),
                'dismissed_forever' => true,
            ]
        );

        return response()->json(['ok' => true]);
    }
}
