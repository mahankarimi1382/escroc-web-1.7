@php
    $brand    = \App\Models\Admin\SiteSections::where('key','brand-section')->first();
    $defualt = get_default_language_code()??'en';
    $en = 'en';
@endphp
<section class="brand-section pt-80">
        <div class="container">
            <div class="brand-slider">
                <div class="swiper-wrapper">
                    @foreach ($brand->value->items ?? [] as $key => $item)
                    <div class="swiper-slide">
                        <div class="brand-item">
                            <a href="#0" class="brand-thumb">
                                <div class="front">
                                    <img src="{{ get_image($item->front_image, "site-section") }}" alt="brand">
                                </div>
                                <div class="back">
                                    <img src="{{ get_image($item->front_image, "site-section") }}" alt="brand">
                                </div>
                            </a>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>