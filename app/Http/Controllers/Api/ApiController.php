<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Yajra\DataTables\Facades\DataTables;
use App\Http\Requests\Admin\ProductRequest;
use App\Http\Requests\Admin\ProductDupRequest;
use App\Repositories\Admin\SettingsBrandRepositoryEloquent as Brand;
use App\Repositories\Admin\ConfigRepositoryEloquent as Config;
use App\Repositories\Admin\ProductRepositoryEloquent as Product;
use App\Repositories\Admin\ProductPhotoRepositoryEloquent as ProductPhoto;
use App\Repositories\Admin\NetworkRepositoryEloquent as Network;
use App\Repositories\Admin\ProductNetworkEloquentRepository as ProductNetwork;
use App\Repositories\Admin\ProductStorageEloquentRepository as ProductStorage;
use App\Repositories\Customer\CustomerSellRepositoryEloquent as CustomerSell;
use App\Repositories\Admin\OrderRepositoryEloquent as Order;
use App\Repositories\Admin\OrderItemRepositoryEloquent as OrderItem;
use App\Repositories\Admin\SettingsStatusEloquentRepository as Status;
use App\Repositories\Admin\SettingsCategoryEloquentRepository as SettingsCategory;
use App\Repositories\Admin\ProductCategoryEloquentRepository as ProductCategory;
use App\Models\TableList;
use Saperemarketing\Phpmailer\Facades\Mailer;

class ApiController extends Controller
{
    protected $brandRepo;
    protected $productRepo;
    protected $productPhotoRepo;
    protected $configRepo;
    protected $networkRepo;
    protected $productNetworkRepo;
    protected $productStorageRepo;
    protected $customerSellRepo;
    protected $orderRepo;
    protected $orderItemRepo;
    protected $statusRepo;
    protected $tablelist;
    protected $settingsCategoryRepo;
    protected $productCategoryRepo;

    function __construct(
                        Brand $brandRepo, 
                        Product $productRepo, 
                        ProductPhoto $productPhotoRepo, 
                        Config $configRepo, 
                        Network $networkRepo, 
                        ProductNetwork $productNetworkRepo, 
                        ProductStorage $productStorageRepo, 
                        CustomerSell $customerSellRepo, 
                        Order $orderRepo, 
                        OrderItem $orderItemRepo, 
                        Status $statusRepo,
                        TableList $tablelist, 
                        SettingsCategory $settingsCategoryRepo, 
                        ProductCategory $productCategoryRepo
                        )
    {
        $this->brandRepo = $brandRepo;
        $this->productRepo = $productRepo;
        $this->productPhotoRepo = $productPhotoRepo;
        $this->configRepo = $configRepo;
        $this->networkRepo = $networkRepo;
        $this->productNetworkRepo = $productNetworkRepo;
        $this->productStorageRepo = $productStorageRepo;
        $this->customerSellRepo = $customerSellRepo;
        $this->orderRepo = $orderRepo;
        $this->orderItemRepo = $orderItemRepo;
        $this->statusRepo = $statusRepo;
        $this->tablelist = $tablelist;
        $this->settingsCategoryRepo = $settingsCategoryRepo;
        $this->productCategoryRepo = $productCategoryRepo;
    }

    public function GetProduct ($id) 
    {
        $product = $this->productRepo->with(['networks.network'])->find($id);
        $product['storages'] = $product->storagesForBuying()->get();
        return response()->json($product);
    }

