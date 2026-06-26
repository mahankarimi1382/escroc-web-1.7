<?php

namespace Database\Seeders\Update;

use Exception;
use Illuminate\Database\Seeder;
use App\Models\Admin\BasicSettings;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class BasicSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()

    {
        $data = [
            'web_version'       => "1.7.0",
            'storage_config' => [
                'method' => 'public',
            ],
        ];
        $basicSettings = BasicSettings::first();
        $basicSettings->update($data);

        try {
            update_project_localization_data();
        } catch (Exception $e) {
        }
    }
}
