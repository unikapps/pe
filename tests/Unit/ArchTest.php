<?php

arch('pricing is plain PHP with no framework dependencies')
    ->expect('App\Pricing')
    ->not->toUse('Illuminate');

arch('only the the provider client talks HTTP')
    ->expect('Illuminate\Support\Facades\Http')
    ->toOnlyBeUsedIn('App\ImageProvider');

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();
