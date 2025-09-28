<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Language;
use App\Models\Level;

class SampleLanguagesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $langs = [
            ['code' => 'ar', 'name' => 'Arabic'],
            ['code' => 'en', 'name' => 'English'],
            ['code' => 'es', 'name' => 'Spanish'],
        ];

        foreach ($langs as $l) {
            $language = Language::updateOrCreate(
                ['code' => $l['code']],
                ['name' => $l['name'], 'active' => true]
            );

            // Seed default levels for each language
            $levels = [
                ['name' => 'Beginner', 'order' => 1],
                ['name' => 'Intermediate', 'order' => 2],
                ['name' => 'Advanced', 'order' => 3],
            ];

            foreach ($levels as $lev) {
                Level::updateOrCreate(
                    ['language_id' => $language->id, 'name' => $lev['name']],
                    ['order' => $lev['order']]
                );
            }
        }
    }
}
