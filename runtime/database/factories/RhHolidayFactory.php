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
            'holiday_date' => $this->faker->date(),
            'name' => $this->faker->sentence(2),
            'scope' => 'municipal',
            'is_active' => true,
        ];
    }
}
