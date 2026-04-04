<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\PriceAlert;
use ovidiuro\myfinance2\App\Models\PriceAlertNotification;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('myfinance2.db_connection');

        $alertsTable = (new PriceAlert())->getTable();
        if (!Schema::connection($connection)->hasTable($alertsTable)) {
            Schema::connection($connection)->create($alertsTable, function (Blueprint $table)
            {
                $table->increments('id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('symbol', 16);
                $table->enum('alert_type', ['PRICE_ABOVE', 'PRICE_BELOW']);
                $table->decimal('target_price', 16, 6);
                $table->unsignedBigInteger('trade_currency_id')->nullable();
                $table->enum('status', ['ACTIVE', 'PAUSED'])->default('ACTIVE');
                $table->string('source', 50)->default('manual');
                $table->string('notification_channel', 20)->default('email');
                $table->text('notes')->nullable();
                $table->timestamp('last_triggered_at')->nullable();
                $table->unsignedInteger('trigger_count')->default(0);
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['user_id', 'symbol', 'status'], 'price_alerts_user_symbol_status');
                $table->index(['status'], 'price_alerts_status');
            });
        }

        $notifTable = (new PriceAlertNotification())->getTable();
        if (!Schema::connection($connection)->hasTable($notifTable)) {
            Schema::connection($connection)->create($notifTable, function (Blueprint $table)
            {
                $table->bigIncrements('id');
                $table->unsignedInteger('price_alert_id');
                $table->unsignedBigInteger('user_id');
                $table->string('symbol', 16);
                $table->string('notification_channel', 20)->default('email');
                $table->decimal('current_price', 16, 6);
                $table->decimal('target_price', 16, 6);
                $table->enum('alert_type', ['PRICE_ABOVE', 'PRICE_BELOW']);
                $table->decimal('projected_gain_eur', 16, 2)->nullable();
                $table->decimal('projected_gain_pct', 8, 4)->nullable();
                $table->timestamp('sent_at');
                $table->enum('status', ['SENT', 'FAILED']);
                $table->text('error_message')->nullable();
                $table->timestamps();

                $table->index(
                    ['user_id', 'symbol', 'sent_at'],
                    'price_alert_notifs_user_symbol_sent'
                );
            });
        }
    }

    public function down(): void
    {
        $connection = config('myfinance2.db_connection');
        Schema::connection($connection)->dropIfExists(
            (new PriceAlertNotification())->getTable()
        );
        Schema::connection($connection)->dropIfExists((new PriceAlert())->getTable());
    }
};
