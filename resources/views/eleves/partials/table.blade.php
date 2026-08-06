{{-- Table + pagination for the eleves list. Extracted from eleves/index.blade.php
     so EleveController::index() can also return just this fragment for the
     live-search AJAX requests (see resources/js/live-search.js), instead of
     the whole page. Needs $eleves, $obligatoireTypeIds and $classes. --}}
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
            // A fiche started via the wizard but not yet "Terminer"-ed: its
            // nom/prénom/classe may still be empty, so it gets its own
            // read-only badge (and a "Continuer" action) instead of the
            // Actif/Archivé dropdown and Classe/Documents columns, which
            // only make sense for a fiche whose infos are actually filled in.
            $isBrouillon = $eleve->statut === \App\Enums\StatutEleve::Brouillon;
            $inscriptionActive = $eleve->inscriptionActive();
            $missingCount = $obligatoireTypeIds->diff($eleve->documents->pluck('type_document_id'))->count();
            $photoIdentite = $eleve->photoIdentite();
        @endphp
        <tr @if ($isBrouillon) class="pending" @endif>
            <td class="name-cell">
                <div class="avatar avatar-neutral">
                    @if ($photoIdentite)
                        <img src="{{ route('eleves.documents.show', ['eleve' => $eleve, 'document' => $photoIdentite]) }}" alt="">
                    @else
                        {{ mb_strtoupper(mb_substr($eleve->nom ?? '?', 0, 1).mb_substr($eleve->prenom ?? '', 0, 1)) }}
                    @endif
                </div>
                <div class="info"><b>{{ $eleve->nom || $eleve->prenom ? $eleve->nomComplet() : 'Nouvelle fiche (brouillon)' }}</b><span>{{ $eleve->matricule ?: 'Matricule non renseigné' }}</span></div>
            </td>
            <td>
                @if ($isBrouillon)
                    <span class="classe-badge none">{{ $eleve->niveauSouhaite ? "{$eleve->niveauSouhaite->libelle} souhaité" : 'Non précisé' }}</span>
                @else
                    <select
                        class="filter-select classe-assign-select"
                        data-eleve-id="{{ $eleve->id }}"
                        data-eleve-nom="{{ $eleve->nomComplet() }}"
                        data-update-url="{{ route('eleves.classe.update', $eleve) }}"
                        @disabled($isArchived)
                    >
                        <option value="" @selected(! $inscriptionActive)>
                            Sans classe{{ ! $inscriptionActive && $eleve->niveauSouhaite ? " — {$eleve->niveauSouhaite->libelle} souhaité" : '' }}
                        </option>
                        @foreach ($classes as $classeOption)
                            <option value="{{ $classeOption->id }}" @selected($inscriptionActive?->classe_id === $classeOption->id)>
                                {{ $classeOption->niveau->libelle }} — {{ $classeOption->nom }}
                            </option>
                        @endforeach
                    </select>
                @endif
            </td>
            <td>
                @if ($isBrouillon)
                    <span class="status pending-status"><span class="dot"></span> Brouillon</span>
                @else
                    <select
                        class="filter-select statut-assign-select"
                        data-eleve-id="{{ $eleve->id }}"
                        data-eleve-nom="{{ $eleve->nomComplet() }}"
                        data-archiver-url="{{ route('eleves.archiver', $eleve) }}"
                        data-desarchiver-url="{{ route('eleves.desarchiver', $eleve) }}"
                    >
                        <option value="actif" @selected(! $isArchived)>Actif</option>
                        <option value="archive" @selected($isArchived)>Archivé</option>
                    </select>
                @endif
            </td>
            <td>{{ $eleve->created_at->format('d/m/Y') }}</td>
            <td>
                @if ($isBrouillon)
                    <span class="doc-status-badge manquant">— À compléter</span>
                @elseif ($missingCount === 0)
                    <span class="doc-status-badge complet">✓ Complet</span>
                @else
                    <span class="doc-status-badge manquant">⚠ {{ $missingCount }} manquant(s)</span>
                @endif
            </td>
            <td>
                @if ($isBrouillon)
                    <a href="{{ route('eleves.wizard.edit', $eleve) }}" class="btn ghost" style="padding:6px 12px; font-size:12px;">Continuer</a>
                @else
                    {{-- The row-actions "⋯" submenu (Consulter/Modifier/Archiver) was
                         dropped in favor of a single "consulter la fiche" trigger:
                         Statut and Classe are now changed directly from their own
                         editable columns, so the submenu only ever hid the fiche
                         shortcut behind an extra click. --}}
                    <button
                        type="button"
                        class="fiche-item-btn"
                        data-panel-open="fiche"
                        data-fiche-trigger
                        data-fiche-url="{{ route('eleves.fiche', $eleve) }}"
                        title="Consulter la fiche"
                    >
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                @endif
            </td>
        </tr>
    @endforeach
</x-data-table>

<x-pagination :paginator="$eleves" />
