<?php

namespace App\Http\Controllers;

// Laravel 11ではAuthorizesRequestsトレイトが削除されているため、
// $this->authorize()の代わりにGate::authorize()を使用してください
// use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // use AuthorizesRequests; // Laravel 11では削除されました
}
