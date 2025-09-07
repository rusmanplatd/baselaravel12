<?php

namespace Database\Seeders;

use App\Models\Channel\ChannelCategory;
use Illuminate\Database\Seeder;

class ChannelCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ChannelCategory::seedDefaultCategories();
    }
}