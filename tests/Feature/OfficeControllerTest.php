<?php

namespace Tests\Feature;

use App\Models\Image;
use App\Models\Office;
use App\Models\Reservation;
use App\Models\Tag;
use App\Models\User;
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


    /**
     * @test
     */
    public function isFilterByHostId(){
       Office::factory(3)->create();

       $host = User::factory()->create();

       $office = Office::factory()->for($host)->create();

        $response = $this->get(
            '/api/offices?host_id='.$host->id
        );

        $response->assertOk();
        $response->assertJsonCount(1,'data');
        $this->assertEquals($office->id, $response->json('data')[0]['id']);

    }

    /**
     * @test
     */
    public function isFilterByUserId(){
        Office::factory(3)->create();

        $user = User::factory()->create();

        $office = Office::factory()->create();

        Reservation::factory()->for(Office::factory())->create();
        Reservation::factory()->for($office)->for($user)->create();

        $response = $this->get(
            '/api/offices?user_id='.$user->id
        );

        $response->assertOk();
        $response->assertJsonCount(1,'data');
        $this->assertEquals($office->id, $response->json('data')[0]['id']);

    }


    /**
     * @test
     */
    public function itIncludesImagesTagsUser()
   {
       $user = User::factory()->create();

       $tag = Tag::factory()->create();

       $office = Office::factory()->for($user)->create();

       $office->tags()->attach($tag);
       $office->images()->create(['path' => 'image.jpg']);

       $response = $this->get('/api/offices');

       $response->assertOk();

       $this->assertIsArray($response->json('data')[0]['tags']);
       $this->assertCount(1,$response->json('data')[0]['tags']);
       $this->assertIsArray($response->json('data')[0]['images']);
       $this->assertCount(1,$response->json('data')[0]['images']);
       $this->assertEquals($user->id,$response->json('data')[0]['user']['id']);

   }


    /**
     * @test
     */
    public function isReturnsTheNumberOfActiveReservations()
   {
       $office = Office::factory()->create();

       Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_ACTIVE]);

       Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_CANCELLED]);

       $response = $this->get('/api/offices');

       $response->assertOk();

       $this->assertEquals(1,$response->json('data')[0]['reservations_count']);

   }


    /**
     * @test
     */
    public function isOrderByDistanceWhenCoordinatesAreProvider()
   {
       //22.334155292421983 , 91.7888275985061


       $office1 = Office::factory()->create([
           'lat' => '22.339553840957688',
           'lng' => '91.78123158250924',
           'title' => 'I Block'
       ]);

       $office2 = Office::factory()->create([
           'lat' => '22.330185638112855',
           'lng' => '91.79063004297996',
           'title' => 'Chevron'
       ]);

       $response = $this->get('/api/offices?lat=22.334155292421983&lng=91.7888275985061');


       $response->assertOk();
       $this->assertEquals('Chevron',$response->json('data')[0]['title']);
       $this->assertEquals('I Block',$response->json('data')[1]['title']);

       $response = $this->get('/api/offices');


       $response->assertOk();
       $this->assertEquals('I Block',$response->json('data')[0]['title']);
       $this->assertEquals('Chevron',$response->json('data')[1]['title']);


   }

    /**
     * @test
     */
    public function itShowsTheOffice()
   {
       $user = User::factory()->create();

       $tag = Tag::factory()->create();

       $office = Office::factory()->for($user)->create();

       $office->tags()->attach($tag);
       $office->images()->create(['path' => 'image.jpg']);

       Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_ACTIVE]);

       Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_CANCELLED]);

       $response = $this->get('/api/office/'.$office->id);

       $this->assertEquals(1,$response->json('data')['reservations_count']);
       $this->assertIsArray($response->json('data')['tags']);
       $this->assertCount(1,$response->json('data')['tags']);
       $this->assertIsArray($response->json('data')['images']);
       $this->assertCount(1,$response->json('data')['images']);
       $this->assertEquals($user->id,$response->json('data')['user']['id']);



   }

}
