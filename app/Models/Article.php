<?php
/**
 * LaraCMS - CMS based on laravel
 *
 * @category  LaraCMS
 * @package   Laravel
 * @author    Wanglelecc <wanglelecc@gmail.com>
 * @date      2018/06/06 09:08:00
 * @copyright Copyright 2018 LaraCMS
 * @license   https://opensource.org/licenses/MIT
 * @github    https://github.com/wanglelecc/laracms
 * @link      https://www.laracms.cn
 * @version   Release 1.0
 */

namespace App\Models;

use Laravel\Scout\Searchable;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Traits\WithCommonHelper;
use App\Events\BehaviorLogEvent;

/**
 * 文章模型
 *
 * Class Article
 * @package App\Models
 */
class Article extends Model
{
    use WithCommonHelper;
    use Searchable;

    public $dispatchesEvents  = [
        'saved' => BehaviorLogEvent::class,
    ];

    public function titleName(){
        return 'title';
    }

    public $asYouType = true;

    protected $fillable = [
         'id','object_id', 'alias','title', 'subtitle', 'keywords', 'description', 'author', 'source', 'order', 'content', 'attributes', 'thumb', 'type', 'is_link','link', 'template', 'status', 'views', 'reply_count', 'weight', 'css', 'js', 'top', 'created_op', 'updated_op',
    ];

    public function toSearchableArray()
    {
        $array = $this->toArray();

        return $array;
    }


    public function user(){
        return $this->created_user();
    }

    public function created_user(){
        return $this->belongsTo('App\Models\User', 'created_op');
    }

    public function updated_user(){
        return $this->belongsTo('App\Models\User', 'updated_op');
    }

    public function filterWith($type = 'article'){
        return $this->where('type', $type)->with(['created_user','updated_user']);
    }

    public function replies()
    {
        return $this->hasMany(Reply::class);
    }

    /**
     * 多对多多态关联
     *
     * @return MorphToMany
     */
    public function category(): MorphToMany
    {
        return $this->morphToMany(
            'App\Models\Category',
            'model',
            'model_has_category',
            'model_id',
            'category_id'
        );
    }

    /**
     * 多对多
     *
     * @return BelongsToMany
     */
    public function categorys(): BelongsToMany
    {
        return $this->belongsToMany(
            'App\Models\Category',
            'article_category',
            'article_id',
            'category_id'
        );
    }

    public function giveCategoryTo(...$categorys)
    {
        $categorys = collect($categorys)
            ->flatten()
            ->map(function ($category) {
                return $this->getStoredCategory($category);
            })
            ->each(function ($category) {
                $this->ensureModelSharesArticle($category);
            })
            ->all();

        $this->categorys()->saveMany($categorys);

        return $this;
    }


    public function syncCategory(...$categorys)
    {
        $this->categorys()->detach();

        return $this->giveCategoryTo($categorys);
    }

    protected function getStoredCategory($categorys)
    {
        if (is_string($categorys) || is_int($categorys)) {
            return app(Category::class)->find(intval($categorys));
        }

        if (is_array($categorys)) {
            return app(Category::class)
                ->whereIn('id', $categorys)
                ->get();
        }

        return $categorys;
    }

    protected function ensureModelSharesArticle($category)
    {
        if (! $category) {
            abort(401);
        }
    }

    /**
     * 生成文章链接
     *
     * @param int $navigation_id
     * @param int $category_id
     * @return string
     */
    public function getLink($navigation_id = 0, $category_id = 0){
        if($this->is_link == 1 && !empty($this->link)){
            return $this->link;
        }
        return route('article.show',[$navigation_id, $category_id, $this->id]);
    }

    /**
     * 获取扩展属性
     *
     * @param string $attribute
     * @return mixed|string
     */
    public function getAttr($attribute){
        return get_json_params($this->attributes, $attribute, null);
    }

}
