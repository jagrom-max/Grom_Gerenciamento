<?php

namespace Database\Factories;

use App\Models\RhHoliday;
use Illuminate\Database\Eloquent\Factories\Factory;

class RhHolidayFactory extends Factory
{
    protected $model = RhHoliday::class;

    public function definition(): array
    {
        return [
            'date' => $this->faker->date(),
            'descricao' => $this->faker->sentence(2),
        ];
    }
}
