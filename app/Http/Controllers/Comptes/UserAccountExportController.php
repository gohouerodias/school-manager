<?php

namespace App\Http\Controllers\Comptes;

use App\Exports\UsersExport;
use App\Http\Controllers\Controller;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class UserAccountExportController extends Controller
{
    public function excel(): Response
    {
        return Excel::download(new UsersExport(), 'comptes-utilisateurs.xlsx');
    }

    public function pdf(): Response
    {
        $users = User::query()->orderBy('name')->get();

        return Pdf::loadView('comptes.export-pdf', ['users' => $users])
            ->download('comptes-utilisateurs.pdf');
    }
}
