<?php

namespace App\Http\Controllers;

use App\Http\Resources\OfficeResource;
use App\Models\Office;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;


class OfficeController extends Controller
{
   public function index(): JsonResource
   {
       $offices = Office ::query()
           -> where('approval_status', Office::APPROVAL_APPROVED)
           -> where('hidden', false)
           -> when(request('user_id'), fn($builder) => $builder -> whereUserId(request('user_id')))
           -> when(request('visitor_id'),
               fn(Builder $builder) => $builder -> whereRelation('reservations', 'user_id', '=', request('visitor_id'))
           )
           -> when(
               request('lat') && request('lng'),
                     fn($builder) => $builder->nearestTo(request('lat'),request('lng')),
                     fn($builder) => $builder->orderBy('id','ASC')
           )
           -> with(['images', 'tags', 'user'])
           ->withCount(['reservations' => fn ($builder) => $builder->where('status',Reservation::STATUS_ACTIVE)])
           ->paginate(20);
      return OfficeResource::collection(
        $offices
      );
   }


   public function show(Office $office){
       $office->loadCount(['reservations' => fn ($builder) => $builder->where('status',Reservation::STATUS_ACTIVE)])
              ->load(['images', 'tags', 'user']);
       return OfficeResource::make($office);
   }

   public function create():JsonResource
   {
      if(!auth()->user()->tokenCan('office.create')){
         abort(Response::HTTP_FORBIDDEN);
      }
     $attributes =  validator(request()->all(),
     [
         'title' => ['string','required'],
         'description' => ['string','required'],
         'lat' => ['numeric','required'],
         'lng' => ['numeric','required'],
         'address_line1' => ['string','required'],
         'hidden' => ['bool'],
         'price_per_day' => ['required','integer','min:100'],
         'monthly_discount' => ['integer','min:0','max:90'],


         'tags' => ['array'],
         'tags.*' => ['integer',Rule::exists('tags','id')]
     ]
     )->validate();

     $attributes['approval_status'] = Office::APPROVAL_PENDING;

     $office = DB::transaction(function () use ($attributes){
         $office = auth()->user()->offices()->create(
             Arr::except($attributes,['tags'])
         );

         $office->tags()->attach($attributes['tags']);

         return$office;
     });

     return OfficeResource::make(
         $office->load(['images','tags','user'])
     );

   }


}
