<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use App\Repositories\Customer\CustomerRepositoryEloquent as Customer;
use App\Repositories\Admin\ConfigRepositoryEloquent as Config;

class CustomerController extends Controller
{
    protected $customerRepo;
    protected $configRepo;

    function __construct(Customer $customerRepo, Config $configRepo)
    {
        $this->customerRepo = $customerRepo;
        $this->configRepo = $configRepo;
    }

    public function index()
    {
        $data['module'] = 'customer';
        $config = $this->configRepo->find(1);
        $data['is_dark_mode'] = ($config['is_dark_mode'] == 1) ? true : false;
        return view('admin.customers.index', $data);
    }
}
