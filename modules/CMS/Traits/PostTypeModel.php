<?php
/**
 * JUZAWEB CMS - Laravel CMS for Your Project
 *
 * @package    juzaweb/juzacms
 * @author     The Anh Dang
 * @link       https://github.com/juzaweb/juzacms
 * @license    GNU V2
 */

namespace Juzaweb\CMS\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Juzaweb\Backend\Http\Resources\TaxonomyResource;
use Juzaweb\Backend\Models\Comment;
use Juzaweb\Backend\Models\PostMeta;
use Juzaweb\Backend\Models\Taxonomy;
use Juzaweb\CMS\Facades\HookAction;

/**
 * @method Builder wherePublish()
 * @method Builder whereTaxonomy($taxonomy)
 * @method Builder whereTaxonomyIn($taxonomies)
 * @method Builder whereFilter(array $params)
 * @method Builder whereMeta($key, $val)
 */
trait PostTypeModel
{
    use ResourceModel;
    use UseSlug;
    use UseThumbnail;
    use UseChangeBy;
    use UseDescription;

    public static function selectFrontendBuilder(): Builder
    {
        $builder = static::with(
            [
                'createdBy',
                'taxonomies',
            ]
        )->select(
            [
                'id',
                'title',
                'description',
                'thumbnail',
                'slug',
                'views',
                'total_rating',
                'total_comment',
                'type',
                'status',
                'created_by',
                'created_at',
                'json_metas',
            ]
        )->wherePublish();

        return apply_filters('post.selectFrontendBuilder', $builder);
    }

    /**
     * Create Builder for frontend
     *
     * @return Builder
     */
    public static function createFrontendBuilder(): Builder
    {
        $builder = static::with(
            [
                'createdBy',
                'taxonomies',
            ]
        )
            ->wherePublish();

        return apply_filters('post.createFrontendBuilder', $builder);
    }

    public static function getStatuses($type = 'posts'): array
    {
        $statuses = [
            'publish' => trans('cms::app.publish'),
            'private' => trans('cms::app.private'),
            'draft' => trans('cms::app.draft'),
            'trash' => trans('cms::app.trash'),
        ];

        return apply_filters($type . '.statuses', $statuses);
    }

    public function attributeLabels(): array
    {
        return apply_filters(
            "{$this->type}.attribute_labels",
            [
                'title' => trans('cms::app.title'),
                'content' => trans('cms::app.content'),
                'status' => trans('cms::app.status'),
                'slug' => trans('cms::app.slug'),
                'thumbnail' => trans('cms::app.thumbnail'),
                'views' => trans('cms::app.views'),
            ]
        );
    }

