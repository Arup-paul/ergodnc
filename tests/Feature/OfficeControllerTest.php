<?php

namespace Tests\Feature;

use App\Models\Office;
use Cassandra\Exception\TruncateException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class OfficeControllerTest extends TestCase
{
    use RefreshDatabase;


    /**
     * @test
     */
    public function itListsAllOfficesPaginatedWay()
    {
        Office::factory(3)->create();
        $response = $this->get('/api/offices');

        $response->assertOk();
        $response->assertJsonCount(3,'data');
        $this->assertNotNull($response->json('data')[0]['id']);
        $this->assertNotNull($response->json('meta'));
        $this->assertNotNull($response->json('links'));

    }

    /**
     * @test
     */
    public function isOnlyListsOfficeThatAreNotHiddenApproved()
    {
        Office::factory(3)->create();

        Office::factory(3)->create(['hidden' => true]);
        Office::factory(3)->create(['approval_status' => Office::APPROVAL_PENDING]);

        $response = $this->get('/api/offices');

        $response->assertOk();
        $response->assertJsonCount(3,'data');

    }


}
