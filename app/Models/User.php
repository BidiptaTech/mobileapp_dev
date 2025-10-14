<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    use HasRoles;
    use SoftDeletes;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'userId';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    use HasFactory;
    protected $table = 'users'; 
    protected $guarded = []; 

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /*
    * Specify User Types.
    * Date 14-10-2024
    */
    protected static $userTypes = [
        '1' => 'Admin',
        '2' => 'DMC',
        '3' => 'Master Dmc',
        '4' => 'Channel Partner',
    ];

    /*
    * Return User Types
    * Date 14-10-2024
    */
    public static function getUserTypes()
    {
        $userTypes = self::$userTypes;
        return $userTypes;
    }

    /*
    * Return User Specific User_type name.
    * Date 14-10-2024
    */
    public function getUserTypeName()
    {
        return self::$userTypes[$this->user_type] ?? 'Unknown';
    }

    /*
    * Return relationship to Master DMC user.
    */
    public function masterDmc()
    {
        return $this->belongsTo(User::class, 'master_dmc_id', 'userId');
    }

    /*
    * Return All Country Codes for Phone Number.
    * Date 14-10-2024
    */
    public static function countryCodes()
    {
        return [
            '93' => 'Afghanistan (93)',
            '355' => 'Albania (355)',
            '213' => 'Algeria (213)',
            '376' => 'Andorra (376)',
            '244' => 'Angola (244)',
            '54' => 'Argentina (54)',
            '374' => 'Armenia (374)',
            '61' => 'Australia (61)',
            '43' => 'Austria (43)',
            '994' => 'Azerbaijan (994)',
            '1-242' => 'Bahamas (1-242)',
            '973' => 'Bahrain (973)',
            '880' => 'Bangladesh (880)',
            '1-246' => 'Barbados (1-246)',
            '375' => 'Belarus (375)',
            '32' => 'Belgium (32)',
            '501' => 'Belize (501)',
            '229' => 'Benin (229)',
            '975' => 'Bhutan (975)',
            '591' => 'Bolivia (591)',
            '387' => 'Bosnia and Herzegovina (387)',
            '267' => 'Botswana (267)',
            '55' => 'Brazil (55)',
            '673' => 'Brunei (673)',
            '359' => 'Bulgaria (359)',
            '226' => 'Burkina Faso (226)',
            '257' => 'Burundi (257)',
            '855' => 'Cambodia (855)',
            '237' => 'Cameroon (237)',
            '1' => 'Canada (1)',
            '238' => 'Cape Verde (238)',
            '236' => 'Central African Republic (236)',
            '235' => 'Chad (235)',
            '56' => 'Chile (56)',
            '86' => 'China (86)',
            '57' => 'Colombia (57)',
            '506' => 'Costa Rica (506)',
            '385' => 'Croatia (385)',
            '357' => 'Cyprus (357)',
            '420' => 'Czech Republic (420)',
            '45' => 'Denmark (45)',
            '1-809, 1-829, 1-849' => 'Dominican Republic (1-809, 1-829, 1-849)',
            '593' => 'Ecuador (593)',
            '20' => 'Egypt (20)',
            '503' => 'El Salvador (503)',
            '372' => 'Estonia (372)',
            '268' => 'Eswatini (268)',
            '251' => 'Ethiopia (251)',
            '679' => 'Fiji (679)',
            '358' => 'Finland (358)',
            '33' => 'France (33)',
            '49' => 'Germany (49)',
            '233' => 'Ghana (233)',
            '30' => 'Greece (30)',
            '502' => 'Guatemala (502)',
            '509' => 'Haiti (509)',
            '504' => 'Honduras (504)',
            '36' => 'Hungary (36)',
            '354' => 'Iceland (354)',
            '+91' => 'India (91)',
            '62' => 'Indonesia (62)',
            '98' => 'Iran (98)',
            '964' => 'Iraq (964)',
            '353' => 'Ireland (353)',
            '972' => 'Israel (972)',
            '39' => 'Italy (39)',
            '1-876' => 'Jamaica (1-876)',
            '81' => 'Japan (81)',
            '962' => 'Jordan (962)',
            '7' => 'Kazakhstan (7)',
            '254' => 'Kenya (254)',
            '965' => 'Kuwait (965)',
            '996' => 'Kyrgyzstan (996)',
            '856' => 'Laos (856)',
            '371' => 'Latvia (371)',
            '961' => 'Lebanon (961)',
            '231' => 'Liberia (231)',
            '218' => 'Libya (218)',
            '370' => 'Lithuania (370)',
            '352' => 'Luxembourg (352)',
            '60' => 'Malaysia (60)',
            '356' => 'Malta (356)',
            '52' => 'Mexico (52)',
            '373' => 'Moldova (373)',
            '377' => 'Monaco (377)',
            '212' => 'Morocco (212)',
            '977' => 'Nepal (977)',
            '31' => 'Netherlands (31)',
            '64' => 'New Zealand (64)',
            '505' => 'Nicaragua (505)',
            '234' => 'Nigeria (234)',
            '47' => 'Norway (47)',
            '92' => 'Pakistan (92)',
            '51' => 'Peru (51)',
            '63' => 'Philippines (63)',
            '48' => 'Poland (48)',
            '351' => 'Portugal (351)',
            '974' => 'Qatar (974)',
            '40' => 'Romania (40)',
            '7' => 'Russia (7)',
            '966' => 'Saudi Arabia (966)',
            '65' => 'Singapore (65)',
            '27' => 'South Africa (27)',
            '82' => 'South Korea (82)',
            '34' => 'Spain (34)',
            '94' => 'Sri Lanka (94)',
            '46' => 'Sweden (46)',
            '41' => 'Switzerland (41)',
            '886' => 'Taiwan (886)',
            '66' => 'Thailand (66)',
            '90' => 'Turkey (90)',
            '380' => 'Ukraine (380)',
            '971' => 'United Arab Emirates (971)',
            '44' => 'United Kingdom (44)',
            '1' => 'United States (1)',
            '598' => 'Uruguay (598)',
            '58' => 'Venezuela (58)',
            '84' => 'Vietnam (84)',
        ];
    }


    //get dial code 
    public static function dialCodeToCountryCode($dialCode)
    {
        $mapping = [
            '93' => 'AF', // Afghanistan
            '355' => 'AL', // Albania
            '213' => 'DZ', // Algeria
            '376' => 'AD', // Andorra
            '244' => 'AO', // Angola
            '54' => 'AR', // Argentina
            '374' => 'AM', // Armenia
            '61' => 'AU', // Australia
            '43' => 'AT', // Austria
            '994' => 'AZ', // Azerbaijan
            '1-242' => 'BS', // Bahamas
            '973' => 'BH', // Bahrain
            '880' => 'BD', // Bangladesh
            '1-246' => 'BB', // Barbados
            '375' => 'BY', // Belarus
            '32' => 'BE', // Belgium
            '501' => 'BZ', // Belize
            '229' => 'BJ', // Benin
            '975' => 'BT', // Bhutan
            '591' => 'BO', // Bolivia
            '387' => 'BA', // Bosnia and Herzegovina
            '267' => 'BW', // Botswana
            '55' => 'BR', // Brazil
            '673' => 'BN', // Brunei
            '359' => 'BG', // Bulgaria
            '226' => 'BF', // Burkina Faso
            '257' => 'BI', // Burundi
            '855' => 'KH', // Cambodia
            '237' => 'CM', // Cameroon
            '1' => 'CA', // Canada
            '238' => 'CV', // Cape Verde
            '236' => 'CF', // Central African Republic
            '235' => 'TD', // Chad
            '56' => 'CL', // Chile
            '86' => 'CN', // China
            '57' => 'CO', // Colombia
            '506' => 'CR', // Costa Rica
            '385' => 'HR', // Croatia
            '357' => 'CY', // Cyprus
            '420' => 'CZ', // Czech Republic
            '45' => 'DK', // Denmark
            '1-809' => 'DO', // Dominican Republic
            '593' => 'EC', // Ecuador
            '20' => 'EG', // Egypt
            '503' => 'SV', // El Salvador
            '372' => 'EE', // Estonia
            '268' => 'SZ', // Eswatini
            '251' => 'ET', // Ethiopia
            '679' => 'FJ', // Fiji
            '358' => 'FI', // Finland
            '33' => 'FR', // France
            '49' => 'DE', // Germany
            '233' => 'GH', // Ghana
            '30' => 'GR', // Greece
            '502' => 'GT', // Guatemala
            '509' => 'HT', // Haiti
            '504' => 'HN', // Honduras
            '36' => 'HU', // Hungary
            '354' => 'IS', // Iceland
            '+91' => 'IN', // India
            '62' => 'ID', // Indonesia
            '98' => 'IR', // Iran
            '964' => 'IQ', // Iraq
            '353' => 'IE', // Ireland
            '972' => 'IL', // Israel
            '39' => 'IT', // Italy
            '1-876' => 'JM', // Jamaica
            '81' => 'JP', // Japan
            '962' => 'JO', // Jordan
            '7' => 'KZ', // Kazakhstan
            '254' => 'KE', // Kenya
            '965' => 'KW', // Kuwait
            '996' => 'KG', // Kyrgyzstan
            '856' => 'LA', // Laos
            '371' => 'LV', // Latvia
            '961' => 'LB', // Lebanon
            '231' => 'LR', // Liberia
            '218' => 'LY', // Libya
            '370' => 'LT', // Lithuania
            '352' => 'LU', // Luxembourg
            '60' => 'MY', // Malaysia
            '356' => 'MT', // Malta
            '52' => 'MX', // Mexico
            '373' => 'MD', // Moldova
            '377' => 'MC', // Monaco
            '212' => 'MA', // Morocco
            '977' => 'NP', // Nepal
            '31' => 'NL', // Netherlands
            '64' => 'NZ', // New Zealand
            '505' => 'NI', // Nicaragua
            '234' => 'NG', // Nigeria
            '47' => 'NO', // Norway
            '92' => 'PK', // Pakistan
            '51' => 'PE', // Peru
            '63' => 'PH', // Philippines
            '48' => 'PL', // Poland
            '351' => 'PT', // Portugal
            '974' => 'QA', // Qatar
            '40' => 'RO', // Romania
            '7' => 'RU', // Russia
            '966' => 'SA', // Saudi Arabia
            '65' => 'SG', // Singapore
            '27' => 'ZA', // South Africa
            '82' => 'KR', // South Korea
            '34' => 'ES', // Spain
            '94' => 'LK', // Sri Lanka
            '46' => 'SE', // Sweden
            '41' => 'CH', // Switzerland
            '886' => 'TW', // Taiwan
            '66' => 'TH', // Thailand
            '90' => 'TR', // Turkey
            '380' => 'UA', // Ukraine
            '971' => 'AE', // United Arab Emirates
            '44' => 'GB', // United Kingdom
            '1' => 'US', // United States
            '598' => 'UY', // Uruguay
            '58' => 'VE', // Venezuela
            '84' => 'VN', // Vietnam
        ];

        // Check if dial code exists in the mapping
        return $mapping[$dialCode] ?? null;
    }

    /*
    * Relation with roles.
    * Date 14-10-2024
    */
    public function role()
    {
        return $this->belongsTo(Role::class,'role_id', 'role_id');    
    }

    public function hasPermission($permissionId)
    {
        $permission = Permission::find($permissionId);
        if (!$permission) {
            return false;
        }
        $roleIdsWithPermission = json_decode($permission->feature_roles, true);
        foreach ($this->role as $rol) {
            if (in_array($rol->id, $roleIdsWithPermission)) {
                return true; 
            }
        }
        return false;
    }
}
