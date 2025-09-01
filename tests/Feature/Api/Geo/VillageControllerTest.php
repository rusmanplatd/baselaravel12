<?php

use App\Models\Master\Geo\City;
use App\Models\Master\Geo\Country;
use App\Models\Master\Geo\District;
use App\Models\Master\Geo\Province;
use App\Models\Master\Geo\Village;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

uses(RefreshDatabase::class, WithFaker::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->systemUser = User::factory()->create(['email' => 'system@geo.local']);
    $this->country = Country::factory()->create();
    $this->province = Province::factory()->create(['country_id' => $this->country->id]);
    $this->city = City::factory()->create(['province_id' => $this->province->id]);
    $this->district = District::factory()->create(['city_id' => $this->city->id]);
});

it('can list villages', function () {
    Village::factory()->count(3)->create(['district_id' => $this->district->id]);

    $response = $this->getJson('/api/v1/geo/villages');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'district_id',
                    'code',
                    'name',
                    'created_at',
                    'updated_at',
                    'districts',
                ],
            ],
        ]);
});

it('can filter villages by district id', function () {
    $district2 = District::factory()->create(['city_id' => $this->city->id]);
    Village::factory()->create(['district_id' => $this->district->id]);
    Village::factory()->create(['district_id' => $district2->id]);

    $response = $this->getJson("/api/v1/geo/villages?filter[district_id]={$this->district->id}");

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.district_id', $this->district->id);
});

it('can filter villages by code', function () {
    Village::factory()->create(['code' => 'WEST', 'district_id' => $this->district->id]);
    Village::factory()->create(['code' => 'EAST', 'district_id' => $this->district->id]);

    $response = $this->getJson('/api/v1/geo/villages?filter[code]=WEST');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'WEST');
});

it('can filter villages by district name', function () {
    $district = District::factory()->create(['name' => 'Hollywood', 'city_id' => $this->city->id]);
    Village::factory()->create(['district_id' => $district->id]);

    $response = $this->getJson('/api/v1/geo/villages?filter[district_name]=Hollywood');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.district.name', 'Hollywood');
});

it('can filter villages by city id', function () {
    $city2 = City::factory()->create(['province_id' => $this->province->id]);
    $district2 = District::factory()->create(['city_id' => $city2->id]);
    Village::factory()->create(['district_id' => $this->district->id]);
    Village::factory()->create(['district_id' => $district2->id]);

    $response = $this->getJson("/api/v1/geo/villages?filter[city_id]={$this->city->id}");

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.district.city_id', $this->city->id);
});

it('can filter villages by province id', function () {
    $province2 = Province::factory()->create(['country_id' => $this->country->id]);
    $city2 = City::factory()->create(['province_id' => $province2->id]);
    $district2 = District::factory()->create(['city_id' => $city2->id]);
    Village::factory()->create(['district_id' => $this->district->id]);
    Village::factory()->create(['district_id' => $district2->id]);

    $response = $this->getJson("/api/v1/geo/villages?filter[province_id]={$this->province->id}");

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.district.city.province_id', $this->province->id);
});

it('can filter villages by country id', function () {
    $country2 = Country::factory()->create();
    $province2 = Province::factory()->create(['country_id' => $country2->id]);
    $city2 = City::factory()->create(['province_id' => $province2->id]);
    $district2 = District::factory()->create(['city_id' => $city2->id]);
    Village::factory()->create(['district_id' => $this->district->id]);
    Village::factory()->create(['district_id' => $district2->id]);

    $response = $this->getJson("/api/v1/geo/villages?filter[country_id]={$this->country->id}");

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.district.city.province.country_id', $this->country->id);
});

it('can sort villages by name', function () {
    Village::factory()->create(['name' => 'West Hollywood', 'district_id' => $this->district->id]);
    Village::factory()->create(['name' => 'East Hollywood', 'district_id' => $this->district->id]);

    $response = $this->getJson('/api/v1/geo/villages?sort=name');

    $response->assertStatus(200)
        ->assertJsonPath('data.0.name', 'East Hollywood')
        ->assertJsonPath('data.1.name', 'West Hollywood');
});

