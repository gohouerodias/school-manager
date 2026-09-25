@extends('layouts.app')

@section('title', 'Rapports')

@section('content')
<x-page-header
    title="Rapports"
    subtitle="Statistiques d'effectifs, de résultats et d'archives, générées à la demande."
/>

<form method="GET" action="{{ route('rapports.index') }}" class="bulletins-toolbar">
    <select class="filter-select" name="type" onchange="this.form.submit()">
        <option value="">Choisir un type de rapport</option>
        @foreach ($types as $t)
            <option value="{{ $t->value }}" @selected($type === $t)>{{ $t->label() }}</option>
        @endforeach
    </select>

    @if ($type?->necessiteAnneeAcademique())
        <select class="filter-select" name="annee_academique_id" onchange="this.form.submit()">
            @forelse ($annees as $a)
                <option value="{{ $a->id }}" @selected($anneeAcademique?->id === $a->id)>{{ $a->libelle }}</option>
            @empty
                <option value="">Aucune année académique</option>
            @endforelse
        </select>
    @endif
</form>

@if (! $type)
    <div class="data-card" style="padding:24px;">
        <p style="margin:0;color:var(--ink-muted);font-size:13.5px;">Choisissez un type de rapport ci-dessus pour afficher ses statistiques.</p>
    </div>
@else
    <p class="hint" style="margin:-8px 0 16px;">{{ $type->description() }}</p>

    @if ($type->necessiteAnneeAcademique() && ! $anneeAcademique)
        <div class="alert-error">Aucune année académique disponible.</div>
    @elseif ($donnees)
        <div style="margin-bottom:16px;">
            <x-export-buttons
                :excel-route="route('rapports.export.excel', array_filter(['type' => $type->value, 'annee_academique_id' => $anneeAcademique?->id]))"
                :pdf-route="route('rapports.export.pdf', array_filter(['type' => $type->value, 'annee_academique_id' => $anneeAcademique?->id]))"
            />
        </div>

        @if ($type === \App\Enums\TypeRapport::Effectifs)
            <div class="rapport-summary-row">
                <div class="data-card rapport-summary-card">
                    <span class="rapport-summary-label">Total apprenants</span>
                    <span class="rapport-summary-value">{{ $donnees['total'] }}</span>
                </div>
                <div class="data-card rapport-summary-card">
                    <span class="rapport-summary-label">Féminin</span>
                    <span class="rapport-summary-value">{{ $donnees['par_sexe']['F'] ?? 0 }}</span>
                </div>
                <div class="data-card rapport-summary-card">
                    <span class="rapport-summary-label">Masculin</span>
                    <span class="rapport-summary-value">{{ $donnees['par_sexe']['M'] ?? 0 }}</span>
                </div>
                <div class="data-card rapport-summary-card">
                    <span class="rapport-summary-label">Sans classe</span>
                    <span class="rapport-summary-value">{{ $donnees['sans_classe'] }}</span>
                </div>
            </div>

            <x-data-table id="rapport-effectifs-table">
                <x-slot:head>
                    <th>Niveau</th>
                    <th>Classe</th>
                    <th>Effectif</th>
                </x-slot:head>

                @forelse ($donnees['par_classe'] as $ligne)
                    <tr>
                        <td>{{ $ligne['niveau'] }}</td>
                        <td>{{ $ligne['classe'] }}</td>
                        <td>{{ $ligne['effectif'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="table-empty-state">Aucune classe pour cette année académique.</td></tr>
                @endforelse
            </x-data-table>
        @elseif ($type === \App\Enums\TypeRapport::Resultats)
            <x-data-table id="rapport-resultats-table">
                <x-slot:head>
                    <th>Niveau</th>
                    <th>Classe</th>
                    <th>Effectif</th>
                    <th>Moyenne de classe</th>
                    <th>Taux Admis</th>
                    <th>Taux Redouble</th>
                    <th>Taux Exclu</th>
                </x-slot:head>

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
                    <tr><td colspan="7" class="table-empty-state">Aucune classe pour cette année académique.</td></tr>
                @endforelse
            </x-data-table>
        @elseif ($type === \App\Enums\TypeRapport::Archives)
            <x-data-table id="rapport-archives-table">
                <x-slot:head>
                    <th>Apprenant</th>
                    <th>Matricule</th>
                    <th>Date d'archivage</th>
                    <th>Dernière classe</th>
                </x-slot:head>

                @forelse ($donnees['lignes'] as $ligne)
                    <tr>
                        <td>{{ $ligne['nom_complet'] }}</td>
                        <td>{{ $ligne['matricule'] ?: '—' }}</td>
                        <td>{{ $ligne['date_archivage'] ?? '—' }}</td>
                        <td>{{ $ligne['derniere_classe'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="table-empty-state">Aucun apprenant archivé.</td></tr>
                @endforelse
            </x-data-table>
        @endif
    @endif
@endif
@endsection
