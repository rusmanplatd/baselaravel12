<?php

use App\Models\Master\Geo\City;
use App\Models\Master\Geo\Country;
use App\Models\Master\Geo\District;
use App\Models\Master\Geo\Province;
use App\Models\Master\Geo\Village;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class, WithFaker::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->systemUser = User::factory()->create(['email' => 'system@geo.local']);

    // Authenticate via Passport for API requests
    Passport::actingAs($this->user);
    $this->country = Country::factory()->create();
    $this->province = Province::factory()->create(['country_id' => $this->country->id]);
    $this->city = City::factory()->create(['province_id' => $this->province->id]);
});

it('can list districts', function () {
    District::factory()->count(3)->create(['city_id' => $this->city->id]);

    $response = $this->getJson('/api/v1/geo/districts');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'city_id',
                    'code',
                    'name',
                    'created_at',
                    'updated_at',
                    'city',
                ],
            ],
        ]);
});

it('can filter districts by city id', function () {
    $city2 = City::factory()->create(['province_id' => $this->province->id]);
    District::factory()->create(['city_id' => $this->city->id]);
    District::factory()->create(['city_id' => $city2->id]);

    $response = $this->getJson("/api/v1/geo/districts?filter[city_id]={$this->city->id}");

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.city_id', $this->city->id);
});

it('can filter districts by code', function () {
    District::factory()->create(['code' => 'HOLLY', 'city_id' => $this->city->id]);
    District::factory()->create(['code' => 'DOWN', 'city_id' => $this->city->id]);

    $response = $this->getJson('/api/v1/geo/districts?filter[code]=HOLLY');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'HOLLY');
});

it('can filter districts by city name', function () {
    $city = City::factory()->create(['name' => 'Los Angeles', 'province_id' => $this->province->id]);
    District::factory()->create(['city_id' => $city->id]);

    $response = $this->getJson('/api/v1/geo/districts?filter[city_name]=Los Angeles');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.city.name', 'Los Angeles');
});

it('can filter districts by province id', function () {
    $province2 = Province::factory()->create(['country_id' => $this->country->id]);
    $city2 = City::factory()->create(['province_id' => $province2->id]);
    District::factory()->create(['city_id' => $this->city->id]);
    District::factory()->create(['city_id' => $city2->id]);

    $response = $this->getJson("/api/v1/geo/districts?filter[province_id]={$this->province->id}");

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.city.province_id', $this->province->id);
});

it('can filter districts by country id', function () {
    $country2 = Country::factory()->create();
    $province2 = Province::factory()->create(['country_id' => $country2->id]);
    $city2 = City::factory()->create(['province_id' => $province2->id]);
    District::factory()->create(['city_id' => $this->city->id]);
    District::factory()->create(['city_id' => $city2->id]);

    $response = $this->getJson("/api/v1/geo/districts?filter[country_id]={$this->country->id}");

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.city.province.country_id', $this->country->id);
});

it('can sort districts by name', function () {
    District::factory()->create(['name' => 'Hollywood', 'city_id' => $this->city->id]);
    District::factory()->create(['name' => 'Downtown', 'city_id' => $this->city->id]);

    $response = $this->getJson('/api/v1/geo/districts?sort=name');

    $response->assertStatus(200)
        ->assertJsonPath('data.0.name', 'Downtown')
        ->assertJsonPath('data.1.name', 'Hollywood');
});

it('can create districts', function () {
    $districtData = [
        'city_id' => $this->city->id,
        'code' => 'HOLLY',
        'name' => 'Hollywood',
    ];

    $response = $this->postJson('/api/v1/geo/districts', $districtData);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'id',
            'city_id',
            'code',
            'name',
            'created_at',
            'updated_at',
        ])
        ->assertJson($districtData);

    $this->assertDatabaseHas('ref_geo_district', $districtData);
});

it('validates required fields when creating districts', function () {
    $response = $this->postJson('/api/v1/geo/districts', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['city_id', 'code', 'name']);
});

