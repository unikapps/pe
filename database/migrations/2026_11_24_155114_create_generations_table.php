<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generations', function (Blueprint $table) {
            $table->id();
            // Our public id. Also the Idempotency-Key and metadata.generation_id we
            // send the provider, so retries and early webhooks find this row.
            $table->uuid()->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider_id')->nullable()->unique();
            $table->string('status')->index();

            $table->text('prompt');
            $table->string('size');
            $table->string('quality');
            $table->jsonb('reference_images');

            // Money columns are whole nanodollars (1e-9 USD), see App\Pricing\Nanodollars.
            $table->bigInteger('estimated_price')->comment('nanodollars, user price, expected');
            $table->bigInteger('estimated_price_max')->comment('nanodollars, user price, upper bound');
            $table->bigInteger('provider_cost')->nullable()->comment('nanodollars, what the provider billed us');
            $table->bigInteger('price')->nullable()->comment('nanodollars, what the user was charged');

            $table->jsonb('rates')->nullable()->comment('per 1M tokens, fixed by the provider at creation');
            $table->jsonb('usage')->nullable();

            $table->text('image_url')->nullable();
            $table->timestamp('image_url_expires_at')->nullable();
            $table->string('image_path')->nullable()->comment('our stored copy; provider image URLs expire after 24h');

            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();

            // The provider's timestamps. Usage is billed on the UTC day of completed_at.
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
