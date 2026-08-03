{{-- Table + pagination for the eleves list. Extracted from eleves/index.blade.php
     so EleveController::index() can also return just this fragment for the
     live-search AJAX requests (see resources/js/live-search.js), instead of
     the whole page. Needs $eleves and $obligatoireTypeIds. --}}
@if ($eleves->isEmpty())
    <p class="table-empty-state">Aucun apprenant ne correspond à votre recherche.</p>
@endif

<x-data-table id="eleves-table">
    <x-slot:head>
        <th>Apprenant</th>
        <th>Classe</th>
        <th>Statut</th>
        <th>Date de création</th>
        <th>Documents</th>
        <th></th>
    </x-slot:head>

    @foreach ($eleves as $eleve)
        @php
            $isArchived = $eleve->statut === \App\Enums\StatutEleve::Archive;
            $inscription = $eleve->inscriptions->first();
            $missingCount = $obligatoireTypeIds->diff($eleve->documents->pluck('type_document_id'))->count();
            $champsMap = $eleve->valeursPersonnalisees->pluck('valeur', 'champ_personnalise_id');
            $photoIdentite = $eleve->photoIdentite();
        @endphp
        <tr>
            <td class="name-cell">
                <div class="avatar avatar-neutral">
                    @if ($photoIdentite)
                        <img src="{{ route('eleves.documents.show', ['eleve' => $eleve, 'document' => $photoIdentite]) }}" alt="">
                    @else
                        {{ mb_strtoupper(mb_substr($eleve->nom, 0, 1).mb_substr($eleve->prenom, 0, 1)) }}
                    @endif
                </div>
                <div class="info"><b>{{ $eleve->nomComplet() }}</b><span>{{ $eleve->matricule }}</span></div>
            </td>
            <td>
                @if ($inscription?->classe)
                    <span class="classe-badge">{{ $inscription->classe->niveau->libelle }} — {{ $inscription->classe->nom }}</span>
                @elseif ($eleve->niveauSouhaite)
                    <span class="classe-badge none">Sans classe — {{ $eleve->niveauSouhaite->libelle }} souhaité</span>
                @else
                    <span class="classe-badge none">Sans classe</span>
                @endif
            </td>
            <td>
                <span @class(['status', $isArchived ? 'archived' : 'active'])>
                    <span class="dot"></span>{{ $isArchived ? 'Archivé' : 'Actif' }}
                </span>
            </td>
            <td>{{ $eleve->created_at->format('d/m/Y') }}</td>
            <td>
                @if ($missingCount === 0)
                    <span class="doc-status-badge complet">✓ Complet</span>
                @else
                    <span class="doc-status-badge manquant">⚠ {{ $missingCount }} manquant(s)</span>
                @endif
            </td>
            <td>
                <x-action-menu>
                    <button
                        type="button"
                        data-panel-open="fiche"
                        data-fiche-trigger
                        data-fiche-url="{{ route('eleves.fiche', $eleve) }}"
                    >👁 Consulter la fiche</button>

                    @if ($isArchived)
                        <hr>
                        <form method="POST" action="{{ route('eleves.desarchiver', $eleve) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="positive">↺ Désarchiver</button>
                        </form>
                    @else
                        <button
                            type="button"
                            data-panel-open="edit-eleve"
                            data-edit-eleve-trigger
                            data-edit-url="{{ route('eleves.update', $eleve) }}"
                            data-edit-nom="{{ $eleve->nom }}"
                            data-edit-prenom="{{ $eleve->prenom }}"
                            data-edit-sexe="{{ $eleve->sexe }}"
                            data-edit-date-naissance="{{ $eleve->date_naissance->format('Y-m-d') }}"
                            data-edit-niveau-souhaite-id="{{ $eleve->niveau_souhaite_id }}"
                            data-edit-champs="{{ json_encode($champsMap) }}"
                        >✎ Modifier la fiche</button>
                        <hr>
                        <form method="POST" action="{{ route('eleves.archiver', $eleve) }}" onsubmit="return confirm('Archiver cette fiche ?');">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="danger">🗄 Archiver</button>
                        </form>
                    @endif
                </x-action-menu>
            </td>
        </tr>
    @endforeach
</x-data-table>

<x-pagination :paginator="$eleves" />
