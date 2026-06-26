Immediate Older Version: 1.6.0
Current Version: 1.7.0

Feature Update:

1. Updated Assets Architecture & Base URL
2. Security Update
3. Rate Limiter Added
4. Google Recaptcha added

Please Use Those Command On Your Terminal To Update v1.6.0 to v1.7.0

1. Update Composer To Add New Package (Make Sure Your Targeted Location Is Project Root)
   composer update && composer dumpautoload
2. To Added New Migration File
   php artisan migrate
3. To Update Version Related Feature Please Run This Command On Your Terminal (Make Sure Your Targeted Location Is Project Root)
   php artisan db:seed --class=Database\\Seeders\\Update\\VersionUpdateSeeder
4. To link with Storage
   php artisan storage:link
5. To clear all compiled caches (Make Sure Your Targeted Location Is Project Root)
   php artisan optimize:clear

Please Use Those Command On Your Terminal To Update Full System

1. To Run project Please Run This Command On Your Terminal
   composer update && composer dumpautoload && php artisan migrate:fresh --seed && php artisan passport:install --force
