<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orthodontics module tables. Everything hangs off an existing case (cases.id);
 * the patient is always read through the case, so merging patients (which
 * moves cases) keeps these records with the right patient.
 *
 * Case foreign keys RESTRICT deletes: cases are soft-deleted everywhere in the
 * app, and a hard delete must never silently drop medical records. Large
 * imaging data (CBCT volumes, 3D meshes, radiographs) lives on disk or in
 * Orthanc - only metadata and analysis snapshots are kept here.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Original uploads: radiographs, face photos, 3D models.
        Schema::create('orthodontic_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id');
            // ceph_xray | face_frontal | face_smile | face_profile | intraoral_photo
            // | model_upper | model_lower | model_combined
            $table->string('kind', 40);
            $table->string('disk', 40);
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->string('extension', 16)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->char('sha256', 64)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            // Kind specific: model format/units confirmation, capture date, notes.
            $table->json('meta')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('case_id')->references('id')->on('cases')->restrictOnDelete();
            $table->index(['case_id', 'kind']);
        });

        // DICOM studies (CBCT). Pixel data is never stored in MySQL.
        Schema::create('orthodontic_cbct_studies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id');
            $table->string('driver', 20); // local | orthanc
            // uploading | ready | failed
            $table->string('status', 20)->default('uploading');
            $table->string('description')->nullable();
            $table->string('study_instance_uid', 128)->nullable();
            $table->string('orthanc_study_id', 64)->nullable();
            $table->string('modality', 16)->nullable();
            $table->date('study_date')->nullable();
            $table->unsignedInteger('instances_count')->default(0);
            $table->unsignedBigInteger('total_size')->default(0);
            // Series summary (uid, description, instance count, rows, columns, frames).
            $table->json('series')->nullable();
            // Last saved viewer layout: window/level, slice indices, camera.
            $table->json('viewer_state')->nullable();
            $table->text('error')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('case_id')->references('id')->on('cases')->restrictOnDelete();
            $table->index(['case_id', 'status']);
        });

        Schema::create('orthodontic_cbct_instances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('study_id');
            $table->string('sop_instance_uid', 128);
            $table->string('series_instance_uid', 128)->nullable();
            $table->integer('instance_number')->nullable();
            $table->unsignedInteger('frames')->default(1);
            $table->unsignedInteger('rows')->nullable();
            $table->unsignedInteger('columns')->nullable();
            // Position along the slice normal, for ordering slices.
            $table->double('slice_position')->nullable();
            $table->string('path')->nullable();              // local driver
            $table->string('orthanc_instance_id', 64)->nullable(); // orthanc driver
            $table->unsignedBigInteger('size')->default(0);
            $table->char('sha256', 64)->nullable();
            $table->timestamps();

            $table->foreign('study_id')->references('id')->on('orthodontic_cbct_studies')->cascadeOnDelete();
            $table->unique(['study_id', 'sop_instance_uid']);
            $table->index(['study_id', 'series_instance_uid']);
        });

        // One saved analysis (ceph tracing, cast analysis, face analysis, CBCT
        // measurements, implant plan). The current state is kept here and
        // every save also writes an immutable revision row.
        Schema::create('orthodontic_analyses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id');
            $table->string('type', 20); // ceph | cast | face | cbct
            $table->string('title')->nullable();
            $table->unsignedBigInteger('file_id')->nullable();
            $table->unsignedBigInteger('cbct_study_id')->nullable();
            $table->date('analysis_date')->nullable();
            // draft | reviewed (a doctor reviewed it; required before it is
            // included in a final report)
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('revision')->default(1);
            // Landmarks, calibration, annotations - what the doctor entered.
            $table->json('data')->nullable();
            // Measurements computed by the server from `data`.
            $table->json('results')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('case_id')->references('id')->on('cases')->restrictOnDelete();
            $table->foreign('file_id')->references('id')->on('orthodontic_files')->nullOnDelete();
            $table->foreign('cbct_study_id')->references('id')->on('orthodontic_cbct_studies')->nullOnDelete();
            $table->index(['case_id', 'type']);
        });

        Schema::create('orthodontic_analysis_revisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('analysis_id');
            $table->unsignedInteger('revision');
            $table->string('status', 20);
            $table->json('data')->nullable();
            $table->json('results')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('analysis_id')->references('id')->on('orthodontic_analyses')->cascadeOnDelete();
            $table->unique(['analysis_id', 'revision']);
        });

        // One treatment plan per case. Follow-up visits use the existing case
        // notes (sessions), so they are not duplicated here.
        Schema::create('orthodontic_treatment_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id')->unique();
            // Initial assessment
            $table->text('chief_complaint')->nullable();
            $table->text('clinical_examination')->nullable();
            $table->text('diagnosis')->nullable();
            $table->string('skeletal_class', 20)->nullable();   // I | II | III
            $table->string('molar_class_right', 20)->nullable(); // I | II | III | II_div1 ...
            $table->string('molar_class_left', 20)->nullable();
            $table->string('canine_class_right', 20)->nullable();
            $table->string('canine_class_left', 20)->nullable();
            $table->decimal('crowding_upper_mm', 6, 2)->nullable(); // negative = spacing
            $table->decimal('crowding_lower_mm', 6, 2)->nullable();
            $table->decimal('overjet_mm', 6, 2)->nullable();
            $table->decimal('overbite_mm', 6, 2)->nullable();
            $table->string('crossbite')->nullable();
            $table->decimal('midline_upper_mm', 6, 2)->nullable(); // + right, - left
            $table->decimal('midline_lower_mm', 6, 2)->nullable();
            $table->json('observations')->nullable(); // [{label, value}]
            // Plan
            $table->text('objectives')->nullable();
            $table->text('planned_treatment')->nullable();
            $table->string('appliance_type')->nullable();
            $table->unsignedSmallInteger('estimated_duration_months')->nullable();
            $table->json('stages')->nullable(); // [{title, description, status, target_date}]
            $table->text('doctor_notes')->nullable();
            $table->date('start_date')->nullable();
            // planned | active | retention | completed | discontinued
            $table->string('status', 20)->default('planned');
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('case_id')->references('id')->on('cases')->restrictOnDelete();
        });

        // Who did what: uploads, saves, file and DICOM access.
        Schema::create('orthodontic_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 60);
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            // For updates: the previous snapshot, so nothing is lost.
            $table->json('details')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['case_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orthodontic_audit_logs');
        Schema::dropIfExists('orthodontic_treatment_plans');
        Schema::dropIfExists('orthodontic_analysis_revisions');
        Schema::dropIfExists('orthodontic_analyses');
        Schema::dropIfExists('orthodontic_cbct_instances');
        Schema::dropIfExists('orthodontic_cbct_studies');
        Schema::dropIfExists('orthodontic_files');
    }
};
