<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel's database queue inserts jobs and failed jobs without an id, but
 * these two tables were created with a UUID primary key that has no default:
 * every dispatch failed ("Field 'id' doesn't have a default value"), so
 * nothing ever ran in the background. Rows can't exist in a table that
 * rejects every insert, so recreating them loses nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if ($this->needsFix('jobs')) {
            Schema::drop('jobs');
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        if ($this->needsFix('failed_jobs')) {
            Schema::drop('failed_jobs');
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        // The UUID version never worked; there is nothing to go back to.
    }

    /** True when the table exists and its id is not a number. */
    private function needsFix(string $table): bool
    {
        return Schema::hasTable($table)
            && !str_contains(strtolower(Schema::getColumnType($table, 'id')), 'int');
    }
};
