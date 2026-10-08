<?php

namespace App\Http\Controllers\Comptes;

use App\Enums\ProfilUtilisateur;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAccountsRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\User;
use App\Support\UserFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserAccountController extends Controller
{
    /**
     * Fixed page size for the account list; no longer user-configurable.
     */
    public const PER_PAGE = 50;

    /**
     * List accounts, filtered server-side by search/profil/statut (all
     * optional query params) so the pagination totals and page links always
     * match what is actually displayed, no matter which filters are active.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search', ''));
        $profilFilter = (string) $request->input('profil', '');
        $statutFilter = (string) $request->input('statut', '');

        // Always alphabetical by name, regardless of which filters are active.
        $query = UserFilters::apply(User::query()->orderBy('name'), $request);

        $users = $query->paginate(self::PER_PAGE)->withQueryString();

        $subtitle = sprintf(
            '%d comptes · %d administrateurs, %d secrétariat, %d enseignants, %d direction',
            User::query()->count(),
            User::query()->where('profil', ProfilUtilisateur::Administrateur)->count(),
            User::query()->where('profil', ProfilUtilisateur::AgentScolarite)->count(),
            User::query()->where('profil', ProfilUtilisateur::Enseignant)->count(),
            User::query()->where('profil', ProfilUtilisateur::Direction)->count(),
        );

        return view('comptes.index', [
            'users' => $users,
            'profils' => ProfilUtilisateur::cases(),
            'subtitle' => $subtitle,
            'search' => $search,
            'profilFilter' => $profilFilter,
            'statutFilter' => $statutFilter,
            'activeFilters' => array_filter(['search' => $search, 'profil' => $profilFilter, 'statut' => $statutFilter]),
            'breadcrumbs' => [
                'Tableau de bord' => route('dashboard'),
                'Gestion des comptes' => null,
            ],
        ]);
    }

    /**
     * Create one account per pending invite (name + telephone + email +
     * profil), each with a random, never-disclosed password: the new user
     * sets their own via the password-reset e-mail sent right after.
     */
    public function store(StoreAccountsRequest $request): RedirectResponse
    {
        $invites = collect($request->validated('invites'));

        $invites->each(function (array $invite): void {
            $user = User::create([
                'name' => $invite['name'],
                'telephone' => $invite['telephone'],
                'email' => $invite['email'],
                'password' => Str::password(32),
                'profil' => $invite['profil'],
                'doit_changer_mot_de_passe' => true,
            ]);

            Password::sendResetLink(['email' => $user->email]);
        });

        $count = $invites->count();

        return back()->with('toast', $count > 1
            ? "{$count} invitations envoyées avec succès"
            : '1 invitation envoyée avec succès');
    }

    /**
     * Update another user's name/e-mail. Reachable only by administrators
     * (route middleware 'profile:administrateur'), which is how the "only
     * an admin can change another user's e-mail" rule is enforced.
     */
    public function update(UpdateAccountRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        $user->update([
            'name' => $validated['name'],
            'telephone' => $validated['telephone'],
            'email' => $validated['email'],
        ]);

        return back()->with('toast', "Le profil de {$user->name} a été mis à jour.");
    }

    public function archiver(User $user): RedirectResponse
    {
        $user->archiverCompte();

        return back()->with('toast', "Le compte de {$user->name} a été archivé.");
    }

    public function reactiver(User $user): RedirectResponse
    {
        $user->reactiverCompte();

        return back()->with('toast', "Le compte de {$user->name} a été réactivé.");
    }

    /**
     * Re-sends the « Votre compte a été créé » activation e-mail to an
     * account still waiting for its first login (lost e-mail, expired
     * link…). Same mail as the initial invitation (see store()):
     * ResetPasswordNotification points to compte.activer while the account
     * is pending. Each new link replaces the previous one.
     */
    public function renvoyerInvitation(User $user): RedirectResponse
    {
        if (! $user->estEnAttenteActivation()) {
            return back()->with('toast', "Le compte de {$user->name} est déjà activé : aucun lien à renvoyer.");
        }

        $statut = Password::sendResetLink(['email' => $user->email]);

        if ($statut === Password::RESET_THROTTLED) {
            return back()->with('toast', "Un lien vient déjà d'être envoyé à {$user->email}. Patientez une minute avant de réessayer.");
        }

        return back()->with('toast', "Le lien d'activation a été renvoyé à {$user->email}.");
    }
}
