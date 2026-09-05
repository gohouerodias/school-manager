<?php

namespace App\Support;

/**
 * Écrit une archive ZIP minimale (méthode « stored », sans compression) en
 * PHP pur, sans dépendre de la classe ZipArchive (extension `zip`, pas
 * toujours activée par défaut — notamment sur certaines installations PHP
 * Windows/XAMPP) — voir BulletinGenerationService::genererPourClasse(), qui
 * doit pouvoir produire l'archive des bulletins d'une classe quelle que soit
 * la configuration du serveur, sans jamais planter faute d'extension
 * optionnelle. Les PDF étant déjà compressés en interne, l'absence de
 * compression ici n'a qu'un impact minime sur la taille de l'archive.
 *
 * Implémente le format ZIP standard (PKWARE APPNOTE) : un en-tête de fichier
 * local + les données par entrée, suivis d'un répertoire central puis de son
 * enregistrement de fin — lisible par n'importe quel outil ZIP (Explorateur
 * Windows, macOS Archive Utility, 7-Zip, ZipArchive lui-même, etc.).
 */
class ZipWriter
{
    /** @var array<int, array{nom: string, donnees: string}> */
    private array $entrees = [];

    public function ajouterFichier(string $nomDansLeZip, string $contenu): void
    {
        $this->entrees[] = ['nom' => $nomDansLeZip, 'donnees' => $contenu];
    }

    public function enregistrer(string $cheminDestination): void
    {
        $donneesLocales = '';
        $repertoireCentral = '';
        $decalage = 0;

        foreach ($this->entrees as $entree) {
            [$enteteLocal, $entreeCentrale] = $this->construireEntree($entree['nom'], $entree['donnees'], $decalage);
            $donneesLocales .= $enteteLocal;
            $repertoireCentral .= $entreeCentrale;
            $decalage += strlen($enteteLocal);
        }

        $finRepertoireCentral = pack(
            'VvvvvVVv',
            0x06054B50,          // signature de fin de répertoire central
            0,                   // numéro de ce disque
            0,                   // disque où débute le répertoire central
            count($this->entrees), // entrées sur ce disque
            count($this->entrees), // total des entrées
            strlen($repertoireCentral),
            $decalage,
            0                    // longueur du commentaire d'archive
        );

        file_put_contents($cheminDestination, $donneesLocales.$repertoireCentral.$finRepertoireCentral);
    }

    /**
     * @return array{0: string, 1: string} [en-tête local + données, entrée de répertoire central]
     */
    private function construireEntree(string $nom, string $contenu, int $decalage): array
    {
        $crc = crc32($contenu);
        $taille = strlen($contenu);
        [$tempsDos, $dateDos] = $this->dateHeureDos();

        $enteteLocal = pack(
            'VvvvvvVVVvv',
            0x04034B50, // signature d'en-tête de fichier local
            20,         // version nécessaire pour extraire (2.0)
            0,          // bit flags
            0,          // méthode de compression : 0 = stored (aucune)
            $tempsDos,
            $dateDos,
            $crc,
            $taille,    // taille compressée = taille réelle (stored)
            $taille,    // taille non compressée
            strlen($nom),
            0           // longueur du champ « extra »
        ).$nom.$contenu;

        $entreeCentrale = pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014B50, // signature d'en-tête de répertoire central
            20, 20,     // version « made by », version nécessaire
            0,          // bit flags
            0,          // méthode de compression
            $tempsDos,
            $dateDos,
            $crc,
            $taille,
            $taille,
            strlen($nom),
            0, 0, 0, 0, // extra, commentaire, disque de départ, attributs internes
            0,          // attributs externes
            $decalage   // décalage de l'en-tête local depuis le début de l'archive
        ).$nom;

        return [$enteteLocal, $entreeCentrale];
    }

    /**
     * @return array{0: int, 1: int} [heure au format DOS, date au format DOS]
     */
    private function dateHeureDos(): array
    {
        $temps = ((int) date('G') << 11) | ((int) date('i') << 5) | (int) ((int) date('s') / 2);
        $date = (((int) date('Y') - 1980) << 9) | ((int) date('n') << 5) | (int) date('j');

        return [$temps, $date];
    }
}
