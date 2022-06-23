<?php

namespace App\Http\Controllers\Auth;

use App\Contracts\AuthUserAPI;
use App\Http\Controllers\Controller;
use App\Models\activityLog;
use App\Models\Member;
use App\Models\Unit;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::REG;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    public function index()
    {
        $member = Member::get();
        //   logger($member);
        if (Member::exists()) {
            if (Auth::check()) {
                Toastr::success('คุณเข้าสู่ระบบอยู่แล้ว', 'แจ้งเตือน', ['positionClass' => 'toast-top-right']);

                return back();
            } else {
                return view('auth.login');
            }
        } else {
            $units = Unit::orderby('unitname', 'asc')->get();

            return view('auth.startapp', ['units'=>$units]);
        }
    }

    public function store(Request $request)
    {
        //   dd('ok');
        $org_id = $request->get('org_id');
        $username = $request->get('login');
        $full_name = $request->get('full_name');
        $office_name = $request->get('office_name');
        $is_admin = 1;
        $status = 'Active';
        $unit = Unit::where('unitid', $office_name)->first();

        $member = new Member;
        $member->org_id = $org_id;
        $member->is_admin = $is_admin;
        $member->status = $status;
        //   logger($member);
        $member->save();

        $users = new User;
        $users->org_id = $org_id;
        $users->username = $username;
        $users->full_name = $full_name;
        $users->office_name = $unit->unitname;
        $users->is_admin = $is_admin;
        $users->status = $status;
        //   logger($users);
        $users->save();

        return Redirect::route('docShow');
    }

    public function authenticate(Request $request, AuthUserAPI $api)
    {
        //  Authen siriraj user
        $sirirajUser = $api->authenticate($request->username, $request->password);
        if ($sirirajUser['reply_code'] != 0) {
            //ถ้าไม่เท่ากับ 0 => ชื่อผู้ใช้หรือรหัสผ่านผิด
            $errors = ['message' => $sirirajUser['reply_text']];
            Log::critical($request->username.' '.$sirirajUser['reply_text']);

            return Redirect::back()->withErrors($errors)->withInput($request->all());
        }

        $member = Member::where('org_id', $sirirajUser['org_id'])->where('status', 'Active')->first();

        //   no permis
        if (! $member) {
            Log::critical($sirirajUser['full_name'].' ไม่มีสิทธิ์เข้าถึง');
            abort(403);
        } else {
            $users = User::where('org_id', $sirirajUser['org_id'])->first();
            // ไม่มีสิทธิ์เข้าถึงระบบ
            if ($users->username == null) {
                $users = User::where('org_id', $sirirajUser['org_id'])->first();

                Auth::login($users);

                $full_name = Auth::user()->full_name;
                Toastr::success('เข้าสู่ระบบสำเร็จ', 'แจ้งเตือน', ['positionClass' => 'toast-top-right']);

                Log::info($full_name.' ลงชื่อเข้าใช้งานระบบ');

                $units = Unit::orderby('unitname', 'asc')->get();
                $org_id = $sirirajUser['org_id'];
                $login = $sirirajUser['login'];
                $full_name = $sirirajUser['full_name'];

                return view('auth.register', compact('units', 'org_id', 'login', 'full_name'));
            }

            // logger($member->org_id);
            if ($users->username != $sirirajUser['login'] || $users->full_name != $sirirajUser['full_name']) {
                $user = User::where('org_id', $member->org_id)->first();
                $user->username = $sirirajUser['login'];
                $user->full_name = $sirirajUser['full_name'];
                $user->save();
            }
            Auth::login($users);

            $log_activity = new activityLog;
            $log_activity->username = Auth::user()->username;
            $log_activity->full_name = Auth::user()->full_name;
            $log_activity->office_name = Auth::user()->office_name;
            $log_activity->action = 'เข้าสู่ระบบ';
            $log_activity->type = 'login';
            $log_activity->url = URL::current();
            $log_activity->method = $request->method();
            $log_activity->user_agent = $request->header('user-agent');
            $log_activity->date_time = date('d-m-Y H:i:s');
            $log_activity->save();

            $full_name = Auth::user()->full_name;
            Toastr::success('เข้าสู่ระบบสำเร็จ', 'แจ้งเตือน', ['positionClass' => 'toast-top-right']);

            Log::info($full_name.' เข้าสู่ระบบ');

            return Redirect::route('docShow');
        }
    }

    public function logout(Request $request)
    {
        $log_activity = new activityLog;
        $log_activity->username = Auth::user()->username;
        $log_activity->full_name = Auth::user()->full_name;
        $log_activity->office_name = Auth::user()->office_name;
        $log_activity->action = 'ออกจากระบบ';
        $log_activity->type = 'logout';
        $log_activity->url = URL::current();
        $log_activity->method = $request->method();
        $log_activity->user_agent = $request->header('user-agent');
        $log_activity->date_time = date('d-m-Y H:i:s');
        $log_activity->save();

        Auth::logout();
        Session::forget('user');
        Toastr::success('ออกจากระบบสำเร็จ', 'แจ้งเตือน', ['positionClass' => 'toast-top-right']);

        return Redirect::route('login');
    }
}
