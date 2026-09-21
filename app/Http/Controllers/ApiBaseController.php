<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller;

class ApiBaseController extends Controller
{
    use ApiResponse, AuthorizesRequests;
}
