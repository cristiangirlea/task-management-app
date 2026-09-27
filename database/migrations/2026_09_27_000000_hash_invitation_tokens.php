<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Store invitation tokens as SHA-256 hashes, like password-reset and API
 * tokens, so a copy of the database (a leak, an off-site backup) holds no
 * working invitation links. Links already sent keep working: their tokens
 * are hashed in place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->string('token_hash', 64)->nullable()->after('email');
        });

        DB::table('invitations')->select(['id', 'token'])->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('invitations')->where('id', $row->id)->update(['token_hash' => hash('sha256', $row->token)]);
            }
        });

        Schema::table('invitations', function (Blueprint $table) {
            $table->dropUnique(['token']);
            $table->dropColumn('token');
        });

        Schema::table('invitations', function (Blueprint $table) {
            $table->string('token_hash', 64)->nullable(false)->change();
            $table->unique('token_hash');
        });
    }

    /**
     * A hash cannot be turned back into its token, so pending invitations
     * have no working link after rolling back: send them again.
     */
    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->string('token', 64)->nullable()->unique()->after('email');
        });

        Schema::table('invitations', function (Blueprint $table) {
            $table->dropUnique(['token_hash']);
            $table->dropColumn('token_hash');
        });
    }
};
