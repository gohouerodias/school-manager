<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Applies a "search box" filter across several columns so that typing more
 * than one word together (e.g. a tuteur or élève's "nom prénom") still
 * matches. Without this, searching "Grégoire Ahouansou" as one string would
 * never match a record whose `nom` is "Ahouansou" and `prenom` is
 * "Grégoire" — neither column alone contains the full two-word string, even
 * though the person clearly matches. Instead, each *word* is required to
 * appear in *some* one of the given columns (order-independent), which is
 * what "search by nom, prénom, or both" actually means to a user.
 *
 * Used by EleveFilters (nom/prénom/matricule) and Tuteurs\TuteurController
 * (nom/prénom/téléphone/email) — reusable for any future "people list"
 * search that has the same nom+prénom-in-separate-columns shape.
 */
class MultiWordSearch
{
    /**
     * @param  Builder<*>  $query
     * @param  array<int, string>  $columns
     * @return Builder<*>
     */
    public static function apply(Builder $query, string $search, array $columns): Builder
    {
        $mots = preg_split('/\s+/', trim($search), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($mots === [] || $columns === []) {
            return $query;
        }

        return $query->where(function ($outer) use ($mots, $columns) {
            foreach ($mots as $mot) {
                $outer->where(function ($inner) use ($mot, $columns) {
                    foreach ($columns as $column) {
                        $inner->orWhere($column, 'like', "%{$mot}%");
                    }
                });
            }
        });
    }
}
