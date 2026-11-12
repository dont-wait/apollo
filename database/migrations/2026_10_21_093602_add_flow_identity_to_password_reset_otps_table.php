<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('password_reset_otps', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->string('purpose')->nullable()->after('user_id');
            $table->uuid('flow_id')->nullable()->after('purpose');
            $table->timestamp('delivered_at')->nullable()->after('verified_at');
            $table->dropUnique('password_reset_otps_email_unique');
        });

        DB::statement(
            "UPDATE password_reset_otps AS otp
             INNER JOIN users AS users ON LOWER(users.email) = LOWER(otp.email)
             SET otp.user_id = users.id,
                 otp.purpose = 'password_reset'
             WHERE otp.user_id IS NULL",
        );
        DB::statement('DELETE FROM password_reset_otps WHERE user_id IS NULL');
        DB::statement('UPDATE password_reset_otps SET flow_id = UUID() WHERE flow_id IS NULL');

        Schema::table('password_reset_otps', function (Blueprint $table): void {
            $table->unique('flow_id');
            $table->unique(['user_id', 'purpose']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement(
            'DELETE older FROM password_reset_otps AS older '
            .'INNER JOIN password_reset_otps AS newer '
            .'ON older.email = newer.email AND older.id < newer.id',
        );

        Schema::table('password_reset_otps', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->dropUnique('password_reset_otps_flow_id_unique');
            $table->dropUnique('password_reset_otps_user_id_purpose_unique');
            $table->dropColumn(['user_id', 'purpose', 'flow_id', 'delivered_at']);
            $table->unique('email');
        });
    }
};
