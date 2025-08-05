<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
}); 

Broadcast::channel('store.{store_id}', function (User $user, $store_id) {   
    return check_broadcast($user,$store_id);
});

Broadcast::channel('update-order-store.{store_id}', function (User $user, $store_id) {   
    return check_broadcast($user,$store_id);
});


function check_broadcast($user,$store_id){
    $cur_store_id = \App\Classes\Store::getId();
    if($store_id && $store_id == $cur_store_id){ 
        return true;
    }else{
        return false;
    }
}