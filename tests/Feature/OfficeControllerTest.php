<?php

namespace Tests\Feature;

use App\Models\Image;
use App\Models\Office;
use App\Models\Reservation;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\OfficePendingApprovalNotification;
use Cassandra\Exception\TruncateException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OfficeControllerTest extends TestCase
{
    use LazilyRefreshDatabase;


    /**
     * @test
     */
    public function itListsAllOfficesPaginatedWay()
    {
        Office::factory(30)->create();
        $response = $this->get('/api/offices');

        $response->assertOk();
        $response->assertJsonCount(20,'data');
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

        Office::factory(3)->hidden()->create();
        Office::factory(3)->pending()->create();

        $response = $this->get('/api/offices');

        $response->assertOk();
        $response->assertJsonCount(3,'data');

    }

    /**
     * @test
     */
    public function isListsOfficesIncludingHiddenAndApprovedIfFilteringForTheCurrentLoggedInUser()
    {
        $user = User::factory()->create();
        Office::factory(3)->for($user)->create();

        Office::factory()->hidden()->for($user)->create();
        Office::factory()->pending()->for($user)->create();

        $this->actingAs($user);

        $response = $this->get('/api/offices?user_id='.$user->id);

        $response->assertOk();
        $response->assertJsonCount(5,'data');

    }


    /**
     * @test
     */
    public function isFilterByUserId(){
       Office::factory(3)->create();

       $host = User::factory()->create();

       $office = Office::factory()->for($host)->create();

        $response = $this->get(
            '/api/offices?user_id='.$host->id
        );

        $response->assertOk();
        $response->assertJsonCount(1,'data');
        $this->assertEquals($office->id, $response->json('data')[0]['id']);

    }

    /**
     * @test
     */
    public function isFilterByVisitorId(){
        Office::factory(3)->create();

        $user = User::factory()->create();

        $office = Office::factory()->create();

        Reservation::factory()->for(Office::factory())->create();
        Reservation::factory()->for($office)->for($user)->create();

        $response = $this->get(
            '/api/offices?visitor_id='.$user->id
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


    /**
     * @test
     */
    public function itCreatesAnOffice()
   {
       $user =User::factory()->createQuietly();
       $admin = User::factory()->create(['is_admin' => true]);

       Notification::fake();

       $tags = Tag::factory(2)->create();

       $this->actingAs($user);

       $response = $this->postJson('/api/offices', Office::factory()->raw([
             'tags' => $tags->pluck('id')->toArray()
       ]));

       $response->assertCreated()
           ->assertJsonPath('data.approval_status', Office::APPROVAL_PENDING)
           ->assertJsonPath('data.user.id', $user->id)
           ->assertJsonCount(2, 'data.tags');

       $this->assertDatabaseHas('offices', [
           'id' => $response->json('data.id')
       ]);
       Notification::assertSentTo($admin,OfficePendingApprovalNotification::class);




   }

    /**
     * @test
     */
    public function itDoesntAllowCreatingIfScopeNotProvided()
    {
        $user = User::factory()->createQuietly();

        $token = $user->createToken('test',[]);


        $response = $this->postJson('/api/offices',[],[
            'Authorization' => 'Bearer '.$token->plainTextToken
        ]);

     $response->assertStatus(Response::HTTP_FORBIDDEN);

    }

    /**
     * @test
     */
    public function itAllowsCreatingIfScopeIsProvided()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, ['office.create']);

        $response = $this->postJson('/offices');

        $this->assertNotEquals(Response::HTTP_FORBIDDEN, $response->status());
    }

    /**
     * @test
     */
    public function itUpdatesAnOffice()
    {
        $user =User::factory()->createQuietly();

        $tags = Tag::factory(2)->create();
        $office  =   Office::factory()->for($user)->create();

       $office->tags()->attach($tags);


        $this->actingAs($user);

        $anotherTag = Tag::factory()->create();


        $response = $this->putJson('/api/offices/'.$office->id,  [
               'title' => "Amazing Office",
                'tags' => [$tags[0]->id,$anotherTag->id]
         ]);



        $response->assertOk()
            ->assertJsonCount(2,'data.tags')
            ->assertJsonPath('data.tags.0.id',$tags[0]->id)
            ->assertJsonPath('data.tags.1.id',$anotherTag->id)
            ->assertJsonPath('data.title','Amazing Office');


    }


    /**
     * @test
     */
    public function itUpdatedTheFeaturedImageOfAnOffice()
    {
        $user =User::factory()->createQuietly();

        $office  =   Office::factory()->for($user)->create();

        $image = $office->images()->create([
            'path' => 'image.jpg'
        ]);
        $this->actingAs($user);

        $response = $this->putJson('/api/offices/'.$office->id,  [
            'featured_image_id' => $image->id,
        ]);

        $response->assertOk()
               ->assertJsonPath('data.featured_image_id',$image->id);



    }

    /**
     * @test
     */
    public function itDoesntUpdateFeatureImageThatBelongsToAnotherOffice()
    {
        $user =User::factory()->create();

        $office  =   Office::factory()->for($user)->create();
        $office2  =   Office::factory()->for($user)->create();

        $image = $office2->images()->create([
            'path' => 'image.jpg'
        ]);
        $this->actingAs($user);

        $response = $this->putJson('/api/offices/'.$office->id,  [
            'featured_image_id' => $image->id,
        ]);

        $response->assertUnprocessable()->assertInvalid('featured_image_id');



    }

    /**
     * @test
     */
    public function itDoesntUpdatesOfficeThatDoesntBelongToUser()
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();
        $office  =   Office::factory()->for($anotherUser)->create();

        $this->actingAs($user);

        $response = $this->putJson('/api/offices/'.$office->id,  [
            'title' => "Amazing Office"
        ]);

         $response->assertStatus(Response::HTTP_FORBIDDEN);


    }

    /**
     * @test
     */
    public function itMarksTheOfficeAsPendingIfDirty()
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Notification::fake();

        $user = User::factory()->create();
        $office  =   Office::factory()->for($user)->create();



        $this->actingAs($user);

        $response = $this->putJson('/api/offices/'.$office->id,  [
            'lat' => 23.339553840957688,
        ]);

        $response->assertOK();

        $this->assertDatabaseHas('offices',[
            'id' => $office->id,
            'approval_status' => Office::APPROVAL_PENDING
        ]);

        Notification::assertSentTo($admin,OfficePendingApprovalNotification::class);



    }

    /**
     * @test
     */
    public function itCanDeleteOffices()
    {
        Storage::put('/office_image.jpg','empty');

        $user = User::factory()->create();
        $office  =   Office::factory()->for($user)->create();

        $image = $office -> images() -> create([
            'path' => 'office_image.jpg'
        ]);

        $this->actingAs($user);

        $response = $this->delete('/api/offices/'.$office->id);

        $response->assertOk();

        $this->assertSoftDeleted($office);

        $this -> assertModelMissing($image);

        Storage::assertMissing('office_image.jpg');

    }

    /**
     * @test
     */
    public function itCannotDeleteAnOfficeThatHasReservation()
    {
        $user = User::factory()->create();
        $office  =   Office::factory()->for($user)->create();


        Reservation::factory(3)->for($office)->create();

        $this->actingAs($user);

        $response = $this->deleteJson('/api/offices/'.$office->id);

        $response->assertUnprocessable();

        $this->assertNotSoftDeleted($office);
    }


}
