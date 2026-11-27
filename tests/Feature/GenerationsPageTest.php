<?php

use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Http::fake(['*/v1/pricing' => Http::response(providerPricing())]);
});

it('shows only the signed-in user\'s generations', function () {
    $user = User::factory()->create();
    Generation::factory()->for($user)->status(GenerationStatus::Queued)->create();
    Generation::factory()->status(GenerationStatus::Queued)->create();

    $this->actingAs($user)
        ->get(route('generations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('generations/Index')
            ->has('generations', 1)
            ->where('generations.0.status', 'queued'));
});

it('estimates the price on the server', function () {
    $this->actingAs(User::factory()->create())
        ->postJson(route('generations.estimate'), [
            'prompt' => str_repeat('a', 400),
            'size' => '1536x1024',
            'quality' => 'high',
            'reference_image_count' => 2,
        ])
        ->assertOk()
        ->assertJsonPath('max', '0.467343750');
});

it('saves a pending generation with its estimate and queues the submission', function () {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('generations.store'), [
            'prompt' => 'A lighthouse on a cliff at dawn',
            'size' => '1024x1024',
            'quality' => 'low',
            'reference_images' => [],
        ])
        ->assertRedirect(route('generations.index'));

    $generation = Generation::sole();
    expect($generation->user_id)->toBe($user->id)
        ->and($generation->status)->toBe(GenerationStatus::Pending)
        ->and($generation->estimated_price_max->amount)->toBeGreaterThan(0);
    Queue::assertPushed(SubmitGeneration::class);
});

it('validates requests against the provider\'s limits', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('generations.store'), [
            'prompt' => '',
            'size' => '512x512',
            'quality' => 'ultra',
            'reference_images' => array_fill(0, 5, 'https://example.com/a.png'),
        ])
        ->assertSessionHasErrors(['prompt', 'size', 'quality', 'reference_images']);
});

it('does not serve other users\' images', function () {
    $generation = Generation::factory()->status(GenerationStatus::Succeeded)->create(['image_path' => 'generations/x.png']);

    $this->actingAs(User::factory()->create())
        ->get(route('generations.image', $generation))
        ->assertNotFound();
});
