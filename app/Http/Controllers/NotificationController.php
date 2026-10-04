<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('notifications/Index', [
            'notifications' => $request->user()->notifications()->paginate(15)
                ->through(fn (DatabaseNotification $notification) => self::present($notification)),
        ]);
    }

    public function recent(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->notifications()->limit(10)->get()
                ->map(fn (DatabaseNotification $notification) => self::present($notification))->values(),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['count' => $request->user()->unreadNotifications()->count()]);
    }

    public function read(Request $request, string $id): RedirectResponse
    {
        $user = $request->user();
        $notification = $user->notifications()->findOrFail($id);
        $notification->markAsRead();

        $reportId = $notification->data['report_id'] ?? null;
        $report = $reportId ? Report::find($reportId) : null;

        if ($reportId && ($report === null || ! $user->can('view', $report))) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Laporan tidak lagi ditugaskan kepada Anda.']);

            return redirect()->route($user->role === UserRole::Pic ? 'pic.tasks.index' : 'dashboard');
        }

        return redirect(self::safeUrl($notification->data['url'] ?? null) ?? route('dashboard'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }

    /** Only same-site relative paths: rejects absolute URLs, protocol-relative `//host` and backslash tricks. */
    private static function safeUrl(mixed $url): ?string
    {
        if (! is_string($url) || ! str_starts_with($url, '/') || str_starts_with($url, '//') || str_contains($url, '\\')) {
            return null;
        }

        return $url;
    }

    /** @return array<string, mixed> */
    private static function present(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'report_id' => $data['report_id'] ?? null,
            'ticket_number' => $data['ticket_number'] ?? null,
            'status' => $data['status'] ?? null,
            'message' => $data['message'] ?? '',
            'url' => $data['url'] ?? null,
            'read' => $notification->read_at !== null,
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
