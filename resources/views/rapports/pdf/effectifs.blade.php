<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport — Effectifs — CSCMT</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; color: #33383A; font-size: 11px; }
        h1 { font-size: 16px; color: #26594A; margin: 0 0 4px; }
        p.meta { color: #787F82; margin: 0 0 18px; font-size: 10.5px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        th, td { padding: 7px 10px; border-bottom: 1px solid #E2DFD8; text-align: left; }
        thead th { background: #FAF8F2; text-transform: uppercase; font-size: 9px; letter-spacing: .03em; color: #787F82; }
        .resume { display: table; width: 100%; margin-bottom: 18px; }
        .resume .cell { display: table-cell; padding: 10px 14px; border: 1px solid #E2DFD8; }
        .resume .cell b { display: block; font-size: 15px; color: #26594A; }
        .resume .cell span { font-size: 9.5px; text-transform: uppercase; color: #787F82; }
    </style>
</head>
<body>
    <h1>Rapport — Effectifs — {{ $donnees['annee_academique']->libelle }}</h1>
    <p class="meta">CSC Madre Trinidad — Généré le {{ now()->format('d/m/Y à H:i') }}</p>

    <div class="resume">
        <div class="cell"><b>{{ $donnees['total'] }}</b><span>Total apprenants</span></div>
        <div class="cell"><b>{{ $donnees['par_sexe']['F'] ?? 0 }}</b><span>Féminin</span></div>
        <div class="cell"><b>{{ $donnees['par_sexe']['M'] ?? 0 }}</b><span>Masculin</span></div>
        <div class="cell"><b>{{ $donnees['sans_classe'] }}</b><span>Sans classe</span></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Niveau</th>
                <th>Classe</th>
                <th>Effectif</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($donnees['par_classe'] as $ligne)
                <tr>
                    <td>{{ $ligne['niveau'] }}</td>
                    <td>{{ $ligne['classe'] }}</td>
                    <td>{{ $ligne['effectif'] }}</td>
                </tr>
            @empty
                <tr><td colspan="3">Aucune classe pour cette année académique.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
