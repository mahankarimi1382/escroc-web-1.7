@extends('frontend.layouts.master')
@php
    $defualt = get_default_language_code()??'en';
    $default_lng = 'en';
@endphp
@section('content')  
    <!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        Start Contact
    ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
     @foreach ($page_section->sections ?? [] as $item)

        @if ( $item->section->key == 'about-section')
            @include('frontend.sections.about-section')
        @elseif($item->section->key == 'banner-section')
            @include('frontend.sections.banner-section')
        @elseif($item->section->key == 'brand-section')
            @include('frontend.sections.brand-section')
        @elseif($item->section->key == 'service-section')
            @include('frontend.sections.services-section')
        @elseif($item->section->key == 'feature-section')
            @include('frontend.sections.features-section')
         @elseif($item->section->key == 'testimonial-section')
            @include('frontend.sections.testimonial-section')
        @elseif($item->section->key == 'app-section')
            @include('frontend.sections.app-section')
         @elseif($item->section->key == 'contact-section')
            @include('frontend.sections.contact-section')
        @elseif($item->section->key == 'faq-section')
            @include('frontend.sections.faq-section')
        @endif
        
    @endforeach
    <!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        End Contact
    ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->


@endsection 
@push("script")
    
@endpush