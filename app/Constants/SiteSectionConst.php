<?php

namespace App\Constants;

class SiteSectionConst{
    const AUTH_SECTION        = "Auth Section";
    const BANNER_SECTION      = "Banner Section";
    const ABOUT_SECTION       = "About Section";
    const BRAND_SECTION       = "Brand Section";
    const SERVICE_SECTION     = "Service Section";
    const FEATURE_SECTION     = "Feature Section";
    const CONTACT_SECTION     = "Contact Section";
    const APP_SECTION         = "App Section";
    const TESTIMONIAL_SECTION = "Testimonial Section";
    const FAQ_SECTION         = "FAQ Section";
    const BLOG_SECTION        = "Blog Section";

    const NOT_DISPLAY_COOKIE_SECTION     = "site_cookie";
    const NOT_DISPLAY_AUTH_SECTION       = "auth-section";
    const NOT_DISPLAY_FOOTER_SECTION     = "footer-section";
    
    public static function notDisplaySections(): array{
            return [
                self::NOT_DISPLAY_COOKIE_SECTION,
                self::NOT_DISPLAY_AUTH_SECTION,
                self::NOT_DISPLAY_FOOTER_SECTION
            ];
    }
}