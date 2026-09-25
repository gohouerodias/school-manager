@props(['user'])

@php
    $notifications = $user->notifications()->latest()->limit(15)->get();
    $unreadCount = $user->unreadNotifications()->count();
@endphp

<div class="notif-wrap" data-notifications-menu>
    <button type="button" class="notif-bell" data-notifications-menu-trigger aria-label="Notifications">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        @if ($unreadCount > 0)
            <span class="notif-badge">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
        @endif
    </button>

    <div class="notif-menu" data-notifications-menu-panel>
        <div class="notif-menu-head">Notifications</div>

        @if ($notifications->isEmpty())
            <p class="notif-empty">Aucune notification pour le moment.</p>
        @else
            <ul class="notif-list">
                @foreach ($notifications as $notification)
                    <li class="notif-item {{ $notification->read_at ? '' : 'unread' }}">
                        <form method="POST" action="{{ route('notifications.marquer-lu', $notification) }}">
                            @csrf
                            <button type="submit" class="notif-item-link">
                                <span class="notif-item-title">{{ $notification->data['titre'] ?? 'Notification' }}</span>
                                <span class="notif-item-message">{{ $notification->data['message'] ?? '' }}</span>
                                <span class="notif-item-date">{{ $notification->created_at->diffForHumans() }}</span>
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>

            @if ($unreadCount > 0)
                <form method="POST" action="{{ route('notifications.marquer-toutes-lues') }}" class="notif-mark-all">
                    @csrf
                    <button type="submit" class="btn ghost">Tout marquer comme lu</button>
                </form>
            @endif
        @endif
    </div>
</div>
