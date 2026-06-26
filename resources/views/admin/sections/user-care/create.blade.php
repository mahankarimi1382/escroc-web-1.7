@extends('admin.layouts.master')

@push('css')
    <style>
        .fileholder {
            min-height: 280px !important;
        }

        .fileholder-files-view-wrp.accept-single-file .fileholder-single-file-view,.fileholder-files-view-wrp.fileholder-perview-single .fileholder-single-file-view{
            height: 246px !important;
        }
    </style>
@endpush

@section('page-title')
    @include('admin.components.page-title',['title' => __($page_title)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("admin.dashboard"),
        ]
    ], 'active' => __($page_title)])
@endsection

@section('content')
    <div class="custom-card">
        <div class="card-header">
            <h6 class="title">{{ __($page_title) }}</h6>
        </div>
        <div class="card-body"> 
                <div class="row mb-10-none">
                    <div class="personal-account ptb-30 select-account" data-select-target="1">
                                <form action="{{ setRoute('admin.users.store') }}" class="card-form" method="POST">
                                    @csrf
                                    <div class="row">
                                        <div class="col-xl-6 col-lg-6 col-md-6 col-sm-6 form-group">
                                            @include('admin.components.form.switcher', [
                                                'label'         => __("Account Type"),
                                                'value'         => old('account_type','buyer'),
                                                'name'          => "account_type",
                                                'options'       => [__("Buyer") => 'buyer', __("Seller") => 'seller'],
                                            ])
                                        </div>
                                        <div class="col-lg-6 form-group">
                                            @include('admin.components.form.input',[
                                                'label'         => __("First Name")."*",
                                                'name'          => "firstname",
                                                'placeholder'   => __("First Name"),
                                                'value'         => old("firstname"),
                                            ])
                                        </div>
                                        <div class="col-lg-6 form-group">
                                            @include('admin.components.form.input',[
                                                'label'         => __("Last Name")."*",
                                                'name'          => "lastname",
                                                'placeholder'   => __("Last Name"),
                                                'value'         => old("lastname"),
                                            ])
                                        </div>
                                        <div class="col-lg-6 form-group">
                                            @include('admin.components.form.input',[
                                                'label'         => __("Email")."*",
                                                'type'          => "email",
                                                'name'          => "email",
                                                'placeholder'   => __("Email"),
                                                'value'         => old("email"),
                                            ])
                                        </div>
                                        <div class="col-lg-6 form-group">  
                                            <label>{{ __("Select Country") }} <span>*</span></label>
                                            <select name="country" class="form--control select2-auto-tokenize country-select" data-old="{{ old('country',$user_country) }}">
                                                <option selected disabled>{{ __("Select Country") }}</option>
                                            </select>
                                        </div>  
                                        <div class="col-lg-6 form-group show_hide_password">
                                            <label>{{ __("Password") }}*</label>
                                            <div class="input-group">
                                                <input type="text" class="form--control place_random_password @error("password") is-invalid @enderror" placeholder="{{ __('Enter Password') }}" name="password">
                                                <button class="input-group-text rand_password_generator" type="button">{{ __("Generate") }}</button>
                                            </div>
                                            @error("password")
                                                <span class="invalid-feedback d-block" role="alert">
                                                    <strong>{{ $message }}</strong>
                                                </span>
                                            @enderror
                                        </div> 
                                        <div class="col-xl-6 col-lg-6 col-md-6 col-sm-6 form-group">
                                            @include('admin.components.form.switcher', [
                                                'label'         => __("Email Verification"),
                                                'value'         => old('email_verified',1),
                                                'name'          => "email_verified",
                                                'options'       => [__("Verified") => 1, __("Unverified") => 0],
                                            ])
                                        </div>
                                        <div class="col-xl-6 col-lg-6 col-md-6 col-sm-6 form-group">
                                            @include('admin.components.form.switcher', [
                                                'label'     => __("KYC Verification"),
                                                'value'     => old('kyc_verified',1),
                                                'name'      => "kyc_verified",
                                                'options'       => [__("Verified") => 1, __("Unverified") => 0],
                                            ])
                                        </div>

                                        <div class="col-xl-12 col-lg-12 form-group">
                                            <button type="submit" class="btn--base w-100 btn-loading">{{ __("Add") }}</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
@endsection

@push("script")
    <script>
        function placeRandomPassword(clickedButton,placeInput) {
            $(clickedButton).click(function(){
                var generateRandomPassword = makeRandomString(10);
                $(placeInput).val(generateRandomPassword);
            });
        }
        placeRandomPassword(".rand_password_generator",".place_random_password");
    </script>
     <script> 
        getAllCountries("{{ setRoute('global.countries') }}",$(".country-select"));
        $(document).ready(function(){
            $("select[name=country]").change(function(){
                var phoneCode = $("select[name=country] :selected").attr("data-mobile-code");
                placePhoneCode(phoneCode);
            });

            setTimeout(() => {
                var phoneCodeOnload = $("select[name=country] :selected").attr("data-mobile-code");
                placePhoneCode(phoneCodeOnload);
            }, 400);
            countrySelect(".country-select",$(".country-select").siblings(".select2"));
        });
    </script>
    <script>
        // $(".account-type").change(function(){
        //     var targetItem = $(this).val();
        //     selectContainItem(targetItem);
        // });

        // $(document).ready(function() {
        //     var professionSelectedItem = $(".account-type").val();
        //     selectContainItem(professionSelectedItem);
        // });


        function selectContainItem(targetItem) {
            $(".select-account").slideUp(300);
            if(targetItem == null) return false;
            if(targetItem.length > 0) {
                var findTargetItem = $(".select-account");
                $.each(findTargetItem, function(index,item) {
                    if($(item).attr("data-select-target") == targetItem) {
                        $(item).slideDown(300);
                    }else {
                        $(item).slideUp(300);
                    }
                })
            }
        }
    </script>
@endpush
