<?php

use Illuminate\Support\Facades\Route;
use OpenAI\Laravel\Facades\OpenAI;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Route::get('/', static fn () => response()->json(['status' => 'OK']));


// Route::get('/', function (){
//     $result = OpenAI::completions()->create([
//         'model' => 'text-davinci-003',
//         'prompt' => 'php',
//         'max_tokens' => 16,
//         // 'n' => 1
//     ]);
//     return response()->json($result['choices'][0]['text']);
// });


Route::get('/', static fn () =>  redirect()->away('https://www.mygupta.co'));