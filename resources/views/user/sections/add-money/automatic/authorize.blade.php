@extends('user.layouts.master')

@push('css')
<style>
    .checkout-wrapper {
        background: white;
        border-radius: 10px;
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        padding: 30px;
    }
    @media screen and (max-width: 480px) {
       .checkout-wrapper {
        padding: 20px;
    }
    }
    .checkout-section{
        height: 100% !important;
    }
    .card-number-container,
    .cvc-container {
        position: relative;
    }

    .card-icons {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        display: flex;
        gap: 6px;
    }

    .card-icon {
        width: 40px;
        height: 25px;
        border-radius: 4px;
        background-size: contain;
        background-position: center;
        background-repeat: no-repeat;
        transition: all 0.3s ease;
        display: none;
        border: 1px solid #a0a0a0;
    }

    /* Real-looking card brand images */
    .card-icon.visa {
        background-image: url('https://upload.wikimedia.org/wikipedia/commons/thumb/5/5e/Visa_Inc._logo.svg/2560px-Visa_Inc._logo.svg.png');
        background-color: white;
    }

    .card-icon.mastercard {
        background-image: url('https://upload.wikimedia.org/wikipedia/commons/thumb/2/2a/Mastercard-logo.svg/1280px-Mastercard-logo.svg.png');
        background-color: white;
    }

    .card-icon.amex {
        background-image: url('https://upload.wikimedia.org/wikipedia/commons/thumb/3/30/American_Express_logo.svg/1024px-American_Express_logo.svg.png');
        background-color: white;
    }

    .card-icon.discover {
        background-image: url('https://upload.wikimedia.org/wikipedia/commons/thumb/5/57/Discover_Card_logo.svg/1024px-Discover_Card_logo.svg.png');
        background-color: white;
    }

    .card-icon.default {
        display: block;
        background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 16"><rect width="24" height="16" rx="3" fill="%23f0f0f0"/><rect x="16" y="6" width="5" height="3" rx="1" fill="%236c757d"/></svg>');
    }

    .card-icon.active {
        display: block;
        border-color: #abcbfa;
        box-shadow: 0 0 0 1px #abcbfa;
    }

    .cvc-hint {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        color: #6c757d;
    }

    .form-control.is-invalid {
        border-color: #dc3545;
        padding-right: calc(1.5em + 0.75rem);
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right calc(0.375em + 0.1875rem) center;
        background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
    }

    .invalid-feedback {
        display: none;
        width: 100%;
        margin-top: 0.25rem;
        font-size: 0.875em;
        color: #dc3545;
    }

    .is-invalid~.invalid-feedback {
        display: block;
    }
</style>
@endpush

@section('breadcrumb')
    @include('user.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("user.dashboard"),
        ]
    ], 'active' => __("Authorize Payment")])
