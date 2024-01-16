<?php

namespace App\Http\Services\Dashboard;
use App\Http\Mail\ConvertMoney;
use App\Http\Requests\Dashboard\UserRequest;
use App\Traits\GeneralTrait;
use App\Models\User;
use App\Models\OrderTicket;
use App\Models\Ticket;
use App\Models\Event;
use App\Models\subcategory;
use App\Models\Order;
use App\Models\Box;
use Mail;

class UserService
{
    use GeneralTrait;

    public function index()
    {
        $users = User::when(request()->has('search') && request('search') !== "", function ($query) {
                        $searchTerm = '%' . request('search') . '%';
                        $query->where('name', 'like', $searchTerm)
                            ->orWhere('email', 'like', $searchTerm)
                            ->orWhere('phone', 'like', $searchTerm);
                    })
                        ->orderBy('created_at', 'desc')
                        ->paginate(10);
        return view('dashboard.user.index' , ['users' => $users]);
    }

    public function create()
    {
        return view('dashboard.user.create');
    }

    public function store(UserRequest $request)
    {
        $data = array_merge($request->input());
        User::create($data);
        return redirect('user')->with(["success"=>__('dashboard.recored created successfully.')]);
    }

    public function show($id)
    {
        $user = User::find($id);
        $wallet = OrderTicket::where('from_user', $user->id)
                                ->whereHas('order', function ($query) {
                                    $query->where(['is_adminAccepted' => 1, 'is_userAccepted' => 1]);
                                })
                                ->with(['order.payments' => function ($query) {
                                    $query->where('is_accepted', 1);
                                }])
                                ->get()
                                ->sum(function ($orderTicket) {
                                    return $orderTicket->order->payments->sum('price');
                                });
        $user->wallet = $wallet;
        $tickets = Ticket::where('user_id',null)->get();
        return view('dashboard.user.show' , ['user' => $user , 'id' => $id , 'tickets' => $tickets]);
    }

    public function edit($id)
    {
        $user = User::find($id);
        return view('dashboard.user.edit' , ['user' => $user]);
    }

    public function update(UserRequest $request,$id)
    {
        $user = User::find($id);
        if($request->is_commission != null)
        {
            $is_commission = 1;
            $specialcommission = $request->commission;
        }
        else
        {
            $is_commission = 0;
            $specialcommission = 0;
        }
         if($request->special != null)
         {
             $special = 1;
         }
         else
         {
             $special = 0;
         }
        $user->update([
                        'special' => $special,
                        'is_commission' => $is_commission,
                        'commission' => $specialcommission,
                    ]);
        return redirect('user')->with(["success"=>__('dashboard.recored updated successfully.')]);
    }

    public function delete($id)
    {
        $user = User::find($id);
        $user->delete();
        return redirect('user')->with(["success"=>__('dashboard.recored deleted successfully.')]);
    }

    public function getUserSales($id)
    {
        $user = User::find($id);
        $orders_tickets = OrderTicket::where('from_user', $user->id)
                                        ->whereHas('order', function ($query) {
                                            $query->where(['is_adminAccepted' =>  1 , 'is_userAccepted' => 2]);
                                        })
                                        ->get();
        foreach($orders_tickets as $orders_ticket)
        {
            $orders_ticket->ticket = Ticket::find($orders_ticket->ticket_id);
            $orders_ticket->event = Event::find($orders_ticket->event_id);

            $order = Order::where([
                                        'id' => $orders_ticket->order_id,
                                        'is_adminAccepted' => 1,
                                        'is_userAccepted' => 2,
                                    ])->first();
            if($order)
            {
                $orders_ticket->order = $order;
            }
            $subcategory_name = subcategory::find($orders_ticket->ticket->subcategory_id);
            $orders_ticket->subcategory_name = $subcategory_name->name;
        }
        $totalprice = 0;
        foreach($orders_tickets as $user)
        {
            $ticket = Ticket::find($user->ticket_id);
            if($ticket)
            {
                $totalprice += $user->quantity * $ticket->price;
            }
        }
        return view('dashboard.user.showSales' , ['orders_tickets' => $orders_tickets , 'totalprice' => $totalprice]);
    }

    public function getUserPurchases($id)
    {
        $user = User::find($id);
        $orders_tickets = OrderTicket::where('to_user', $user->id)
                                        ->whereHas('order', function ($query) {
                                            $query->where(['is_adminAccepted' =>  1 , 'is_userAccepted' => 2]);
                                        })
                                        ->get();
        foreach($orders_tickets as $orders_ticket)
        {
            $orders_ticket->ticket = Ticket::find($orders_ticket->ticket_id);
            $orders_ticket->event = Event::find($orders_ticket->event_id);

            $order = Order::where([
                                        'id' => $orders_ticket->order_id,
                                        'is_adminAccepted' => 1,
                                        'is_userAccepted' => 2,
                                    ])->first();
            if($order)
            {
                $orders_ticket->order = $order;
            }
            $subcategory_name = subcategory::find($orders_ticket->ticket->subcategory_id);
            $orders_ticket->subcategory_name = $subcategory_name->name;
        }

        $totalprice = 0;
        foreach($orders_tickets as $user)
        {
            $totalprice += $user->newprice;
        }
        return view('dashboard.user.showPurchases' , ['orders_tickets' => $orders_tickets , 'totalprice' => $totalprice]);
    }

    public function ticketconvert($id)
    {
        $orders_ticket = OrderTicket::find($id);
        $orders_ticket->update(['is_convert' => 1]);
        $user = User::find($orders_ticket->from_user);
        $ticket = Ticket::find($orders_ticket->ticket_id);
        $details = [
                        'message'           => 'تم تحويل سعر التذكره من الماللك',
                        'order_number'      =>  $orders_ticket->order->order_number,
                        'amount'            => $ticket->price * $orders_ticket->quantity
                    ];

        Mail::to($user->email)->send(new ConvertMoney($details));
        return redirect()->back()->with(["success"=>__('dashboard.recored updated successfully.')]);
    }

}
