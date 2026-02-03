<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, SoftDeletes;

    /**
     * Always use the central database for User model
     * Users are stored in main database, not tenant database
     *
     * @var string
     */
    protected $connection = 'mysql';

    /**
     * The guard name for Spatie Permission
     * Using 'web' guard for compatibility with JWT authentication
     *
     * @var string
     */
    protected $guard_name = 'web';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'clinic_id',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the identifier that will be stored in the JWT subject claim.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [
            'email' => $this->email,
            'role' => $this->role,
        ];
    }

    /**
     * Get the cases assigned to the doctor.
     */
    public function cases()
    {
        return $this->hasMany(CaseModel::class, 'doctor_id');
    }

    /**
     * Get the recipes created by the doctor.
     */
    public function recipes()
    {
        return $this->hasMany(Recipe::class, 'doctors_id');
    }

    /**
     * Get the reservations assigned to the doctor.
     */
    public function reservations()
    {
        return $this->hasMany(Reservation::class, 'doctor_id');
    }

    /**
     * Get the recipe items created by the doctor.
     */
    public function recipeItems()
    {
        return $this->hasMany(RecipeItem::class, 'doctors_id');
    }

    /**
     * Get the bills assigned to the doctor.
     */
    public function bills()
    {
        return $this->hasMany(Bill::class, 'doctor_id');
    }

    /**
     * Get the clinic that the user belongs to.
     */
    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    /**
     * Get all of the user's images (profile picture, documents, etc.).
     */
    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }
}
