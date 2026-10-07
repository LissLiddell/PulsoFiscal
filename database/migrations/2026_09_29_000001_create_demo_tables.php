<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('reference');
            $table->string('concept')->nullable();
            $table->date('issued_on');
            $table->date('due_on');
            $table->unsignedBigInteger('amount_cents');
            $table->string('status')->default('vigente');
            $table->timestamps();
            $table->unique(['organization_id', 'reference']);
            $table->index(['organization_id', 'status', 'issued_on']);
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('reference');
            $table->date('paid_on');
            $table->unsignedBigInteger('amount_cents');
            $table->timestamps();
            $table->unique(['organization_id', 'reference']);
            $table->index(['organization_id', 'paid_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('organizations');
    }
};
