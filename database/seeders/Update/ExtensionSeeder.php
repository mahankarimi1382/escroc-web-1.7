<?php

namespace Database\Seeders\Update;

use App\Models\Admin\Extension;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class ExtensionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $extensions = [
            ['name' => 'Google Recaptcha', 'slug' => 'google-recaptcha', 'description' => 'Google Recaptcha', 'image' => 'recaptcha3.png', 'script' => null, 'shortcode' => '{"site_key":{"title":"Site key","value":"6LfW4SYsAAAAAFqWjRukTSI0bA690X-Ct72ujdv5"},"secret_key":{"title":"Secret Key","value":"6LfW4SYsAAAAAKCrPZjDmh_HQ1rVN8Ok6qFwGF4O"}}', 'support_image' => 'recaptcha.png', 'status' => '0', 'created_at' => '2025-12-05 17:25:26', 'updated_at' => '2025-12-09 14:37:13'],
        ];

        Extension::insert($extensions);
        
    }
}
