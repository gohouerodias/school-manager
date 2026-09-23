<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport — Archives — CSCMT</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; color: #33383A; font-size: 11px; }
        h1 { font-size: 16px; color: #26594A; margin: 0 0 4px; }
        p.meta { color: #787F82; margin: 0 0 18px; font-size: 10.5px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 7px 10px; border-bottom: 1px solid #E2DFD8; text-align: left; }
        thead th { background: #FAF8F2; text-transform: uppercase; font-size: 9px; letter-spacing: .03em; color: #787F82; }
    </style>
</head>
<body>
    <h1>Rapport — Apprenants archivés</h1>
    <p class="meta">CSC Madre Trinidad — Généré le {{ now()->format('d/m/Y à H:i') }} — {{ count($donnees['lignes']) }} apprenant(s)</p>

    <table>
        <thead>
            <tr>
                <th>Apprenant</th>
                <th>Matricule</th>
                <th>Date d'archivage</th>
                <th>Dernière classe</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($donnees['lignes'] as $ligne)
                <tr>
                    <td>{{ $ligne['nom_complet'] }}</td>
                    <td>{{ $ligne['matricule'] ?: '—' }}</td>
                    <td>{{ $ligne['date_archivage'] ?? '—' }}</td>
                    <td>{{ $ligne['derniere_classe'] }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Aucun apprenant archivé.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
