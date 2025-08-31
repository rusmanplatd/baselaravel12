<?php

namespace Database\Seeders;

use App\Models\Master\Geo\City;
use App\Models\Master\Geo\Country;
use App\Models\Master\Geo\District;
use App\Models\Master\Geo\Province;
use App\Models\Master\Geo\Village;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class RegionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting region data import...');

        // Get system user for created_by/updated_by fields
        $systemUser = \App\Models\User::first();
        if (! $systemUser) {
            $this->command->error('No users found. Please run user seeders first.');

            return;
        }

        // Clear existing data
        $this->clearExistingData();

        // Import countries
        $this->importCountries($systemUser);

        // Import provinces
        $this->importProvinces($systemUser);

        // Import cities
        $this->importCities($systemUser);

        // Import districts
        $this->importDistricts($systemUser);

        // Import villages
        $this->importVillages($systemUser);

        $this->command->info('Region data import completed successfully!');
    }

    private function clearExistingData(): void
    {
        $this->command->info('Clearing existing region data...');

        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        } elseif ($driver === 'pgsql') {
            DB::statement('SET session_replication_role = replica;');
        }

        Village::truncate();
        District::truncate();
        City::truncate();
        Province::truncate();
        Country::truncate();

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        } elseif ($driver === 'pgsql') {
            DB::statement('SET session_replication_role = DEFAULT;');
        }
    }

    private function importCountries($systemUser): void
    {
        $this->command->info('Importing countries...');

        $csvPath = database_path('seeders/csv/country.csv');

        if (! File::exists($csvPath)) {
            $this->command->error('Country CSV file not found!');

            return;
        }

        $csvData = $this->readCsvFile($csvPath);
        $countries = [];

        foreach ($csvData as $row) {
            if (isset($row['kode']) && isset($row['nama'])) {
                $countries[] = [
                    'id' => \Illuminate\Support\Str::ulid(),
                    'code' => $row['kode'],
                    'name' => $row['nama'],
                    'iso_code' => $row['iso_code'] ?? null,
                    'phone_code' => $row['phone_code'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'created_by' => $systemUser->id,
                    'updated_by' => $systemUser->id,
                ];
            }
        }

        // Insert in chunks to avoid memory issues
        foreach (array_chunk($countries, 1000) as $chunk) {
            Country::insert($chunk);
        }

        $this->command->info('Imported '.count($countries).' countries');
    }

    private function importProvinces($systemUser): void
    {
        $this->command->info('Importing provinces...');

        $csvPath = database_path('seeders/csv/province.csv');

        if (! File::exists($csvPath)) {
            $this->command->error('Province CSV file not found!');

            return;
        }

        $csvData = $this->readCsvFile($csvPath);
        $provinces = [];
        $countries = Country::pluck('id', 'code')->toArray();

        foreach ($csvData as $row) {
            if (isset($row['kode']) && isset($row['nama']) && isset($row['country_code'])) {
                $countryCode = $row['country_code'];
                $countryId = $countries[$countryCode] ?? null;

                if ($countryId) {
                    $provinces[] = [
                        'id' => \Illuminate\Support\Str::ulid(),
                        'country_id' => $countryId,
                        'code' => $row['kode'],
                        'name' => $row['nama'],
                        'created_at' => now(),
                        'updated_at' => now(),
                        'created_by' => $systemUser->id,
                        'updated_by' => $systemUser->id,
                    ];
                }
            }
        }

        // Insert in chunks to avoid memory issues
        foreach (array_chunk($provinces, 1000) as $chunk) {
            Province::insert($chunk);
        }

        $this->command->info('Imported '.count($provinces).' provinces');
    }

    private function importCities($systemUser): void
    {
        $this->command->info('Importing cities...');

        $csvPath = database_path('seeders/csv/city.csv');

        if (! File::exists($csvPath)) {
            $this->command->error('City CSV file not found!');

            return;
        }

        $csvData = $this->readCsvFile($csvPath);
        $cities = [];

        $provinces = Province::with('country')->get()->groupBy('country.code')->map(function ($countryProvinces) {
            return $countryProvinces->pluck('id', 'code');
        })->toArray();

        foreach ($csvData as $row) {
            if (isset($row['kode']) && isset($row['nama']) && isset($row['country_code']) && isset($row['province_code'])) {
                $countryCode = $row['country_code'];
                $provinceCode = $row['province_code'];

                // For Indonesian cities, use the province code directly
                // For international cities, use the region code as province
                $lookupCode = $provinceCode;

                $provinceId = $provinces[$countryCode][$lookupCode] ?? null;

                if ($provinceId) {
                    $cities[] = [
                        'id' => \Illuminate\Support\Str::ulid(),
                        'province_id' => $provinceId,
                        'code' => $row['kode'],
                        'name' => $row['nama'],
                        'created_at' => now(),
                        'updated_at' => now(),
                        'created_by' => $systemUser->id,
                        'updated_by' => $systemUser->id,
                    ];
                } else {
                    $this->command->warn("Skipping city {$row['nama']} (code: {$row['kode']}) - Province not found for code: {$lookupCode}");
                }
            }
        }

        // Insert in chunks
        foreach (array_chunk($cities, 1000) as $chunk) {
            City::insert($chunk);
        }

        $this->command->info('Imported '.count($cities).' cities');
    }

    private function importDistricts($systemUser): void
    {
        $this->command->info('Importing districts...');

        $csvPath = database_path('seeders/csv/district.csv');

        if (! File::exists($csvPath)) {
            $this->command->error('District CSV file not found!');

            return;
        }

        $csvData = $this->readCsvFile($csvPath);
        $districts = [];
        $cities = City::pluck('id', 'code')->toArray();

        foreach ($csvData as $row) {
            if (isset($row['kode']) && isset($row['nama'])) {
                $cityCode = substr($row['kode'], 0, 5);

                if (isset($cities[$cityCode])) {
                    $districts[] = [
                        'id' => \Illuminate\Support\Str::ulid(),
                        'city_id' => $cities[$cityCode],
                        'code' => $row['kode'],
                        'name' => $row['nama'],
                        'created_at' => now(),
                        'updated_at' => now(),
                        'created_by' => $systemUser->id,
                        'updated_by' => $systemUser->id,
                    ];
                }
            }
        }

        // Insert in chunks
        foreach (array_chunk($districts, 1000) as $chunk) {
            District::insert($chunk);
        }

        $this->command->info('Imported '.count($districts).' districts');
    }

    private function importVillages($systemUser): void
    {
        $this->command->info('Importing villages...');

        $csvPath = database_path('seeders/csv/villages.csv');

        if (! File::exists($csvPath)) {
            $this->command->error('Villages CSV file not found!');

            return;
        }

        $districts = District::pluck('id', 'code')->toArray();
        $villages = [];
        $count = 0;

        // Read CSV in chunks to handle large file
        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            $this->command->error('Cannot open villages CSV file!');

            return;
        }

        // Skip header
        fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) >= 2) {
                $districtCode = substr($row[0], 0, 8);

                if (isset($districts[$districtCode])) {
                    $villages[] = [
                        'id' => \Illuminate\Support\Str::ulid(),
                        'district_id' => $districts[$districtCode],
                        'code' => $row[0],
                        'name' => $row[1],
                        'created_at' => now(),
                        'updated_at' => now(),
                        'created_by' => $systemUser->id,
                        'updated_by' => $systemUser->id,
                    ];
                    $count++;

                    // Insert in chunks to manage memory
                    if (count($villages) >= 1000) {
                        Village::insert($villages);
                        $villages = [];
                    }
                }
            }
        }

        // Insert remaining villages
        if (! empty($villages)) {
            Village::insert($villages);
        }

        fclose($handle);

        $this->command->info('Imported '.$count.' villages');
    }

    private function readCsvFile(string $path): array
    {
        $data = [];
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return $data;
        }

        // Read header and remove BOM
        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);

            return $data;
        }

        // Clean headers from BOM and whitespace
        $headers = array_map(function ($header) {
            return trim($header, "\xEF\xBB\xBF \t\n\r\0\x0B");
        }, $headers);

        // Read data rows
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === count($headers)) {
                $data[] = array_combine($headers, $row);
            }
        }

        fclose($handle);

        return $data;
    }
}
