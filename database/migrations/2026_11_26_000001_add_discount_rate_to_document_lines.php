<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Remise en pourcentage par ligne de devis / facture (modèle de document « Px unit. HT / Remise / Px unit. NET »). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_lines', function (Blueprint $table) {
            $table->decimal('discount_rate', 5, 2)->default(0)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('document_lines', function (Blueprint $table) {
            $table->dropColumn('discount_rate');
        });
    }
};
