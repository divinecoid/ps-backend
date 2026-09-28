<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Converts an already-deployed model_id-based mdx_cmt_model_rates table
     * (from the original, single-model-per-rate version of this feature)
     * into the group_id-based schema: one CmtRateGroup is created per
     * distinct model that had a rate, named after that model, and existing
     * rate rows are repointed at it — preserving every rate 1:1 rather than
     * losing data. A fresh install (where the table was just created with
     * group_id directly) has nothing to convert and this is a no-op.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('mdx_cmt_model_rates', 'model_id')) {
            return;
        }

        // Not wrapped in DB::transaction(): the Schema::table() calls below
        // are DDL, which MySQL always auto-commits — an explicit transaction
        // around them provides no atomicity and just fails when it later
        // tries to commit a transaction MySQL already closed implicitly.
        $modelIds = DB::table('mdx_cmt_model_rates')->pluck('model_id')->unique();
        $groupIdByModel = [];

        foreach ($modelIds as $modelId) {
            $model = DB::table('mdx_models')->where('id', $modelId)->first();
            if (!$model) {
                continue;
            }

            $groupId = (string) Str::orderedUuid();
            DB::table('mdx_cmt_rate_groups')->insert([
                'id' => $groupId,
                'name' => $model->name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('mdx_models')->where('id', $modelId)->update(['cmt_rate_group_id' => $groupId]);
            $groupIdByModel[$modelId] = $groupId;
        }

        Schema::table('mdx_cmt_model_rates', function (Blueprint $table) {
            $table->uuid('group_id')->nullable()->after('id');
        });

        foreach ($groupIdByModel as $modelId => $groupId) {
            DB::table('mdx_cmt_model_rates')->where('model_id', $modelId)->update(['group_id' => $groupId]);
        }

        // Any rate row whose model no longer exists has no group_id and
        // must be dropped before group_id can become non-nullable.
        DB::table('mdx_cmt_model_rates')->whereNull('group_id')->delete();

        Schema::table('mdx_cmt_model_rates', function (Blueprint $table) {
            $table->dropForeign('mdx_cmt_model_rates_model_id_foreign');
            $table->dropUnique('mdx_cmt_model_rates_model_id_kategori_unique');
            $table->dropColumn('model_id');
        });

        // group_id stays nullable at the schema level (changing it to
        // NOT NULL would require doctrine/dbal, which isn't installed),
        // but every row was backfilled above and the controller already
        // validates it as required on write.
        Schema::table('mdx_cmt_model_rates', function (Blueprint $table) {
            $table->foreign('group_id')->references('id')->on('mdx_cmt_rate_groups')->onDelete('restrict');
            $table->unique(['group_id', 'kategori']);
        });
    }

    /**
     * Not reversible — group_id-to-model_id would be ambiguous once
     * multiple models are pointed at the same group.
     */
    public function down(): void
    {
    }
};