it('validates city exists when creating districts', function () {
    $response = $this->postJson('/api/v1/geo/districts', [
        'city_id' => 'nonexistent-id',
        'code' => 'HOLLY',
        'name' => 'Hollywood',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['city_id']);
});

it('validates unique code when creating districts', function () {
    District::factory()->create(['code' => 'HOLLY', 'city_id' => $this->city->id]);

    $response = $this->postJson('/api/v1/geo/districts', [
        'city_id' => $this->city->id,
        'code' => 'HOLLY',
        'name' => 'Hollywood',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['code']);
});

it('can show districts', function () {
    $district = District::factory()->create(['city_id' => $this->city->id]);

    $response = $this->getJson("/api/v1/geo/districts/{$district->id}");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'id',
            'city_id',
            'code',
            'name',
            'created_at',
            'updated_at',
            'city',
        ])
        ->assertJson([
            'id' => $district->id,
            'code' => $district->code,
            'name' => $district->name,
        ]);
});

it('returns 404 for nonexistent districts', function () {
    $response = $this->getJson('/api/v1/geo/districts/nonexistent-id');

    $response->assertStatus(404);
});

it('can update districts', function () {
    $district = District::factory()->create(['city_id' => $this->city->id]);
    $updateData = [
        'name' => 'Updated District Name',
    ];

    $response = $this->putJson("/api/v1/geo/districts/{$district->id}", $updateData);

    $response->assertStatus(200)
        ->assertJson($updateData);

    $this->assertDatabaseHas('ref_geo_district', [
        'id' => $district->id,
        'name' => 'Updated District Name',
    ]);
});

it('validates unique code when updating districts', function () {
    $district1 = District::factory()->create(['code' => 'HOLLY', 'city_id' => $this->city->id]);
    $district2 = District::factory()->create(['code' => 'DOWN', 'city_id' => $this->city->id]);

    $response = $this->putJson("/api/v1/geo/districts/{$district2->id}", [
        'code' => 'HOLLY',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['code']);
});

it('can delete districts', function () {
    $district = District::factory()->create(['city_id' => $this->city->id]);

    $response = $this->deleteJson("/api/v1/geo/districts/{$district->id}");

    $response->assertStatus(200)
        ->assertJson(['message' => 'District deleted successfully']);

    $this->assertDatabaseMissing('ref_geo_district', ['id' => $district->id]);
});

it('cannot delete district with villages', function () {
    $district = District::factory()->create(['city_id' => $this->city->id]);
    Village::factory()->create(['district_id' => $district->id]);

    $response = $this->deleteJson("/api/v1/geo/districts/{$district->id}");

    $response->assertStatus(422)
        ->assertJson(['message' => 'Cannot delete district. It has associated villages.']);

    $this->assertDatabaseHas('ref_geo_district', ['id' => $district->id]);
});

it('can get districts list for dropdown', function () {
    District::factory()->count(3)->create(['city_id' => $this->city->id]);

    $response = $this->getJson('/api/v1/geo/districts/list');

    $response->assertStatus(200)
        ->assertJsonStructure([
            '*' => [
                'id',
                'city_id',
                'code',
                'name',
                'city',
            ],
        ]);
});

it('can filter districts list by city id', function () {
    $city2 = City::factory()->create(['province_id' => $this->province->id]);
    District::factory()->create(['city_id' => $this->city->id]);
    District::factory()->create(['city_id' => $city2->id]);

    $response = $this->getJson("/api/v1/geo/districts/list?filter[city_id]={$this->city->id}");

    $response->assertStatus(200)
        ->assertJsonCount(1)
        ->assertJsonPath('0.city_id', $this->city->id);
});

it('can get districts by city', function () {
    District::factory()->count(3)->create(['city_id' => $this->city->id]);

    $response = $this->getJson("/api/v1/geo/cities/{$this->city->id}/districts");

    $response->assertStatus(200)
        ->assertJsonStructure([
            '*' => [
                'id',
                'code',
                'name',
            ],
        ])
        ->assertJsonCount(3);
});

it('returns empty array for city without districts', function () {
    $city = City::factory()->create(['province_id' => $this->province->id]);

    $response = $this->getJson("/api/v1/geo/cities/{$city->id}/districts");

    $response->assertStatus(200)
        ->assertJson([]);
});
