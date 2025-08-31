<?php

use App\Models\Master\Geo\Country;
use App\Models\Master\Geo\Province;
use App\Models\Master\Geo\City;
use App\Models\Master\Geo\District;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

uses(RefreshDatabase::class, WithFaker::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->systemUser = User::factory()->create(['email' => 'system@geo.local']);
    $this->country = Country::factory()->create();
    $this->province = Province::factory()->create(['country_id' => $this->country->id]);
});

it('can list cities', function () {
    City::factory()->count(3)->create(['province_id' => $this->province->id]);

    $response = $this->getJson('/api/v1/geo/cities');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'province_id',
                    'code',
                    'name',
                    'created_at',
                    'updated_at',
                    'province',
                    'district'
                ]
            ]
        ]);
});

it('can filter cities by province id', function () {
    $province2 = Province::factory()->create(['country_id' => $this->country->id]);
    City::factory()->create(['province_id' => $this->province->id]);
    City::factory()->create(['province_id' => $province2->id]);

    $response = $this->getJson("/api/v1/geo/cities?filter[province_id]={$this->province->id}");

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.province_id', $this->province->id);
});

it('can filter cities by code', function () {
    City::factory()->create(['code' => 'LA', 'province_id' => $this->province->id]);
    City::factory()->create(['code' => 'NY', 'province_id' => $this->province->id]);

    $response = $this->getJson('/api/v1/geo/cities?filter[code]=LA');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'LA');
});

it('can filter cities by province name', function () {
    $province = Province::factory()->create(['name' => 'California', 'country_id' => $this->country->id]);
    City::factory()->create(['province_id' => $province->id]);

    $response = $this->getJson('/api/v1/geo/cities?filter[province_name]=California');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.province.name', 'California');
});

it('can filter cities by country id', function () {
    $country2 = Country::factory()->create();
    $province2 = Province::factory()->create(['country_id' => $country2->id]);
    City::factory()->create(['province_id' => $this->province->id]);
    City::factory()->create(['province_id' => $province2->id]);

    $response = $this->getJson("/api/v1/geo/cities?filter[country_id]={$this->country->id}");

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.province.country_id', $this->country->id);
});

it('can sort cities by name', function () {
    City::factory()->create(['name' => 'Los Angeles', 'province_id' => $this->province->id]);
    City::factory()->create(['name' => 'Anaheim', 'province_id' => $this->province->id]);

    $response = $this->getJson('/api/v1/geo/cities?sort=name');

    $response->assertStatus(200)
        ->assertJsonPath('data.0.name', 'Anaheim')
        ->assertJsonPath('data.1.name', 'Los Angeles');
});

it('can create city', function () {
    $cityData = [
        'province_id' => $this->province->id,
        'code' => 'LA',
        'name' => 'Los Angeles'
    ];

    $response = $this->postJson('/api/v1/geo/cities', $cityData);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'id',
            'province_id',
            'code',
            'name',
            'created_at',
            'updated_at'
        ])
        ->assertJson($cityData);

    $this->assertDatabaseHas('ref_geo_city', $cityData);
});

it('validates required fields when creating city', function () {
    $response = $this->postJson('/api/v1/geo/cities', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['province_id', 'code', 'name']);
});

it('validates province exists when creating city', function () {
    $response = $this->postJson('/api/v1/geo/cities', [
        'province_id' => 'nonexistent-id',
        'code' => 'LA',
        'name' => 'Los Angeles'
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['province_id']);
});

it('validates unique code when creating city', function () {
    City::factory()->create(['code' => 'LA', 'province_id' => $this->province->id]);

    $response = $this->postJson('/api/v1/geo/cities', [
        'province_id' => $this->province->id,
        'code' => 'LA',
        'name' => 'Los Angeles'
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['code']);
});

it('can show city', function () {
    $city = City::factory()->create(['province_id' => $this->province->id]);

    $response = $this->getJson("/api/v1/geo/cities/{$city->id}");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'id',
            'province_id',
            'code',
            'name',
            'created_at',
            'updated_at',
            'province',
            'district'
        ])
        ->assertJson([
            'id' => $city->id,
            'code' => $city->code,
            'name' => $city->name
        ]);
});

it('returns 404 for nonexistent city', function () {
    $response = $this->getJson('/api/v1/geo/cities/nonexistent-id');

    $response->assertStatus(404);
});

it('can update city', function () {
    $city = City::factory()->create(['province_id' => $this->province->id]);
    $updateData = [
        'name' => 'Updated City Name'
    ];

    $response = $this->putJson("/api/v1/geo/cities/{$city->id}", $updateData);

    $response->assertStatus(200)
        ->assertJson($updateData);

    $this->assertDatabaseHas('ref_geo_city', [
        'id' => $city->id,
        'name' => 'Updated City Name'
    ]);
});

it('validates unique code when updating city', function () {
    $city1 = City::factory()->create(['code' => 'LA', 'province_id' => $this->province->id]);
    $city2 = City::factory()->create(['code' => 'NY', 'province_id' => $this->province->id]);

    $response = $this->putJson("/api/v1/geo/cities/{$city2->id}", [
        'code' => 'LA'
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['code']);
});

it('can delete city', function () {
    $city = City::factory()->create(['province_id' => $this->province->id]);

    $response = $this->deleteJson("/api/v1/geo/cities/{$city->id}");

    $response->assertStatus(200)
        ->assertJson(['message' => 'City deleted successfully']);

    $this->assertDatabaseMissing('ref_geo_city', ['id' => $city->id]);
});

it('cannot delete city with districts', function () {
    $city = City::factory()->create(['province_id' => $this->province->id]);
    District::factory()->create(['city_id' => $city->id]);

    $response = $this->deleteJson("/api/v1/geo/cities/{$city->id}");

    $response->assertStatus(422)
        ->assertJson(['message' => 'Cannot delete city. It has associated districts.']);

    $this->assertDatabaseHas('ref_geo_city', ['id' => $city->id]);
});

it('can get cities list for dropdown', function () {
    City::factory()->count(3)->create(['province_id' => $this->province->id]);

    $response = $this->getJson('/api/v1/geo/cities/list');

    $response->assertStatus(200)
        ->assertJsonStructure([
            '*' => [
                'id',
                'province_id',
                'code',
                'name',
                'province'
            ]
        ]);
});

it('can filter cities list by province id', function () {
    $province2 = Province::factory()->create(['country_id' => $this->country->id]);
    City::factory()->create(['province_id' => $this->province->id]);
    City::factory()->create(['province_id' => $province2->id]);

    $response = $this->getJson("/api/v1/geo/cities/list?filter[province_id]={$this->province->id}");

    $response->assertStatus(200)
        ->assertJsonCount(1)
        ->assertJsonPath('0.province_id', $this->province->id);
});

it('can get cities by province', function () {
    City::factory()->count(3)->create(['province_id' => $this->province->id]);

    $response = $this->getJson("/api/v1/geo/provinces/{$this->province->id}/cities");

    $response->assertStatus(200)
        ->assertJsonStructure([
            '*' => [
                'id',
                'code',
                'name'
            ]
        ])
        ->assertJsonCount(3);
});

it('returns empty array for province without cities', function () {
    $province = Province::factory()->create(['country_id' => $this->country->id]);

    $response = $this->getJson("/api/v1/geo/provinces/{$province->id}/cities");

    $response->assertStatus(200)
        ->assertJson([]);
});
