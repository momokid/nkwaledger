<?php

use App\Models\AppSetting;
use App\Models\AssistantMessage;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.openrouter.key' => 'test-key',
        'services.openrouter.url' => 'https://openrouter.test/api/v1',
    ]);
});

function fakeAssistant(string $reply = 'Tap Reports to print one.'): void
{
    Http::fake(['openrouter.test/*' => Http::response(['choices' => [['message' => ['content' => $reply]]]])]);
}

test('a signed-in user gets an answer and the chat is stored', function () {
    fakeAssistant();
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/assistant', ['message' => 'How do I print a report?'])
        ->assertOk()
        ->assertJson(['reply' => 'Tap Reports to print one.']);

    $this->assertDatabaseHas('assistant_messages', [
        'user_id' => $user->id,
        'message' => 'How do I print a report?',
        'reply' => 'Tap Reports to print one.',
    ]);
});

test('the request sends the knowledge files and the page list, and asks the provider not to keep data', function () {
    fakeAssistant();

    $this->actingAs(User::factory()->create())->postJson('/assistant', ['message' => 'Hi'])->assertOk();

    Http::assertSent(function ($request) {
        $system = $request['messages'][0]['content'];

        return $request['provider'] === ['data_collection' => 'deny']
            && str_contains($system, '/my-reports')
            && str_contains($system, 'Reports')
            && $request['messages'][1] === ['role' => 'user', 'content' => 'Hi'];
    });
});

test('only the typed question leaves the app, never the name or phone', function () {
    fakeAssistant();
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/assistant', ['message' => 'Hi'])->assertOk();

    Http::assertSent(fn ($request) => ! str_contains(json_encode($request->data()), (string) $user->phone)
        && ! str_contains(json_encode($request->data()), (string) $user->first_name));
});

test('a user is stopped after 50 questions in a day and sent to the support number', function () {
    fakeAssistant();
    AppSetting::put('support_phone', '0200000000');
    $user = User::factory()->create();
    AssistantMessage::factory()->count(50)->create(['user_id' => $user->id]);

    $this->actingAs($user)->postJson('/assistant', ['message' => 'Hi'])
        ->assertStatus(429)
        ->assertJsonPath('support_phone', '0200000000');

    Http::assertNothingSent();
});

test("yesterday's questions do not count toward today's limit", function () {
    fakeAssistant();
    $user = User::factory()->create();
    AssistantMessage::factory()->count(50)->create(['user_id' => $user->id, 'created_at' => now()->subDay()]);

    $this->actingAs($user)->postJson('/assistant', ['message' => 'Hi'])->assertOk();
});

test('when the provider fails the user is told and nothing breaks', function () {
    Http::fake(['openrouter.test/*' => Http::response([], 500)]);
    AppSetting::put('support_phone', '0200000000');

    $this->actingAs(User::factory()->create())->postJson('/assistant', ['message' => 'Hi'])
        ->assertStatus(503)
        ->assertJsonPath('support_phone', '0200000000');
});

test('an empty or very long question is refused', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/assistant', ['message' => ''])->assertStatus(422);
    $this->actingAs($user)->postJson('/assistant', ['message' => str_repeat('a', 501)])->assertStatus(422);
});

test('a guest cannot use the assistant', function () {
    $this->postJson('/assistant', ['message' => 'Hi'])->assertStatus(401);
});
