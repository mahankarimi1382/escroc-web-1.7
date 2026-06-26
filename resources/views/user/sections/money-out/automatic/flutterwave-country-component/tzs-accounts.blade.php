<div class="col-12 col-lg-6 form-group">
    <label for="bank_name">{{ __("Select Bank") }} <span class="text-danger">*</span></label>
    <select name="bank_name" id="bank_name" class="form--control select2-basic" required data-placeholder="{{ __("Select Bank") }}" >
          <option disabled selected value="">{{ __("Select Bank") }}</option>
        @foreach ($allBanks ??[] as $bank)
            <option value="{{ $bank['code'] }}" data-bank-id="{{ $bank['id'] }}">{{ $bank['name'] }}</option>
        @endforeach
    </select>
</div>

@if($branch_status)
    <div class="col-12 col-lg-6 form-group">
        <div class="branches-list">
        <label for="branch_code">{{ __("Bank Branch") }} <span class="text-danger">*</span></label>
        <select name="branch_code" class="form--control select2-basic" required data-placeholder="{{ __("Select Bank Branch") }}">
            <option disabled selected value="">{{ __("Select Bank Branch") }}</option>
        </select>
        </div>
    </div>
@endif
<div class="col-12 col-lg-6 form-group">
    <label for="account_number">{{ __("Account Number") }} <span class="text-danger">*</span></label>
    <input type="text" class="form--control check_bank number-input" id="account_number"  name="account_number" value="{{ old('account_number') }}" placeholder="{{ __("Enter Account Number") }}">
    <label class="exist text-start"></label>
</div>
<div class="col-12 col-lg-6 form-group">
    <label for="beneficiary_name">{{ __("Beneficiary Name") }} <span class="text-danger">*</span></label>
    <input type="text" class="form--control" id="beneficiary_name"  name="beneficiary_name" value="{{ old('beneficiary_name') }}" placeholder="{{ __("Beneficiary Name") }}">
</div>

<div class="col-12 col-lg-6 form-group">
    <label for="sender">{{ __("Sender") }} <span class="text-danger">*</span></label>
    <input type="text" class="form--control" id="sender"  name="sender" value="{{ old('sender') }}" placeholder="{{ __("Enter Sender Name") }}">
</div>

<div class="col-12 col-lg-6 form-group">
    <label for="sender_country">{{ __("Sender Country") }} <span class="text-danger">*</span></label>
    <select name="sender_country" id="sender_country" class="form--control select2-basic" required data-placeholder="{{ __("Select Sender Country") }}" >
        <option disabled selected value="">{{ __("Select Beneficiary Country") }}</option>
        @foreach ($countries as $country)
            <option value="{{ $country->iso2 }}" >{{ $country->name }}</option>
        @endforeach
    </select>
</div>


<div class="col-12 col-lg-6 form-group">
    <label for="sender_address">{{ __("Sender Address") }} <span class="text-danger">*</span></label>
    <input type="text" class="form--control" id="sender_address"  name="sender_address" value="{{ old('sender_address') }}" placeholder="{{ __("Enter Sender Address") }}">
</div>





