<?php

namespace Database\Factories;

use App\Models\Classe;
use App\Models\ClasseDomaine;
use App\Models\DomaineEvaluation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClasseDomaine>
 */
class ClasseDomaineFactory extends Factory
{
    protected $model = ClasseDomaine::class;

    public function definition(): array
    {
        return [
            'classe_id' => Classe::factory(),
            'domaine_evaluation_id' => DomaineEvaluation::factory(),
        ];
    }
}
