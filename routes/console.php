<?php

use App\Mail\newCustomerFollowup;
use App\Mail\Reciept;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $Year = Carbon::now()->isoFormat('YYYY-MM-DD');
    $now =Carbon::now()->isoFormat('YYYY-MM-DD');
    
    $this->comment($now);
})->purpose('Display an inspiring quote');


Artisan::command('logs:clear', function() {
    
    exec('rm -f ' . storage_path('logs/*.log'));

    exec('rm -f ' . base_path('*.log'));
    
    $this->comment('Logs have been cleared!');
    
})->describe('Clear log files');



Artisan::command('nurse', function () {

    

    
        $reveiverEmailAddress = "boluawosika@gmail.com";
        $details = [
            'custname' => 'Jolami stores',

        ];

        Mail::to($reveiverEmailAddress)->send(new newCustomerFollowup($details));
        dd('success');
   
       
        if (Mail::failures() != 0) {
            return "Email has been sent successfully.";
        }
        return "Oops! There was some error sending the email.";

    // $this->comment(Inspiring::quote());


})->purpose('Display an inspiring quote');