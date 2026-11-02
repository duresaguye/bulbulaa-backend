<?php

namespace App\Models;

use App\Enums\ServiceAvailability;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\RecordsAudit;
use App\Models\Concerns\TracksEditor;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A service the city offers residents, listed on the website with whether it can be used yet.
 */
#[Fillable(['title', 'description', 'icon', 'availability', 'link_url', 'sort_order', 'is_published'])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    use HasTranslations;
    use RecordsAudit;
    use TracksEditor;

    /**
     * Icons the website can draw, by name in its icon set, with what they suggest to editors.
     *
     * @var array<string, string>
     */
    public const ICONS = [
        'Briefcase' => 'Business',
        'MapPinned' => 'Land and places',
        'Building2' => 'Buildings and construction',
        'Droplets' => 'Water',
        'Zap' => 'Electricity',
        'Trash2' => 'Waste and cleaning',
        'FileText' => 'Documents and certificates',
        'Users' => 'Residents and community',
        'HeartPulse' => 'Health',
        'GraduationCap' => 'Education',
        'Bus' => 'Transport',
        'Landmark' => 'Administration',
        'Receipt' => 'Payments and taxes',
        'MessageSquare' => 'Complaints and feedback',
    ];

    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'description' => 'array',
            'availability' => ServiceAvailability::class,
            'is_published' => 'boolean',
        ];
    }
}