@endsection
@section('content')
<div class="body-wrapper">
    <div class="dashboard-area mt-10">
        <div class="dashboard-header-wrapper">
            <h3 class="title">{{__(@$page_title)}}</h3>
        </div>
    </div>
    <div class="row mb-30-none justify-content-center">
        <div class="col-lg-6">
            <div class="custom-card">
                <div class="dashboard-header-wrapper">
                    <h4 class="title">{{ __("Pay With Card") }}</h3>
                </div>
                <div class="card-body">
                    <form id="payment-form" action="{{ setRoute('user.add.money.authorize.payment.submit',$temp_data->identifier) }}" method="post" class="checkout-form" novalidate>
                        @csrf
                        <div class="form-group mb-3">
                            <label for="card-number" class="form-label">{{ __("Card Number") }}*</label>
                            <div class="card-number-container">
                                <input type="text" id="card-number" name="card_number" class="form--control"
                                    placeholder="1234 1234 1234 1234" required>
                                <div class="card-icons">
                                    <div class="card-icon visa"></div>
                                    <div class="card-icon mastercard"></div>
                                    <div class="card-icon amex"></div>
                                    <div class="card-icon discover"></div>
                                    <div class="card-icon default"></div>
                                </div>
                            </div>
                            <div class="invalid-feedback" id="card-number-error">{{ __("Please enter a valid card number") }}
                            </div>
                        </div>
                        <div class="row mb-10-none">
                            <div class="col-lg-6 col-md-6 col-sm-6 mb-10">
                                <div class="form-group">
                                    <label for="expiry" class="form-label">{{ __("Expiration Date") }}*</label>
                                    <input type="text" id="expiry" name="date" class="form--control" placeholder="YY / MM" required>
                                    <div class="invalid-feedback">{{ __("Please enter a valid future expiration date (YY/MM)") }}</div>
                                </div>
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-6 mb-10">
                                <div class="form-group">
                                    <label for="cvc" class="form-label">{{ __("Security Code") }} {{ __("CVV/CVC") }}*</label>
                                    <div class="cvc-container">
                                        <input type="text" id="cvc" name="code" class="form--control" placeholder="CVC" required>
                                    </div>
                                    <div class="invalid-feedback">{{ __("Please enter a valid 3 or 4 digit security code") }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="btn-area mt-3">
                            <button type="submit" class="btn--base w-100">{{ __("Submit") }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="dash-payment-item-wrapper">
                <div class="dash-payment-item active">
                    <div class="dash-payment-title-area mb-3">
                        <h4 class="title">{{__("Payment Information")}}</h5>
                    </div>
                    <div class="dash-payment-body">
                        <div class="preview-list-wrapper">
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-receipt"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __("Entered Amount") }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span class="request-amount">{{ get_amount(@$temp_data->data->amount->requested_amount,@$temp_data->data->amount->sender_currency,4 )}}</span>
                                </div>
                            </div>
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-exchange-alt"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __("Exchange Rate") }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span class="request-amount">{{ __("1") }} {{ $temp_data->data->amount->sender_currency }} =  {{ get_amount(@$temp_data->data->amount->exchange_rate, @$temp_data->data->amount->gateway_cur_code,4 )}}</span>
                                </div>
                            </div>
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-battery-half"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __("Total Fees & Charges") }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span class="fees">{{ get_amount(@$temp_data->data->amount->gateway_total_charge,@$temp_data->data->amount->gateway_cur_code,4 )}} </span>
                                </div>
                            </div>
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="lab la-get-pocket"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __("Will Get") }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span class="will-get">{{ get_amount(@$temp_data->data->amount->requested_amount,@$temp_data->data->amount->sender_currency,4 )}}</span>
                                </div>
                            </div>
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-money-check-alt"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span class="last">{{ __("Total Payable Amount") }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span class="text--warning last pay-in-total">{{ get_amount(@$temp_data->data->amount->total_payable_amount,@$temp_data->data->amount->gateway_cur_code,4 )}}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
@push('script')
<script>
    document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('payment-form');
            const cardNumberInput = document.getElementById('card-number');
            const expiryInput = document.getElementById('expiry');
            const cvcInput = document.getElementById('cvc');
            const emailInput = document.getElementById('email');
            const cardIcons = document.querySelectorAll('.card-icon:not(.default)');
            const defaultIcon = document.querySelector('.card-icon.default');

            const cardTypes = [
                { name: 'Visa', pattern: /^4/, icon: 'visa', cvcLength: 3 },
                { name: 'Mastercard', pattern: /^(5[1-5]|2[2-7])/, icon: 'mastercard', cvcLength: 3 },
                { name: 'American Express', pattern: /^3[47]/, icon: 'amex', cvcLength: 4 },
                { name: 'Discover', pattern: /^(6011|65|64[4-9]|622)/, icon: 'discover', cvcLength: 3 }
            ];

            function detectCardType(cardNumber) {
                const cleanedNumber = cardNumber.replace(/\s/g, '');
                for (const type of cardTypes) {
                    if (type.pattern.test(cleanedNumber)) {
                        return type;
                    }
                }
                return null;
            }

            function validateEmail() {
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value);
            }

            function validateCardNumber() {
                const value = cardNumberInput.value.replace(/\s/g, '');
                if (value.length < 13 || value.length > 19) return false;

                // Luhn algorithm check
                let sum = 0;
                let shouldDouble = false;
                for (let i = value.length - 1; i >= 0; i--) {
                    let digit = parseInt(value.charAt(i), 10);
                    if (shouldDouble) {
                        digit *= 2;
                        if (digit > 9) digit -= 9;
                    }
                    sum += digit;
                    shouldDouble = !shouldDouble;
                }
                return (sum % 10) === 0;
            }

            function validateExpiry() {
                const value = expiryInput.value;
                if (!/^\d{2}\/\d{2}$/.test(value)) return false;

                const [yearStr, monthStr] = value.split('/');
                const year = parseInt(yearStr, 10);
                const month = parseInt(monthStr, 10);

                if (month < 1 || month > 12) return false;

                const currentYear = new Date().getFullYear() % 100;
                const currentMonth = new Date().getMonth() + 1;

                if (year < currentYear) return false;
                if (year === currentYear && month < currentMonth) return false;

                return true;
            }

            function validateCVC() {
                const cardType = detectCardType(cardNumberInput.value.replace(/\s/g, ''));
                const cvcLength = cardType ? cardType.cvcLength : 3;
                return new RegExp(`^\\d{${cvcLength}}$`).test(cvcInput.value);
            }

            function showError(input, isValid) {
                input.classList.toggle('is-invalid', !isValid);
            }

            cardNumberInput.addEventListener('input', function (e) {
                let value = e.target.value.replace(/\D/g, '').slice(0, 16);
                value = value.match(/.{1,4}/g)?.join(' ') || value;
                e.target.value = value;

                const cardType = detectCardType(value.replace(/\s/g, ''));

                if (value.length > 0) {
                    defaultIcon.style.display = 'none';
                } else {
                    defaultIcon.style.display = 'block';
                    cardIcons.forEach(icon => icon.classList.remove('active'));
                }

                if (cardType) {
                    document.querySelector(`.card-icon.${cardType.icon}`).classList.add('active');
                }

                showError(cardNumberInput, validateCardNumber());

                const cvcLength = cardType ? cardType.cvcLength : 3;
                cvcInput.maxLength = cvcLength;
                cvcInput.placeholder = '●'.repeat(cvcLength);
                cvcInput.value = cvcInput.value.slice(0, cvcLength);
                showError(cvcInput, validateCVC());
            });

            expiryInput.addEventListener('input', function (e) {
                let value = e.target.value.replace(/\D/g, '').slice(0, 4);
                if (value.length > 2) {
                    value = value.slice(0, 2) + '/' + value.slice(2);
                    if (value.length === 5 && e.target.selectionStart === 4) {
                        setTimeout(() => {
                            e.target.setSelectionRange(3, 3);
                        }, 0);
                    }
                }
                e.target.value = value;
                showError(expiryInput, validateExpiry());
            });

            cvcInput.addEventListener('input', function (e) {
                let cardType = detectCardType(cardNumberInput.value.replace(/\s/g, ''));
                let maxLength = cardType ? cardType.cvcLength : 3;
                e.target.value = e.target.value.replace(/\D/g, '').slice(0, maxLength);
                showError(cvcInput, validateCVC());
            });

            emailInput.addEventListener('input', function () {
                showError(emailInput, validateEmail());
            });

            form.addEventListener('submit', function (e) {
                e.preventDefault();

                const isEmailValid = validateEmail();
                const isCardValid = validateCardNumber();
                const isExpiryValid = validateExpiry();
                const isCvcValid = validateCVC();

                showError(emailInput, isEmailValid);
                showError(cardNumberInput, isCardValid);
                showError(expiryInput, isExpiryValid);
                showError(cvcInput, isCvcValid);

                if (isEmailValid && isCardValid && isExpiryValid && isCvcValid) {
                    alert('Payment successful!');
                    // form.submit(); // Uncomment to actually submit the form
                }
            });
        });
</script>

@endpush
