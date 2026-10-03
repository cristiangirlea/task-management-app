<?php

use App\Http\Controllers\OAuth\AuthorizationController;
use App\Http\Controllers\OAuth\TokenController;
use App\Http\Middleware\AuthenticateMcp;
use App\Http\Middleware\RejectMalformedClientId;
use App\Mcp\Servers\TaskBoardServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Http\Controllers\OAuthRegisterController;

/*
|--------------------------------------------------------------------------
| MCP servers
|--------------------------------------------------------------------------
|
| The Task Board server is exposed over Streamable HTTP, so an agent always
| acts as one user inside one workspace. It accepts two kinds of token:
|
| - an OAuth access token (the "api" guard, Passport), which MCP clients
|   such as Claude or Cursor get by themselves: they discover the endpoints
|   below from the 401's WWW-Authenticate header, register, and send the
|   person to /oauth/authorize, which hands over to the web app to sign in
|   and allow or deny. These tokens work here and nowhere else.
| - a Sanctum token made in the web app (POST /api/tokens), for clients
|   that only take a fixed header.
|
| It is deliberately not registered as a local (stdio) server: without an
| authenticated user there is no tenant to scope to.
|
*/

Route::middleware('throttle:oauth')->group(function () {
    // Discovery metadata (.well-known/oauth-*) and dynamic client registration.
    Mcp::oauthRoutes();

    Route::middleware(RejectMalformedClientId::class)->group(function () {
        Route::get('/oauth/authorize', AuthorizationController::class)->name('passport.authorizations.authorize');
        Route::post('/oauth/token', TokenController::class)->name('passport.token');
    });
});

// Registering writes a client row, so it has its own, lower limit. This
// replaces the route oauthRoutes() just added for the same URI.
Route::post('/oauth/register', OAuthRegisterController::class)->middleware(['throttle:oauth', 'throttle:oauth-register']);

Mcp::web('/mcp', TaskBoardServer::class)->middleware(AuthenticateMcp::class);
