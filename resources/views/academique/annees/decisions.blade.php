@extends('layouts.app')

@section('title', 'Décisions de passage — '.$anneeAcademique->libelle)

@section('content')
<x-page-header
    :title="'Décisions de passage — '.$anneeAcademique->libelle"
    :subtitle="'Moyenne annuelle = moyenne des bulletins mensuels déjà validés. Seuil de passage actuel : '.number_format($seuil, 2).'/20 (voir Niveaux & matières → Paramètres académiques).'"
>
    <x-slot:actions>
        <a href="{{ route('academique.annees.show', $anneeAcademique) }}" class="btn ghost">← {{ $anneeAcademique->libelle }}</a>
    </x-slot:actions>
</x-page-header>

@if ($lignes->isNotEmpty())
    <div class="search" style="max-width:360px; margin-bottom:14px;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
        <input type="text" placeholder="Rechercher un apprenant ou une classe..." data-table-filter-for="decisions-table">
    </div>
@endif

<x-data-table id="decisions-table">
    <x-slot:head>
        <th>Apprenant</th>
        <th>Classe</th>
        <th>Moyenne annuelle</th>
        <th>Proposition</th>
        <th>Décision retenue</th>
        <th></th>
    </x-slot:head>

    @forelse ($lignes as $ligne)
        @php $inscription = $ligne['inscription']; @endphp
        <tr data-row data-search="{{ \Illuminate\Support\Str::lower($inscription->eleve->nomComplet().' '.$inscription->classe->nom) }}">
            <td><b>{{ $inscription->eleve->nomComplet() }}</b></td>
            <td>{{ $inscription->classe->nom }}</td>
            <td>{{ number_format($ligne['moyenne'], 2) }}/20</td>
            <td><span class="chip">{{ $ligne['proposition']->label() }}</span></td>
            <td>
                @if ($inscription->decision)
                    <span class="chip {{ $inscription->decision->value === 'exclu' ? 'chip-danger' : '' }}">{{ $inscription->decision->label() }}</span>
                    @if ($inscription->motif_decision)
                        <div class="hint">Motif : {{ $inscription->motif_decision }}</div>
                    @endif
                @else
                    <span class="chip">En attente</span>
                @endif
            </td>
            <td>
                <button
                    type="button"
                    class="row-edit"
                    title="Décider"
                    data-panel-open="edit-decision"
                    data-edit-decision-trigger
                    data-edit-url="{{ route('academique.inscriptions.decision.update', $inscription) }}"
                    data-edit-eleve="{{ $inscription->eleve->nomComplet() }}"
                    data-edit-moyenne="{{ number_format($ligne['moyenne'], 2) }}"
                    data-edit-proposition="{{ $ligne['proposition']->value }}"
                    data-edit-decision="{{ $inscription->decision?->value }}"
                    data-edit-motif="{{ $inscription->motif_decision }}"
                >✎</button>
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="6" class="table-empty-state">Aucun apprenant inscrit dans une classe de cette année pour l'instant.</td>
        </tr>
    @endforelse
    @if ($lignes->isNotEmpty())
        <tr data-table-empty-for="decisions-table" style="display:none;">
            <td colspan="6" class="table-empty-state">Aucun apprenant ne correspond à cette recherche.</td>
        </tr>
    @endif
</x-data-table>

{{-- Valider ou modifier la décision de passage --}}
<x-slide-panel id="edit-decision" title="Décision de passage">
    <form method="POST" action="{{ old('_edit_url', '') }}" id="edit-decision-form">
        @csrf
        @method('PATCH')
        <input type="hidden" name="_panel" value="edit-decision">
        <input type="hidden" name="_edit_url" id="edit-decision-edit-url" value="{{ old('_edit_url') }}">

        @error('decision')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('motif')
            <div class="alert-error">{{ $message }}</div>
        @enderror

        <div class="field">
            <label>Apprenant</label>
            <div class="field-static" id="edit-decision-eleve">—</div>
        </div>

        <div class="field">
            <label>Moyenne annuelle / proposition automatique</label>
            <div class="field-static" id="edit-decision-proposition">—</div>
        </div>

        <div class="field">
            <label for="edit-decision-select">Décision</label>
            <select class="role-select" id="edit-decision-select" name="decision" required>
                @foreach (\App\Enums\DecisionAnnuelle::cases() as $decision)
                    <option value="{{ $decision->value }}" @selected(old('decision') === $decision->value)>{{ $decision->label() }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="edit-decision-motif">Motif (obligatoire si vous modifiez la proposition)</label>
            <textarea id="edit-decision-motif" name="motif" placeholder="Ex : redoublement demandé par la famille malgré une moyenne suffisante.">{{ old('motif') }}</textarea>
        </div>
    </form>

    <x-slot:footer>
        <button type="button" class="btn ghost" data-panel-close="edit-decision">Annuler</button>
        <button type="submit" form="edit-decision-form" class="btn dark">Enregistrer</button>
    </x-slot:footer>
</x-slide-panel>
@endsection
