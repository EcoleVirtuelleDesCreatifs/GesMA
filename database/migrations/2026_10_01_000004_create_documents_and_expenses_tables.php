<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->date('issued_at');
            $table->date('valid_until')->nullable();
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('delivery_fee')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('unpaid');
            $table->date('issued_at');
            $table->date('due_at')->nullable();
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('delivery_fee')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->unsignedBigInteger('amount_paid')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('document_lines', function (Blueprint $table) {
            $table->id();
            $table->morphs('document');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('designation');
            $table->string('reference')->nullable();
            $table->unsignedBigInteger('unit_price');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('line_total');
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->date('spent_at');
            $table->unsignedBigInteger('amount');
            $table->foreignId('expense_category_id')->constrained();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->string('payment_method', 30)->nullable();
            $table->string('source', 20)->default('gesma');
            $table->timestamps();
            $table->index(['store_id', 'spent_at']);
        });

        Schema::create('sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key', 30);
            $table->unsignedInteger('year');
            $table->unsignedInteger('last_value')->default(0);
            $table->unique(['key', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequences');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('document_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('quotes');
    }
};
