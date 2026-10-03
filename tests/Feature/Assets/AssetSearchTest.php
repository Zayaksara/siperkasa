<?php

use App\Models\Asset;
use App\Models\User;

test('guests can not search assets', function () {
    $this->getJson(route('assets.search', ['q' => 'proyektor']))->assertUnauthorized();
});

test('any logged-in role can search by name or code', function (string $state) {
    Asset::factory()->create(['name' => 'Proyektor Epson', 'asset_code' => '3050101001-1']);
    Asset::factory()->create(['name' => 'Kursi Kuliah']);

    $this->actingAs(User::factory()->{$state}()->create())
        ->getJson(route('assets.search', ['q' => 'proyek']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Proyektor Epson');

    $this->actingAs(User::factory()->{$state}()->create())
        ->getJson(route('assets.search', ['q' => '3050101001']))
        ->assertJsonCount(1, 'data');
})->with(['reporter', 'admin', 'pic']);

test('results are capped at 20', function () {
    Asset::factory()->count(25)->create(['name' => 'Kursi Kuliah']);

    $this->actingAs(User::factory()->create())
        ->getJson(route('assets.search', ['q' => 'kursi']))
        ->assertJsonCount(20, 'data');
});

test('short queries and wildcard characters return nothing harmful', function () {
    Asset::factory()->count(3)->create();
    $user = User::factory()->create();

    $this->actingAs($user)->getJson(route('assets.search', ['q' => 'a']))->assertJsonCount(0, 'data');
    $this->actingAs($user)->getJson(route('assets.search', ['q' => '%%']))->assertJsonCount(0, 'data');
});
