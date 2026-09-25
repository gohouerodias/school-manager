<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Cloche de notifications du topbar (voir components/notifications-menu.blade.php)
 * : accessible à tout utilisateur authentifié, chacun ne voyant/marquant que
 * ses propres notifications (voir Illuminate\Notifications\Notifiable sur
 * App\Models\User).
 */
class NotificationController extends Controller
{
    public function marquerLu(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        abort_unless($notification->notifiable_id === $request->user()->id
            && $notification->notifiable_type === $request->user()->getMorphClass(), 403);

        if (is_null($notification->read_at)) {
            $notification->markAsRead();
        }

        $url = $notification->data['url'] ?? null;

        return $url ? redirect($url) : back();
    }

    public function marquerToutesLues(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
