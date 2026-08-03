<?php

namespace App\Support;

use App\Enums\StatutEleve;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Applies the éleves list's search/classe/statut/date de création filters
 * to an Eleve query builder. Shared between EleveController::index() (the
 * on-screen, paginated list) and EleveExportController (Excel/PDF exports),
 * so "export the list" always means whatever is currently filtered on
 * screen, not always the full roster — the two must never drift apart.
 */
class EleveFilters
{
    /**
     * @param  Builder<\App\Models\Eleve>  $query
     * @return Builder<\App\Models\Eleve>
     */
    public static function apply(Builder $query, Request $request): Builder
    {
        $search = trim((string) $request->input('search', ''));
        $classeFilter = (string) $request->input('classe', '');
        $statutFilter = (string) $request->input('statut', '');
        $dateFilter = (string) $request->input('date_creation', '');

        if ($search !== '') {
            $query->where(function ($inner) use ($search) {
                $inner->where('nom', 'like', "%{$search}%")
                    ->orWhere('prenom', 'like', "%{$search}%")
                    ->orWhere('matricule', 'like', "%{$search}%");
            });
        }

        if ($classeFilter === 'sans_classe') {
            $query->whereDoesntHave('inscriptions');
        } elseif ($classeFilter !== '') {
            $query->whereHas('inscriptions', fn ($q) => $q->where('classe_id', $classeFilter));
        }

        if ($statutFilter === 'archive') {
            $query->where('statut', StatutEleve::Archive);
        } elseif ($statutFilter === 'actif') {
            $query->where('statut', StatutEleve::Actif);
        }

        if ($dateFilter !== '') {
            $query->whereDate('created_at', $dateFilter);
        }

        return $query;
    }
}
