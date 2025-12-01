<?php

namespace App\Models\Channel;

use App\Models\Chat\Conversation;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ChannelCategory extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'color',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
    ];

    public function channels(): HasMany
    {
        return $this->hasMany(Conversation::class, 'category', 'slug')
            ->where('type', 'channel');
    }

    public function activeChannels(): HasMany
    {
        return $this->channels()->where('is_active', true);
    }

    public function publicChannels(): HasMany
    {
        return $this->activeChannels()->where('privacy', 'public');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopeBySlug($query, $slug)
    {
        return $query->where('slug', $slug);
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function getChannelCount(): int
    {
        return $this->activeChannels()->count();
    }

    public function getPublicChannelCount(): int
    {
        return $this->publicChannels()->count();
    }

    public function getTotalSubscribers(): int
    {
        return $this->activeChannels()
            ->withSum('subscriptions', 'subscriber_count')
            ->sum('subscriptions_sum_subscriber_count') ?? 0;
    }

    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    public function updateSortOrder(int $order): void
    {
        $this->update(['sort_order' => $order]);
    }

    // Boot method to auto-generate slug
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
                
                // Ensure uniqueness
                $originalSlug = $category->slug;
                $counter = 1;
                while (static::where('slug', $category->slug)->exists()) {
                    $category->slug = $originalSlug . '-' . $counter;
                    $counter++;
                }
            }
        });

        static::updating(function ($category) {
            if ($category->isDirty('name') && !$category->isDirty('slug')) {
                $category->slug = Str::slug($category->name);
                
                // Ensure uniqueness (excluding current record)
                $originalSlug = $category->slug;
                $counter = 1;
                while (static::where('slug', $category->slug)->where('id', '!=', $category->id)->exists()) {
                    $category->slug = $originalSlug . '-' . $counter;
                    $counter++;
                }
            }
        });
    }

    // Static helper methods
    public static function getActiveCategories(): \Illuminate\Database\Eloquent\Collection
    {
        return static::active()->ordered()->get();
    }

    public static function getCategoriesWithChannels(): \Illuminate\Database\Eloquent\Collection
    {
        return static::active()
            ->withCount(['activeChannels', 'publicChannels'])
            ->having('active_channels_count', '>', 0)
            ->ordered()
            ->get();
    }

    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }

    public static function getPopularCategories(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return static::active()
            ->withCount('publicChannels')
            ->orderBy('public_channels_count', 'desc')
            ->limit($limit)
            ->get();
    }

    public static function createCategory(array $data): self
    {
        return static::create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? null,
            'description' => $data['description'] ?? null,
            'icon' => $data['icon'] ?? null,
            'color' => $data['color'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);
    }

    public static function seedDefaultCategories(): void
    {
        $defaultCategories = [
            [
                'name' => 'News',
                'slug' => 'news',
                'description' => 'Breaking news and current events',
                'icon' => '📰',
                'color' => '#EF4444',
                'sort_order' => 1,
            ],
            [
                'name' => 'Technology',
                'slug' => 'technology',
                'description' => 'Tech news and discussions',
                'icon' => '💻',
                'color' => '#3B82F6',
                'sort_order' => 2,
            ],
            [
                'name' => 'Entertainment',
                'slug' => 'entertainment',
                'description' => 'Movies, music, and entertainment',
                'icon' => '🎬',
                'color' => '#F59E0B',
                'sort_order' => 3,
            ],
            [
                'name' => 'Sports',
                'slug' => 'sports',
                'description' => 'Sports news and updates',
                'icon' => '⚽',
                'color' => '#10B981',
                'sort_order' => 4,
            ],
            [
                'name' => 'Education',
                'slug' => 'education',
                'description' => 'Educational content and resources',
                'icon' => '📚',
                'color' => '#8B5CF6',
                'sort_order' => 5,
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'description' => 'Business and finance news',
                'icon' => '💼',
                'color' => '#6B7280',
                'sort_order' => 6,
            ],
        ];

        foreach ($defaultCategories as $categoryData) {
            static::firstOrCreate(
                ['slug' => $categoryData['slug']],
                $categoryData
            );
        }
    }
}