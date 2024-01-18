<?php

namespace App\Http\Controllers\Web\Admin;

use App\Models\User;
use App\Exports\UsersExport;
use App\Models\Admin\Driver;
use Illuminate\Http\Request;
use App\Exports\DriverExport;
use App\Exports\TravelExport;
use App\Base\Constants\Auth\Role;
use App\Models\Admin\VehicleType;
use Illuminate\Support\Facades\DB;
use App\Exports\DriverDutiesExport;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use App\Base\Filters\Admin\UserFilter;
use App\Base\Filters\Admin\DriverFilter;
use App\Base\Filters\Admin\RequestFilter;
use App\Base\Constants\Masters\DateOptions;
use App\Base\Filters\Master\CommonMasterFilter;
use App\Models\Request\Request as RequestRequest;
use App\Base\Libraries\QueryFilter\QueryFilterContract;
use Carbon\Carbon;
use App\Models\Admin\Owner;
use App\Exports\OwnerExport;
use App\Base\Filters\Admin\OwnerFilter;

class ReportController extends Controller
{
    protected $format = ['xlsx','xls','csv','pdf'];

    public function userReport()
    {
        $page = trans('pages_names.user_report');

        $main_menu = 'reports';
        $sub_menu = 'user_report';
        $formats = $this->format;

        return view('admin.reports.user_report', compact('page', 'main_menu', 'sub_menu', 'formats'));
    }

    public function driverReport()
    {
        $page = trans('pages_names.driver_report');

        $main_menu = 'reports';
        $sub_menu = 'driver_report';
        $formats = $this->format;
        $vehicletype = VehicleType::active()->get();

        return view('admin.reports.driver_report', compact('page', 'main_menu', 'sub_menu', 'formats', 'vehicletype'));
    }
    public function ownerReport()
    {
        $page = trans('pages_names.owner_report');

        $main_menu = 'reports';
        $sub_menu = 'owner_report';
        $formats = $this->format;
        $vehicletype = VehicleType::active()->get();

        return view('admin.reports.owner_report', compact('page', 'main_menu', 'sub_menu', 'formats', 'vehicletype'));
    }

    public function driverDutiesReport()
    {
        $page = trans('pages_names.driver_duties_report');
        $main_menu = 'reports';
        $sub_menu = 'driver_duties_report';
        $formats = $this->format;
        $drivers = Driver::get();

        return view('admin.reports.driver_duties', compact('page', 'main_menu', 'sub_menu', 'formats', 'drivers'));
    }

    public function travelReport()
    {
        $page = trans('pages_names.finance_report');

        $main_menu = 'reports';
        $sub_menu = 'travel_report';
        $formats = $this->format;
        $vehicletype = VehicleType::active()->get();

        return view('admin.reports.travel_report', compact('page', 'main_menu', 'sub_menu', 'formats', 'vehicletype'));
    }

    public function downloadReport(Request $request, QueryFilterContract $queryFilter)
    {

        $method = "download".$request->model."Report";

        $filename = $this->$method($request, $queryFilter);

        $file = url('storage/'.$filename);

        return $file;
    }

    public function downloadUserReport(Request $request, QueryFilterContract $queryFilter)
    {
        $format = $request->format;

        $query = User::companyKey()->belongsToRole(Role::USER);

        $data = $queryFilter->builder($query)->customFilter(new UserFilter)->defaultSort('-date')->get();

        $filename = "$request->model Report-".date('ymdis').'.'.$format;

        Excel::store(new UsersExport($data), $filename, 'local');

        return $filename;
    }

    public function downloadDriverReport(Request $request, QueryFilterContract $queryFilter)
    {
        $format = $request->format;

        $query = Driver::query();

        $data = $queryFilter->builder($query)->customFilter(new DriverFilter)->defaultSort('-date')->get();

        $filename = "$request->model Report-".date('ymdis').'.'.$format;

        Excel::store(new DriverExport($data), $filename, 'local');

        return $filename;
    }

