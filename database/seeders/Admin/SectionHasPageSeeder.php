<?php

namespace Database\Seeders\Admin;

use Illuminate\Database\Seeder;
use App\Models\Admin\SetupPageHasSection;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class SectionHasPageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
     $setup_page_has_sections = array(
        array('id' => '1','setup_page_id' => '2','site_section_id' => '4','position' => '1','status' => '1','created_at' => '2025-08-23 11:01:21','updated_at' => '2025-08-23 11:01:21'),
        array('id' => '2','setup_page_id' => '2','site_section_id' => '3','position' => '9','status' => '0','created_at' => '2025-08-23 11:01:21','updated_at' => '2025-08-26 05:43:36'),
        array('id' => '3','setup_page_id' => '2','site_section_id' => '5','position' => '4','status' => '0','created_at' => '2025-08-23 11:01:21','updated_at' => '2025-08-26 05:43:36'),
        array('id' => '4','setup_page_id' => '2','site_section_id' => '6','position' => '2','status' => '0','created_at' => '2025-08-23 11:01:21','updated_at' => '2025-08-26 05:43:36'),
        array('id' => '5','setup_page_id' => '2','site_section_id' => '7','position' => '3','status' => '0','created_at' => '2025-08-23 11:01:21','updated_at' => '2025-08-26 05:43:36'),
        array('id' => '6','setup_page_id' => '2','site_section_id' => '8','position' => '5','status' => '0','created_at' => '2025-08-23 11:01:21','updated_at' => '2025-08-26 05:43:36'),
        array('id' => '7','setup_page_id' => '2','site_section_id' => '9','position' => '8','status' => '0','created_at' => '2025-08-23 11:01:21','updated_at' => '2025-08-26 05:43:36'),
        array('id' => '8','setup_page_id' => '2','site_section_id' => '10','position' => '7','status' => '0','created_at' => '2025-08-23 11:01:21','updated_at' => '2025-08-26 05:43:36'),
        array('id' => '9','setup_page_id' => '2','site_section_id' => '11','position' => '6','status' => '0','created_at' => '2025-08-23 11:01:21','updated_at' => '2025-08-26 05:43:36'),
        array('id' => '10','setup_page_id' => '3','site_section_id' => '5','position' => '1','status' => '1','created_at' => '2025-08-23 11:01:44','updated_at' => '2025-08-23 11:01:48'),
        array('id' => '11','setup_page_id' => '3','site_section_id' => '3','position' => '7','status' => '0','created_at' => '2025-08-23 11:01:44','updated_at' => '2025-08-26 05:58:34'),
        array('id' => '12','setup_page_id' => '3','site_section_id' => '4','position' => '4','status' => '0','created_at' => '2025-08-23 11:01:44','updated_at' => '2025-08-26 05:58:34'),
        array('id' => '13','setup_page_id' => '3','site_section_id' => '6','position' => '2','status' => '0','created_at' => '2025-08-23 11:01:44','updated_at' => '2025-08-26 05:58:34'),
        array('id' => '14','setup_page_id' => '3','site_section_id' => '7','position' => '3','status' => '0','created_at' => '2025-08-23 11:01:44','updated_at' => '2025-08-26 05:58:34'),
        array('id' => '15','setup_page_id' => '3','site_section_id' => '8','position' => '5','status' => '0','created_at' => '2025-08-23 11:01:44','updated_at' => '2025-08-26 05:58:34'),
        array('id' => '16','setup_page_id' => '3','site_section_id' => '9','position' => '8','status' => '0','created_at' => '2025-08-23 11:01:44','updated_at' => '2025-08-26 05:58:34'),
        array('id' => '17','setup_page_id' => '3','site_section_id' => '10','position' => '9','status' => '0','created_at' => '2025-08-23 11:01:44','updated_at' => '2025-08-26 05:58:34'),
        array('id' => '18','setup_page_id' => '3','site_section_id' => '11','position' => '6','status' => '0','created_at' => '2025-08-23 11:01:44','updated_at' => '2025-08-26 05:58:34'),
        array('id' => '19','setup_page_id' => '4','site_section_id' => '8','position' => '1','status' => '1','created_at' => '2025-08-23 11:02:02','updated_at' => '2025-08-23 11:02:02'),
        array('id' => '20','setup_page_id' => '4','site_section_id' => '3','position' => '8','status' => '0','created_at' => '2025-08-23 11:02:02','updated_at' => '2025-08-26 06:02:28'),
        array('id' => '21','setup_page_id' => '4','site_section_id' => '4','position' => '5','status' => '0','created_at' => '2025-08-23 11:02:02','updated_at' => '2025-08-26 06:02:28'),
        array('id' => '22','setup_page_id' => '4','site_section_id' => '5','position' => '4','status' => '0','created_at' => '2025-08-23 11:02:02','updated_at' => '2025-08-26 06:02:28'),
        array('id' => '23','setup_page_id' => '4','site_section_id' => '6','position' => '2','status' => '0','created_at' => '2025-08-23 11:02:02','updated_at' => '2025-08-26 06:02:28'),
        array('id' => '24','setup_page_id' => '4','site_section_id' => '7','position' => '3','status' => '0','created_at' => '2025-08-23 11:02:02','updated_at' => '2025-08-26 06:02:28'),
        array('id' => '25','setup_page_id' => '4','site_section_id' => '9','position' => '7','status' => '0','created_at' => '2025-08-23 11:02:02','updated_at' => '2025-08-26 06:02:28'),
        array('id' => '26','setup_page_id' => '4','site_section_id' => '10','position' => '9','status' => '0','created_at' => '2025-08-23 11:02:02','updated_at' => '2025-08-26 06:02:28'),
        array('id' => '27','setup_page_id' => '4','site_section_id' => '11','position' => '6','status' => '0','created_at' => '2025-08-23 11:02:02','updated_at' => '2025-08-26 06:02:28'),
        array('id' => '28','setup_page_id' => '5','site_section_id' => '11','position' => '1','status' => '0','created_at' => '2025-08-23 11:02:26','updated_at' => '2025-08-26 06:04:08'),
        array('id' => '29','setup_page_id' => '5','site_section_id' => '3','position' => '2','status' => '0','created_at' => '2025-08-23 11:02:26','updated_at' => '2025-08-23 11:02:26'),
        array('id' => '30','setup_page_id' => '5','site_section_id' => '4','position' => '3','status' => '0','created_at' => '2025-08-23 11:02:26','updated_at' => '2025-08-23 11:02:26'),
        array('id' => '31','setup_page_id' => '5','site_section_id' => '5','position' => '4','status' => '0','created_at' => '2025-08-23 11:02:26','updated_at' => '2025-08-23 11:02:26'),
        array('id' => '32','setup_page_id' => '5','site_section_id' => '6','position' => '5','status' => '0','created_at' => '2025-08-23 11:02:26','updated_at' => '2025-08-23 11:02:26'),
        array('id' => '33','setup_page_id' => '5','site_section_id' => '7','position' => '6','status' => '0','created_at' => '2025-08-23 11:02:26','updated_at' => '2025-08-23 11:02:26'),
        array('id' => '34','setup_page_id' => '5','site_section_id' => '8','position' => '7','status' => '0','created_at' => '2025-08-23 11:02:26','updated_at' => '2025-08-23 11:02:26'),
        array('id' => '35','setup_page_id' => '5','site_section_id' => '9','position' => '8','status' => '0','created_at' => '2025-08-23 11:02:26','updated_at' => '2025-08-23 11:02:26'),
        array('id' => '36','setup_page_id' => '5','site_section_id' => '10','position' => '9','status' => '0','created_at' => '2025-08-23 11:02:26','updated_at' => '2025-08-23 11:02:26'),
        array('id' => '37','setup_page_id' => '6','site_section_id' => '9','position' => '1','status' => '1','created_at' => '2025-08-23 11:02:43','updated_at' => '2025-08-23 11:02:43'),
        array('id' => '38','setup_page_id' => '6','site_section_id' => '3','position' => '8','status' => '0','created_at' => '2025-08-23 11:02:43','updated_at' => '2025-08-26 06:11:45'),
        array('id' => '39','setup_page_id' => '6','site_section_id' => '4','position' => '4','status' => '0','created_at' => '2025-08-23 11:02:43','updated_at' => '2025-08-26 06:11:45'),
        array('id' => '40','setup_page_id' => '6','site_section_id' => '5','position' => '5','status' => '0','created_at' => '2025-08-23 11:02:43','updated_at' => '2025-08-26 06:11:45'),
        array('id' => '41','setup_page_id' => '6','site_section_id' => '6','position' => '2','status' => '0','created_at' => '2025-08-23 11:02:43','updated_at' => '2025-08-26 06:11:45'),
        array('id' => '42','setup_page_id' => '6','site_section_id' => '7','position' => '3','status' => '0','created_at' => '2025-08-23 11:02:43','updated_at' => '2025-08-26 06:11:45'),
        array('id' => '43','setup_page_id' => '6','site_section_id' => '8','position' => '6','status' => '0','created_at' => '2025-08-23 11:02:43','updated_at' => '2025-08-26 06:11:45'),
        array('id' => '44','setup_page_id' => '6','site_section_id' => '10','position' => '9','status' => '0','created_at' => '2025-08-23 11:02:43','updated_at' => '2025-08-26 06:11:45'),
        array('id' => '45','setup_page_id' => '6','site_section_id' => '11','position' => '7','status' => '0','created_at' => '2025-08-23 11:02:43','updated_at' => '2025-08-26 06:11:45')
        );

        SetupPageHasSection::upsert($setup_page_has_sections,['id'],['setup_page_id','site_section_id','position','status']);
    }
}
