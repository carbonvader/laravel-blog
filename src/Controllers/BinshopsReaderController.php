<?php

namespace BinshopsBlog\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use BinshopsBlog\Laravel\Fulltext\Search;
use BinshopsBlog\Models\BinshopsCategoryTranslation;
use Faker\Extension\Helper;
use Illuminate\Http\Request;
use BinshopsBlog\Captcha\UsesCaptcha;
use BinshopsBlog\Middleware\DetectLanguage;
use BinshopsBlog\Models\BinshopsCategory;
use BinshopsBlog\Models\BinshopsLanguage;
use BinshopsBlog\Models\BinshopsPost;
use BinshopsBlog\Models\BinshopsPostTranslation;

/**
 * Class BinshopsReaderController
 * All of the main public facing methods for viewing blog content (index, single posts)
 * @package BinshopsBlog\Controllers
 */
class BinshopsReaderController extends Controller
{
    use UsesCaptcha;

    public function __construct()
    {
        $this->middleware(DetectLanguage::class);
    }

    /**
     * Show blog posts
     * If category_slug is set, then only show from that category
     *
     * @param null $category_slug
     * @return mixed
     */
    public function index($locale, Request $request, $category_slug = null)
    {
        // the published_at + is_published are handled by BinshopsBlogPublishedScope, and don't take effect if the logged in user can manageb log posts
        //todo

        $categoryChain = null;
        $posts=BinshopsPostTranslation::get_posts_with_category($request,$category_slug);
        $title = 'Son Eklenenler'; // default title...
        //search$title = 'Posts in ' . $category->category_name . " category"; // hardcode title here...

        //load category hierarchy
        $rootList = BinshopsCategory::rootsByWebsite()->get();
        BinshopsCategory::loadSiblingsWithList($rootList);
        $popular_posts=BinshopsPostTranslation::get_posts_with_category($request,"popular");


        return view("binshopsblog::index", [
            'lang_list' => BinshopsLanguage::all('locale', 'name'),
            'locale' => $request->get("locale"),
            'lang_id' => $request->get('lang_id'),
            'categories' => $rootList,
            'category_slug' => $category_slug,
            'popular_posts' => $popular_posts,
            'posts' => $posts,
            'title' => $title,
        ]);
    }

    /**
     * Show the search results for $_GET['s']
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Exception
     */
    public function search(Request $request)
    {
        if (!config("binshopsblog.search.search_enabled"))
        {
            throw new \Exception("Search is disabled");
        }
        $query = $request->get("s");
        $search = new Search();
        $search_results = $search->run($query);

        \View::share("title", "Arama sonucu " . e($query));

        $rootList = BinshopsCategory::rootsByWebsite()->get();
        BinshopsCategory::loadSiblingsWithList($rootList);
        $popular_posts=BinshopsPostTranslation::get_posts_with_category($request,"popular");

        return view("binshopsblog::search", [
                'lang_id' => $request->get('lang_id'),
                'locale' => $request->get("locale"),
                'categories' => $rootList,
                'category_slug' => null,
                'popular_posts' => $popular_posts,
                'query' => $query,
                'search_results' => $search_results]
        );

    }

    /**
     * View all posts in $category_slug category
     *
     * @param Request $request
     * @param $category_slug
     * @return mixed
     */
    public function view_category($locale, $hierarchy, Request $request)
    {
        $categories = explode('/', $hierarchy);
        return $this->index($locale, $request, end($categories));
    }

    /**
     * View a single post and (if enabled) it's comments
     *
     * @param Request $request
     * @param $blogPostSlug
     * @return mixed
     */
    public function viewSinglePost(Request $request, $locale, $blogPostSlug)
    {
        // the published_at + is_published are handled by BinshopsBlogPublishedScope, and don't take effect if the logged in user can manage log posts
        $blog_post = BinshopsPostTranslation::where([
            ["slug", "=", $blogPostSlug],
            ['lang_id', "=", $request->get("lang_id")]
        ])->firstOrFail();

        if ($captcha = $this->getCaptchaObject())
        {
            $captcha->runCaptchaBeforeShowingPosts($request, $blog_post);
        }
        $rootList = BinshopsCategory::rootsByWebsite()->get();
        $popular_posts=BinshopsPostTranslation::get_posts_with_category($request,"popular");

        return view("binshopsblog::single_post", [
            'post' => $blog_post,
            // the default scope only selects approved comments, ordered by id
            'comments' => $blog_post->post->comments()
                ->with("user")
                ->get(),
            'category_slug'=>null,
            'captcha' => $captcha,
            'categories' => $rootList,
            'popular_posts'=>$popular_posts,
            'locale' => $request->get("locale")
        ]);
    }

}
