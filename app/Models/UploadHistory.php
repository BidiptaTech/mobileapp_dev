<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class UploadHistory extends Model
{
    use HasFactory;

    protected $table = 'upload_histories';

    protected $fillable = [
        'upload_type',
        'file_name',
        'original_file_name',
        'total_records',
        'success_count',
        'error_count',
        'errors',
        'status',
        'uploaded_by'
    ];

    protected $casts = [
        'errors' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Relationship with User
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'uploaded_by', 'userId');
    }

    /**
     * Get formatted date - Modern detailed format
     */
    public function getFormattedDateAttribute()
    {
        return $this->created_at->format('l, jS F Y g:i A'); // Thursday, 10th July 2025 6:45 PM
    }

    /**
     * Get relative time (human readable)
     */
    public function getRelativeTimeAttribute()
    {
        return $this->created_at->diffForHumans(); // 2 hours ago
    }

    /**
     * Get compact date for mobile view
     */
    public function getCompactDateAttribute()
    {
        return $this->created_at->format('M j, Y g:i A'); // Jul 10, 2025 6:45 PM
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeAttribute()
    {
        switch ($this->status) {
            case 'success':
                return 'badge bg-success';
            case 'partial':
                return 'badge bg-warning';
            case 'failed':
                return 'badge bg-danger';
            default:
                return 'badge bg-secondary';
        }
    }

    /**
     * Get status text
     */
    public function getStatusTextAttribute()
    {
        switch ($this->status) {
            case 'success':
                return 'Completed';
            case 'partial':
                return 'Partial Success';
            case 'failed':
                return 'Failed';
            default:
                return 'Unknown';
        }
    }

    /**
     * Create upload history record
     */
    public static function createRecord($uploadType, $fileName, $originalFileName, $totalRecords, $successCount, $errorCount, $errors, $uploadedBy)
    {
        $status = 'success';
        if ($errorCount > 0 && $successCount > 0) {
            $status = 'partial';
        } elseif ($errorCount > 0 && $successCount == 0) {
            $status = 'failed';
        }

        return self::create([
            'upload_type' => $uploadType,
            'file_name' => $fileName,
            'original_file_name' => $originalFileName,
            'total_records' => $totalRecords,
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'errors' => $errors,
            'status' => $status,
            'uploaded_by' => $uploadedBy
        ]);
    }

    /**
     * Get recent upload history by type and user
     */
    public static function getRecentHistory($uploadType, $userId, $limit = 10)
    {
        return self::where('upload_type', $uploadType)
                   ->where('uploaded_by', $userId)
                   ->orderBy('created_at', 'desc')
                   ->limit($limit)
                   ->get();
    }
} 