    public function PatchProduct (Request $request, $hashedId) 
    {
        if ($request['product_id'] == 0 || $request['product_id'] == '') {
            $response['status'] = 400;
            $response['message'] = "Product is required.";
        } else if ($request['product_storage_id'] == 0 || $request['product_storage_id'] == '') {
            $response['status'] = 400;
            $response['message'] = "Storage is required.";
        } else if ($request['quantity'] == 0 || $request['quantity'] == '') {
            $response['status'] = 400;
            $response['message'] = "Quantity is required.";
        } else if ($request['network_id'] == 0 || $request['network_id'] == '') {
            $response['status'] = 400;
            $response['message'] = "Carrier is required.";
        } else if ($request['device_type'] == 0 || $request['device_type'] == '') {
            $response['status'] = 400;
            $response['message'] = "Device Condition is required.";
        } else  {
            $id = app('App\Http\Controllers\GlobalFunctionController')->decodeHashid($hashedId);
            $model = $this->orderItemRepo->rawByField("id = ?", [$id]);
            $productStorage = $this->productStorageRepo->rawByField("id = ? and product_id = ?", [$request['product_storage_id'], $request['product_id']]);
            
            if ($request['device_type'] == 1) {
                $amount = $productStorage['excellent_offer'];
            } else if ($request['device_type'] == 2) {
                $amount = $productStorage['good_offer'];
            } else if ($request['device_type'] == 3) {
                $amount = $productStorage['fair_offer'];
            } else {
                $amount = $productStorage['poor_offer'];
            } 
            $total = $amount * $request['quantity'];
            $makeRequest = [
                'product_id' => $request['product_id'],
                'quantity' => $request['quantity'],
                'network_id' => $request['network_id'],
                'product_storage_id' => $request['product_storage_id'],
                'amount' => $total, 
                'device_type' => $request['device_type'],
            ];
            $this->orderItemRepo->update($makeRequest, $id);
            $response['status'] = 200;
            $response['message'] = "Details updated.";
        }
        return response()->json($response);
    }

    public function GetModules () 
    {
        $response['status'] = 200;
        $response['model'] = $this->tablelist->modulesList;
        return response()->json($response);
    }

    public function GetEnableOptions () 
    {
        $response['status'] = 200;
        $response['model'] = $this->tablelist->enableOption;
        return response()->json($response);
    }
    
    public function PatchStatus (Request $request) 
    {
        if ($request['id']) {
            $id = app('App\Http\Controllers\GlobalFunctionController')->decodeHashid($request['id']);
            $checkDuplicate = $this->statusRepo->rawByField("name = ? and module = ? and id != ?", [$request['name'], $request['module'], $id]);
        } else {
            $checkDuplicate = $this->statusRepo->rawByField("name = ? and module = ?", [$request['name'], $request['module']]);
        }
        if ($checkDuplicate) 
        {
            $response['status'] = 400;
            $response['error'] = $request['name'].' in '.$request['module'].' already exists';
        } 
        else 
        {
            $response['status'] = 200;
            $response['message'] = 'Status has been successfully updated';
            if ($request['id']) 
            {
                $makeRequest = [
                    'name' => $request['name'],
                    'module' => $request['module'],
                    'email_sending' => ($request['email_sending']) ? $request['email_sending'] : 'Disable',
                    'template' => ($request['template']) ? $request['template'] : ''
                ];
                $this->statusRepo->update($makeRequest, $id);
            }
            else 
            {
                $response['status'] = 200;
                $response['message'] = 'Status has been successfully added';
                $makeRequest = [
                    'id' => $request['id'],
                    'name' => $request['name'],
                    'module' => $request['module'],
                    'default' => 0,
                    'email_sending' => ($request['email_sending']) ? $request['email_sending'] : 'Disable',
                    'template' => ($request['template']) ? $request['template'] : ''
                ];
                $this->statusRepo->create($makeRequest);
            }
        }
        return response()->json($response);   
    }

    public function GetStatusDetails ($hashedId) 
    {
        $id = app('App\Http\Controllers\GlobalFunctionController')->decodeHashid($hashedId);
        $response['status'] = 200;
        $response['model'] = $this->statusRepo->rawByField("id = ?", [$id]);
        return response()->json($response);   
    }

    public function DeleteStatus ($hashedId) 
    {
        $id = app('App\Http\Controllers\GlobalFunctionController')->decodeHashid($hashedId);
        $checkInUsed = $this->orderRepo->rawByField("status_id = ?", [$id]);
        $status = $this->statusRepo->find($id);
        if ($checkInUsed) 
        {
            $response['status'] = 1010;
            $response['error'] = "Selected record is currently in used. Cannot be deleted";
        }
        else if ($status->default == 1) 
        {
            $response['status'] = 406;
            $response['error'] = "Selected record cannot be modify";
        }
        else 
        {
            $this->statusRepo->delete($id);
            $response['status'] = 200;
            $response['message'] = "Record has been successfully deleted";
        }
        return response()->json($response);  
    }

