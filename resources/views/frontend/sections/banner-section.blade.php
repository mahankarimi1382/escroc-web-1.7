@php
    $banner = \App\Models\Admin\SiteSections::where('key', 'banner-section')->first();
    $defualt = get_default_language_code() ?? 'en';
    $en = 'en';
@endphp
<section class="banner-section bg_img" data-background="{{ get_image('frontend/images/element/banner-bg.png') }}">
    <div class="container">
        <div class="row align-items-center mb-30-none">
            <div class="col-xxl-6 col-xl-5 col-lg-12 mb-30">
                <div class="banner-content">
                    <span class="sub-title">{{ @$banner->value->language->$defualt->left_heading ?? '' }}</span>
                    <h1 class="title cd-headline clip">
                        {{ @$banner->value->language->$defualt->left_sub_heading ?? '' }}
                        <span class="cd-words-wrapper">
                            <b class="is-visible">{{ @$banner->value->language->$defualt->left_input_one ?? '' }}</b>
                            <b>{{ @$banner->value->language->$defualt->left_input_two ?? '' }}</b>
                        </span>
                    </h1>
                    <p>{{ @$banner->value->language->$defualt->left_details ?? '' }}</p>
                    <div class="banner-btn">
                        <a href="{{ setRoute('user.register') }}"
                            class="btn--base">{{ @$banner->value->language->$defualt->left_button ?? '' }}</a>
                    </div>
                </div>
            </div>
            <div class="col-xxl-6 col-xl-7 col-lg-12 mb-30">
                <div class="banner-form-wrapper">
                    <div class="ribbon-top-right">
                        {{ @$banner->value->language->$defualt->right_input_two ?? 'Popular' }}</div>
                    <h2 class="title">{{ @$banner->value->language->$defualt->right_heading ?? '' }}</h2>
                    <p>{{ @$banner->value->language->$defualt->right_details ?? '' }}</p>
                    <form action="{{ route('user.my-escrow.add') }}" method="get" class="banner-form">
                        <div class="banner-form-group">
                            <div class="left-field">
                                <div class="field-input">
                                    <div class="field-preffix">
                                        <div class="field-preffix-wrapper">
                                            <span class="field-preffix-label">{{ __("I'm") }}</span>
                                        </div>
                                    </div>
                                    <div class="field-select">
                                        <select class="form--control nice-select" name="role">
                                            <option value="seller">{{ __('Selling') }}</option>
                                            <option value="buyer">{{ __('Buying') }}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="right-field">
                                <div class="field-input">
                                    <input type="text" class="banner-input" data-target="field-focusable"
                                        id="field-calculator-search" name="title"
                                        placeholder="{{ @$banner->value->language->$defualt->right_input_one ?? '' }}"
                                        data-component="calculator-price" aria-describedby=" error-price"
                                        data-e2e-target="calculator-price-input" autocomplete="off">
                                </div>
                            </div>
                        </div>
                        <div class="banner-form-group">
                            <div class="field-price">
                                <div class="field-input">
                                    <div class="field-preffix">
                                        <div class="field-preffix-wrapper">
                                            <span class="field-preffix-label currency-symbol">{{ __('for') }}
                                                $</span>
                                        </div>
                                    </div>
                                    <input type="text" class="defaultInput" data-target="field-focusable"
                                        id="amount" value="1000" name="amount" step="10" min="0"
                                        data-component="calculator-price" aria-describedby=" error-price"
                                        data-e2e-target="calculator-price-input" autocomplete="off">
                                </div>
                            </div>
                            <div class="select-field">
                                <div class="field-select">
                                    <select class="form--control nice-select" name="escrow_currency">
                                        @foreach ($currencies ?? [] as $item)
                                            <option value="{{ $item->code }}" data-symbol="{{ $item->symbol }}">
                                                {{ $item->code }}</option>
                                        @endforeach

                                    </select>
                                </div>
                            </div>
                        </div>
                        <button type="submit"
                            class="btn--base mt-10">{{ @$banner->value->language->$defualt->right_button ?? 'Get Started Now' }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
