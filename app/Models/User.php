<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // Import Sanctum's HasApiTokens trait

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

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
        'password' => 'hashed', // Auto-hash passwords when set
    ];

    /**
     * Relationship: User has many tasks.
     */
    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Relationship: User owns many projects.
     */
    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Scope to filter users by email domain.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $domain
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByEmailDomain($query, $domain)
    {
        return $query->where('email', 'like', '%' . $domain);
    }

    /**
     * Generate a new API token for the user.
     *
     * @return string
     */
    public function generateApiToken(): string
    {
        return $this->createToken('api_token')->plainTextToken;
    }
}