    public function DeleteOrder ($hashedId) 
    {
        $id = app('App\Http\Controllers\GlobalFunctionController')->decodeHashid($hashedId);
        $this->orderRepo->delete($id);
        $response['status'] = 200;
        $response['message'] = "Record has been successfully deleted";
        return response()->json($response);  
    }


    public function DeleteOrderItem ($hashedId) 
    {
        $id = app('App\Http\Controllers\GlobalFunctionController')->decodeHashid($hashedId);
        $this->orderItemRepo->delete($id);
        $response['status'] = 200;
        $response['message'] = "Record has been successfully deleted";
        return response()->json($response);  
    }


    
    public function PatchCategories (Request $request) 
    {
        if ($request['id']) {
            $id = app('App\Http\Controllers\GlobalFunctionController')->decodeHashid($request['id']);
            $checkDuplicate = $this->settingsCategoryRepo->rawByField("name = ? and id != ?", [$request['name'], $id]);
        } else {
            $checkDuplicate = $this->settingsCategoryRepo->rawByField("name = ?", [$request['name']]);
        }
        if ($checkDuplicate) 
        {
            $response['status'] = 400;
            $response['error'] = $request['name'].' already exists';
        } 
        else 
        {
            $response['status'] = 200;
            $response['message'] = 'Status has been successfully saved.';
            $makeRequest = ['name' => $request['name']];
            if ($request['id']) 
            {
                $this->settingsCategoryRepo->update($makeRequest, $id);
            }
            else 
            {
                $this->settingsCategoryRepo->create($makeRequest);
            }
        }
        return response()->json($response);   
    }

    public function GetCategoryDetails ($hashedId) 
    {
        $id = app('App\Http\Controllers\GlobalFunctionController')->decodeHashid($hashedId);
        $response['status'] = 200;
        $response['model'] = $this->settingsCategoryRepo->rawByField("id = ?", [$id]);
        return response()->json($response);   
    }

    public function DeleteCategory ($hashedId) 
    {
        $id = app('App\Http\Controllers\GlobalFunctionController')->decodeHashid($hashedId);
        $checkInUsed = $this->productCategoryRepo->rawByField("category_id = ?", [$id]);
        $category = $this->settingsCategoryRepo->find($id);
        if ($checkInUsed) 
        {
            $response['status'] = 1010;
            $response['error'] = "Selected record is currently in used. Cannot be deleted";
        }
        else 
        {
            $this->settingsCategoryRepo->delete($id);
            $response['status'] = 200;
            $response['message'] = "Record has been successfully deleted";
        }
        return response()->json($response);  
    }



    

    /**
     * CRON JOBS
     */

    

    public function NotifyDay7 () 
    {
        $email = 'f.glenn.abalos@gmail.com';
        $subject = 'TronicsPay: Order Reminder';
        
        $data['config'] = $this->configRepo->find(1);
        
        $data['shippingFee'] = 10;
        $data['overallSubTotal'] = 0;
        $data['counter'] = 1;

        // $id = 1;

        $dateMinusToday = date('Y-m-d', strtotime("-7 day"));

        // $ordersStarted7Days = $this->orderRepo->rawByWithField('transaction_date = ?', [$dateTodayMinus7]);
        $ordersStartedDays = $this->orderRepo->rawByWithField(
                                                [
                                                    'customer', 
                                                    'customer.bill',
                                                    'order_item',
                                                    'order_item.product',
                                                    'order_item.product.brand',
                                                    'order_item.network',
                                                    'order_item.product_storage'
                                                ], "transaction_date = ?", [$dateMinusToday]);

        foreach ($ordersStartedDays as $key => $value) 
        {
            $data['customer_transaction'] = $value;
            $content = view('mail.notifyday7', $data)->render();
            Mailer::sendEmail($email, $subject, $content);
        }
        

        // $data['customer_transaction'] = $this->orderRepo->rawByWithField(
        //                                             [
        //                                                 'customer', 
        //                                                 'customer.bill',
        //                                                 'order_item',
        //                                                 'order_item.product',
        //                                                 'order_item.product.brand',
        //                                                 'order_item.network',
        //                                                 'order_item.product_storage'
        //                                             ], "id = ?", [$id]);

        // $content = view('mail.notifyday7', $data)->render();
        // Mailer::sendEmail($email, $subject, $content);
        return true;
        return $content;
    }

