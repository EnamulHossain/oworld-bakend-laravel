<?php

namespace Database\Seeders;

use App\Models\Area;
use Illuminate\Database\Seeder;

class AreaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $areas = [
            'Gulshan',
            'Banani',
            'Baridhara',
            'Niketan',
            'Bashundhara R/A',
            'Uttara',
            'Mirpur',
            'Dhanmondi',
            'Mohammadpur',
            'Lalmatia',
            'Farmgate',
            'Karwan Bazar',
            'Tejgaon',
            'Bijoy Sarani',
            'Shyamoli',
            'Puran Dhaka',
            'Purbachol',
        ];

        foreach ($areas as $name) {
            Area::updateOrCreate(['name' => $name], []);
        }
    }
}
