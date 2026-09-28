<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function invitationFor(string $email, string $teamName = 'Laravel Team'): TeamInvitation
{
    $owner = User::factory()->create();
    $team = Team::factory()->create(['name' => $teamName]);
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    return TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => $email,
        'invited_by' => $owner->id,
    ]);
}

function registrationData(string $email = 'test@example.com'): array
{
    return [
        'name' => 'Test User',
        'email' => $email,
        'password' => 'password',
        'password_confirmation' => 'password',
    ];
}

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('auth/Register')
        ->where('inviteOnly', true),
    );
});

test('registration screen includes team invitation context', function () {
    $invitation = invitationFor('invited@example.com');

    $response = $this->get(route('register', ['invitation' => $invitation->code]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('auth/Register')
        ->where('teamInvitation.code', $invitation->code)
        ->where('teamInvitation.teamName', 'Laravel Team')
        ->where('teamInvitation.email', 'invited@example.com'),
    );
});

test('invited users can register', function () {
    invitationFor('test@example.com');

    $response = $this->post(route('register.store'), registrationData());

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard'));
});

test('the invitation email is matched case-insensitively', function () {
    invitationFor('Test@Example.com');

    $this->post(route('register.store'), registrationData('test@example.com'));

    $this->assertAuthenticated();
});

test('uninvited emails cannot register', function () {
    $response = $this->post(route('register.store'), registrationData());

    $response->assertSessionHasErrors([
        'email' => 'Pendaftaran hanya untuk email yang sudah diundang ke team. Minta undangan dari admin team Anda.',
    ]);
    $this->assertGuest();
    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
});

test('used or expired invitations do not allow registration', function (string $state) {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    TeamInvitation::factory()->{$state}()->create([
        'team_id' => $team->id,
        'email' => 'test@example.com',
        'invited_by' => $owner->id,
    ]);

    $this->post(route('register.store'), registrationData())
        ->assertSessionHasErrors('email');

    $this->assertGuest();
})->with(['accepted', 'expired']);

test('anyone can register when invite-only registration is turned off', function () {
    config(['fortify.invite_only' => false]);

    $this->post(route('register.store'), registrationData());

    $this->assertAuthenticated();
});

test('the sign up links are hidden while registration is invite-only', function () {
    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->component('auth/Login')->where('canRegister', false));

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->component('Welcome')->where('canRegister', false));

    config(['fortify.invite_only' => false]);

    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->where('canRegister', true));
});
