<?php

namespace App\Support;

/**
 * Pools of realistic Béninois names/places used by factories and seeders
 * so fake data feels representative of CSC Madre Trinidad's context.
 */
class BeninData
{
    /** @var list<string> */
    public static array $prenomsMasculins = [
        'Kokou', 'Kossi', 'Fiacre', 'Rodrigue', 'Gildas', 'Espoir', 'Emmanuel', 'Judicaël',
        'Freddy', 'Landry', 'Steeve', 'Yves', 'Bienvenu', 'Coffi', 'Sègbédji', 'Aristide',
        'Franck', 'Roméo', 'Ulrich', 'Christian', 'Dieudonné', 'Gabin', 'Prince', 'Wilfried',
    ];

    /** @var list<string> */
    public static array $prenomsFeminins = [
        'Chimène', 'Bénie', 'Grâce', 'Rachida', 'Sandrine', 'Divine', 'Aïcha', 'Nadège',
        'Prudence', 'Rosine', 'Sefako', 'Edwige', 'Mahoutin', 'Carine', 'Sonagnon', 'Pélagie',
        'Fifamè', 'Sêmèvo', 'Winnie', 'Bertille', 'Reine', 'Josiane', 'Adjoavi', 'Mireille',
    ];

    /** @var list<string> */
    public static array $noms = [
        'Adjovi', 'Houngbédji', 'Zannou', 'Agbodjan', 'Dossou', 'Kpossou', 'Sossou', 'Gbaguidi',
        'Ahouansou', 'Tossou', 'Amoussou', 'Houessou', 'Adjahoui', 'Sagbo', 'Djossou', 'Codjo',
        'Aïhounton', 'Dègbey', 'Hounkpatin', 'Lokossou', 'Sanni', 'Alassane', 'Boni', 'Yaya',
        'Adéyèmi', 'Fanou', 'Gnonlonfoun', 'Kpadonou', 'Mensah', 'Zinsou',
    ];

    /** @var list<string> */
    public static array $villes = [
        'Cotonou', 'Porto-Novo', 'Abomey-Calavi', 'Parakou', 'Bohicon', 'Lokossa', 'Ouidah',
        'Natitingou', 'Djougou', 'Abomey', 'Come', 'Pobè',
    ];

    public static function nomComplet(?string $sexe = null): string
    {
        $sexe ??= fake()->randomElement(['M', 'F']);
        $prenom = $sexe === 'F'
            ? fake()->randomElement(self::$prenomsFeminins)
            : fake()->randomElement(self::$prenomsMasculins);

        return $prenom.' '.fake()->randomElement(self::$noms);
    }
}
