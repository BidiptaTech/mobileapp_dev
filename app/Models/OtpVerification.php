<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class OtpVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'otp',
        'registration_data',
        'expires_at',
        'is_verified',
        'attempts',
    ];

    protected $casts = [
        'registration_data' => 'array',
        'expires_at' => 'datetime',
        'is_verified' => 'boolean',
        'attempts' => 'integer',
    ];

    /**
     * Check if the OTP has expired
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if the OTP is still valid
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->is_verified && $this->attempts < 5;
    }

    /**
     * Generate a new OTP for the given email
     *
     * @param string $email
     * @param array $registrationData
     * @param int $expiryMinutes
     * @return self
     */
    public static function generateFor(string $email, array $registrationData = [], int $expiryMinutes = 10): self
    {
        // Invalidate any existing OTPs for this email
        self::where('email', $email)
            ->where('is_verified', false)
            ->update(['is_verified' => true]);

        // Create a new OTP
        return self::create([
            'email' => $email,
            'otp' => rand(1000, 9999), // 4-digit OTP
            'registration_data' => $registrationData,
            'expires_at' => Carbon::now()->addMinutes($expiryMinutes),
            'is_verified' => false,
            'attempts' => 0,
        ]);
    }

    /**
     * Verify the OTP for the given email
     *
     * @param string $email
     * @param string $otp
     * @return self|null
     */
    public static function verify(string $email, string $otp)
    {
        $verification = self::where('email', $email)
            ->where('is_verified', false)
            ->where('expires_at', '>', Carbon::now())
            ->latest()
            ->first();

        if (!$verification) {
            return null;
        }

        $verification->attempts++;
        $verification->save();

        if ($verification->otp === $otp) {
            $verification->is_verified = true;
            $verification->save();
            return $verification;
        }

        return null;
    }
}
