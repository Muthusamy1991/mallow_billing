<?php

namespace Tests\Feature;

use App\Models\Merchant;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    public function test_home_lists_merchants(): void
    {
        Merchant::factory()->create(['name' => 'Northwind SaaS']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Northwind SaaS');
    }
}
