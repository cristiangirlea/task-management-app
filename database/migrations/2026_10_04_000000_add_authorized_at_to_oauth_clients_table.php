<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When someone first allowed the client in. A client that registered and
     * was never allowed is deleted after a day (oauth:purge-clients); its codes
     * and tokens cannot tell, as passport:purge removes them a week after they
     * expire while a refresh token lives on for 30 days.
     */
    public function up(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->timestamp('authorized_at')->nullable();
        });

        DB::table('oauth_clients')
            ->where(fn (Builder $query) => $query
                ->whereExists(fn (Builder $codes) => $codes->from('oauth_auth_codes')->whereColumn('oauth_auth_codes.client_id', 'oauth_clients.id'))
                ->orWhereExists(fn (Builder $tokens) => $tokens->from('oauth_access_tokens')->whereColumn('oauth_access_tokens.client_id', 'oauth_clients.id')))
            ->update(['authorized_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->dropColumn('authorized_at');
        });
    }
};
