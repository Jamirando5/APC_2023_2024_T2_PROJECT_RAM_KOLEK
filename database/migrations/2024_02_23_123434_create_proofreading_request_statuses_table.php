<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('proofreading_request_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proofreading_request_id')->constrained('proofreading_requests');
            $table->foreignId('user_id')->constrained('users')->nullable();
            $table->string('type',25);
            $table->string('status',25);
            $table->string('feedback')->nullable();
            $table->string('attachments')->nullable();
            $table->string('attachments_names')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proofreading_request_statuses');
    }
};
