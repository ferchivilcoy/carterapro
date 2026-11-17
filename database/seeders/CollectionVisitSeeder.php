<?php

namespace Database\Seeders;

use App\Models\CollectionVisit;
use Illuminate\Database\Seeder;

class CollectionVisitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CollectionVisit::factory()->count(30)->create();
    }
}
