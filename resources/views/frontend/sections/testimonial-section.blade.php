@php
    $testimonial    = \App\Models\Admin\SiteSections::where('key','testimonial-section')->first();
    $defualt = get_default_language_code()??'en';
    $en = 'en';
@endphp
<section class="testimonial-section pt-80">
        <div class="circle-blur"></div>
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xl-7 col-lg-7 text-center">
                    <div class="section-header">
                        <span class="section-sub-title"><span class="gradient-text">{{ $testimonial->value->language->$defualt->heading ?? "" }}</span></span>
                        <h2 class="section-title">{{ $testimonial->value->language->$defualt->sub_heading ?? "" }}</h2>
                    </div>
                </div>
            </div>
            <div class="row justify-content-center">
                <div class="col-xl-10 col-lg-12">
                    <div class="testimonial-area">
                        <div class="testimonial-slider">
                            <div class="swiper-wrapper">
                                @foreach ($testimonial->value->items ?? [] as $key => $item)
                                <div class="swiper-slide">
                                    <div class="testimonial-item">
                                        <div class="testimonial-content">
                                            <div class="testimonial-ratings">
                                                @for ($i = 0; $i < $item->icon_show; $i++)
                                                <i class="fas fa-star"></i>
                                                @endfor 
                                            </div>
                                            <p>{{ @$item->language->$defualt->details ?? "" }}</p>
                                            <div class="testimonial-user-wrapper">
                                                <div class="testimonial-user-thumb">
                                                    <img src="{{ get_image($item->user_image ?? "","site-section") }}" alt="user">
                                                </div>
                                                <div class="testimonial-user-content">
                                                    <h4 class="title">{{ $item->user_name ?? "" }}</h4>
                                                    <span class="sub-title">{{ $item->user_type ?? "" }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div> 
                                </div> 
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>