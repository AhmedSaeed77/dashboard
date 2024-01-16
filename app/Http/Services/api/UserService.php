<?php

namespace App\Http\Services\api;

use App\Models\User;
use App\Models\Ticket;
use App\Models\OrderTicket;
use App\Models\ResetPassword;
use App\Traits\GeneralTrait;
use App\Http\Resources\api\UserResource;
use App\Http\Resources\api\UserInfoResource;
use App\Http\Requests\api\UserRequest;
use App\Http\Requests\api\UserLoginRequest;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\api\UserResetRequest;
use App\Http\Requests\api\UpdateProfileRequest;
use Mail;
use Auth;
use App\Http\Mail\NotifyMail;
use App\Http\Requests\api\UserConfirmRequest;
use App\Http\Requests\api\UserChangePasswordRequest;
use App\Http\Requests\api\UserChangePasswordDashboardRequest;

class UserService
{
    use GeneralTrait;

    public function register(UserRequest $request)
    {
        try
        {
            if($request->email)
            {
                $users = User::all();
                foreach($users as $user)
                {
                    if($request->email == $user->email)
                    {
                        return $this->returnError(422,__('site.Email_IS_Exist'));
                    }
                    if($request->phone == $user->phone)
                    {
                        return $this->returnError(422,__('site.Phone_IS_Exist'));
                    }
                }
            }
            $data = array_merge($request->input());
            $user = User::create($data);
            $token = $user->createToken('myapptoken')->plainTextToken;
            $user_data = new UserResource($user);
            return $this->returnData('data',['user_data' => $user_data , 'token' => $token] , __('site.User_Is_Registered'));
        }
        catch (\Exception $e)
        {
            return response()->json($e->getMessage(), 422);
        }
    }

    public function login(UserLoginRequest $request)
    {
        try
        {
            $user = User::where('email',  $request->email)->first();
            if (!$user || !Hash::check($request->password,$user->password))
            {
                return $this->returnError('',__('site.Invalid_email_or_password'));
            }
            $token = $user->createToken('myapptoken')->plainTextToken;
            $user_data = new UserResource($user);
            return $this->returnData('data',['user_data' => $user_data , 'token' => $token], __('site.User_Is_Login'));
        }
        catch (\Exception $e)
        {
            return response()->json($e->getMessage(), 401);
        }
    }

    public function reset(UserResetRequest $request)
    {
        try
        {
            $user = User::where('email',$request->email)->first();
            if($user)
            {

                $randomNumber = random_int(1000, 9999);
                $details = [
                                'title' => 'Reset',
                                'body' =>  $randomNumber,
                            ];

                Mail::to($request->email)->send(new NotifyMail($details));
                ResetPassword::where('user_id',$user->id)->delete();
                $reset = new ResetPassword();
                $reset->user_id = $user->id;
                $reset->reset = $randomNumber;
                $reset->save();
                return $this->returnData('data',__('site.Email_Send'), __('site.Email_Send'));
            }
            else
            {
                return $this->returnError('',__('site.Email_Not_Found'));
            }
        }
        catch (\Exception $e)
        {
            return response()->json($e->getMessage(), 401);
        }
    }

    public function resetUserconfirm(UserConfirmRequest $request)
    {
        try
        {
            $reset = ResetPassword::where('reset',$request->confirm)->first();
            if($reset)
            {
                return $this->returnData('data',__('site.code_Is_Confirm'), __('site.Code_Is_Send'));
            }
            else
            {
                return $this->returnError('',__('site.code_Not_Confirm'));
            }
        }
        catch (\Exception  $e)
        {
            return response()->json($e->getMessage(), 401);
        }
    }

    public function changePassword(UserChangePasswordRequest $request)
    {
        try
        {
            $user = User::where('email',$request->email)->first();
            if($user)
            {
                if($request->newpassword == $request->confirmpassword)
                {
                    $user->password = Hash::make($request->newpassword);
                    $user->save();
                    ResetPassword::where('user_id',$user->id)->delete();
                    return $this->returnData('data',__('site.password_Is_Changed'));
                }
                else
                {
                    return $this->returnError('',__('site.Not_Confirm'));
                }
            }
            return $this->returnError('',__('site.User_Not_Found'));
        }
        catch (\Exception  $e)
        {
            return response()->json($e->getMessage(), 401);
        }
    }

