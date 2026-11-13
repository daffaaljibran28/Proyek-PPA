<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Laporan extends Model
{
    use HasFactory;

    // Menentukan nama tabel di database secara eksplisit
    protected $table = 'laporan';

    // Menentukan primary key tabel
    protected $primaryKey = 'id_laporan';

    // Mengizinkan semua kolom diisi secara massal
    protected $guarded = [];

    // Format tipe data kolom tanggal jika diperlukan
    protected $casts = [
        'tanggal_kejadian' => 'datetime',
        'riwayat_status' => 'array',
    ];

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_petugas_id');
    }
}
