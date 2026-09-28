<?php

use App\Enums\TeamRole;
use App\Models\ScanCategory;
use App\Models\ScanSetting;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Http::fake(['*/health' => Http::response(['status' => 'ok', 'classifier' => true])]);

    $this->owner = User::factory()->create();
    $this->member = User::factory()->create();
    $this->team = Team::factory()->create();
    $this->team->members()->attach($this->owner, ['role' => TeamRole::Owner->value]);
    $this->team->members()->attach($this->member, ['role' => TeamRole::Member->value]);
});

function validSettings(array $overrides = []): array
{
    return [
        'idle_change_ratio' => 0.002,
        'low_change_ratio' => 0.02,
        'time_gap_minutes' => 20,
        'clock_mismatch_minutes' => 10,
        'jiggler_min_streak' => 3,
        'similar_distance' => 5,
        'read_screen_clock' => true,
        'classify_screenshots' => false,
        'ai_min_confidence' => 0.6,
        ...$overrides,
    ];
}

test('the settings page lists the default categories and effective settings', function () {
    $this->actingAs($this->member)
        ->get(route('scan-settings.edit', $this->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('scans/Settings')
            ->has('categories', count(config('scanning.default_categories')))
            ->where('settings.time_gap_minutes', config('scanning.time_gap_minutes'))
            ->where('canEdit', false)
            ->missing('settings.pixel_threshold')
        );
});

test('owners can change the analysis settings', function () {
    $this->actingAs($this->owner)
        ->put(route('scan-settings.update', $this->team), validSettings())
        ->assertRedirect();

    expect(ScanSetting::valuesFor($this->team->id))
        ->toMatchArray(['time_gap_minutes' => 20, 'classify_screenshots' => false, 'ai_min_confidence' => 0.6]);
});

test('members cannot change the analysis settings', function () {
    $this->actingAs($this->member)
        ->put(route('scan-settings.update', $this->team), validSettings())
        ->assertForbidden();

    expect(ScanSetting::count())->toBe(0);
});

test('the low activity threshold cannot be below the idle threshold', function () {
    $this->actingAs($this->owner)
        ->put(route('scan-settings.update', $this->team), validSettings(['idle_change_ratio' => 0.05, 'low_change_ratio' => 0.01]))
        ->assertSessionHasErrors(['low_change_ratio' => 'Batas aktivitas rendah harus lebih besar atau sama dengan batas layar diam.']);
});

test('owners can add a category with comma separated keywords', function () {
    $this->actingAs($this->owner)
        ->post(route('scan-categories.store', $this->team), [
            'name' => 'Musik',
            'prompt' => 'a screenshot of a music streaming app',
            'keywords' => ' Spotify, joox ,, spotify',
            'is_productive' => false,
            'is_enabled' => true,
        ])
        ->assertRedirect();

    $category = $this->team->scanCategories()->sole();
    expect($category->keywords)->toBe(['spotify', 'joox'])
        ->and($category->is_productive)->toBeFalse();
});

test('owners can update and delete a category', function () {
    $category = ScanCategory::factory()->for($this->team)->create();

    $this->actingAs($this->owner)
        ->put(route('scan-categories.update', [$this->team, $category]), [
            'name' => 'Renamed',
            'prompt' => 'a screenshot of something',
            'keywords' => '',
            'is_productive' => true,
            'is_enabled' => false,
        ])
        ->assertRedirect();

    expect($category->fresh())->name->toBe('Renamed')->is_enabled->toBeFalse()->keywords->toBe([]);

    $this->actingAs($this->owner)
        ->delete(route('scan-categories.destroy', [$this->team, $category]))
        ->assertRedirect();

    $this->assertModelMissing($category);
});

test('members cannot change categories', function () {
    $category = ScanCategory::factory()->for($this->team)->create();

    $this->actingAs($this->member)
        ->delete(route('scan-categories.destroy', [$this->team, $category]))
        ->assertForbidden();

    $this->assertModelExists($category);
});

test('categories of another team cannot be changed', function () {
    $otherCategory = ScanCategory::factory()->create();

    $this->actingAs($this->owner)
        ->delete(route('scan-categories.destroy', [$this->team, $otherCategory]))
        ->assertNotFound();

    $this->assertModelExists($otherCategory);
});
