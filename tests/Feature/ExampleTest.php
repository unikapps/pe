<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_sends_people_into_the_app()
    {
        $response = $this->get(route('home'));

        $response->assertRedirect(route('generations.index'));
    }
}
