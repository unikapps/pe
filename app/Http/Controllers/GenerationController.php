<?php

namespace App\Http\Controllers;

use App\Actions\CreateGeneration;
use App\Enums\Quality;
use App\Enums\Size;
use App\Events\GenerationUpdated;
use App\Http\Requests\StoreGenerationRequest;
use App\Http\Resources\GenerationResource;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class GenerationController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('generations/Index', [
            // Reloaded whenever one of this user's generations changes.
            'generations' => Inertia::live(
                fn () => GenerationResource::collection(
                    $user->generations()->latest()->limit(50)->get(),
                )->resolve(),
                on: GenerationUpdated::class,
                channel: GenerationUpdated::channelFor($user->id),
            ),
            'sizes' => array_column(Size::cases(), 'value'),
            'qualities' => array_column(Quality::cases(), 'value'),
        ]);
    }

    public function store(StoreGenerationRequest $request, CreateGeneration $createGeneration): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $createGeneration->handle(
            $user,
            $request->string('prompt')->toString(),
            $request->size(),
            $request->quality(),
            $request->referenceImages(),
        );

        return to_route('generations.index');
    }

    public function image(Request $request, Generation $generation): HttpResponse
    {
        abort_unless($generation->user_id === $request->user()?->id, 404);

        if ($generation->image_path !== null) {
            return Storage::response($generation->image_path);
        }

        abort_if($generation->image_url === null || $generation->image_url_expires_at?->isPast() !== false, 404);

        // Not copied yet: the provider's signed URL still works for now.
        return redirect()->away($generation->image_url);
    }
}
