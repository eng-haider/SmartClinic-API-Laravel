<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Trigger to update case.is_paid when bills are created/updated/deleted
        DB::unprepared('
            CREATE TRIGGER update_case_paid_after_bill_insert
            AFTER INSERT ON bills
            FOR EACH ROW
            BEGIN
                IF NEW.billable_type = "App\\\\Models\\\\Case" THEN
                    UPDATE cases
                    SET is_paid = (
                        SELECT IF(SUM(CASE WHEN is_paid = 1 THEN price ELSE 0 END) >= cases.price, 1, 0)
                        FROM bills
                        WHERE bills.billable_id = NEW.billable_id
                        AND bills.billable_type = "App\\\\Models\\\\Case"
                        AND bills.deleted_at IS NULL
                    )
                    WHERE id = NEW.billable_id;
                END IF;
            END
        ');

        DB::unprepared('
            CREATE TRIGGER update_case_paid_after_bill_update
            AFTER UPDATE ON bills
            FOR EACH ROW
            BEGIN
                IF NEW.billable_type = "App\\\\Models\\\\Case" THEN
                    UPDATE cases
                    SET is_paid = (
                        SELECT IF(SUM(CASE WHEN is_paid = 1 THEN price ELSE 0 END) >= cases.price, 1, 0)
                        FROM bills
                        WHERE bills.billable_id = NEW.billable_id
                        AND bills.billable_type = "App\\\\Models\\\\Case"
                        AND bills.deleted_at IS NULL
                    )
                    WHERE id = NEW.billable_id;
                END IF;
            END
        ');

        DB::unprepared('
            CREATE TRIGGER update_case_paid_after_bill_delete
            AFTER UPDATE ON bills
            FOR EACH ROW
            BEGIN
                IF NEW.deleted_at IS NOT NULL AND OLD.deleted_at IS NULL AND NEW.billable_type = "App\\\\Models\\\\Case" THEN
                    UPDATE cases
                    SET is_paid = (
                        SELECT IF(COALESCE(SUM(CASE WHEN is_paid = 1 THEN price ELSE 0 END), 0) >= cases.price, 1, 0)
                        FROM bills
                        WHERE bills.billable_id = NEW.billable_id
                        AND bills.billable_type = "App\\\\Models\\\\Case"
                        AND bills.deleted_at IS NULL
                    )
                    WHERE id = NEW.billable_id;
                END IF;
            END
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS update_case_paid_after_bill_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS update_case_paid_after_bill_update');
        DB::unprepared('DROP TRIGGER IF EXISTS update_case_paid_after_bill_delete');
    }
};
