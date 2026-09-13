<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bulletin — {{ $eleve->nomComplet() }}</title>
    <style>
        {{-- 'DejaVu Sans' (police maison de dompdf, voir vendor/dompdf/dompdf/
             lib/fonts) plutôt que Helvetica/Arial : ces polices "core" PDF
             n'ont que l'encodage WinAnsi et affichent un "?" pour tout
             caractère hors de ce jeu — notamment ✕ (case cochée de la grille
             de domaines) et ☺ ○ ◐ (légende des symboles de résultat), qui ont
             besoin d'une police Unicode embarquée. --}}
        body { font-family: 'DejaVu Sans', sans-serif; color: #33383A; font-size: 14px; }
        .bulletin-page { padding: 20px 0; page-break-after: always; }
        .bulletin-page:last-child { page-break-after: auto; }

        table.bulletin-head-row { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table.bulletin-head-row td { vertical-align: top; padding: 0; border: none; }
        .bulletin-head-row h2 { font-size: 18px; margin: 0; color: #26594A; }
        .bulletin-head-row .school { text-align: right; font-size: 12.5px; color: #787F82; line-height: 1.4; }
        .bulletin-sub { font-size: 13.5px; color: #787F82; margin-bottom: 16px; }

        table.bulletin-grid2 { width: 100%; border-collapse: collapse; margin-bottom: 14px; font-size: 13.5px; }
        table.bulletin-grid2 td { border-bottom: 1px dotted #E2DFD8; padding: 4px 10px 4px 0; }
        table.bulletin-grid2 td.label { color: #787F82; width: 34%; }
        table.bulletin-grid2 td.val { color: #33383A; font-weight: bold; padding-right: 26px; }

        table.bulletin-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .bulletin-table th, .bulletin-table td { border: 1px solid #E2DFD8; padding: 6px 10px; font-size: 13.5px; text-align: left; }
        .bulletin-table th { background: #FAF8F2; font-weight: bold; font-size: 12px; text-transform: uppercase; color: #787F82; }
        .bulletin-table td.num, .bulletin-table th.num { text-align: center; }
        .bulletin-table tr.total-row td { font-weight: bold; background: #FAF7EE; }
        .bulletin-table-domaines th.num { text-transform: none; font-size: 13px; }
        .bulletin-table-domaines td.check-cell { font-weight: bold; font-size: 15px; color: #26594A; }
        .bulletin-legende { font-size: 11.5px; color: #787F82; margin-bottom: 4px; }
        .bulletin-nb { font-size: 11px; color: #787F82; font-style: italic; margin: 0 0 14px; }

        .bulletin-comment-box { border: 1px solid #E2DFD8; border-radius: 6px; padding: 10px 12px; margin-bottom: 14px; background: #FAF8F2; }
        .bulletin-comment-box .label { font-size: 11.5px; font-weight: bold; text-transform: uppercase; color: #B08D2B; margin-bottom: 6px; }
        .bulletin-symbol-row { margin-top: 8px; font-size: 13.5px; font-weight: bold; color: #26594A; }
        .bulletin-symbol-row .circle { width: 18px; height: 18px; border-radius: 50%; border: 2px solid #26594A; display: inline-block; text-align: center; line-height: 14px; font-size: 12px; margin-right: 8px; }
        .bulletin-symboles-legende { margin-top: 8px; font-size: 12.5px; }
        .bulletin-symboles-legende .titre { font-size: 11px; color: #787F82; margin-bottom: 4px; }
        .bulletin-symboles-legende .symbole-row { color: #787F82; padding: 2px 0; }
        .bulletin-symboles-legende .symbole-row .circle { width: 18px; height: 18px; border-radius: 50%; border: 2px solid #E2DFD8; display: inline-block; text-align: center; line-height: 14px; font-size: 12px; margin-right: 8px; }
        .bulletin-symboles-legende .symbole-row.is-actuel { color: #26594A; font-weight: bold; }
        .bulletin-symboles-legende .symbole-row.is-actuel .circle { border-color: #26594A; }

        table.bulletin-manual-grid { width: 100%; border-collapse: separate; border-spacing: 14px 0; margin-top: 8px; }
        table.bulletin-manual-grid td { width: 50%; vertical-align: top; padding: 0; }
        .bulletin-manual-zone { border: 1.5px dashed #E2DFD8; border-radius: 6px; padding: 12px; min-height: 90px; text-align: center; font-size: 12px; color: #787F82; font-style: italic; }
    </style>
</head>
<body>
    <div class="bulletin-page">
        @include('eleves.bulletins._papier_pdf')
    </div>
</body>
</html>
