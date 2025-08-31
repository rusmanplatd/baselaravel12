<?php

use App\Models\Master\Geo\Country;
use App\Models\Master\Geo\Province;
use App\Models\Master\Geo\City;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

uses(RefreshDatabase::class, WithFaker::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->systemUser = User::factory()->create(['email' => 'system@geo.local']);
    $this->country = Country::factory()->create();
});

it('can list provinces', function () {
    Province::factory()->count(3)->create(['country_id' => $this->country->id]);

    $response = $this->getJson('/api/v1/geo/provinces');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'country_id',
                    'code',
                    'name',
                    'created_at',
                    'updated_at',
                    'country',
                    'cities'
                ]
            ]
        ]);
});

it('can filter provinces by country id', function () {
    $country2 = Country::factory()->create();
    Province::factory()->create(['country_id' => $this->country->id]);
    Province::factory()->create(['country_id' => $country2->id]);

    $response = $this->getJson("/api/v1/geo/provinces?filter[country_id]={$this->country->id}");

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.country_id', $this->country->id);
});

it('can filter provinces by code', function () {
    Province::factory()->create(['code' => 'CA', 'country_id' => $this->country->id]);
    Province::factory()->create(['code' => 'NY', 'country_id' => $this->country->id]);

    $response = $this->getJson('/api/v1/geo/provinces?filter[code]=CA');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'CA');
});

it('can filter provinces by country name', function () {
    $country = Country::factory()->create(['name' => 'United States']);
    Province::factory()->create(['country_id' => $country->id]);

    $response = $this->getJson('/api/v1/geo/provinces?filter[country_name]=United');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.country.name', 'United States');
});

it('can sort provinces by name', function () {
    Province::factory()->create(['name' => 'California', 'country_id' => $this->country->id]);
    Province::factory()->create(['name' => 'Alabama', 'country_id' => $this->country->id]);

    $response = $this->getJson('/api/v1/geo/provinces?sort=name');

    $response->assertStatus(200)
        ->assertJsonPath('data.0.name', 'Alabama')
        ->assertJsonPath('data.1.name', 'California');
});

it('can create province', function () {
    $provinceData = [
        'country_id' => $this->country->id,
        'code' => 'CA',
        'name' => 'California'
    ];

    $response = $this->postJson('/api/v1/geo/provinces', $provinceData);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'id',
            'country_id',
            'code',
            'name',
            'created_at',
            'updated_at'
        ])
        ->assertJson($provinceData);

    $this->assertDatabaseHas('ref_geo_province', $provinceData);
});

it('validates required fields when creating province', function () {
    $response = $this->postJson('/api/v1/geo/provinces', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['country_id', 'code', 'name']);
});

it('validates country exists when creating province', function () {
    $response = $this->postJson('/api/v1/geo/provinces', [
        'country_id' => 'nonexistent-id',
        'code' => 'CA',
        'name' => 'California'
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['country_id']);
});

it('validates unique code when creating province', function () {
    Province::factory()->create(['code' => 'CA', 'country_id' => $this->country->id]);

    $response = $this->postJson('/api/v1/geo/provinces', [
        'country_id' => $this->country->id,
        'code' => 'CA',
        'name' => 'California'
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['code']);
});

it('can show province', function () {
    $province = Province::factory()->create(['country_id' => $this->country->id]);

    $response = $this->getJson("/api/v1/geo/provinces/{$province->id}");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'id',
            'country_id',
            'code',
            'name',
            'created_at',
            'updated_at',
            'country',
            'cities'
        ])
        ->assertJson([
            'id' => $province->id,
            'code' => $province->code,
            'name' => $province->name
        ]);
});

it('returns 404 for nonexistent province', function () {
    $response = $this->getJson('/api/v1/geo/provinces/nonexistent-id');

    $response->assertStatus(404);
});

it('can update province', function () {
    $province = Province::factory()->create(['country_id' => $this->country->id]);
    $updateData = [
        'name' => 'Updated Province Name'
    ];

    $response = $this->putJson("/api/v1/geo/provinces/{$province->id}", $updateData);

    $response->assertStatus(200)
        ->assertJson($updateData);

    $this->assertDatabaseHas('ref_geo_province', [
        'id' => $province->id,
        'name' => 'Updated Province Name'
    ]);
});

it('validates unique code when updating province', function () {
    $province1 = Province::factory()->create(['code' => 'CA', 'country_id' => $this->country->id]);
    $province2 = Province::factory()->create(['code' => 'NY', 'country_id' => $this->country->id]);

    $response = $this->putJson("/api/v1/geo/provinces/{$province2->id}", [
        'code' => 'CA'
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['code']);
});

it('can delete province', function () {
    $province = Province::factory()->create(['country_id' => $this->country->id]);

    $response = $this->deleteJson("/api/v1/geo/provinces/{$province->id}");

    $response->assertStatus(200)
        ->assertJson(['message' => 'Province deleted successfully']);

    $this->assertDatabaseMissing('ref_geo_province', ['id' => $province->id]);
});

it('cannot delete province with cities', function () {
    $province = Province::factory()->create(['country_id' => $this->country->id]);
    City::factory()->create(['province_id' => $province->id]);

    $response = $this->deleteJson("/api/v1/geo/provinces/{$province->id}");

    $response->assertStatus(422)
        ->assertJson(['message' => 'Cannot delete province. It has associated cities.']);

    $this->assertDatabaseHas('ref_geo_province', ['id' => $province->id]);
});

it('can get provinces list for dropdown', function () {
    Province::factory()->count(3)->create(['country_id' => $this->country->id]);

    $response = $this->getJson('/api/v1/geo/provinces/list');

    $response->assertStatus(200)
        ->assertJsonStructure([
            '*' => [
                'id',
                'country_id',
                'code',
                'name',
                'country'
            ]
        ]);
});

it('can filter provinces list by country id', function () {
    $country2 = Country::factory()->create();
    Province::factory()->create(['country_id' => $this->country->id]);
    Province::factory()->create(['country_id' => $country2->id]);

    $response = $this->getJson("/api/v1/geo/provinces/list?filter[country_id]={$this->country->id}");

    $response->assertStatus(200)
        ->assertJsonCount(1)
        ->assertJsonPath('0.country_id', $this->country->id);
});

it('can get provinces by country', function () {
    Province::factory()->count(3)->create(['country_id' => $this->country->id]);

    $response = $this->getJson("/api/v1/geo/countries/{$this->country->id}/provinces");

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

it('returns empty array for country without provinces', function () {
    $country = Country::factory()->create();

    $response = $this->getJson("/api/v1/geo/countries/{$country->id}/provinces");

    $response->assertStatus(200)
        ->assertJson([]);
});
