<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['user', 'laporan'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Tabel [{$table}] harus tersedia sebelum dukungan Petugas ditambahkan.");
            }
        }

        $this->validateExistingColumn('user', 'id', 'bigint unsigned', false);
        $this->validateExistingColumn('user', 'no_id_card', 'varchar(100)', true);
        $this->validateExistingColumn('laporan', 'assigned_petugas_id', 'bigint unsigned', true);
        $this->validateExistingColumn('laporan', 'catatan_petugas', 'text', true);
        $this->validateExistingAssignmentForeignKey();

        if (Schema::hasColumn('user', 'no_id_card') && ! Schema::hasIndex('user', ['no_id_card'], 'unique')) {
            $this->assertNoDuplicateIdentityCards();
        }

        if (Schema::hasColumn('laporan', 'assigned_petugas_id')) {
            $this->assertNoOrphanAssignments();
        }

        if (! Schema::hasColumn('user', 'no_id_card')) {
            Schema::table('user', function (Blueprint $table): void {
                $table->string('no_id_card', 100)->nullable();
            });
        }

        Schema::table('laporan', function (Blueprint $table): void {
            if (! Schema::hasColumn('laporan', 'assigned_petugas_id')) {
                $table->unsignedBigInteger('assigned_petugas_id')->nullable();
            }

            if (! Schema::hasColumn('laporan', 'catatan_petugas')) {
                $table->text('catatan_petugas')->nullable();
            }
        });

        if (! Schema::hasIndex('user', ['no_id_card'], 'unique')) {
            $this->assertIndexNameAvailable('user', 'user_no_id_card_unique');

            Schema::table('user', function (Blueprint $table): void {
                $table->unique('no_id_card', 'user_no_id_card_unique');
            });
        }

        if (! Schema::hasIndex('laporan', ['assigned_petugas_id'])) {
            $this->assertIndexNameAvailable('laporan', 'laporan_assigned_petugas_id_index');

            Schema::table('laporan', function (Blueprint $table): void {
                $table->index('assigned_petugas_id', 'laporan_assigned_petugas_id_index');
            });
        }

        if (! $this->hasValidAssignmentForeignKey()) {
            Schema::table('laporan', function (Blueprint $table): void {
                $table->foreign('assigned_petugas_id', 'laporan_assigned_petugas_id_foreign')
                    ->references('id')
                    ->on('user')
                    ->restrictOnDelete();
            });
        }

        $this->validateExistingColumn('user', 'no_id_card', 'varchar(100)', true);
        $this->validateExistingColumn('laporan', 'assigned_petugas_id', 'bigint unsigned', true);
        $this->validateExistingColumn('laporan', 'catatan_petugas', 'text', true);
    }

    public function down(): void
    {
        // Intentionally keep additive schema in place to protect existing data and installations.
    }

    private function validateExistingColumn(string $table, string $column, string $expectedType, bool $nullable): void
    {
        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        $definition = collect(Schema::getColumns($table))
            ->firstWhere('name', $column);

        if ($definition === null) {
            throw new RuntimeException("Definisi kolom [{$table}.{$column}] tidak dapat diverifikasi.");
        }

        $driver = Schema::getConnection()->getDriverName();
        $actualType = strtolower((string) ($definition['type'] ?? $definition['type_name'] ?? ''));
        $typeMatches = match ($expectedType) {
            'varchar(100)' => in_array($driver, ['mysql', 'mariadb'], true)
                ? $actualType === 'varchar(100)'
                : in_array($actualType, ['varchar', 'varchar(100)'], true),
            'bigint unsigned' => in_array($driver, ['mysql', 'mariadb'], true)
                ? $actualType === 'bigint unsigned'
                : in_array($actualType, ['integer', 'bigint', 'bigint unsigned'], true),
            'text' => str_starts_with($actualType, 'text'),
            default => false,
        };

        if (! $typeMatches || (bool) $definition['nullable'] !== $nullable) {
            throw new RuntimeException(
                "Kolom [{$table}.{$column}] memiliki tipe atau nullability yang tidak sesuai; tidak ada perubahan otomatis dilakukan."
            );
        }
    }

    private function assertNoDuplicateIdentityCards(): void
    {
        $duplicateExists = DB::table('user')
            ->whereNotNull('no_id_card')
            ->select('no_id_card')
            ->groupBy('no_id_card')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicateExists) {
            throw new RuntimeException('Nilai no_id_card duplikat ditemukan; indeks unik tidak ditambahkan.');
        }
    }

    private function assertNoOrphanAssignments(): void
    {
        $orphanExists = DB::table('laporan as reports')
            ->leftJoin('user', 'user.id', '=', 'reports.assigned_petugas_id')
            ->whereNotNull('reports.assigned_petugas_id')
            ->whereNull('user.id')
            ->exists();

        if ($orphanExists) {
            throw new RuntimeException('Assignment laporan tanpa akun user ditemukan; foreign key tidak ditambahkan.');
        }
    }

    private function validateExistingAssignmentForeignKey(): void
    {
        if (! Schema::hasColumn('laporan', 'assigned_petugas_id')) {
            return;
        }

        foreach (Schema::getForeignKeys('laporan') as $foreignKey) {
            if (in_array('assigned_petugas_id', $foreignKey['columns'], true)
                && ! $this->isValidAssignmentForeignKey($foreignKey)) {
                throw new RuntimeException('Foreign key assigned_petugas_id yang sudah ada tidak sesuai target yang diharapkan.');
            }
        }
    }

    private function hasValidAssignmentForeignKey(): bool
    {
        foreach (Schema::getForeignKeys('laporan') as $foreignKey) {
            if (in_array('assigned_petugas_id', $foreignKey['columns'], true)) {
                return $this->isValidAssignmentForeignKey($foreignKey);
            }
        }

        return false;
    }

    private function isValidAssignmentForeignKey(array $foreignKey): bool
    {
        return $foreignKey['foreign_table'] === 'user'
            && $foreignKey['foreign_columns'] === ['id']
            && in_array(strtoupper((string) $foreignKey['on_delete']), ['RESTRICT', 'NO ACTION'], true);
    }

    private function assertIndexNameAvailable(string $table, string $indexName): void
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['name'] === $indexName) {
                throw new RuntimeException("Indeks bernama [{$indexName}] sudah ada dengan definisi berbeda.");
            }
        }
    }
};
