<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Apprenants — CSCMT</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; color: #33383A; font-size: 11px; }
        h1 { font-size: 16px; color: #26594A; margin: 0 0 4px; }
        p.meta { color: #787F82; margin: 0 0 18px; font-size: 10.5px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 7px 10px; border-bottom: 1px solid #E2DFD8; text-align: left; }
        thead th { background: #FAF8F2; text-transform: uppercase; font-size: 9px; letter-spacing: .03em; color: #787F82; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9.5px; font-weight: bold; background: #EDEAE0; color: #787F82; }
    </style>
</head>
<body>
    <h1>Liste des apprenants — CSC Madre Trinidad</h1>
    <p class="meta">Exporté le {{ now()->format('d/m/Y à H:i') }} — {{ $eleves->count() }} apprenants</p>

    <table>
        <thead>
            <tr>
                <th>Matricule</th>
                <th>Nom</th>
                <th>Prénom</th>
                <th>Sexe</th>
                <th>Date de naissance</th>
                <th>Classe</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($eleves as $eleve)
                <tr>
                    <td>{{ $eleve->matricule ?: '—' }}</td>
                    <td>{{ $eleve->nom ?: '—' }}</td>
                    <td>{{ $eleve->prenom ?: '—' }}</td>
                    <td>{{ $eleve->sexe === 'F' ? 'Féminin' : ($eleve->sexe === 'M' ? 'Masculin' : '—') }}</td>
                    <td>{{ $eleve->date_naissance?->format('d/m/Y') ?? '—' }}</td>
                    <td>{{ $eleve->inscriptions->first()?->classe?->nom ?? 'Sans classe' }}</td>
                    <td><span class="badge">{{ match ($eleve->statut) { \App\Enums\StatutEleve::Archive => 'Archivé', \App\Enums\StatutEleve::Brouillon => 'Brouillon', default => 'Actif' } }}</span></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