it('can create village', function () {
    $villageData = [
        'district_id' => $this->district->id,
        'code' => 'WEST',
        'name' => 'West Hollywood',
    ];

    $response = $this->postJson('/api/v1/geo/villages', $villageData);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'id',
            'district_id',
            'code',
            'name',
            'created_at',
            'updated_at',
        ])
        ->assertJson($villageData);

    $this->assertDatabaseHas('ref_geo_village', $villageData);
});

it('validates required fields when creating village', function () {
    $response = $this->postJson('/api/v1/geo/villages', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['district_id', 'code', 'name']);
});

it('validates district exists when creating village', function () {
    $response = $this->postJson('/api/v1/geo/villages', [
        'district_id' => 'nonexistent-id',
        'code' => 'WEST',
        'name' => 'West Hollywood',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['district_id']);
});

it('validates unique code when creating village', function () {
    Village::factory()->create(['code' => 'WEST', 'district_id' => $this->district->id]);

    $response = $this->postJson('/api/v1/geo/villages', [
        'district_id' => $this->district->id,
        'code' => 'WEST',
        'name' => 'West Hollywood',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['code']);
});

it('can show village', function () {
    $village = Village::factory()->create(['district_id' => $this->district->id]);

    $response = $this->getJson("/api/v1/geo/villages/{$village->id}");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'id',
            'district_id',
            'code',
            'name',
            'created_at',
            'updated_at',
            'districts',
        ])
        ->assertJson([
            'id' => $village->id,
            'code' => $village->code,
            'name' => $village->name,
        ]);
});

it('returns 404 for nonexistent village', function () {
    $response = $this->getJson('/api/v1/geo/villages/nonexistent-id');

    $response->assertStatus(404);
});

it('can update village', function () {
    $village = Village::factory()->create(['district_id' => $this->district->id]);
    $updateData = [
        'name' => 'Updated Village Name',
    ];

    $response = $this->putJson("/api/v1/geo/villages/{$village->id}", $updateData);

    $response->assertStatus(200)
        ->assertJson($updateData);

    $this->assertDatabaseHas('ref_geo_village', [
        'id' => $village->id,
        'name' => 'Updated Village Name',
    ]);
});

it('validates unique code when updating village', function () {
    $village1 = Village::factory()->create(['code' => 'WEST', 'district_id' => $this->district->id]);
    $village2 = Village::factory()->create(['code' => 'EAST', 'district_id' => $this->district->id]);

    $response = $this->putJson("/api/v1/geo/villages/{$village2->id}", [
        'code' => 'WEST',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['code']);
});

it('can delete village', function () {
    $village = Village::factory()->create(['district_id' => $this->district->id]);

    $response = $this->deleteJson("/api/v1/geo/villages/{$village->id}");

    $response->assertStatus(200)
        ->assertJson(['message' => 'Village deleted successfully']);

    $this->assertDatabaseMissing('ref_geo_village', ['id' => $village->id]);
});

it('can get villages list for dropdown', function () {
    Village::factory()->count(3)->create(['district_id' => $this->district->id]);

    $response = $this->getJson('/api/v1/geo/villages/list');

    $response->assertStatus(200)
        ->assertJsonStructure([
            '*' => [
                'id',
                'district_id',
                'code',
                'name',
                'districts',
            ],
        ]);
});

it('can filter villages list by district id', function () {
    $district2 = District::factory()->create(['city_id' => $this->city->id]);
    Village::factory()->create(['district_id' => $this->district->id]);
    Village::factory()->create(['district_id' => $district2->id]);

    $response = $this->getJson("/api/v1/geo/villages/list?filter[district_id]={$this->district->id}");

    $response->assertStatus(200)
        ->assertJsonCount(1)
        ->assertJsonPath('0.district_id', $this->district->id);
});

it('can get villages by districts', function () {
    Village::factory()->count(3)->create(['district_id' => $this->district->id]);

    $response = $this->getJson("/api/v1/geo/districts/{$this->district->id}/villages");

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

it('returns empty array for district without villages', function () {
    $district = District::factory()->create(['city_id' => $this->city->id]);

    $response = $this->getJson("/api/v1/geo/districts/{$district->id}/villages");

    $response->assertStatus(200)
        ->assertJson([]);
});
