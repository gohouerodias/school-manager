{{-- Table + pagination for the tuteurs list. Extracted from tuteurs/index.blade.php
     so Tuteurs\TuteurController::index() can also return just this fragment
     for live-search AJAX requests (see resources/js/live-search.js), instead
     of the whole page. Needs $tuteurs. --}}
@if ($tuteurs->isEmpty())
    <p class="table-empty-state">Aucun tuteur ne correspond à votre recherche.</p>
@endif

<x-data-table id="tuteurs-table">
    <x-slot:head>
        <th>Tuteur / Parent</th>
        <th>Téléphone</th>
        <th>Email</th>
        <th>Enfants liés</th>
        <th></th>
    </x-slot:head>

    @foreach ($tuteurs as $tuteur)
        <tr>
            <td class="name-cell">
                <div class="avatar avatar-neutral">{{ mb_strtoupper(mb_substr($tuteur->nom, 0, 1).mb_substr($tuteur->prenom, 0, 1)) }}</div>
                <div class="info"><b>{{ $tuteur->nom }} {{ $tuteur->prenom }}</b></div>
            </td>
            <td>{{ $tuteur->telephone }}</td>
            <td>{{ $tuteur->email ?: '—' }}</td>
            <td>
                <button
                    type="button"
                    class="classe-badge"
                    data-panel-open="tuteur-enfants"
                    data-enfants-trigger
                    data-enfants-url="{{ route('tuteurs.enfants', $tuteur) }}"
                >
                    👁 {{ $tuteur->eleves_count }} {{ \Illuminate\Support\Str::plural('enfant', $tuteur->eleves_count) }}
                </button>
            </td>
            <td>
                <button
                    type="button"
                    class="fiche-item-btn"
                    data-panel-open="edit-tuteur-info"
                    data-edit-tuteur-info-trigger
                    data-edit-url="{{ route('tuteurs.update', $tuteur) }}"
                    data-edit-nom-prenom="{{ trim($tuteur->nom.' '.$tuteur->prenom) }}"
                    data-edit-telephone="{{ $tuteur->telephone }}"
                    data-edit-email="{{ $tuteur->email }}"
                    title="Modifier"
                >
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                </button>
            </td>
        </tr>
    @endforeach
</x-data-table>

<x-pagination :paginator="$tuteurs" />
