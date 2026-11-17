<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('phone', 30);
            $table->string('email', 190)->nullable();
            $table->string('document', 30)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('alias', 50)->nullable();
            $table->string('cbu_cvu', 22)->nullable();
            $table->string('bank_wallet', 100)->nullable();
            $table->string('account_holder', 150)->nullable();
            $table->boolean('is_own_account')->default(true);
            $table->text('notes')->nullable();
            $table->decimal('monthly_income', 14, 2)->nullable();
            $table->string('risk_level', 20)->nullable()->comment('low, medium, high');
            $table->string('status', 20)->default('active');
            $table->integer('current_score')->default(0);
            $table->string('tier', 20)->default('initial');
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_collector_id')->nullable()->constrained('users')->nullOnDelete();

            $table->index('name');
            $table->index('phone');
            $table->index('document');
            $table->index(['status', 'assigned_collector_id'], 'clients_status_collector_index');
        });

        // Dependencia circular: users.client_id apunta a clients, que a su vez
        // referencia users. La columna se crea con users y la FK se agrega acá.
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
        });

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE clients ADD CONSTRAINT chk_clients_status CHECK (status IN ('active', 'inactive'))");
        DB::statement("ALTER TABLE clients ADD CONSTRAINT chk_clients_tier CHECK (tier IN ('initial', 'bronze', 'silver', 'gold'))");
        DB::statement("ALTER TABLE clients ADD CONSTRAINT chk_clients_cbu CHECK (cbu_cvu IS NULL OR cbu_cvu REGEXP '^[0-9]{22}$')");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
        });

        Schema::dropIfExists('clients');
    }
};
