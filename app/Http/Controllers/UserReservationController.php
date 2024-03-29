<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReservationResource;
use App\Models\Office;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserReservationController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->tokenCan('reservation.show'),
            Response::HTTP_FORBIDDEN
        );

        //
        validator(request()->all(),[
             'status' => [Rule::in([Reservation::STATUS_ACTIVE,Reservation::STATUS_CANCELLED])],
             'office_id' => ['integer'],
             'from_date' => ['date','required_with:to_date'],
             'to_date' => ['date','required_with:from_date','after:from_date']
            ])->validate();

        $reservations = Reservation::query()
            ->where('user_id',auth()->id())
            ->when(request('office_id'),
                fn($query) => $query->where('office_id',request('office_id'))
            )->when(request('status'),
                fn($query) => $query->where('status',request('status'))
            )->when(
                request('from_date') && request('to_date'),
                fn($query) => $query->betweenDates(request('from_date') , request('to_date'))
            )
            ->with('office.featuredImage')
            ->paginate(20);

        return ReservationResource::collection($reservations);
    }


    public function create(){
        abort_unless(auth()->user()->tokenCan('reservation.make'),
            Response::HTTP_FORBIDDEN
        );

        $data = validator(request()->all(),[
            'office_id' => ['required','integer'],
            'start_date' => ['required','date:Y-m-d','after:today'],
            'end_date' => ['required','date:Y-m-d','after:start_date'],
        ])->validate();

        try {
            $office = Office::findOrFail($data['office_id']);
       }catch (ModelNotFoundException $e){
            throw ValidationException::withMessages([
               'office_id' => 'Invalid office_id'
            ]);
        }

        throw_if($office->user_id == auth()->id(),
                ValidationException::withMessages(['office_id' => 'you cannot make a reservation on your own office'])
          );

        throw_if($office->hidden || $office->approval_status  == Office::APPROVAL_PENDING,
            ValidationException::withMessages(['office_id' => 'You cannot make a reservation on a hidden office'])
        );

        $reservation = Cache::lock('reservations_office_'.$office->id, 10)->block(3,function () use($data, $office){
            $numberOfDays = Carbon::parse($data['end_date'])->endOfDay()->diffInDays(
                Carbon::parse($data['start_date'])->startOfDay()
            ) + 1;

            throw_if($numberOfDays < 2,
                ValidationException::withMessages(['start_date' => 'You cannot make a reservation for only 1 day'])
            );

            throw_if($office->reservations()->activeBetween($data['start_date'],$data['end_date'])->count() > 0,
                ValidationException::withMessages(['office_id' => 'You cannot make a reservation during this time'])
            );


            $price = $numberOfDays * $office->price_per_day;

            if($numberOfDays >= 28 && $office->monthly_discount){
                $price = $price - ($price * $office->monthly_discount / 100);
            }


            return Reservation::create([
                'user_id' => auth()->id(),
                'office_id' => $office->id,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'status' => Reservation::STATUS_ACTIVE,
                'price' => $price,
            ]);
        });



        return ReservationResource::make($reservation->load('office'));


    }

}
