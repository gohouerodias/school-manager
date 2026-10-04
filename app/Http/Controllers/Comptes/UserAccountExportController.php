<?php

namespace App\Http\Controllers\Comptes;

use App\Exports\UsersExport;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\UserFilters;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class UserAccountExportController extends Controller
{
    /**
     * Exports the same search/profil/statut filters as the on-screen list
     * (see UserAccountController::index()) — "export" always means "what's
     * currently filtered", not every account.
     */
    public function excel(Request $request): Response
    {
        return Excel::download(new UsersExport($request), 'comptes-utilisateurs.xlsx');
    }

    public function pdf(Request $request): Response
    {
        $users = UserFilters::apply(User::query()->orderBy('name'), $request)->get();

        return Pdf::loadView('comptes.export-pdf', ['users' => $users])
            ->download('comptes-utilisateurs.pdf');
    }
}