    /**
    * Download Driver Duties Report
    *
    */
    public function downloadDriverDutiesReport(Request $request)
    {
        $format = $request->format;
        $date_option = $request->date_option;
        $current_date = Carbon::now();
        $driver = $request->driver;

        if ($date_option == DateOptions::TODAY) {
            $date_array = [$current_date->format("Y-m-d"),$current_date->format("Y-m-d"),$driver];
        } elseif ($date_option == DateOptions::YESTERDAY) {
            $yesterday_date = Carbon::yesterday()->format('Y-m-d');
            $date_array = [$yesterday_date,$yesterday_date,$driver];
        } elseif ($date_option == DateOptions::CURRENT_WEEK) {
            $date_array = [$current_date->startOfWeek()->toDateString(),$current_date->endOfWeek()->toDateString(),$driver];
        } elseif ($date_option == DateOptions::LAST_WEEK) {
            $date_array = [$current_date->subWeek()->toDateString(), $current_date->startOfWeek()->toDateString(),$driver];
        } elseif ($date_option == DateOptions::CURRENT_MONTH) {
            $date_array = [$current_date->startOfMonth()->toDateString(), $current_date->endOfMonth()->toDateString(),$driver];
        } elseif ($date_option == DateOptions::PREVIOUS_MONTH) {
            $date_array = [$current_date->startOfMonth()->toDateString(), $current_date->endOfMonth()->toDateString(),$driver];
        } elseif ($date_option == DateOptions::CURRENT_YEAR) {
            $date_array = [$current_date->startOfYear()->toDateString(), $current_date->endOfYear()->toDateString(),$driver];
        } else {
            $date_array = [];
        }

        // $date_array =['2020-11-11','2020-11-20',6];

        $data = DB::select('CALL get_driver_duration_report(?,?,?)', $date_array);
        if (count($data)==1) {
            $data = (object) array();
        }
        $filename = "$request->model Report-".date('ymdis').'.'.$format;

        Excel::store(new DriverDutiesExport($data), $filename, 'local');

        return $filename;

        // dd($record);
    }

public function downloadTravelReport(Request $request, QueryFilterContract $queryFilter)
{
    // Get the format, date_option, and other filter values from the request
    $format = $request->format;
    $date_option = $request->date_option;
    $vehicle_type = $request->vehicle_type;
    $trip_status = $request->trip_status;
    $payment_opt = $request->payment_opt;
        $current_date = Carbon::now(auth()->user()->timezone);

    // Initialize an empty date array
    $date_array = [];

        if ($date_option == "today") {
            $date_array = [$current_date->format("Y-m-d"),$current_date->format("Y-m-d")];
        } elseif ($date_option == DateOptions::YESTERDAY) {
            $yesterday_date = Carbon::yesterday()->format('Y-m-d');
            $date_array = [$yesterday_date,$yesterday_date];
        } elseif ($date_option == DateOptions::WEEK) {
            $date_array = [$current_date->startOfWeek()->toDateString(),$current_date->endOfWeek()->toDateString()];
        } elseif ($date_option == DateOptions::LAST_WEEK) {
            $date_array = [$current_date->subWeek()->toDateString(), $current_date->startOfWeek()->toDateString()];
        } elseif ($date_option == DateOptions::MONTH) {
            $date_array = [$current_date->startOfMonth()->toDateString(), $current_date->endOfMonth()->toDateString()];
        } elseif ($date_option == DateOptions::YEAR) {
            $date_array = [$current_date->startOfYear()->toDateString(), $current_date->endOfYear()->toDateString()];
        } else {
            $date_array = [];
        }

    $data = RequestRequest::whereBetween('created_at', $date_array);

    if ($date_option == 'date') {

        $from = Carbon::parse($request->from)->format('Y-m-d');
        $to = Carbon::parse($request->to)->format('Y-m-d');

        $data = RequestRequest::whereDate('created_at','<=', $from)->where('created_at','>=',$to);

    }


    if ($date_option == "today")
    {
    $data = RequestRequest::whereDate('created_at', $current_date->format("Y-m-d"));

    // Query your RequestRequest model
    }


    // Now, apply custom filters using RequestFilter
    $filteredData = $queryFilter->builder($data)->customFilter(new RequestFilter)->defaultSort('created_at')->get();

        $filename = "$request->model Report-".date('ymdis').'.'.$format;

        Excel::store(new TravelExport($filteredData), $filename, 'local');

        return $filename;


    // $filename = "$request->model Report-".date('ymdis').'.'.$format;

    // // Export the filtered data
    // Excel::store(new TravelExport($filteredData), $filename, 'local');

    // return response()->download(storage_path("app/$filename"));
}
    public function downloadOwnerReport(Request $request, QueryFilterContract $queryFilter)
    {
     
     $format = $request->format;

        $query = Owner::query();
        if (env('APP_FOR')=='demo') {
            $query = Owner::whereHas('owner_id', function ($query) {
                $query->where('active', $request->status);
            });
        }

        $data = $queryFilter->builder($query)->customFilter(new OwnerFilter)->defaultSort('-date')->get();

        $filename = "$request->model Report-".date('ymdis').'.'.$format;

        Excel::store(new OwnerExport($data), $filename, 'local');

        return $filename;
    }
}
