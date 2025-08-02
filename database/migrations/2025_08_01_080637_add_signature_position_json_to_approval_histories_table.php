<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSignaturePositionJsonToApprovalHistoriesTable extends Migration
{
    public function up()
    {
        Schema::table('approval_histories', function (Blueprint $table) {
            $table->json('signature_position')->nullable()->after('comment');
        });
    }

    public function down()
    {
        Schema::table('approval_histories', function (Blueprint $table) {
            $table->dropColumn('signature_position');
        });
    }
}
