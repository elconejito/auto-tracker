<?php

namespace Tests\Feature;

use Tests\TestCase;

class SpaShellTest extends TestCase
{
    public function test_the_spa_shell_is_served_for_the_root_route(): void
    {
        $this->withoutVite();

        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('id="app"', false);
    }

    public function test_the_spa_shell_is_served_for_client_side_routes(): void
    {
        $this->withoutVite();

        $response = $this->get('/vehicles/example');

        $response
            ->assertOk()
            ->assertSee('id="app"', false);
    }
}
