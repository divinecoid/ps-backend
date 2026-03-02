<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('trx_receivedlogs', 'cmt_id')) {
            Schema::table('trx_receivedlogs', function (Blueprint $table) {
                $table->foreignUuid('cmt_id')->nullable()->after('id')->constrained('mdx_cmts')->onDelete('cascade');
            });

            // Populate cmt_id from requests
            DB::statement('UPDATE trx_receivedlogs rl 
                JOIN trx_requests r ON rl.request_id = r.id 
                SET rl.cmt_id = r.cmt_id');
        }

        Schema::table('trx_receivedlogs', function (Blueprint $table) {
            if (Schema::hasColumn('trx_receivedlogs', 'request_id')) {
                // Drop foreign key if it exists
                try {
                    $table->dropForeign(['request_id']);
                } catch (\Exception $e) {
                }
                $table->dropColumn('request_id');
            }

            $table->uuid('cmt_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('trx_receivedlogs', 'request_id')) {
            Schema::table('trx_receivedlogs', function (Blueprint $table) {
                $table->foreignUuid('request_id')->nullable()->after('id')->constrained('trx_requests')->onDelete('cascade');
            });
        }

        // This down migration is tricky because one receivedlog could now map to multiple requests.
        // We can't easily restore the exact request_id if it was grouped.
        // However, for single-request logs, we could try. 
        // For simplicity, we'll just leave them nullable or try to find one request.

        if (Schema::hasColumn('trx_receivedlogs', 'cmt_id')) {
            Schema::table('trx_receivedlogs', function (Blueprint $table) {
                try {
                    $table->dropForeign(['cmt_id']);
                } catch (\Exception $e) {
                }
                $table->dropColumn('cmt_id');
            });
        }
    }
};
