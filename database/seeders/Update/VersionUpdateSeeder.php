<?php

namespace Database\Seeders\Update;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Database\Seeders\Admin\SectionHasPageSeeder;

class VersionUpdateSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // copy directory from public to storage
        $this->copyDirPubToStor();
        //version Update Seeders
        $this->call([
            AppSettingsSeeder::class,
            BasicSettingsSeeder::class,
            ExtensionSeeder::class
        ]);
    }

    /**
     * copy direcotry public to storage folder
     */
    public function copyDirPubToStor()
    {
        File::copyDirectory(public_path('backend'), rtrim(storage_path()) . '/app/public/backend');
        File::copyDirectory(public_path('frontend'), rtrim(storage_path()) . '/app/public/frontend');
        File::copyDirectory(public_path('fileholder'), rtrim(storage_path()) . '/app/public/fileholder');
        File::copyDirectory(public_path('error-images'), rtrim(storage_path()) . '/app/public/error-images');
    }
}
