<?php

namespace App\Http\Controllers\Eleves;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentEleveRequest;
use App\Models\DocumentNumerique;
use App\Models\Eleve;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    /**
     * Upload a document for an existing fiche élève, from the "Documents"
     * tab of the fiche modal. Stored on the private "local" disk (student
     * documents aren't public); format is cross-checked against the
     * selected type's `formats_acceptes` in StoreDocumentEleveRequest.
     */
    public function store(StoreDocumentEleveRequest $request, Eleve $eleve): RedirectResponse
    {
        $validated = $request->validated();

        $chemin = $request->file('fichier')->store('documents-eleves', 'local');

        DocumentNumerique::create([
            'eleve_id' => $eleve->id,
            'type_document_id' => $validated['type_document_id'],
            'televerse_par' => $request->user()->id,
            'chemin_fichier' => $chemin,
            'date_ajout' => now()->toDateString(),
        ]);

        return back()->with('toast', "Document ajouté à la fiche de {$eleve->nomComplet()}.");
    }

    /**
     * Delete a document from this fiche élève: removes both the stored
     * file and the database row.
     */
    public function destroy(Eleve $eleve, DocumentNumerique $document): RedirectResponse
    {
        abort_unless($document->eleve_id === $eleve->id, 404);

        Storage::disk('local')->delete($document->chemin_fichier);
        $document->delete();

        return back()->with('toast', "Document supprimé de la fiche de {$eleve->nomComplet()}.");
    }

    /**
     * Stream the stored file inline (opens in a new tab so the user can
     * read it — PDF/JPG/PNG all render natively in the browser).
     */
    public function show(Eleve $eleve, DocumentNumerique $document): StreamedResponse
    {
        return $this->fileResponse($eleve, $document, download: false);
    }

    /**
     * Stream the stored file as a forced download ("exporter").
     */
    public function download(Eleve $eleve, DocumentNumerique $document): StreamedResponse
    {
        return $this->fileResponse($eleve, $document, download: true);
    }

    private function fileResponse(Eleve $eleve, DocumentNumerique $document, bool $download): StreamedResponse
    {
        abort_unless($document->eleve_id === $eleve->id, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($document->chemin_fichier), 404, 'Le fichier stocké est introuvable.');

        $extension = pathinfo($document->chemin_fichier, PATHINFO_EXTENSION);
        $filename = Str::slug($document->typeDocument->libelle).($extension ? ".{$extension}" : '');

        return $download
            ? $disk->download($document->chemin_fichier, $filename)
            : $disk->response($document->chemin_fichier, $filename);
    }
}
