@php
    $links = [
        ['label' => 'Tableau de bord', 'route' => 'dashboard', 'pattern' => 'dashboard'],
    ];

    if (auth()->user()?->profil === \App\Enums\ProfilUtilisateur::Administrateur) {
        $links[] = ['label' => 'Gestion des comptes', 'route' => 'comptes.index', 'pattern' => 'comptes.*'];
    }
@endphp

<nav class="app-nav">
    @foreach ($links as $link)
        <a href="{{ route($link['route']) }}" @class(['app-nav-link', 'active' => request()->routeIs($link['pattern'])])>
            {{ $link['label'] }}
        </a>
    @endforeach
</nav>
