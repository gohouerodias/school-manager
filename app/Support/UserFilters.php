<?php

namespace App\Support;

use App\Enums\ProfilUtilisateur;
use App\Enums\StatutUtilisateur;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Applies the account list's search/profil/statut filters to a User query
 * builder. Shared between UserAccountController::index() (the on-screen,
 * paginated list) and UserAccountExportController (Excel/PDF exports), so
 * "export the list" always means whatever is currently filtered on screen,
 * not always every account — the two must never drift apart.
 */
class UserFilters
{
    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public static function apply(Builder $query, Request $request): Builder
    {
        $search = trim((string) $request->input('search', ''));
        $profilFilter = (string) $request->input('profil', '');
        $statutFilter = (string) $request->input('statut', '');

        if ($search !== '') {
            $query->where(function (Builder $inner) use ($search) {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($profilFilter !== '' && ProfilUtilisateur::tryFrom($profilFilter) !== null) {
            $query->where('profil', $profilFilter);
        }

        if ($statutFilter === 'archive') {
            $query->where('statut', StatutUtilisateur::Archive);
        } elseif ($statutFilter === 'attente') {
            $query->where('statut', StatutUtilisateur::Actif)->whereNull('derniere_connexion_at');
        } elseif ($statutFilter === 'actif') {
            $query->where('statut', StatutUtilisateur::Actif)->whereNotNull('derniere_connexion_at');
        }

        return $query;
    }
}
