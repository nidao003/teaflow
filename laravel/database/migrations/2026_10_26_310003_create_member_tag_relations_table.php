<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_tag_relations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id')->comment('members.id，逻辑外键');
            $table->unsignedBigInteger('tag_id')->comment('member_tags.id');
            $table->timestamps();

            $table->unique(['member_id', 'tag_id'], 'member_tag_relations_member_tag_unique');
            $table->index('tag_id', 'member_tag_relations_tag_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_tag_relations');
    }
};