    public function NotifyDay29 () 
    {
        $email = 'f.glenn.abalos@gmail.com';
        $subject = 'TronicsPay: Order Cancelled';
     
        $data['config'] = $this->configRepo->find(1);
        
        $data['shippingFee'] = 10;
        $data['overallSubTotal'] = 0;
        $data['counter'] = 1;

        $dateMinusToday = date('Y-m-d', strtotime("-29 day"));

        // $ordersStarted7Days = $this->orderRepo->rawByWithField('transaction_date = ?', [$dateTodayMinus7]);
        // $ordersStartedDays = $this->orderRepo->rawByWithField(
        //                                         [
        //                                             'customer', 
        //                                             'customer.bill',
        //                                             'order_item',
        //                                             'order_item.product',
        //                                             'order_item.product.brand',
        //                                             'order_item.network',
        //                                             'order_item.product_storage'
        //                                         ], "transaction_date = ? and status_id IN (4, 11, 12)", [$dateMinusToday]);

        // foreach ($ordersStartedDays as $key => $value) 
        // {
        //     $data['customer_transaction'] = $value;
        //     $content = view('mail.notifyday29', $data)->render();
        //     Mailer::sendEmail($email, $subject, $content);
        // }
        


        $id = 1;
        $data['customer_transaction'] = $this->orderRepo->rawByWithField(
                                                    [
                                                        'customer', 
                                                        'customer.bill',
                                                        'order_item',
                                                        'order_item.product',
                                                        'order_item.product.brand',
                                                        'order_item.network',
                                                        'order_item.product_storage'
                                                    ], "id = ?", [$id]);

        $content = view('mail.notifyday29', $data)->render();
        Mailer::sendEmail($email, $subject, $content);
        return true;
        return $content;
    }

    
    public function NotifyCustomerOrder () 
    {
        $email = 'f.glenn.abalos@gmail.com';
        $subject = 'TronicsPay:  - Order Reminder';
        
        $data['config'] = $this->configRepo->find(1);
        
        $data['shippingFee'] = 10;
        $data['overallSubTotal'] = 0;
        $data['counter'] = 1;

        $id = 1;

        $data['customer_transaction'] = $this->orderRepo->rawByWithField(
                                                    [
                                                        'customer', 
                                                        'customer.bill',
                                                        'order_item',
                                                        'order_item.product',
                                                        'order_item.product.brand',
                                                        'order_item.network',
                                                        'order_item.product_storage', 
                                                        'status'
                                                    ], "id = ?", [$id]);

        $content = view('mail.customerorder', $data)->render();
        $subject = 'TronicsPay Reminder: Order # '.$data['customer_transaction']['order_no'];
        Mailer::sendEmail($email, $subject, $content);
        return $content;


        // start: correct

        $dateMinusToday = date('Y-m-d', strtotime("-7 day"));

        return $ordersStartedDays = $this->orderRepo->rawByWithField(
                                                [
                                                    'customer', 
                                                    'customer.bill',
                                                    'order_item',
                                                    'order_item.product',
                                                    'order_item.product.brand',
                                                    'order_item.network',
                                                    'order_item.product_storage'
                                                ], "transaction_date = ?", [$dateMinusToday]);

        foreach ($ordersStartedDays as $key => $value) 
        {
            $data['customer_transaction'] = $value;
            $content = view('mail.customerorder', $data)->render();
            // Mailer::sendEmail($email, $subject, $content);
            return $content;
        }
        
        return true;
    }
}
