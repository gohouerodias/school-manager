<?php

namespace App\Support;

/**
 * Upload limits actually enforced by the PHP install serving the app
 * (php.ini / Plesk "PHP Settings"), so the browser can refuse an upload
 * that the server would reject anyway — before the agent waits for a long
 * upload that ends in a "413 Content Too Large".
 */
class LimitesEnvoi
{
    /**
     * Application-level cap on a single document (see the 'max:5120' rules
     * in SaveEleveWizardRequest / StoreDocumentEleveRequest).
     */
    public const TAILLE_MAX_DOCUMENT_OCTETS = 5 * 1024 * 1024;

    /**
     * Largest whole request PHP accepts (post_max_size): every file of a
     * form plus its other fields, sent together.
     */
    public static function octetsMaxParRequete(): int
    {
        return self::enOctets((string) ini_get('post_max_size'));
    }

    /**
     * Largest single file accepted: the smaller of PHP's upload_max_filesize
     * and the application's own 5 Mo limit.
     */
    public static function octetsMaxParFichier(): int
    {
        $limitePhp = self::enOctets((string) ini_get('upload_max_filesize'));

        return $limitePhp > 0 ? min($limitePhp, self::TAILLE_MAX_DOCUMENT_OCTETS) : self::TAILLE_MAX_DOCUMENT_OCTETS;
    }

    /**
     * Human-readable size in Mo, e.g. 8388608 → "8 Mo".
     */
    public static function enMo(int $octets): string
    {
        $mo = $octets / (1024 * 1024);

        return (floor($mo) == $mo ? (string) (int) $mo : number_format($mo, 1, ',', '')).' Mo';
    }

    /**
     * Converts a php.ini shorthand ("8M", "512K", "1G", "0") to bytes; 0
     * means "no limit".
     */
    public static function enOctets(string $valeur): int
    {
        $valeur = trim($valeur);

        if ($valeur === '') {
            return 0;
        }

        $nombre = (int) $valeur;

        return match (strtoupper(substr($valeur, -1))) {
            'G' => $nombre * 1024 * 1024 * 1024,
            'M' => $nombre * 1024 * 1024,
            'K' => $nombre * 1024,
            default => $nombre,
        };
    }
}
