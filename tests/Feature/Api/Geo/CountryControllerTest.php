<?php

use App\Models\Master\Geo\Country;
use App\Models\Master\Geo\Province;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    // Create a system user for geographic data operations
    $this->systemUser = User::factory()->create(['email' => 'system@geo.local']);
});

it('can list countries', function () {
    Country::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/geo/countries');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'code',
                    'name',
                    'iso_code',
                    'phone_code',
                    'created_at',
                    'updated_at',
                    'provinces'
                ]
            ]
        ]);
});

it('can filter countries by code', function () {
    Country::factory()->create(['code' => 'US']);
    Country::factory()->create(['code' => 'CA']);

    $response = $this->getJson('/api/v1/geo/countries?filter[code]=US');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'US');
});

it('can filter countries by name', function () {
    Country::factory()->create(['name' => 'United States']);
    Country::factory()->create(['name' => 'Canada']);

    $response = $this->getJson('/api/v1/geo/countries?filter[name]=United');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'United States');
});

it('can sort countries by name', function () {
    Country::factory()->create(['name' => 'Canada']);
    Country::factory()->create(['name' => 'United States']);

    $response = $this->getJson('/api/v1/geo/countries?sort=name');

    $response->assertStatus(200)
        ->assertJsonPath('data.0.name', 'Canada')
        ->assertJsonPath('data.1.name', 'United States');
});

it('can create country', function () {
    $countryData = [
        'code' => 'US',
        'name' => 'United States',
        'iso_code' => 'USA',
        'phone_code' => '+1'
    ];

    $response = $this->postJson('/api/v1/geo/countries', $countryData);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'id',
            'code',
            'name',
            'iso_code',
            'phone_code',
            'created_at',
            'updated_at'
        ])
        ->assertJson($countryData);

    $this->assertDatabaseHas('ref_country', $countryData);
});

it('validates required fields when creating country', function () {
    $response = $this->postJson('/api/v1/geo/countries', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['code', 'name']);
});

it('validates unique code when creating country', function () {
    Country::factory()->create(['code' => 'US']);

    $response = $this->postJson('/api/v1/geo/countries', [
        'code' => 'US',
        'name' => 'United States'
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['code']);
});

it('validates unique iso code when creating country', function () {
    Country::factory()->create(['iso_code' => 'USA']);

    $response = $this->postJson('/api/v1/geo/countries', [
        'code' => 'US',
        'name' => 'United States',
        'iso_code' => 'USA'
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['iso_code']);
});

it('can show country', function () {
    $country = Country::factory()->create();

    $response = $this->getJson("/api/v1/geo/countries/{$country->id}");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'id',
            'code',
            'name',
            'iso_code',
            'phone_code',
            'created_at',
            'updated_at',
            'provinces'
        ])
        ->assertJson([
            'id' => $country->id,
            'code' => $country->code,
            'name' => $country->name
        ]);
});

it('returns 404 for nonexistent country', function () {
    $response = $this->getJson('/api/v1/geo/countries/nonexistent-id');

    $response->assertStatus(404);
});

it('can update country', function () {
    $country = Country::factory()->create();
    $updateData = [
        'name' => 'Updated Country Name',
        'phone_code' => '+2'
    ];

    $response = $this->putJson("/api/v1/geo/countries/{$country->id}", $updateData);

    $response->assertStatus(200)
        ->assertJson($updateData);

    $this->assertDatabaseHas('ref_country', [
        'id' => $country->id,
        'name' => 'Updated Country Name',
        'phone_code' => '+2'
    ]);
});

it('validates unique code when updating country', function () {
    $country1 = Country::factory()->create(['code' => 'US']);
    $country2 = Country::factory()->create(['code' => 'CA']);

    $response = $this->putJson("/api/v1/geo/countries/{$country2->id}", [
        'code' => 'US'
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['code']);
});

it('can delete country', function () {
    $country = Country::factory()->create();

    $response = $this->deleteJson("/api/v1/geo/countries/{$country->id}");

    $response->assertStatus(200)
        ->assertJson(['message' => 'Country deleted successfully']);

    $this->assertDatabaseMissing('ref_country', ['id' => $country->id]);
});

it('cannot delete country with provinces', function () {
    $country = Country::factory()->create();
    Province::factory()->create(['country_id' => $country->id]);

    $response = $this->deleteJson("/api/v1/geo/countries/{$country->id}");

    $response->assertStatus(422)
        ->assertJson(['message' => 'Cannot delete country. It has associated provinces.']);

    $this->assertDatabaseHas('ref_country', ['id' => $country->id]);
});

it('can get countries list for dropdown', function () {
    Country::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/geo/countries/list');

    $response->assertStatus(200)
        ->assertJsonStructure([
            '*' => [
                'id',
                'code',
                'name',
                'iso_code',
                'phone_code'
            ]
        ]);
});

it('can filter countries list by name', function () {
    Country::factory()->create(['name' => 'United States']);
    Country::factory()->create(['name' => 'Canada']);

    $response = $this->getJson('/api/v1/geo/countries/list?filter[name]=United');

    $response->assertStatus(200)
        ->assertJsonCount(1)
        ->assertJsonPath('0.name', 'United States');
});

it('respects limit parameter in list', function () {
    Country::factory()->count(5)->create();

    $response = $this->getJson('/api/v1/geo/countries/list?limit=2');

    $response->assertStatus(200)
        ->assertJsonCount(2);
});
