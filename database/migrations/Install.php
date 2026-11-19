<?php

declare(strict_types=1);

namespace JustinHoltWeb\Dipstick\Database\Migrations;

use CraftCms\Cms\Database\Migration;
use CraftCms\Cms\Database\Table;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audits and their findings. Findings keep a rule handle and parameters, not
 * prose, so reports are shown in the reader's language.
 */
class Install extends Migration
{
    public function up(): void
    {
        Schema::create('dipstick_scans', function (Blueprint $table) {
            $table->id();
            // queued, running, done, failed
            $table->string('status', 16)->default('queued')->index();
            // cli, cp
            $table->string('trigger', 8)->default('cli');
            // Craft's users.id is a signed int, so no foreignId().
            $table->integer('userId')->nullable();
            $table->foreign('userId')->references('id')->on(Table::USERS)->nullOnDelete();
            $table->unsignedTinyInteger('score')->nullable();
            $table->json('counts')->nullable();
            $table->json('meta')->nullable();
            $table->boolean('probed')->default(false);
            $table->string('error', 500)->nullable();
            $table->unsignedInteger('durationMs')->nullable();
            $table->dateTime('dateCreated')->index();
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->unique();
        });

        Schema::create('dipstick_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scanId')->constrained('dipstick_scans')->cascadeOnDelete();
            $table->string('rule', 64);
            $table->string('severity', 8)->index();
            $table->string('kind', 8);
            $table->string('source', 255);
            $table->string('site', 255)->nullable();
            $table->string('target', 500)->nullable();
            $table->char('fingerprint', 12);
            $table->unsignedInteger('occurrences')->default(1);
            $table->json('params')->nullable();
            $table->json('evidence')->nullable();
            $table->index(['scanId', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dipstick_findings');
        Schema::dropIfExists('dipstick_scans');
    }
}