    public function getuserprofile()
    {
        $user = User::find(Auth::user()->id);
        return $this->returnData('data',$user);
    }

    public function updateprofile(UpdateProfileRequest $request)
    {
        $user = User::find(Auth::user()->id);
        if(is_null($user))
        {
            return $this->returnError('',__('site.User_Not_Found'));
        }
        if($request->oldpassword)
        {
            if(!Hash::check($request->oldpassword,$user->password))
            {
                return $this->returnError('',__('site.Old_Password_Not_Confirm'),__('site.Old_Password_Not_Confirm'));
            }
        }
        if($request->newpassword == $request->confirmpassword)
        {
            $user->update($request->input(),['password' => Hash::make($request->newpassword)]);
            return $this->returnData('data',$user,__('site.User_Profile_Updated'));
        }
        else
        {
            return $this->returnError('',__('site.Not_Confirm'),__('site.Not_Confirm'));
        }
        return $this->returnData('data',$user,__('site.User_Profile_Updated'));
    }

    public function updatechangepassword(UserChangePasswordDashboardRequest $request)
    {
        $user = User::find(Auth::user()->id);
        if(is_null($user))
        {
            return $this->returnError('',__('site.User_Not_Found'));
        }
        if($request->oldpassword)
        {
            if(!Hash::check($request->oldpassword,$user->password))
            {
                return $this->returnError('',__('site.Old_Password_Not_Confirm'),__('site.Old_Password_Not_Confirm'));
            }
        }
        if($request->newpassword == $request->confirmpassword)
        {
            $user->password = Hash::make($request->newpassword);
            $user->save();
            return $this->returnData('data',$user,__('site.Password_changed'));
        }
        else
        {
            return $this->returnError('',__('site.Not_Confirm'),__('site.Not_Confirm'));
        }
        return $this->returnData('data',$user,__('site.User_Profile_Updated'));
    }

    public function getuserinfo()
    {
        $user = User::find(Auth::user()->id);
        $newordercount = OrderTicket::where('from_user', $user->id)
                                        ->whereHas('order', function ($query) {
                                            $query->where(['is_adminAccepted' =>  1 , 'is_userAccepted' => 0]);
                                        })
                                        ->count();

        // $sallescount = OrderTicket::where('from_user', $user->id)
        //                                 ->whereHas('order', function ($query) {
        //                                     $query->where(['is_adminAccepted' =>  1 , 'is_userAccepted' => 1]);
        //                                 })
        //                                 ->sum('newprice');

        $ordertickets = OrderTicket::where('from_user', $user->id)
                                ->whereHas('order', function ($query) {
                                    $query->where(['is_adminAccepted' => 1, 'is_userAccepted' => 2]);
                                })
                                ->get();

        $sumsalles = 0;
        foreach($ordertickets as $orderticket)
        {
            $ticket = Ticket::find($orderticket->ticket_id);
            if($ticket)
            {
                $sumsalles += $orderticket->quantity * $ticket->price;
            }
        }                        

        $wallets = OrderTicket::where('from_user', $user->id)
                                ->where('is_convert',0)
                                ->whereHas('order', function ($query) {
                                    $query->where(['is_adminAccepted' => 1, 'is_userAccepted' => 2]);
                                })
                                ->with(['order.payments' => function ($query) {
                                    $query->where('is_accepted', 1);
                                }])
                                ->get();
        $summoney = 0;
        foreach($wallets as $wallet)
        {
            $ticket = Ticket::find($wallet->ticket_id);
            if($ticket)
            {
                $summoney += $wallet->quantity * $ticket->price;
                // $summoney += $ticket->price;
            }
        }

        $user->newordercount = $newordercount;
        $user->sallescount = $sumsalles;
        $user->wallet = $summoney;
        $user_data = new UserInfoResource($user);
        return $this->returnData('data',$user_data, __('site.User_Info'));
    }
}
