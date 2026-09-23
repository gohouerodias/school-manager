<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport — Résultats — CSCMT</title>
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
    <h1>Rapport — Résultats — {{ $donnees['annee_academique']->libelle }}</h1>
    <p class="meta">CSC Madre Trinidad — Généré le {{ now()->format('d/m/Y à H:i') }}</p>

    <table>
        <thead>
            <tr>
                <th>Niveau</th>
                <th>Classe</th>
                <th>Effectif</th>
                <th>Moyenne de classe</th>
                <th>Taux Admis</th>
                <th>Taux Redouble</th>
                <th>Taux Exclu</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($donnees['par_classe'] as $ligne)
                <tr>
                    <td>{{ $ligne['niveau'] }}</td>
                    <td>{{ $ligne['classe'] }}</td>
                    <td>{{ $ligne['effectif'] }}</td>
                    <td>{{ $ligne['moyenne_classe'] !== null ? number_format($ligne['moyenne_classe'], 2).'/20' : '—' }}</td>
                    <td>{{ $ligne['taux_admis'] !== null ? $ligne['taux_admis'].'%' : '—' }}</td>
                    <td>{{ $ligne['taux_redouble'] !== null ? $ligne['taux_redouble'].'%' : '—' }}</td>
                    <td>{{ $ligne['taux_exclu'] !== null ? $ligne['taux_exclu'].'%' : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7">Aucune classe pour cette année académique.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
