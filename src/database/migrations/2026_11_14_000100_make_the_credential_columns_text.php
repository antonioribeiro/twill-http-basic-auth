<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * The username and the password are stored encrypted, and an encrypted value is longer than what it hides (32 characters become 256,
 * 64 become 312): a `varchar(255)` refuses it (PostgreSQL, strict MySQL) or cuts it (lenient MySQL), and the host then answers 503.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('twill_basic_auth', function (Blueprint $table): void {
            $table->text('username')->nullable()->change();
            $table->text('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        // text may hold what varchar(255) cannot: nothing to take back
    }
};