    public function taxonomies(): BelongsToMany
    {
        return $this->belongsToMany(
            Taxonomy::class,
            'term_taxonomies',
            'term_id',
            'taxonomy_id'
        )
            ->withPivot(['term_type']);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'object_id', 'id');
    }

    public function metas(): HasMany
    {
        return $this->hasMany(PostMeta::class, 'post_id', 'id');
    }

    public function getMeta($key, $default = null): mixed
    {
        return $this->json_metas[$key] ?? $default;
    }

    public function getMetas(): ?array
    {
        return $this->json_metas;
    }

    /**
     * @param Builder $builder
     * @param array $params
     *
     * @return Builder
     */
    public function scopeWhereFilter($builder, $params = []): Builder
    {
        if ($keyword = Arr::get($params, 'q')) {
            $keyword = trim($keyword);
            $builder->where(
                function (Builder $q) use ($keyword) {
                    $q->where('title', JW_SQL_LIKE, '%'.$keyword.'%');
                    $q->orWhere('description', JW_SQL_LIKE, '%'.$keyword.'%');
                }
            );
        }

        if ($status = Arr::get($params, 'status')) {
            $builder->where('status', '=', $status);
        }

        if ($type = Arr::get($params, 'type')) {
            $builder->where('type', '=', $type);
            $taxonomies = HookAction::getTaxonomies($type);

            foreach ($taxonomies as $key => $taxonomy) {
                $ids = Arr::get($params, $key, []);
                if (! is_array($ids)) {
                    $ids = [$ids];
                }

                $ids = array_filter(
                    $ids,
                    function ($item) {
                        return !empty($item);
                    }
                );

                if ($ids) {
                    $builder->whereTaxonomyIn($ids);
                }
            }
        }

        if ($metas = Arr::get($params, 'meta')) {
            foreach ($metas as $key => $val) {
                if (is_null($metas[$key])) {
                    continue;
                }

                $builder->whereMeta($key, $val);
            }
        }

        if ($sort = Arr::get($params, 'sort')) {
            switch ($sort) {
                case 'latest':
                    $builder->orderBy('id', 'DESC');
                    break;
                case 'top_views':
                    $builder->orderBy('views', 'DESC');
                    break;
                case 'new_update':
                    $builder->orderBy('updated_at', 'DESC');
                    break;
            }
        }

        return $builder;
    }

    /**
     * @param Builder $builder
     * @param string $key
     * @param string $value
     *
     * @return Builder
     */
    public function scopeWhereMeta($builder, $key, $value)
    {
        return $builder->whereHas(
            'metas',
            function (Builder $q) use (
                $key,
                $value
            ) {
                $q->where('meta_key', '=', $key);
                if (is_array($value)) {
                    $q->whereIn('meta_value', $value);
                } else {
                    $q->where('meta_value', '=', $value);
                }
            }
        );
    }

    public function scopeWhereMetaIn($builder, $key, $values)
    {
        return $builder->whereHas(
            'metas',
            function (Builder $q) use (
                $key,
                $values
            ) {
                $q->where('meta_key', '=', $key);
                $q->whereIn('meta_value', $values);
            }
        );
    }

    /**
     * @param Builder $builder
     * @param array $params
     *
     * @return Builder
     */
    public function scopeWhereSearch($builder, $params)
    {
        $builder->whereFilter($params);

        $builder = apply_filters(
            'frontend.search_query',
            $builder,
            $params
        );

        return $builder;
    }

    /**
     * Get taxonomies by taxonomy
     *
     * @param string $taxonomy
     * @param int $limit
     * @param bool $tree
     * @return Collection
     */
    public function getTaxonomies(
        $taxonomy = null,
        $limit = null,
        $tree = false
    ) {
        $taxonomies = $this->taxonomies;

        if ($taxonomy) {
            $taxonomies = $taxonomies->where('taxonomy', $taxonomy);
        }

        if ($tree) {
            $taxonomies = $taxonomies->orderBy('level', 'ASC');
        }

        if ($limit) {
            $taxonomies = $taxonomies->take($limit);
        }

        return $taxonomies;
    }

    /**
     * Get Related Posts
     *
     * @param int $limit
     * @param string $taxonomy
     * @return Collection
     */
    public function getRelatedPosts($limit = 5, $taxonomy = null)
    {
        $ids = $this->getTaxonomies($taxonomy)->pluck('id')->toArray();

        return self::whereHas(
            'taxonomies',
            function (Builder $q) use ($ids) {
                $q->whereIn("{$q->getModel()->getTable()}.id", $ids);
            }
        )
            ->where('id', '!=', $this->id)
            ->take($limit)
            ->get();
    }

    /**
     * @param array $attributes
     * @throws \Exception
     */
    public function syncTaxonomies(array $attributes)
    {
        if (empty($this->type)) {
            throw new \Exception('Cannot find Type in post.');
        }

        $taxonomies = HookAction::getTaxonomies($this->type);
        foreach ($taxonomies as $taxonomy) {
            $this->syncTaxonomy(
                $taxonomy->get('taxonomy'),
                $attributes,
                $this->type
            );
        }

        $this->update(
            [
                'json_taxonomies' => TaxonomyResource::collection(
                    $this->taxonomies()->get()
                )->toArray(request())
            ]
        );
    }

    public function syncTaxonomy(
        string $taxonomy,
        array $attributes,
        string $postType = null
    ): bool {
        $postType = $postType ?: $this->type;
        $data = Arr::get($attributes, $taxonomy, []);

        $detachIds = $this->taxonomies()
            ->where('taxonomy', '=', $taxonomy)
            ->whereNotIn('id', $data)
            ->pluck('id')
            ->toArray();

        $this->taxonomies()->detach($detachIds);

        $this->taxonomies()
            ->syncWithoutDetaching(
                combine_pivot(
                    $data,
                    [
                        'term_type' => $postType,
                    ]
                ),
                ['term_type' => $postType]
            );

        $taxonomies = Taxonomy::where('taxonomy', '=', $taxonomy)
            ->whereIn('id', array_merge($detachIds, $data))
            ->get();

        foreach ($taxonomies as $taxonomy) {
            $taxonomy->update(
                [
                    'total_post' => $taxonomy->posts()->count(),
                ]
            );
        }

        return true;
    }

    public function setMeta($key, $value): void
    {
        $metas = $this->getMetas();
        $this->metas()->updateOrCreate(
            [
                'meta_key' => $key
            ],
            [
                'meta_value' => is_array($value) ? json_encode($value) : $value
            ]
        );

        $metas[$key] = $value;

        $this->update(
            [
                'json_metas' => $metas
            ]
        );
    }

    public function deleteMeta($key): bool
    {
        $this->metas()->where('meta_key', $key)->delete();

        $metas = $this->getMetas();

        unset($metas[$key]);

        $this->update(
            [
                'json_metas' => $metas
            ]
        );

        return true;
    }

    public function deleteMetas(array $keys): bool
    {
        $this->metas()->whereIn('meta_key', $keys)->delete();

        $metas = $this->getMetas();

        foreach ($keys as $key) {
            unset($metas[$key]);
        }

        $this->update(
            [
                'json_metas' => $metas
            ]
        );

        return true;
    }

    public function syncMetas(array $data = []): void
    {
        $metas = [];
        $keys = $this->getPostTypeMetaKeys();

        foreach ($data as $key => $val) {
            if (!in_array($key, $keys)) {
                continue;
            }

            $this->metas()->updateOrCreate(
                [
                    'meta_key' => $key
                ],
                [
                    'meta_value' => is_array($val) ? json_encode($val) : $val
                ]
            );

            $metas[$key] = $val;
        }

        $this->update(
            [
                'json_metas' => $metas
            ]
        );

        $this->metas()
            ->whereNotIn('meta_key', array_keys($data))
            ->delete();
    }

    /**
     * @param Builder $builder
     *
     * @return Builder
     **/
    public function scopeWherePublish($builder): Builder
    {
        $builder->where('status', '=', 'publish');

        return $builder;
    }

    /**
     * @param Builder $builder
     * @param int $taxonomy
     *
     * @return Builder
     **/
    public function scopeWhereTaxonomy($builder, $taxonomy): Builder
    {
        $builder->whereHas(
            'taxonomies',
            function (Builder $q) use ($taxonomy) {
                $q->where($q->getModel()->getTable() . '.id', $taxonomy);
            }
        );

        return $builder;
    }

    /**
     * @param Builder $builder
     * @param array $taxonomies
     *
     * @return Builder
     */
    public function scopeWhereTaxonomyIn($builder, $taxonomies): Builder
    {
        $builder->whereHas(
            'taxonomies',
            function (Builder $q) use ($taxonomies) {
                $q->whereIn(
                    $q->getModel()->getTable() . '.id',
                    $taxonomies
                );
            }
        );

        return $builder;
    }

    public function getPostType($key = null)
    {
        if ($key == 'key') {
            return $this->type;
        }

        $postType = HookAction::getPostTypes()
            ->where('key', '=', $this->type)
            ->first();

        if (empty($key)) {
            return $postType;
        }

        return $postType->get($key);
    }

    public function getPostTypeMetaKeys(): array
    {
        return array_keys($this->getPostType('metas'));
    }

    public function getPermalink($key = null)
    {
        $permalink = HookAction::getPermalinks($this->type);

        if (empty($permalink)) {
            return false;
        }

        if (empty($key)) {
            return $permalink;
        }

        return $permalink->get($key);
    }

    public function getTitle($words = null): string
    {
        if ($words > 0) {
            return apply_filters(
                $this->type . '.get_title',
                Str::words(
                    $this->{$this->getFieldName()},
                    $words
                ),
                $words
            );
        }

        return apply_filters(
            $this->type . '.get_title',
            $this->{$this->getFieldName()},
            $words
        );
    }

    public function getContent(): string
    {
        $pattern = '/\<img(.*)src\=\"([0-9a-zA-Z\-\.\/]+)\"(.*)\>/';

        $content = preg_replace_callback(
            $pattern,
            function ($matches) {
                return '<img'.$matches[1].'src="'.upload_url($matches[2]).'"'.$matches[3].'>';
            },
            $this->content
        );

        return apply_filters(
            $this->type . '.get_content',
            $content
        );
    }

    public function getLink(): bool|string
    {
        if ($this->type == 'pages') {
            return route('post', [$this->slug]);
        }

        $permalink = $this->getPermalink('base');
        if (empty($permalink)) {
            return false;
        }

        return route('post', ["{$permalink}/{$this->slug}"]);
    }

    public function getUpdatedDate($format = JW_DATE_TIME): string
    {
        return jw_date_format($this->updated_at, $format);
    }

    public function getCreatedDate($format = JW_DATE_TIME): string
    {
        return jw_date_format($this->updated_at, $format);
    }

    public function getCreatedByName(): string
    {
        if ($this->createdBy) {
            return $this->createdBy->name;
        }

        return 'Admin';
    }

    public function getCreatedByAvatar()
    {
        if ($this->createdBy) {
            return $this->createdBy->getAvatar();
        }

        return asset('jw-styles/juzaweb/images/avatar.png');
    }

    public function getViews(): int|string
    {
        if ($this->views < 1000) {
            return $this->views;
        }

        return round($this->views / 1000, 1) . 'K';
    }

    public function getTotalComments(): int
    {
        return $this->comments()->whereApproved()->count();
    }
}
