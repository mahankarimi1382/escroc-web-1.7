<?php

namespace App\Http\Controllers;
use App\Models\Blog;
use App\Models\Contact;
use Illuminate\Support\Str;
use App\Models\BlogCategory;

use Illuminate\Http\Request;
use App\Models\Admin\Currency;
use App\Models\Admin\Language;
use App\Models\Admin\SetupPage;
use App\Constants\ExtensionConst;
use App\Models\Admin\SiteSections;
use App\Constants\SiteSectionConst;
use App\Models\Admin\BasicSettings;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use App\Providers\Admin\ExtensionProvider;

class SiteController extends Controller
{
    public function home()
    {
        $basic_settings = BasicSettings::first();
        $page_title = setPageTitle($basic_settings->site_title) ?? "Home";
        $currencies = Currency::where('status', true)->get();
        //home page sections data
        $section_slug = Str::slug(SiteSectionConst::BANNER_SECTION);
        $banner       = SiteSections::getData($section_slug)->first();
        $section_slug = Str::slug(SiteSectionConst::BRAND_SECTION);
        $brand        = SiteSections::getData($section_slug)->first();
        $section_slug = Str::slug(SiteSectionConst::ABOUT_SECTION);
        $about        = SiteSections::getData($section_slug)->first();
        $section_slug = Str::slug(SiteSectionConst::SERVICE_SECTION);
        $service      = SiteSections::getData($section_slug)->first();
        $section_slug = Str::slug(SiteSectionConst::FEATURE_SECTION);
        $feature      = SiteSections::getData($section_slug)->first();
        $section_slug = Str::slug(SiteSectionConst::TESTIMONIAL_SECTION);
        $testimonial  = SiteSections::getData($section_slug)->first();
        $blogs = Blog::where('status',1)->orderBy('id',"DESC")->take(3)->get();

        return view('frontend.index', compact('page_title','currencies','banner','brand','about','service','feature','blogs','testimonial'));
    }
    public function aboutUs()
    {
        $page_title = setPageTitle("About Us");
        $section_slug = Str::slug(SiteSectionConst::ABOUT_SECTION);
        $about = SiteSections::getData($section_slug)->first();
        $page_section = SetupPage::where('slug', 'about-us')->with(['sections' => function($q){
            $q->where('status',true);
        }])->first();
        $setupPage = SetupPage::where('slug', 'about-us')->first();
        if($setupPage->status == false) return redirect()->route('index');

        return view('frontend.pages.about-us', compact('page_title','about','page_section'));
    }
    public function contactUs()
    {
        $page_title = setPageTitle("Contact Us");
        $section_slug = Str::slug(SiteSectionConst::CONTACT_SECTION);
        $contact = SiteSections::getData($section_slug)->first();
        $page_section = SetupPage::where('slug', 'contact-us')->with(['sections' => function($q){
            $q->where('status',true);
        }])->first();
        $setupPage = SetupPage::where('slug', 'contact-us')->first();
        if($setupPage->status == false) return redirect()->route('index');

        return view('frontend.pages.contact-us', compact('page_title','contact','page_section'));
    }
    public function contactStore(Request $request){
        $extension = ExtensionProvider::get()->where('slug', ExtensionConst::GOOGLE_RECAPTCHA_SLUG)->first();
        $captcha_rules = "nullable";
        if($extension && $extension->status == true) {
            $captcha_rules = 'required|string|g_recaptcha_verify';
        }
        $validator = Validator::make($request->all(),[
            'name'    => 'required|string',
            'email'   => 'required|email',
            'message' => 'required|string',
            'g-recaptcha-response'  => $captcha_rules
        ]);

        if($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }
        $validated = $validator->validate();
        try {
            Contact::create($validated);
        } catch (\Exception $e) {
            return back()->with(['error' => [__('Something went worng! Please try again')]]);
        }
        return back()->with(['success' => [__('Your message submited!')]]);
    }
    public function services()
    {
        $page_title = setPageTitle("Our Service");
        $section_slug = Str::slug(SiteSectionConst::SERVICE_SECTION);
        $service = SiteSections::getData($section_slug)->first();
        $page_section = SetupPage::where('slug', 'our-services')->with(['sections' => function($q){
            $q->where('status',true);
        }])->first();
        $setupPage = SetupPage::where('slug', 'our-services')->first();
        if($setupPage->status == false) return redirect()->route('index');

        return view('frontend.pages.service', compact('page_title','service','page_section'));
    }
    public function features()
    {
        $page_title = setPageTitle("Features");
        $section_slug = Str::slug(SiteSectionConst::FEATURE_SECTION);
        $feature = SiteSections::getData($section_slug)->first();
        $page_section = SetupPage::where('slug', 'features')->with(['sections' => function($q){
            $q->where('status',true);
        }])->first();
        $setupPage = SetupPage::where('slug', 'features')->first();
        if($setupPage->status == false) return redirect()->route('index');

        return view('frontend.pages.features', compact('page_title','feature','page_section'));
    }
    public function blog()
    {
        $page_title = setPageTitle("Our Blogs");
        $blogs = Blog::where('status',1)->orderBy('id',"DESC")->paginate(6);
        $page_section = SetupPage::where('slug', 'blogs')->with(['sections' => function($q){
            $q->where('status',true);
        }])->first();
        $setupPage = SetupPage::where('slug', 'blogs')->first();
        if($setupPage->status == false) return redirect()->route('index');

        return view('frontend.pages.blog', compact('page_title', 'blogs', 'page_section'));
    }
    public function blogDetails($id,$slug){
        $page_title = setPageTitle("Blog Details");
        $categories = BlogCategory::where('status',1)->orderBy('id',"ASC")->get();
        $blog = Blog::where('id',$id)->where('slug',$slug)->first();
        $recentPost = Blog::where('status',1)->latest()->limit(3)->get();
        return view('frontend.pages.blogDetails',compact('page_title','blog','recentPost','categories'));
    }
    public function blogByCategory($id,$slug){
        $category = BlogCategory::findOrfail($id);
        $blogs = Blog::where('status',1)->where('category_id',$category->id)->latest()->paginate(6);
        $page_title = setPageTitle($category->name);
        return view('frontend.pages.blog',compact('blogs','category','page_title'));
    }
    public function pageView($slug)
    {

        $page_data = SetupPage::where('slug', $slug)->where('status', 1)->first();
        if(empty($page_data)){
            abort(404);
        }
        $page_title = setPageTitle($page_data->title);

        return view('frontend.pages.index',compact('page_title','page_data'));
    }
    public function faq(){
        $page_title = "Faq";
        return view('frontend.pages.faq',compact('page_title'));
    }
    public function languageSwitch(Request $request) {
        $code = $request->target;
        $language = Language::where("code",$code);
        if(!$language->exists()) {
            return back()->with(['error' => [__('Opps! Language not found!')]]);
        }

        Session::put('local',$code);

        return back()->with(['success' => [__('Language switch successfully!')]]);
    }
}
