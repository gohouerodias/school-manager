@extends('layouts.app')

@section('title', 'Journal des actions')

@section('content')
<x-page-header
    title="Journal des actions"
    subtitle="Historique des actions enregistrées par le système (audit log), en lecture seule."
/>

<form method="GET" action="{{ route('administration.journal.index') }}" class="toolbar">
    <x-filter-select name="user_id" :selected="$utilisateurId" placeholder="Tous les utilisateurs" :options="$utilisateurs->pluck('name', 'id')->all()" />

    <x-filter-select name="action" :selected="$action" placeholder="Toutes les actions" :options="$actionsDisponibles->combine($actionsDisponibles)->all()" />

    <input type="date" name="date_debut" class="filter-select" value="{{ $dateDebut }}" onchange="this.form.submit()">
    <input type="date" name="date_fin" class="filter-select" value="{{ $dateFin }}" onchange="this.form.submit()">

    <button type="submit" class="btn ghost">Filtrer</button>

    @if ($utilisateurId || $action || $dateDebut || $dateFin)
        <a href="{{ route('administration.journal.index') }}" class="btn ghost">Réinitialiser</a>
    @endif
</form>

@if ($entrees->isEmpty())
    <p class="table-empty-state">Aucune action ne correspond à ces filtres.</p>
@endif

<x-data-table id="journal-actions-table">
    <x-slot:head>
        <th>Date &amp; heure</th>
        <th>Utilisateur</th>
        <th>Action</th>
        <th>Détails</th>
        <th>Adresse IP</th>
    </x-slot:head>

    @foreach ($entrees as $entree)
        <tr>
            <td>{{ $entree->date_heure->format('d/m/Y H:i') }}</td>
            <td>{{ $entree->user?->name ?? '—' }}</td>
            <td>{{ $entree->action }}</td>
            <td>{{ $entree->details ?? '—' }}</td>
            <td>{{ $entree->adresse_ip ?? '—' }}</td>
        </tr>
    @endforeach
</x-data-table>

<x-pagination :paginator="$entrees" />
@endsection
