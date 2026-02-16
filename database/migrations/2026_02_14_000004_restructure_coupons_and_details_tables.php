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
        if (Schema::hasTable('coupons') && !Schema::hasTable('coouponn_details')) {
            Schema::rename('coupons', 'coouponn_details');
        }

        if (!Schema::hasTable('coupons')) {
            Schema::create('coupons', function (Blueprint $table) {
                $table->id();
                $table->string('name', 200);
                $table->unsignedBigInteger('organization_id')->nullable();
                $table->date('start_date')->nullable();
                $table->time('start_time')->nullable();
                $table->date('end_date')->nullable();
                $table->time('end_time')->nullable();
                $table->enum('status', ['active', 'inactive', 'draft'])->default('draft')->index();
                $table->unsignedInteger('total_coupon')->default(1);
                $table->unsignedBigInteger('created_by');
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->index(['start_date', 'end_date']);
                $table->foreign('organization_id', 'coupons_master_organization_id_foreign')->references('id')->on('users')->nullOnDelete();
                $table->foreign('created_by', 'coupons_master_created_by_foreign')->references('id')->on('users')->restrictOnDelete();
                $table->foreign('updated_by', 'coupons_master_updated_by_foreign')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (Schema::hasTable('coupons')) {
            $databaseName = DB::getDatabaseName();
            $foreignKeys = DB::table('information_schema.TABLE_CONSTRAINTS')
                ->where('CONSTRAINT_SCHEMA', $databaseName)
                ->where('TABLE_NAME', 'coupons')
                ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
                ->pluck('CONSTRAINT_NAME')
                ->all();

            Schema::table('coupons', function (Blueprint $table) use ($foreignKeys) {
                if (!in_array('coupons_master_organization_id_foreign', $foreignKeys, true)
                    && !in_array('coupons_organization_id_foreign', $foreignKeys, true)) {
                    $table->foreign('organization_id', 'coupons_master_organization_id_foreign')
                        ->references('id')->on('users')->nullOnDelete();
                }
                if (!in_array('coupons_master_created_by_foreign', $foreignKeys, true)
                    && !in_array('coupons_created_by_foreign', $foreignKeys, true)) {
                    $table->foreign('created_by', 'coupons_master_created_by_foreign')
                        ->references('id')->on('users')->restrictOnDelete();
                }
                if (!in_array('coupons_master_updated_by_foreign', $foreignKeys, true)
                    && !in_array('coupons_updated_by_foreign', $foreignKeys, true)) {
                    $table->foreign('updated_by', 'coupons_master_updated_by_foreign')
                        ->references('id')->on('users')->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('coouponn_details')) {
            if (!Schema::hasColumn('coouponn_details', 'coupon_id')) {
                Schema::table('coouponn_details', function (Blueprint $table) {
                    $table->unsignedBigInteger('coupon_id')->nullable()->after('id');
                });
            }

            $details = DB::table('coouponn_details')
                ->select([
                    'id',
                    'coupon_name',
                    'organization_id',
                    'start_date',
                    'start_time',
                    'end_date',
                    'end_time',
                    'status',
                    'created_by',
                    'updated_by',
                    'created_at',
                    'updated_at',
                ])
                ->orderBy('id')
                ->get();

            foreach ($details as $detail) {
                $createdBy = $detail->created_by;
                if (!$createdBy || !DB::table('users')->where('id', $createdBy)->exists()) {
                    $firstAdmin = DB::table('users')->where('role', 'admin')->orderBy('id')->value('id');
                    $createdBy = $firstAdmin ?: DB::table('users')->orderBy('id')->value('id');
                }

                $masterId = DB::table('coupons')->insertGetId([
                    'name' => $detail->coupon_name ?: 'Coupon',
                    'organization_id' => $detail->organization_id,
                    'start_date' => $detail->start_date,
                    'start_time' => $detail->start_time,
                    'end_date' => $detail->end_date,
                    'end_time' => $detail->end_time,
                    'status' => $detail->status ?: 'draft',
                    'total_coupon' => 1,
                    'created_by' => $createdBy,
                    'updated_by' => $detail->updated_by,
                    'created_at' => $detail->created_at,
                    'updated_at' => $detail->updated_at,
                ]);

                DB::table('coouponn_details')
                    ->where('id', $detail->id)
                    ->update(['coupon_id' => $masterId]);
            }

            if (!Schema::hasColumn('coouponn_details', 'coupon_id')) {
                // no-op guard
            } else {
                Schema::table('coouponn_details', function (Blueprint $table) {
                    $table->foreign('coupon_id')->references('id')->on('coupons')->cascadeOnDelete();
                });
            }

            Schema::table('coouponn_details', function (Blueprint $table) {
                if (Schema::hasColumn('coouponn_details', 'coupon_name')) {
                    $table->dropColumn('coupon_name');
                }
                if (Schema::hasColumn('coouponn_details', 'status')) {
                    $table->dropColumn('status');
                }
                if (Schema::hasColumn('coouponn_details', 'start_date')) {
                    $table->dropColumn('start_date');
                }
                if (Schema::hasColumn('coouponn_details', 'start_time')) {
                    $table->dropColumn('start_time');
                }
                if (Schema::hasColumn('coouponn_details', 'end_date')) {
                    $table->dropColumn('end_date');
                }
                if (Schema::hasColumn('coouponn_details', 'end_time')) {
                    $table->dropColumn('end_time');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('coouponn_details')) {
            Schema::table('coouponn_details', function (Blueprint $table) {
                if (!Schema::hasColumn('coouponn_details', 'coupon_name')) {
                    $table->string('coupon_name', 200)->nullable();
                }
                if (!Schema::hasColumn('coouponn_details', 'status')) {
                    $table->enum('status', ['active', 'inactive', 'draft'])->default('draft')->index();
                }
                if (!Schema::hasColumn('coouponn_details', 'start_date')) {
                    $table->date('start_date')->nullable();
                }
                if (!Schema::hasColumn('coouponn_details', 'start_time')) {
                    $table->time('start_time')->nullable();
                }
                if (!Schema::hasColumn('coouponn_details', 'end_date')) {
                    $table->date('end_date')->nullable();
                }
                if (!Schema::hasColumn('coouponn_details', 'end_time')) {
                    $table->time('end_time')->nullable();
                }
            });

            if (Schema::hasColumn('coouponn_details', 'coupon_id')) {
                $masters = DB::table('coouponn_details')
                    ->join('coupons', 'coouponn_details.coupon_id', '=', 'coupons.id')
                    ->select([
                        'coouponn_details.id as detail_id',
                        'coupons.name',
                        'coupons.status',
                        'coupons.start_date',
                        'coupons.start_time',
                        'coupons.end_date',
                        'coupons.end_time',
                    ])
                    ->get();

                foreach ($masters as $row) {
                    DB::table('coouponn_details')
                        ->where('id', $row->detail_id)
                        ->update([
                            'coupon_name' => $row->name,
                            'status' => $row->status,
                            'start_date' => $row->start_date,
                            'start_time' => $row->start_time,
                            'end_date' => $row->end_date,
                            'end_time' => $row->end_time,
                        ]);
                }

                Schema::table('coouponn_details', function (Blueprint $table) {
                    $table->dropForeign(['coupon_id']);
                    $table->dropColumn('coupon_id');
                });
            }
        }

        if (Schema::hasTable('coupons') && !Schema::hasTable('coouponn_backup')) {
            Schema::dropIfExists('coupons');
        }

        if (Schema::hasTable('coouponn_details') && !Schema::hasTable('coupons')) {
            Schema::rename('coouponn_details', 'coupons');
        }
    }
};
