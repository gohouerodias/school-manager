@php
    $isAdmin = auth()->user()?->profil === \App\Enums\ProfilUtilisateur::Administrateur;

    // Each entry mirrors a zone of the app (see the class/use-case diagrams).
    // Only "Gestion de compte" has a route today; the others are the app's
    // future feature areas and render as inert placeholders until built.
    $items = [
        ['label' => 'Gestion de compte', 'icon' => 'users', 'route' => $isAdmin ? 'comptes.index' : null, 'pattern' => 'comptes.*'],
        ['label' => 'Sécurité et Administration', 'icon' => 'shield', 'route' => null, 'pattern' => null],
        ['label' => 'Dossier élève et documents', 'icon' => 'folder', 'route' => null, 'pattern' => null],
        ['label' => 'Académique', 'icon' => 'academique', 'route' => null, 'pattern' => null],
        ['label' => 'Rapports', 'icon' => 'rapports', 'route' => null, 'pattern' => null],
    ];

    $icons = [
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'shield' => '<rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'folder' => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"/>',
        'academique' => '<path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c0 1.5 2.5 3 6 3s6-1.5 6-3v-5"/>',
        'rapports' => '<path d="M3 3v18h18"/><path d="M18 17V9M13 17V5M8 17v-4"/>',
    ];
@endphp

<aside class="sidebar" data-sidebar>
    <div class="sidebar-header">
        <button type="button" class="rail-burger" data-sidebar-toggle title="Réduire/agrandir le menu">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <div class="sidebar-logo">CSCMT</div>
        <span class="sidebar-wordmark">Registre CSCMT</span>
    </div>

    <div class="sidebar-inner">
        <nav>
            @foreach ($items as $item)
                @if ($item['route'])
                    <a
                        href="{{ route($item['route']) }}"
                        @class(['nav-item', 'active' => request()->routeIs($item['pattern'])])
                        title="{{ $item['label'] }}"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{!! $icons[$item['icon']] !!}</svg>
                        <span class="nav-label">{{ $item['label'] }}</span>
                    </a>
                @else
                    <span class="nav-item disabled" title="Bientôt disponible">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{!! $icons[$item['icon']] !!}</svg>
                        <span class="nav-label">{{ $item['label'] }}</span>
                    </span>
                @endif
            @endforeach
        </nav>
    </div>
</aside>
