<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('tbl_inventory_tags')) {
            return;
        }

        Schema::create('tbl_inventory_tags', function (Blueprint $table) {
            $table->unsignedBigInteger('tag_id')->autoIncrement();
            $table->string('tag_number', 50)->unique();
            $table->string('barcode', 50)->nullable()->index();
            $table->unsignedInteger('sell_quality_id');
            $table->foreign('sell_quality_id')->references('sell_quality_id')->on('tbl_sell_qualities');
            $table->enum('metal_type', ['gold', 'silver'])->default('gold');
            $table->decimal('gross_weight', 14, 3)->default(0);
            $table->decimal('stone_weight', 14, 3)->default(0);
            $table->decimal('net_weight', 14, 3)->default(0);
            $table->decimal('purity_karat', 5, 2)->default(22.0);
            $table->decimal('fine_weight', 14, 3)->default(0);
            $table->unsignedInteger('pieces')->default(1);
            $table->decimal('cost_rate', 15, 2)->nullable();
            $table->decimal('cost_amount', 15, 2)->nullable();
            $table->decimal('selling_price', 15, 2)->nullable();
            $table->enum('status', ['in_stock', 'issued_karigar', 'sold', 'returned', 'melted', 'adjusted'])
                ->default('in_stock')
                ->index();
            $table->string('source_type', 50)->nullable(); // inward, karigar_return, exchange, opening
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedBigInteger('current_karigar_id')->nullable();
            $table->unsignedBigInteger('sold_invoice_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('tbl_inventory_tags');
    }
};
