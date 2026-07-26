<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Comptes utilisateurs — CSCMT</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; color: #33383A; font-size: 11px; }
        h1 { font-size: 16px; color: #26594A; margin: 0 0 4px; }
        p.meta { color: #787F82; margin: 0 0 18px; font-size: 10.5px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 7px 10px; border-bottom: 1px solid #E2DFD8; text-align: left; }
        thead th { background: #FAF8F2; text-transform: uppercase; font-size: 9px; letter-spacing: .03em; color: #787F82; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9.5px; font-weight: bold; }
        .badge-admin { background: #FAE7E4; color: #C6392F; }
        .badge-scol { background: #E5F1EC; color: #3D8B75; }
        .badge-ens { background: #E7EEF6; color: #3B6EA5; }
        .badge-dir { background: #FCEEDA; color: #8A6717; }
    </style>
</head>
<body>
    <h1>Comptes utilisateurs — CSC Madre Trinidad</h1>
    <p class="meta">Exporté le {{ now()->format('d/m/Y à H:i') }} — {{ $users->count() }} comptes</p>

    <table>
        <thead>
            <tr>
                <th>Nom</th>
                <th>E-mail</th>
                <th>Profil</th>
                <th>Statut</th>
                <th>Dernière connexion</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($users as $user)
                @php
                    $badgeClass = match ($user->profil->value) {
                        'administrateur' => 'badge-admin',
                        'agent_scolarite' => 'badge-scol',
                        'enseignant' => 'badge-ens',
                        'direction' => 'badge-dir',
                        default => '',
                    };

                    $statutLabel = $user->statut === \App\Enums\StatutUtilisateur::Archive
                        ? 'Archivé'
                        : ($user->estEnAttenteActivation() ? 'Invitation en attente' : 'Actif');
                @endphp
                <tr>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td><span class="badge {{ $badgeClass }}">{{ $user->profil->label() }}</span></td>
                    <td>{{ $statutLabel }}</td>
                    <td>{{ $user->derniere_connexion_at?->format('d/m/Y H:i') ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
