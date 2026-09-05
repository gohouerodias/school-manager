@php
    $profil = auth()->user()?->profil?->value;
    $isAdmin = $profil === \App\Enums\ProfilUtilisateur::Administrateur->value;
    $canSeeDossiers = in_array($profil, ['administrateur', 'agent_scolarite'], true);
    $dossiersOpen = request()->routeIs('eleves.*') || request()->routeIs('tuteurs.*');
    $academiqueOpen = request()->routeIs('academique.*');

    // Shortcut straight to the "Affectations enseignants" tab (see
    // annee-academique-show.js's initTabs() ?onglet= deep-link support) of
    // whichever année académique is relevant right now — the active one, or
    // else the most recent — so admins don't have to open "Années
    // académiques" then pick a year just to manage affectations.
    $anneeAffectations = $isAdmin
        ? \App\Models\AnneeAcademique::query()->where('est_active', true)->first()
            ?? \App\Models\AnneeAcademique::query()->orderByDesc('date_debut')->first()
        : null;
    $affectationsActive = request()->routeIs('academique.annees.show') && request()->query('onglet') === 'affectations';

    // Each entry mirrors a zone of the app (see the class/use-case diagrams).
    // "Gestion de compte", "Dossier élève et documents" and "Académique"
    // have real routes; the rest are the app's future feature areas and
    // render as inert placeholders until built.
    $items = [
        ['label' => 'Gestion de compte', 'icon' => 'users', 'route' => $isAdmin ? 'comptes.index' : null, 'pattern' => 'comptes.*'],
        ['label' => 'Sécurité et Administration', 'icon' => 'shield', 'route' => null, 'pattern' => null],
        ['label' => 'Rapports', 'icon' => 'rapports', 'route' => null, 'pattern' => null],
    ];

    $icons = [
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'shield' => '<rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'folder' => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"/>',
        'academique' => '<path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c0 1.5 2.5 3 6 3s6-1.5 6-3v-5"/>',
        'rapports' => '<path d="M3 3v18h18"/><path d="M18 17V9M13 17V5M8 17v-4"/>',
        'chevron' => '<path d="m6 9 6 6 6-6"/>',
    ];
@endphp

<aside class="sidebar" data-sidebar>
    <div class="sidebar-header">
        <button type="button" class="rail-burger" data-sidebar-toggle title="Réduire/agrandir le menu">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <img src="{{ asset('logo-cscmt.jpg') }}" alt="Complexe Scolaire Catholique Madre Trinidad" class="sidebar-logo">
        <span class="sidebar-wordmark">Registre CSCMT</span>
    </div>

    <div class="sidebar-inner">
        <nav>
            @if ($items[0]['route'])
                <a href="{{ route($items[0]['route']) }}" @class(['nav-item', 'active' => request()->routeIs($items[0]['pattern'])]) title="{{ $items[0]['label'] }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{!! $icons[$items[0]['icon']] !!}</svg>
                    <span class="nav-label">{{ $items[0]['label'] }}</span>
                </a>
            @else
                <span class="nav-item disabled" title="Bientôt disponible">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{!! $icons[$items[0]['icon']] !!}</svg>
                    <span class="nav-label">{{ $items[0]['label'] }}</span>
                </span>
            @endif

            @if ($canSeeDossiers)
                <div class="nav-group" data-nav-group>
                    <button
                        type="button"
                        class="nav-item nav-parent"
                        @class(['active' => $dossiersOpen])
                        data-nav-parent-toggle
                        aria-expanded="{{ $dossiersOpen ? 'true' : 'false' }}"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{!! $icons['folder'] !!}</svg>
                        <span class="nav-label">Dossier élève et documents</span>
                        <svg class="nav-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">{!! $icons['chevron'] !!}</svg>
                    </button>

                    <div class="nav-submenu" data-nav-submenu @if ($dossiersOpen) style="display:block;" @endif>
                        <a href="{{ route('eleves.index') }}" @class(['nav-subitem', 'active' => request()->routeIs('eleves.index')])>Liste des apprenants</a>
                        <a href="{{ route('tuteurs.index') }}" @class(['nav-subitem', 'active' => request()->routeIs('tuteurs.*')])>Liste des tuteurs</a>
                        <a href="{{ route('eleves.bulletins.index') }}" @class(['nav-subitem', 'active' => request()->routeIs('eleves.bulletins.*')])>Bulletins</a>
                        @if ($isAdmin)
                            <a href="{{ route('eleves.parametres.index') }}" @class(['nav-subitem', 'active' => request()->routeIs('eleves.parametres.*')])>Paramètres des dossiers</a>
                        @endif
                    </div>
                </div>
            @else
                <span class="nav-item disabled" title="Bientôt disponible">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{!! $icons['folder'] !!}</svg>
                    <span class="nav-label">Dossier élève et documents</span>
                </span>
            @endif

            @if ($isAdmin)
                <div class="nav-group" data-nav-group>
                    <button
                        type="button"
                        class="nav-item nav-parent"
                        @class(['active' => $academiqueOpen])
                        data-nav-parent-toggle
                        aria-expanded="{{ $academiqueOpen ? 'true' : 'false' }}"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{!! $icons['academique'] !!}</svg>
                        <span class="nav-label">Académique</span>
                        <svg class="nav-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">{!! $icons['chevron'] !!}</svg>
                    </button>

                    <div class="nav-submenu" data-nav-submenu @if ($academiqueOpen) style="display:block;" @endif>
                        <a href="{{ route('academique.annees.index') }}" @class(['nav-subitem', 'active' => request()->routeIs('academique.annees.*') && ! $affectationsActive])>Années académiques</a>
                        <a href="{{ route('academique.niveaux-matieres.index') }}" @class(['nav-subitem', 'active' => request()->routeIs('academique.niveaux-matieres.*')])>Niveaux &amp; matières</a>
                        <a href="{{ route('academique.examens.index') }}" @class(['nav-subitem', 'active' => request()->routeIs('academique.examens.*')])>Examens</a>
                        @if ($anneeAffectations)
                            <a href="{{ route('academique.annees.show', $anneeAffectations) }}?onglet=affectations" @class(['nav-subitem', 'active' => $affectationsActive])>Affectation des enseignants</a>
                        @else
                            <span class="nav-subitem disabled" title="Créez d'abord une année académique">Affectation des enseignants</span>
                        @endif
                    </div>
                </div>
            @else
                <span class="nav-item disabled" title="Bientôt disponible">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{!! $icons['academique'] !!}</svg>
                    <span class="nav-label">Académique</span>
                </span>
            @endif

            @foreach (array_slice($items, 1) as $item)
                <span class="nav-item disabled" title="Bientôt disponible">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{!! $icons[$item['icon']] !!}</svg>
                    <span class="nav-label">{{ $item['label'] }}</span>
                </span>
            @endforeach
        </nav>
    </div>
</aside>
