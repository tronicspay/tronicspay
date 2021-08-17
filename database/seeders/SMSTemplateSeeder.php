<?php

namespace Database\Seeders;

use App\Models\Admin\SettingsStatus;
use App\Models\Admin\TemplateSms;
use Illuminate\Database\Seeder;

class SMSTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $templates = ['Comment about order', 'On hold', 'Order complete', 'Package delivered', 'Order submitted', 'Shipping label created', 'Complete', 'Follow up', 'Failed', 'Reduced offer'];

        foreach ($templates as $template) {
            $t = TemplateSms::firstOrCreate([
                'name' => 'Order status: ' . $template,
                'content' => 'Order: {order_no} has a new status of "' . $template . '".',
                'receiver' => 'Customer',
                'status' => 'Active',
                'model' => 'Orders'
            ]);

            $status = SettingsStatus::whereRaw('LOWER(`name`) LIKE "' . strtolower($template) . '"')->first();

            $status->template__sms_id = $t->id;
            $status->save();
        }
    }
}
